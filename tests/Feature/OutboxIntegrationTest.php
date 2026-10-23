<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryMaterializer;
use App\Services\OutboxDispatcher;
use App\Services\PublicationObserver;
use App\Services\PublicationResult;
use App\Support\TestingDatabaseGuard;
use Composer\InstalledVersions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

class OutboxIntegrationTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCampaigns;

    private function event(): DeliveryOutbox
    {
        $campaign = $this->authorize($this->reviewed());
        $this->assertSame(1, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));

        return DeliveryOutbox::sole();
    }

    private function topology(): array
    {
        $name = 'emailing.outbox-test.'.Str::uuid();
        config(['delivery.exchange' => $name, 'delivery.queue' => $name]);

        return [$name, $name];
    }

    private function broker(): AMQPStreamConnection
    {
        $host = config('queue.connections.rabbitmq.hosts.0');

        return new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost']);
    }

    private function cleanupTopology(string $exchange, string $queue): void
    {
        $broker = $this->broker();
        try {
            $channel = $broker->channel();
            $channel->queue_delete($queue);
            $channel->exchange_delete($exchange);
        } finally {
            $broker->close();
        }
    }

    private function receive(string $queue): array
    {
        $broker = $this->broker();
        try {
            $message = $broker->channel()->basic_get($queue, true);
            $this->assertNotNull($message, 'Own broker queue must contain the persistent domain event.');
            $this->assertSame('application/json', $message->get('content_type'));
            $this->assertSame(2, $message->get('delivery_mode'));
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame($payload['event_id'], $message->get('message_id'));

            return $payload;
        } finally {
            $broker->close();
        }
    }

    private function trace(string $case, array $facts): void
    {
        $directory = (getenv('T12_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t12/implementation')).'/protocol';
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$case.'-'.Str::uuid().'.json', json_encode(['run' => getenv('T12_EVIDENCE_RUN') ?: null] + $facts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function recordProcess(string $case, Process $process, ?int $pid): void
    {
        $directory = (getenv('T12_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t12/implementation')).'/processes/'.$case.'-'.Str::uuid();
        mkdir($directory, 0700, true);
        file_put_contents($directory.'/stdout.txt', $process->getOutput());
        file_put_contents($directory.'/stderr.txt', $process->getErrorOutput());
        file_put_contents($directory.'/receipt.json', json_encode(['run' => getenv('T12_EVIDENCE_RUN') ?: null, 'pid' => $pid, 'command' => $process->getCommandLine(), 'exit' => $process->getExitCode(), 'started_at_unix' => $process->getStartTime(), 'ended_at_utc' => gmdate('c'), 'stdout_sha256' => hash_file('sha256', $directory.'/stdout.txt'), 'stderr_sha256' => hash_file('sha256', $directory.'/stderr.txt')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function waitFile(string $file, ?Process $process = null): void
    {
        $deadline = microtime(true) + 40;
        while (! is_file($file)) {
            if (microtime(true) > $deadline || ($process && ! $process->isRunning())) {
                $this->fail('Own process did not reach barrier: '.($process?->getErrorOutput() ?? 'timeout'));
            }
            usleep(10000);
        }
    }

    public function test_partial_protocol_marker_never_releases_consumer_before_complete_identity(): void
    {
        $directory = storage_path('app/private/outbox-partial-'.Str::uuid());
        mkdir($directory, 0700, true);
        $expected = ['stage' => 'confirmed', 'event_id' => (string) Str::uuid(), 'generation' => 1, 'barriers' => ['confirm_selected', 'before_publish', 'published_bytes', 'ack', 'confirmed']];
        // The producer is deliberately stopped after making only a prefix
        // visible. The consumer must stay alive until the producer completes.
        file_put_contents($directory.'/confirmed', '{"stage":');
        $process = new Process([PHP_BINARY, base_path('tests/support/outbox-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
        $process->setInput(json_encode(['action' => 'barrier-consumer', 'ready' => $directory.'/ready', 'barrier' => $directory.'/confirmed', 'expected' => $expected], JSON_THROW_ON_ERROR));
        $process->setTimeout(30)->start();
        $pid = $process->getPid();
        try {
            $this->waitFile($directory.'/ready', $process);
            usleep(150000);
            $this->assertTrue($process->isRunning(), 'A visible partial marker must keep the consumer waiting.');
            file_put_contents($directory.'/confirmed', json_encode($expected, JSON_THROW_ON_ERROR));
            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame($expected, json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR));
        } finally {
            if ($process->isRunning()) {
                $process->stop();
            }
            $this->recordProcess('partial-marker-consumer', $process, $pid);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_atomic_marker_publication_and_permanent_invalid_or_foreign_signal_are_observable(): void
    {
        $directory = storage_path('app/private/outbox-atomic-marker-'.Str::uuid());
        mkdir($directory, 0700, true);
        $path = $directory.'/confirmed';
        $expected = ['stage' => 'confirmed', 'event_id' => (string) Str::uuid(), 'generation' => 1];
        try {
            OutboxProtocolBarrier::publish($path, $expected, function ($temporary) use ($path, $expected) {
                $this->assertFileDoesNotExist($path, 'The final signal must stay invisible until the complete temporary JSON is committed.');
                $this->assertSame($expected, json_decode(file_get_contents($temporary), true, 512, JSON_THROW_ON_ERROR));
            });
            $this->assertSame($expected, OutboxProtocolBarrier::await($path, fn ($data) => $data === $expected, .1));
            file_put_contents($path, '{"stage":');
            try {
                OutboxProtocolBarrier::await($path, fn ($data) => $data === $expected, .05);
                $this->fail('A permanently invalid marker must report failure within its deadline.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('json_valid=false', $error->getMessage());
            }
            file_put_contents($path, json_encode(['stage' => 'confirmed', 'event_id' => 'foreign', 'generation' => 1], JSON_THROW_ON_ERROR));
            try {
                OutboxProtocolBarrier::await($path, fn ($data) => $data === $expected, .05);
                $this->fail('A different event must never release this barrier.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('json_valid=true; identity_valid=false', $error->getMessage());
            }
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_real_mysql_rollback_after_delivery_insert_leaves_neither_half(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $successfulDeliveryInserts = 0;
        DB::listen(function (QueryExecuted $query) use (&$successfulDeliveryInserts) {
            if (preg_match('/^insert into [`]?deliveries[`]?\s/i', $query->sql)) {
                $successfulDeliveryInserts++;
            }
        });
        TestingDatabaseGuard::assertSafe();
        $this->assertDatabaseCount('delivery_outbox', 0);
        $constraint = 't12_outbox_'.str_replace('-', '', (string) Str::uuid());
        $created = false;
        $insertsBeforeRejection = 0;
        try {
            DB::statement('ALTER TABLE delivery_outbox ADD CONSTRAINT '.$constraint." CHECK (type <> 'delivery.ready')");
            $created = true;
            app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
            $this->fail('Rollback must occur.');
        } catch (QueryException $error) {
            $insertsBeforeRejection = $successfulDeliveryInserts;
            $this->trace('rollback-sql-rejection', ['sqlstate' => $error->errorInfo[0], 'driver_code' => $error->errorInfo[1], 'constraint_created' => $created, 'successful_delivery_inserts' => $insertsBeforeRejection]);
            $this->assertTrue($created);
            $this->assertSame(3819, $error->errorInfo[1]);
            $this->assertGreaterThan(0, $insertsBeforeRejection, 'The real delivery INSERT must succeed before MySQL rejects outbox INSERT.');
        } finally {
            if ($created) {
                TestingDatabaseGuard::assertSafe();
                DB::statement('ALTER TABLE delivery_outbox DROP CHECK '.$constraint);
            }
        }
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->assertSame(1, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
        $this->assertDatabaseCount('delivery_outbox', 1);
        $this->trace('rollback', ['successful_delivery_inserts_before_rejection' => $insertsBeforeRejection, 'driver_code' => 3819, 'rolled_back' => ['deliveries' => 0, 'outbox' => 0], 'recovered_event' => DeliveryOutbox::sole()->payload()]);
    }

    public function test_materializer_sql_cost_stays_bounded_for_recipient_batches(): void
    {
        $measuring = false;
        $queries = 0;
        DB::listen(function (QueryExecuted $query) use (&$measuring, &$queries) {
            if ($measuring) {
                $queries++;
            }
        });
        $samples = [];
        foreach ([100, 200, 1000] as $size) {
            $marker = 'bulk-cost-'.Str::uuid();
            $listId = DB::table('contact_lists')->insertGetId(['account_id' => $this->account->id, 'name' => $marker, 'description' => '', 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $rows = [];
            for ($i = 0; $i < $size; $i++) {
                $rows[] = ['account_id' => $this->account->id, 'email' => $marker.'-'.$i.'@example.test', 'name' => 'Fixture '.$i, 'consent_status' => 'granted', 'consent_source' => $marker, 'consented_at' => now('UTC')->subDay(), 'created_at' => now('UTC'), 'updated_at' => now('UTC')];
            }
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('contacts')->insert($chunk);
            }
            $contacts = DB::table('contacts')->where('account_id', $this->account->id)->where('consent_source', $marker)->pluck('id')->all();
            foreach (array_chunk($contacts, 500) as $chunk) {
                DB::table('contact_list_memberships')->insert(array_map(fn ($id) => ['account_id' => $this->account->id, 'contact_list_id' => $listId, 'contact_id' => $id], $chunk));
            }
            $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$listId], 'contact_ids' => []]));
            $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
            $campaign = $this->authorize($campaign);
            $queries = 0;
            $measuring = true;
            try {
                $created = app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
            } finally {
                $measuring = false;
            }
            // A coarse work budget permits implementation changes and extra
            // safety reads, but rules out one SQL round trip per recipient.
            $budget = 25 + (int) ceil($size / 100);
            $samples[] = ['recipients' => $size, 'queries' => $queries, 'budget' => $budget, 'created' => $created];
            $this->trace('materializer-sql-budget', end($samples));
            $this->assertSame($size, $created);
            $this->assertLessThanOrEqual($budget, $queries, 'Materialization SQL cost must stay inside the coarse per-volume work budget.');
            $deliveries = Delivery::where('account_id', $this->account->id)->where('campaign_revision_id', $campaign->reviewed_revision_id)->get();
            $ids = $deliveries->pluck('id')->sort()->values()->all();
            $this->assertCount($size, $ids);
            $this->assertSame($size, count(array_unique($ids)));
            $this->assertTrue($deliveries->every(fn ($delivery) => Str::isUuid($delivery->id) && $delivery->generation === 1 && $delivery->campaign_id === $campaign->id));
            $this->assertSame($size, DeliveryOutbox::where('account_id', $this->account->id)->whereIn('delivery_id', $ids)->count());
            $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
            $this->assertSame($ids, Delivery::where('account_id', $this->account->id)->where('campaign_revision_id', $campaign->reviewed_revision_id)->pluck('id')->sort()->values()->all());
        }
    }

    private function materializedClaimCampaign(int $size): Campaign
    {
        $marker = 'claim-work-'.Str::uuid();
        $now = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS db_now')->db_now;
        $listId = DB::table('contact_lists')->insertGetId(['account_id' => $this->account->id, 'name' => $marker, 'description' => '', 'created_at' => $now, 'updated_at' => $now]);
        $rows = [];
        for ($i = 0; $i < $size; $i++) {
            $rows[] = ['account_id' => $this->account->id, 'email' => $marker.'-'.$i.'@example.test', 'name' => 'Claim fixture', 'consent_status' => 'granted', 'consent_source' => $marker, 'consented_at' => '2026-01-01 00:00:00', 'created_at' => $now, 'updated_at' => $now];
            if (count($rows) === 500) {
                DB::table('contacts')->insert($rows);
                $rows = [];
            }
        }
        if ($rows !== []) {
            DB::table('contacts')->insert($rows);
        }
        $contacts = DB::table('contacts')->where('account_id', $this->account->id)->where('consent_source', $marker)->pluck('id')->all();
        foreach (array_chunk($contacts, 500) as $chunk) {
            DB::table('contact_list_memberships')->insert(array_map(fn ($id) => ['account_id' => $this->account->id, 'contact_list_id' => $listId, 'contact_id' => $id], $chunk));
        }
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$listId], 'contact_ids' => []]));
        $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $campaign = $this->authorize($campaign);
        $this->assertSame($size, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));

        return $campaign;
    }

    private function handlerReads(\PDO $pdo): array
    {
        return array_map('intval', $pdo->query("SHOW SESSION STATUS LIKE 'Handler_read%'")->fetchAll(\PDO::FETCH_KEY_PAIR));
    }

    public function test_global_claim_selection_work_stays_bounded_as_backlog_grows(): void
    {
        $pdo = DB::connection()->getPdo();
        $dispatcher = app(OutboxDispatcher::class);
        $samples = [];
        foreach ([100, 10000] as $size) {
            $campaign = $this->materializedClaimCampaign($size);
            $reads = 0;
            $claimed = [];
            for ($i = 0; $i < 10; $i++) {
                $before = $this->handlerReads($pdo);
                $claim = $dispatcher->claim();
                $after = $this->handlerReads($pdo);
                $reads += array_sum($after) - array_sum($before);
                $this->assertNotNull($claim);
                $this->assertSame($this->account->id, $claim->account_id);
                $this->assertSame($campaign->reviewed_revision_id, Delivery::findOrFail($claim->delivery_id)->campaign_revision_id);
                $claimed[] = $claim->id;
                $this->assertTrue($dispatcher->complete($claim, new PublicationResult(true)));
            }
            $this->assertCount(10, array_unique($claimed));
            $samples[] = ['recipients' => $size, 'global_claims' => 10, 'handler_reads' => $reads, 'same_pdo' => true, 'event_id_filter' => false];
            $this->trace('global-claim-work-budget', end($samples));
            // Retain this completed history while measuring the larger backlog.
            DB::table('delivery_outbox')->where('account_id', $this->account->id)->update(['status' => 'published', 'lease_token' => null, 'lease_expires_at' => null, 'published_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        }
        // Permit constant claim/update/fresh reads and safety overhead, while
        // rejecting a scan proportional to backlog or completed history.
        $this->assertLessThanOrEqual($samples[0]['handler_reads'] * 4 + 500, $samples[1]['handler_reads'], 'Global selection work must not grow with the backlog.');
        $this->assertLessThanOrEqual(1000, $samples[1]['handler_reads'], 'Ten claims must stay inside a coarse SQL handler-work budget.');
    }

    public function test_generated_eligibility_preserves_dates_leases_and_excludes_published_history(): void
    {
        $this->materializedClaimCampaign(8);
        $ids = DeliveryOutbox::orderBy('id')->pluck('id')->all();
        $offset = fn (int $seconds) => DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '.$seconds.' SECOND)');
        $states = [
            ['status' => 'published', 'available_at' => $offset(-30000)],
            ['status' => 'pending', 'available_at' => $offset(3600)],
            ['status' => 'publishing', 'available_at' => $offset(-30000), 'lease_expires_at' => $offset(1800)],
            ['status' => 'publishing', 'available_at' => $offset(-30000), 'lease_expires_at' => null],
            ['status' => 'publishing', 'available_at' => $offset(3600), 'lease_expires_at' => $offset(-7200)],
            ['status' => 'pending', 'available_at' => $offset(-10800)],
            ['status' => 'publishing', 'available_at' => $offset(-14400), 'lease_expires_at' => $offset(-3600)],
            ['status' => 'pending', 'available_at' => $offset(-7200)],
        ];
        foreach ($states as $index => $values) {
            DB::table('delivery_outbox')->where('id', $ids[$index])->update($values);
        }
        $rows = DB::table('delivery_outbox')->get()->keyBy('id');
        $this->assertNull($rows[$ids[0]]->eligible_at);
        $this->assertNull($rows[$ids[3]]->eligible_at);
        $this->assertSame($rows[$ids[4]]->available_at, $rows[$ids[4]]->eligible_at);
        $this->assertSame($rows[$ids[5]]->available_at, $rows[$ids[5]]->eligible_at);
        $this->assertSame($rows[$ids[6]]->lease_expires_at, $rows[$ids[6]]->eligible_at);
        $dispatcher = app(OutboxDispatcher::class);
        $claimed = [];
        foreach ([5, 7, 6] as $index) {
            $claim = $dispatcher->claim();
            $this->assertNotNull($claim);
            $this->assertSame($ids[$index], $claim->id);
            $this->assertSame(1, $claim->generation);
            $claimed[] = $claim->payload();
        }
        $this->assertNull($dispatcher->claim());
        $this->assertSame($ids, DeliveryOutbox::orderBy('id')->pluck('id')->all());
        $this->trace('generated-eligibility', ['selection_order' => $claimed, 'future_pending' => $ids[1], 'active_lease' => $ids[2], 'null_lease' => $ids[3], 'future_available_expired_lease' => $ids[4], 'published' => $ids[0], 'ordering' => 'eligible_at,id']);
    }

    public function test_two_real_dispatchers_skip_locked_candidate_and_claim_distinct_events(): void
    {
        $this->materializedClaimCampaign(3);
        $ids = DeliveryOutbox::orderBy('id')->pluck('id')->all();
        $directory = storage_path('app/private/outbox-claim-concurrency-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = $pids = $claims = [];
        DB::beginTransaction();
        try {
            DeliveryOutbox::whereKey($ids[0])->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/support/outbox-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode(['action' => 'claim-global', 'account_id' => $this->account->id, 'ready' => $directory.'/ready-'.$i, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(30)->start();
                $processes[] = $process;
                $pids[] = $process->getPid();
            }
            foreach ($processes as $i => $process) {
                $this->waitFile($directory.'/ready-'.$i, $process);
            }
            file_put_contents($directory.'/start', 'start');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $claims[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }
            $claimedIds = array_column($claims, 'event_id');
            $this->assertCount(2, array_unique($claimedIds));
            $this->assertNotContains($ids[0], $claimedIds, 'Children must finish while the earliest candidate is still SQL-locked.');
            $this->assertEqualsCanonicalizing(array_slice($ids, 1), $claimedIds);
        } finally {
            DB::rollBack();
            foreach ($processes as $i => $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                $this->recordProcess('concurrent-global-claim', $process, $pids[$i]);
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
        foreach ($claims as $claim) {
            $event = DeliveryOutbox::findOrFail($claim['event_id']);
            $this->assertSame('publishing', $event->status);
            $this->assertSame($claim['lease_token'], $event->lease_token);
            $this->assertSame(1, $claim['generation']);
        }
        $next = app(OutboxDispatcher::class)->claim();
        $this->assertNotNull($next);
        $this->assertSame($ids[0], $next->id);
        $this->assertNull(app(OutboxDispatcher::class)->claim());
        $this->trace('concurrent-global-claims', ['locked_event_id' => $ids[0], 'completed_while_lock_held' => $claims, 'after_release_event_id' => $next->id, 'distinct' => true]);
    }

    public function test_two_real_processes_materialize_one_stable_authorized_revision(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $directory = storage_path('app/private/outbox-materialize-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = [];
        $pids = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $process = new Process([PHP_BINARY, base_path('tests/support/outbox-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode(['action' => 'materialize', 'account_id' => $this->account->id, 'campaign_id' => $campaign->id, 'ready' => $directory.'/ready-'.$i, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(60)->start();
                $processes[] = $process;
                $pids[] = $process->getPid();
            }
            foreach ($processes as $i => $process) {
                $this->waitFile($directory.'/ready-'.$i, $process);
            }
            file_put_contents($directory.'/start', 'go');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            }
            $results = array_map(fn ($p) => trim($p->getOutput()), $processes);
            sort($results);
            $this->assertSame(['0', '1'], $results);
            $event = DeliveryOutbox::sole();
            $delivery = Delivery::sole();
            $this->assertSame($campaign->reviewed_revision_id, $delivery->campaign_revision_id);
            $this->assertSame($delivery->id, $event->delivery_id);
            $this->service->pause($this->account, $this->owner->id, $campaign->id, $campaign->version);
            $paused = $campaign->fresh();
            $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
            $this->service->resume($this->account, $this->owner->id, $campaign->id, $paused->version);
            $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
            $this->assertSame($event->id, DeliveryOutbox::sole()->id);
            $this->trace('two-materializers', ['results' => $results, 'event' => $event->payload(), 'revision_id' => $delivery->campaign_revision_id, 'source_hash' => $delivery->source_hash]);
        } finally {
            foreach ($processes as $i => $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                $this->recordProcess('materializer-'.$i, $process, $pids[$i]);
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function test_draft_review_future_paused_and_foreign_campaigns_cannot_materialize(): void
    {
        $draft = $this->draft();
        $reviewed = $this->reviewed();
        $future = $this->authorize($this->reviewed(), now('UTC')->addDay()->format('Y-m-d\TH:i'));
        $paused = $this->authorize($this->reviewed());
        $this->service->pause($this->account, $this->owner->id, $paused->id, $paused->version);
        foreach ([$draft, $reviewed, $future, $paused] as $campaign) {
            $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
        }
        $foreign = Account::create(['name' => 'Other account', 'owner_id' => $this->owner->id]);
        try {
            app(DeliveryMaterializer::class)->materialize($foreign->id, $paused->id);
            $this->fail('Cross-account lookup must fail.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('deliveries', 0);
        }
        $this->assertDatabaseCount('delivery_outbox', 0);
    }

    public function test_real_broker_confirm_persists_minimal_json_separate_from_laravel_queue(): void
    {
        $event = $this->event();
        [$exchange, $queue] = $this->topology();
        try {
            $this->assertNotSame(config('queue.connections.rabbitmq.queue'), $queue);
            $this->assertSame(2, Artisan::call('app:dispatch-outbox', ['--limit' => 0]));
            $this->assertSame('pending', $event->fresh()->status);
            $this->assertSame(0, Artisan::call('app:dispatch-outbox', ['--limit' => 1]));
            $this->assertSame('published', $event->fresh()->status);
            $this->assertSame($event->payload(), $this->receive($queue));
            $this->assertFalse(app(OutboxDispatcher::class)->dispatch($event->id));
            $this->trace('confirmed', ['event' => $event->payload(), 'sql_status' => $event->fresh()->status]);
        } finally {
            $this->cleanupTopology($exchange, $queue);
        }
    }

    public function test_real_mandatory_return_followed_by_ack_is_not_published_and_reconciles(): void
    {
        $event = $this->event();
        [$exchange, $queue] = $this->topology();
        $observer = new class extends PublicationObserver
        {
            public array $stages = [];

            public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
            {
                $this->stages[] = $stage;
            }
        };
        $this->app->instance(PublicationObserver::class, $observer);
        config(['delivery.routing_key' => 'missing.'.Str::uuid()]);
        try {
            app(OutboxDispatcher::class)->dispatch($event->id);
            $this->assertSame(['confirm_selected', 'before_publish', 'published_bytes', 'return', 'ack'], $observer->stages);
            $this->assertSame('pending', $event->fresh()->status);
            $this->assertSame('unroutable', $event->fresh()->last_error);
            $this->trace('return-ack', ['event' => $event->payload(), 'barriers' => $observer->stages, 'sql_status' => $event->fresh()->status]);
            config(['delivery.routing_key' => 'delivery.ready']);
            DeliveryOutbox::whereKey($event->id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
            app(OutboxDispatcher::class)->dispatch($event->id);
            $this->assertSame('published', $event->fresh()->status);
            $this->assertSame($event->payload(), $this->receive($queue));
        } finally {
            $this->cleanupTopology($exchange, $queue);
        }
    }

    public function test_connection_failure_keeps_pending_identity_and_sanitized_diagnostic(): void
    {
        $event = $this->event();
        $listener = stream_socket_server('tcp://127.0.0.1:0', $code, $error);
        $port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
        fclose($listener);
        config(['queue.connections.rabbitmq.hosts.0.port' => $port]);
        app(OutboxDispatcher::class)->dispatch($event->id);
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame('publish_uncertain', $event->fresh()->last_error);
        $this->assertSame($event->id, DeliveryOutbox::sole()->id);
        $this->assertNull($event->fresh()->published_at);
    }

    public function test_composite_sql_provenance_and_future_availability_are_enforced(): void
    {
        $event = $this->event();
        $foreign = Account::create(['name' => 'Foreign preserved account', 'owner_id' => $this->owner->id]);
        foreach (['deliveries' => $event->delivery_id, 'delivery_outbox' => $event->id] as $table => $id) {
            try {
                DB::table($table)->where('id', $id)->update(['account_id' => $foreign->id]);
                $this->fail('Composite account provenance must reject a foreign account.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
        }
        $another = $this->reviewed();
        $wrongRecipient = DB::table('campaign_recipients')->where('campaign_revision_id', $another->reviewed_revision_id)->value('id');
        try {
            DB::table('deliveries')->where('id', $event->delivery_id)->update(['campaign_recipient_id' => $wrongRecipient]);
            $this->fail('Recipient from a different revision must be rejected by MySQL.');
        } catch (QueryException $error) {
            $this->assertSame('23000', $error->errorInfo[0]);
        }
        DeliveryOutbox::whereKey($event->id)->update(['available_at' => DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)')]);
        $this->assertFalse(app(OutboxDispatcher::class)->dispatch($event->id));
        $this->assertSame(0, $event->fresh()->publish_attempts);
        $this->assertSame($this->account->id, $event->fresh()->account_id);
        $this->assertSame('Foreign preserved account', $foreign->fresh()->name);
    }

    public function test_incremental_migration_down_up_is_repeatable_and_keeps_campaign_snapshots(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $migration = require database_path('migrations/2026_10_23_000009_create_delivery_outbox.php');
        $workerMigration = require database_path('migrations/2026_10_23_000010_add_delivery_reservations.php');
        for ($i = 0; $i < 2; $i++) {
            $workerMigration->down();
            $migration->down();
            $this->assertFalse(Schema::hasTable('delivery_outbox'));
            $this->assertSame($campaign->reviewed_revision_id, $campaign->fresh()->reviewed_revision_id);
            $this->assertDatabaseCount('campaign_recipients', 1);
            $migration->up();
            $workerMigration->up();
            $this->assertTrue(Schema::hasTable('delivery_outbox'));
            $this->assertTrue(Schema::hasIndex('deliveries', 'delivery_recovery'));
            $this->assertTrue(Schema::hasColumn('deliveries', 'lease_token'));
            $this->assertTrue(Schema::hasColumn('deliveries', 'attempts'));
        }
        $this->assertSame(1, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
    }

    public function test_expired_lease_generation_and_token_fence_every_final_write(): void
    {
        $event = $this->event();
        $dispatcher = app(OutboxDispatcher::class);
        $old = $dispatcher->claim($event->id);
        $this->assertNotNull($old);
        $this->assertNull($dispatcher->claim($event->id));
        DeliveryOutbox::whereKey($event->id)->update(['lease_expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
        $this->assertFalse($dispatcher->complete($old, new PublicationResult(true)));
        $new = $dispatcher->claim($event->id);
        $this->assertNotSame($old->lease_token, $new->lease_token);
        $this->assertFalse($dispatcher->complete($old, new PublicationResult(true)));
        $wrong = clone $new;
        $wrong->generation++;
        $this->assertFalse($dispatcher->complete($wrong, new PublicationResult(true)));
        $this->assertTrue($dispatcher->complete($new, new PublicationResult(true)));
        $this->assertFalse($dispatcher->complete($old, new PublicationResult(false, 'publish_uncertain')));
        $this->assertSame('published', $event->fresh()->status);
        $this->trace('fencing', ['event' => $event->payload(), 'old_token' => $old->lease_token, 'new_token' => $new->lease_token, 'old_cas' => false, 'current_cas' => true]);
    }

    public function test_publisher_rejects_outer_sql_transaction_and_laravel_queue_collision(): void
    {
        $event = $this->event();
        DB::beginTransaction();
        try {
            try {
                app(OutboxDispatcher::class)->dispatch($event->id);
                $this->fail('Dispatch inside caller SQL transaction is forbidden.');
            } catch (\LogicException) {
                $this->assertSame('pending', $event->fresh()->status);
            }
        } finally {
            DB::rollBack();
        }
        config(['delivery.queue' => config('queue.connections.rabbitmq.queue')]);
        $this->expectException(\LogicException::class);
        app(ConfirmedDeliveryPublisher::class)->publish($event);
    }

    public function test_application_identity_cannot_access_foreign_broker_vhost(): void
    {
        $host = config('queue.connections.rabbitmq.hosts.0');
        $own = $this->broker();
        $own->close();
        try {
            $foreign = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], 'emailing-lab');
            $foreign->close();
            $this->fail('Foreign vhost must deny access.');
        } catch (AMQPConnectionClosedException $error) {
            $this->assertContains($error->getCode(), [403, 530]);
        }
    }

    public function test_real_proxy_loses_confirm_after_routed_publish_and_retries_same_identity(): void
    {
        $event = $this->event();
        [$exchange, $queue] = $this->topology();
        $directory = storage_path('app/private/outbox-proxy-'.Str::uuid());
        mkdir($directory, 0700, true);
        $host = config('queue.connections.rabbitmq.hosts.0');
        $proxy = new Process(['python', base_path('tests/support/outbox-confirm-proxy.py'), $directory, $host['host'], (string) $host['port']], base_path());
        $proxy->setTimeout(50)->start();
        $proxyPid = $proxy->getPid();
        try {
            $this->waitFile($directory.'/proxy-ready', $proxy);
            config(['queue.connections.rabbitmq.hosts.0.port' => (int) file_get_contents($directory.'/proxy-ready'), 'delivery.confirm_timeout' => 1]);
            $observer = new class($directory) extends PublicationObserver
            {
                public array $stages = [];

                public function __construct(private string $directory) {}

                public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
                {
                    $this->stages[] = $stage;
                    if ($stage === 'before_publish') {
                        file_put_contents($this->directory.'/drop-confirm', 'confirm-select-ok already received');
                        $deadline = microtime(true) + 5;
                        while (! is_file($this->directory.'/drop-armed')) {
                            if (microtime(true) > $deadline) {
                                throw new \RuntimeException('Own proxy was not armed.');
                            }
                            usleep(10000);
                        }
                    }
                }
            };
            $this->app->instance(PublicationObserver::class, $observer);
            app(OutboxDispatcher::class)->dispatch($event->id);
            config(['queue.connections.rabbitmq.hosts.0.port' => $host['port']]);
            $this->assertSame(['confirm_selected', 'before_publish', 'published_bytes'], $observer->stages);
            $this->assertSame('pending', $event->fresh()->status);
            $this->assertSame('publish_uncertain', $event->fresh()->last_error);
            $first = $this->receive($queue);
            $this->assertSame($event->payload(), $first);
            $observation = json_decode(file_get_contents($directory.'/proxy-observation'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertGreaterThan(0, $observation['dropped_server_bytes']);
            $this->assertContains('60:80', $observation['dropped_methods'], 'The proxy must discard basic.ack after confirm.select-ok reached the publisher.');
            $this->app->instance(PublicationObserver::class, new PublicationObserver);
            DeliveryOutbox::whereKey($event->id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
            app(OutboxDispatcher::class)->dispatch($event->id);
            $second = $this->receive($queue);
            $this->assertSame($first, $second);
            $this->assertSame('published', $event->fresh()->status);
            $this->trace('confirm-lost', ['barriers' => $observer->stages, 'proxy' => $observation, 'first' => $first, 'retry' => $second, 'sql_after_loss' => 'pending', 'sql_after_retry' => $event->fresh()->status]);
        } finally {
            config(['queue.connections.rabbitmq.hosts.0.port' => $host['port']]);
            if ($proxy->isRunning()) {
                $proxy->stop();
            }
            $this->recordProcess('confirm-proxy', $proxy, $proxyPid);
            $this->trace('proxy-process', ['exit' => $proxy->getExitCode(), 'stdout' => $proxy->getOutput(), 'stderr' => $proxy->getErrorOutput()]);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
            $this->cleanupTopology($exchange, $queue);
        }
    }

    public function test_real_linux_dispatcher_crash_after_confirm_before_sql_reconciles_same_event(): void
    {
        $event = $this->event();
        [$exchange, $queue] = $this->topology();
        $uuid = (string) Str::uuid();
        $directory = storage_path('app/private/outbox-crash-'.$uuid);
        mkdir($directory, 0700, true);
        $name = 'emailing-t12-crash-'.$uuid;
        $command = ['docker', 'run', '--rm', '-i', '--name', $name, '--network', 'emailing-lab'];
        $profile = getenv('T12_VENDOR_PROFILE') ?: 'image';
        if ($profile === 'host-bind') {
            array_push($command, '--mount', 'type=bind,source='.base_path().',target=/var/www/emailing');
        } else {
            // Keep Linux vendor from the image while executing the current
            // application, configuration, migration and fixture sources.
            foreach (['app', 'config', 'database', 'routes', 'tests', 'storage', 'bootstrap/app.php', 'bootstrap/providers.php', '.env.testing'] as $path) {
                array_push($command, '--mount', 'type=bind,source='.base_path($path).',target=/var/www/emailing/'.$path.($path === 'storage' ? '' : ',readonly'));
            }
        }
        array_push($command, '-e', 'APP_ENV=testing', '-e', 'DB_HOST=mysql', '-e', 'DB_PORT=3306', '-e', 'DB_DATABASE=emailing_testing', '-e', 'DB_USERNAME=emailing_testing', '-e', 'DB_URL=', '-e', 'RABBITMQ_HOST=rabbitmq', '-e', 'T12_PROGRESS=/var/www/emailing/storage/app/private/outbox-crash-'.$uuid.'/progress.jsonl', 'emailing-app/php:local', 'php', 'tests/support/outbox-action.php');
        $process = new Process($command, base_path());
        $process->setInput(json_encode(['action' => 'crash-after-confirm', 'event_id' => $event->id, 'exchange' => $exchange, 'queue' => $queue, 'confirmed' => '/var/www/emailing/storage/app/private/outbox-crash-'.$uuid.'/confirmed'], JSON_THROW_ON_ERROR));
        $process->setTimeout(90)->start();
        $pid = $process->getPid();
        try {
            $barrier = OutboxProtocolBarrier::await($directory.'/confirmed', fn ($data) => ($data['stage'] ?? null) === 'confirmed' && ($data['event_id'] ?? null) === $event->id && ($data['generation'] ?? null) === $event->generation && ($data['barriers'] ?? null) === ['confirm_selected', 'before_publish', 'published_bytes', 'ack', 'confirmed'], 40, fn () => $process->isRunning());
            $barrierBytes = file_get_contents($directory.'/confirmed');
            $this->trace('linux-barrier-read', ['bytes' => strlen($barrierBytes), 'sha256' => hash('sha256', $barrierBytes), 'json_valid' => json_validate($barrierBytes), 'vendor_profile' => $profile]);
            $this->assertSame($event->id, $barrier['event_id']);
            $this->assertSame(['confirm_selected', 'before_publish', 'published_bytes', 'ack', 'confirmed'], $barrier['barriers']);
            $entries = array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($directory.'/progress.jsonl', FILE_IGNORE_NEW_LINES));
            $loaded = array_values(array_filter($entries, fn ($entry) => $entry['stage'] === 'loaded_sources'))[0];
            foreach ($loaded['hashes'] as $class => $hash) {
                $this->assertSame(hash_file('sha256', (new \ReflectionClass($class))->getFileName()), $hash, 'Linux must execute the exact current source for '.$class);
            }
            $this->assertSame(hash_file('sha256', base_path('composer.lock')), $loaded['composer_lock_sha256']);
            $this->assertSame(InstalledVersions::getPrettyVersion('php-amqplib/php-amqplib'), $loaded['amqp_version']);
            $this->assertSame('publishing', $event->fresh()->status);
            $this->assertNull($event->fresh()->published_at);
            $first = $this->receive($queue);
            $kill = new Process(['docker', 'kill', '--signal=KILL', $name]);
            $kill->setTimeout(20);
            $kill->mustRun();
            $this->recordProcess('linux-kill', $kill, null);
            $process->wait();
            $this->assertSame(137, $process->getExitCode(), $process->getErrorOutput());
            DeliveryOutbox::whereKey($event->id)->update(['lease_expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
            app(OutboxDispatcher::class)->dispatch($event->id);
            $second = $this->receive($queue);
            $this->assertSame($first, $second);
            $this->assertSame($event->payload(), $second);
            $this->assertSame('published', $event->fresh()->status);
            $this->trace('linux-crash-after-confirm', ['container' => $name, 'barrier' => $barrier, 'sql_at_barrier' => 'publishing', 'published_at_at_barrier' => null, 'exit' => $process->getExitCode(), 'first' => $first, 'retry' => $second, 'sql_after_retry' => $event->fresh()->status]);
        } finally {
            if ($process->isRunning()) {
                $kill = new Process(['docker', 'kill', '--signal=KILL', $name]);
                $kill->setTimeout(20);
                $kill->run();
                $process->wait();
            }
            $this->recordProcess('linux-dispatcher', $process, $pid);
            $progress = is_file($directory.'/progress.jsonl') ? array_map(fn ($line) => json_decode($line, true, 512, JSON_THROW_ON_ERROR), file($directory.'/progress.jsonl', FILE_IGNORE_NEW_LINES)) : [];
            $this->trace('linux-fixture-progress', ['event_id' => $event->id, 'vendor_profile' => $profile, 'progress' => $progress, 'sql_status' => $event->fresh()->status]);
            $this->trace('linux-process', ['container' => $name, 'exit' => $process->getExitCode(), 'stdout' => $process->getOutput(), 'stderr' => $process->getErrorOutput()]);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
            $this->cleanupTopology($exchange, $queue);
        }
    }

    public function test_real_sql_connection_loss_after_broker_confirm_keeps_durable_reconciliation(): void
    {
        $event = $this->event();
        [$exchange, $queue] = $this->topology();
        $directory = storage_path('app/private/outbox-sql-proxy-'.Str::uuid());
        mkdir($directory, 0700, true);
        $database = config('database.connections.mysql');
        $proxy = new Process(['python', base_path('tests/support/outbox-confirm-proxy.py'), $directory, $database['host'], (string) $database['port']], base_path());
        $proxy->setTimeout(50)->start();
        $pid = $proxy->getPid();
        try {
            $this->waitFile($directory.'/proxy-ready', $proxy);
            config(['database.connections.mysql.host' => '127.0.0.1', 'database.connections.mysql.port' => (int) file_get_contents($directory.'/proxy-ready')]);
            DB::purge('mysql');
            $observer = new class($directory) extends PublicationObserver
            {
                public array $stages = [];

                public function __construct(private string $directory) {}

                public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
                {
                    $this->stages[] = $stage;
                    if ($stage === 'confirmed') {
                        file_put_contents($this->directory.'/disconnect-sql', $event->id);
                        $deadline = microtime(true) + 5;
                        while (! is_file($this->directory.'/sql-disconnected')) {
                            if (microtime(true) > $deadline) {
                                throw new \RuntimeException('SQL proxy did not disconnect.');
                            }
                            usleep(10000);
                        }
                    }
                }
            };
            $this->app->instance(PublicationObserver::class, $observer);
            try {
                app(OutboxDispatcher::class)->dispatch($event->id);
                $this->fail('The final SQL write must fail across the disconnected owned proxy.');
            } catch (QueryException) {
                $this->assertContains('confirmed', $observer->stages);
            } finally {
                config(['database.connections.mysql' => $database]);
                DB::purge('mysql');
            }
            $proxy->wait();
            $this->assertSame(0, $proxy->getExitCode(), $proxy->getErrorOutput());
            $this->assertSame('publishing', $event->fresh()->status);
            $this->assertNull($event->fresh()->published_at);
            $first = $this->receive($queue);
            $this->app->instance(PublicationObserver::class, new PublicationObserver);
            DeliveryOutbox::whereKey($event->id)->update(['lease_expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
            app(OutboxDispatcher::class)->dispatch($event->id);
            $second = $this->receive($queue);
            $this->assertSame($first, $second);
            $this->assertSame('published', $event->fresh()->status);
            $this->trace('sql-loss-after-confirm', ['barriers' => $observer->stages, 'sql_after_cut' => 'publishing', 'published_at_after_cut' => null, 'first' => $first, 'retry' => $second, 'sql_after_retry' => $event->fresh()->status]);
        } finally {
            config(['database.connections.mysql' => $database]);
            DB::purge('mysql');
            if ($proxy->isRunning()) {
                $proxy->stop();
            }
            $this->recordProcess('sql-proxy', $proxy, $pid);
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
            $this->cleanupTopology($exchange, $queue);
        }
    }
}
