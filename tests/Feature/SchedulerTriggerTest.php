<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\Membership;
use App\Models\User;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryMaterializer;
use App\Services\OutboxDispatcher;
use App\Services\PublicationObserver;
use App\Services\SchedulerActions;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

#[Group('delivery')]
class SchedulerTriggerTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCampaigns { setUp as private campaignSetUp; }

    private const TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private string $topology;

    private bool $brokerUsed = false;

    protected function setUp(): void
    {
        $this->campaignSetUp();
        $this->topology = 'emailing.outbox-test.'.Str::uuid();
        config(['cronicle.secret' => self::TOKEN, 'cronicle.dispatch_claim_limit' => 20, 'cronicle.dispatch_budget_ms' => 10000,
            'delivery.exchange' => $this->topology, 'delivery.queue' => $this->topology, 'delivery.failure_queue' => $this->topology.'.failed']);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->brokerUsed) {
                $broker = $this->broker();
                try {
                    $channel = $broker->channel();
                    $channel->queue_delete($this->topology);
                    $channel->exchange_delete($this->topology);
                    $this->record('broker-cleanup', ['topology' => $this->topology, 'deleted' => true]);
                } finally {
                    $broker->close();
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function broker(): AMQPStreamConnection
    {
        $host = config('queue.connections.rabbitmq.hosts.0');

        return new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost']);
    }

    private function trigger(string $action = 'materialize')
    {
        return $this->call('POST', '/internal/cronicle/trigger', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => 'application/json'], json_encode(['action' => $action], JSON_THROW_ON_ERROR));
    }

    private function record(string $name, array $facts): void
    {
        $directory = (getenv('T15_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t15/implementation')).'/protocol';
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $loaded = [];
        foreach ([SchedulerActions::class, DeliveryMaterializer::class, OutboxDispatcher::class, ConfirmedDeliveryPublisher::class] as $class) {
            $file = (new \ReflectionClass($class))->getFileName();
            $loaded[$class] = ['origin' => $file, 'sha256' => hash_file('sha256', $file)];
        }
        file_put_contents($directory.'/'.$name.'-'.Str::uuid().'.json', json_encode(['run' => getenv('T15_EVIDENCE_RUN'), 'utc' => gmdate('c'), 'monotonic_ns' => hrtime(true), 'pid' => getmypid(), 'platform' => PHP_OS_FAMILY, 'loaded' => $loaded, 'facts' => $facts], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function campaignWithRecipients(int $count): Campaign
    {
        $marker = 'scheduler-'.Str::uuid();
        $now = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS clock')->clock;
        $consentedAt = '2026-01-01 00:00:00';
        $list = DB::table('contact_lists')->insertGetId(['account_id' => $this->account->id, 'name' => $marker, 'description' => '', 'created_at' => $now, 'updated_at' => $now]);
        foreach (array_chunk(range(1, $count), 500) as $chunk) {
            DB::table('contacts')->insert(array_map(fn ($i) => ['account_id' => $this->account->id, 'email' => $marker.'-'.$i.'@example.test', 'name' => 'Scheduler fixture', 'consent_status' => 'granted', 'consent_source' => $marker, 'consented_at' => $consentedAt, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }
        $contacts = DB::table('contacts')->where('account_id', $this->account->id)->where('consent_source', $marker)->pluck('id')->all();
        foreach (array_chunk($contacts, 500) as $chunk) {
            DB::table('contact_list_memberships')->insert(array_map(fn ($id) => ['account_id' => $this->account->id, 'contact_list_id' => $list, 'contact_id' => $id], $chunk));
        }
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$list], 'contact_ids' => []]));
        $this->record('factory-clock-precondition', ['consented_at_supplied' => $consentedAt, 'sql_clock' => $now, 'persisted_consent' => DB::table('contacts')->whereIn('id', $contacts)->value('consented_at'), 'php_utc' => now('UTC')->format('Y-m-d H:i:s.u'), 'contacts' => $count]);
        $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);

        return $this->authorize($campaign);
    }

    public function test_idle_ticks_and_direct_replay_do_not_probe_completed_recipient_history(): void
    {
        $reads = static function (): int {
            return (int) DB::selectOne("SHOW SESSION STATUS LIKE 'Handler_read_key'")->Value;
        };
        $before = $reads();
        $this->trigger()->assertOk()->assertJson(['stop_reason' => 'empty']);
        $baseline = $reads() - $before;
        $campaigns = [];
        for ($i = 0; $i < 10; $i++) {
            $campaign = $this->campaignWithRecipients(200);
            $this->assertSame(200, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
            $campaigns[] = $campaign->id;
        }
        $ticks = [];
        for ($i = 0; $i < 3; $i++) {
            $before = $reads();
            $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
            $ticks[] = $reads() - $before;
        }
        $before = $reads();
        $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
        $replay = $reads() - $before;
        $this->record('checkpoint-history', compact('campaigns', 'baseline', 'ticks', 'replay'));
        foreach ($ticks as $tick) {
            $this->assertLessThanOrEqual($baseline + 100, $tick, 'Idle selection must exclude completed snapshots before probing recipients.');
        }
        $this->assertLessThan(100, $replay, 'Direct replay must use the durable checkpoint.');
        $this->assertDatabaseCount('deliveries', 2000);
        $this->assertDatabaseCount('delivery_outbox', 2000);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_checkpoint_and_pairs_rollback_together_and_replay_and_pause_preserve_identity(): void
    {
        $campaign = $this->campaignWithRecipients(2);
        $failed = false;
        DB::listen(function ($query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'update `campaigns`') && str_contains($query->sql, '`materialized_revision_id`')) {
                $failed = true;
                throw new \RuntimeException('Synthetic failure after checkpoint SQL, before commit.');
            }
        });
        try {
            app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
            $this->fail('The injected transaction failure must propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Synthetic failure after checkpoint SQL, before commit.', $error->getMessage());
        }
        $this->assertTrue($failed);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->assertNull($campaign->fresh()->materialized_revision_id);
        $this->assertSame(1, (int) $campaign->fresh()->materialization_pending);
        $this->assertSame(2, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
        $identities = DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all();
        $this->assertSame($campaign->reviewed_revision_id, (int) $campaign->fresh()->materialized_revision_id);
        $this->assertSame('authorized', $campaign->fresh()->status);
        $paused = $this->service->pause($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id));
        $resumed = $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version);
        $this->assertSame(0, app(DeliveryMaterializer::class)->materialize($this->account->id, $resumed->id));
        $this->assertSame($identities, DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all());
        $this->assertDatabaseCount('mail_messages', 0);
        $this->record('checkpoint-rollback-replay', ['rollback_observed' => $failed, 'identities' => $identities,
            'checkpoint' => $campaign->fresh()->materialized_revision_id, 'status' => $campaign->fresh()->status]);
    }

    public function test_new_review_identity_invalidates_a_retained_legacy_checkpoint_without_an_entrypoint_reset(): void
    {
        $reviewed = $this->reviewed();
        // Simulate a retained legacy checkpoint; this fixture does not claim any
        // SMTP work. The valid edit/review transition must supersede its identity.
        DB::table('campaigns')->where('id', $reviewed->id)->update(['materialized_revision_id' => $reviewed->reviewed_revision_id]);
        $this->assertSame(0, (int) $reviewed->fresh()->materialization_pending);
        $edited = $this->service->save($this->account, $this->owner->id, $reviewed->id, $reviewed->version, $this->input());
        $this->assertNull($edited->reviewed_revision_id);
        $this->assertSame(0, (int) $edited->materialization_pending);
        $newReview = $this->service->review($this->account, $this->owner->id, $edited->id, $edited->version);
        $this->assertNotSame($reviewed->reviewed_revision_id, $newReview->reviewed_revision_id);
        $this->assertSame(1, (int) $newReview->materialization_pending);
        $authorized = $this->authorize($newReview);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 1, 'emails_completed' => null]);
        $this->assertSame($newReview->reviewed_revision_id, (int) $authorized->fresh()->materialized_revision_id);
        $this->assertSame([$newReview->reviewed_revision_id], Delivery::pluck('campaign_revision_id')->all());
        $this->record('checkpoint-new-review', ['old_revision' => $reviewed->reviewed_revision_id,
            'new_revision' => $newReview->reviewed_revision_id, 'legacy_checkpoint_fixture' => true]);
    }

    public function test_zero_recipient_legacy_revision_is_checkpointed_and_does_not_starve_later_work(): void
    {
        $empty = $this->campaignWithRecipients(1);
        DB::table('campaign_recipients')->where('campaign_revision_id', $empty->reviewed_revision_id)->delete();
        DB::table('campaign_revisions')->where('id', $empty->reviewed_revision_id)->update(['recipient_count' => 0]);
        $next = $this->campaignWithRecipients(1);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'campaign_limit']);
        $this->assertSame($empty->reviewed_revision_id, (int) $empty->fresh()->materialized_revision_id);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 1]);
        $this->assertSame([$next->id], Delivery::pluck('campaign_id')->all());
        $this->trigger()->assertOk()->assertJson(['stop_reason' => 'empty']);
        $this->record('checkpoint-empty-prefix', ['empty_revision' => $empty->reviewed_revision_id, 'next_campaign' => $next->id]);
    }

    public function test_incremental_checkpoint_migration_preserves_data_and_classifies_complete_incomplete_and_invalid_snapshots(): void
    {
        $complete = $this->campaignWithRecipients(1);
        $partial = $this->campaignWithRecipients(2);
        $unpaired = $this->campaignWithRecipients(1);
        $empty = $this->campaignWithRecipients(1);
        $invalid = $this->campaignWithRecipients(1);
        $draft = $this->draft();
        $otherAccount = Account::create(['name' => 'Synthetic legacy foreign revision', 'owner_id' => $this->owner->id]);
        // Legacy invalid pointer: intentionally account-foreign and never an
        // authorized domain input. Backfill must not certify this association.
        $foreign = Campaign::create(['account_id' => $otherAccount->id, 'created_by' => $this->owner->id,
            'name' => 'Synthetic foreign pointer', 'status' => 'authorized', 'version' => 1,
            'template_id' => $this->template->id, 'sender_id' => $this->sender->id, 'list_ids' => [], 'contact_ids' => [],
            'reviewed_revision_id' => $complete->reviewed_revision_id, 'authorized_by' => $this->owner->id,
            'authorized_at' => now('UTC'), 'effective_at' => now('UTC')]);
        foreach ([$complete, $partial, $unpaired] as $campaign) {
            app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
        }
        Delivery::where('campaign_id', $partial->id)->orderBy('id')->firstOrFail()->delete();
        DeliveryOutbox::where('delivery_id', Delivery::where('campaign_id', $unpaired->id)->value('id'))->delete();
        Delivery::where('campaign_id', $unpaired->id)->update(['status' => 'ambiguous', 'attempts' => 1]);
        DB::table('campaign_recipients')->where('campaign_revision_id', $empty->reviewed_revision_id)->delete();
        DB::table('campaign_revisions')->where('id', $empty->reviewed_revision_id)->update(['recipient_count' => 0]);
        DB::table('campaigns')->where('id', $invalid->id)->update(['reviewed_revision_id' => $complete->reviewed_revision_id]);
        $snapshot = static function (): array {
            return json_decode(json_encode(['deliveries' => Delivery::orderBy('id')->get()->toArray(), 'outbox' => DeliveryOutbox::orderBy('id')->get()->toArray(),
                'revisions' => DB::table('campaign_revisions')->orderBy('id')->get()->toArray(),
                'recipients' => DB::table('campaign_recipients')->orderBy('id')->get()->toArray(),
                'campaigns' => DB::table('campaigns')->orderBy('id')->get(['id', 'account_id', 'status', 'reviewed_revision_id', 'authorized_at'])->toArray()], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        };
        $before = $snapshot();
        $file = database_path('migrations/2026_10_24_000012_add_materialization_checkpoint.php');
        $migration = require $file;
        $this->record('checkpoint-migration-before', ['migration_sha256' => hash_file('sha256', $file), 'before' => $before]);
        for ($iteration = 0; $iteration < 2; $iteration++) {
            $migration->down();
            $primary = null;
            try {
                $this->assertFalse(Schema::hasColumn('campaigns', 'materialized_revision_id'));
                $this->assertSame($before, $snapshot());
            } catch (\Throwable $error) {
                $primary = $error;
            } finally {
                try {
                    $migration->up();
                    $this->record('checkpoint-migration-restored', ['iteration' => $iteration, 'restored' => true, 'primary_class' => $primary === null ? null : get_class($primary)]);
                } catch (\Throwable $restoration) {
                    $this->record('checkpoint-migration-restoration-failed', ['iteration' => $iteration, 'primary_class' => $primary === null ? null : get_class($primary), 'restoration_class' => get_class($restoration)]);
                    if ($primary === null) {
                        throw $restoration;
                    }
                }
            }
            if ($primary !== null) {
                throw $primary;
            }
            $this->assertTrue(Schema::hasIndex('campaigns', 'campaign_materialization_due'));
            $this->assertSame($before, $snapshot());
            $this->assertSame($complete->reviewed_revision_id, (int) $complete->fresh()->materialized_revision_id);
            $this->assertSame($empty->reviewed_revision_id, (int) $empty->fresh()->materialized_revision_id);
            foreach ([$partial, $unpaired, $invalid, $foreign, $draft] as $pending) {
                $this->assertNull($pending->fresh()->materialized_revision_id);
            }
            $this->assertSame(0, (int) $draft->fresh()->materialization_pending);
        }
        $this->assertSame(1, app(DeliveryMaterializer::class)->materialize($this->account->id, $partial->id));
        try {
            app(DeliveryMaterializer::class)->materialize($this->account->id, $unpaired->id);
            $this->fail('Missing initial outbox must not be certified or blindly republished.');
        } catch (\LogicException $error) {
            $this->assertSame('Materialization pair is incomplete.', $error->getMessage());
        }
        $this->assertNull($unpaired->fresh()->materialized_revision_id);
        $this->assertSame('ambiguous', Delivery::where('campaign_id', $unpaired->id)->value('status'));
        $this->assertSame(0, DeliveryOutbox::where('delivery_id', Delivery::where('campaign_id', $unpaired->id)->value('id'))->count());
        $this->record('checkpoint-migration', ['migration_sha256' => hash_file('sha256', $file),
            'before' => $before, 'after' => $snapshot(), 'complete' => $complete->id, 'partial' => $partial->id,
            'unpaired' => $unpaired->id, 'unpaired_ambiguous_is_sql_fixture_not_observed_smtp' => true,
            'empty' => $empty->id, 'invalid_revision_owner' => $invalid->id, 'foreign_account_revision' => $foreign->id, 'draft' => $draft->id]);
    }

    public function test_real_route_requires_only_the_dedicated_current_header_and_does_no_domain_sql_when_denied(): void
    {
        $this->authorize($this->reviewed());
        $queries = [];
        DB::listen(function ($event) use (&$queries) {
            $queries[] = $event->sql;
        });
        foreach (['', 'Basic '.self::TOKEN, 'Bearer '.str_repeat('b', 64), 'Bearer '.str_repeat('A', 64)] as $header) {
            $this->call('POST', '/internal/cronicle/trigger', [], [], [], ['HTTP_AUTHORIZATION' => $header, 'CONTENT_TYPE' => 'application/json'], '{"action":"materialize"}')
                ->assertStatus(401)->assertJson(['error' => 'unauthorized'])->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->postJson('/internal/cronicle/trigger?token='.self::TOKEN, ['action' => 'materialize', 'token' => self::TOKEN])->assertUnauthorized();
        $this->assertSame([], $queries, 'Interactive auth/session/body/query must not enter domain SQL.');
        $this->assertDatabaseCount('deliveries', 0);
        foreach ([null, '', str_repeat('x', 64), 123] as $invalid) {
            config(['cronicle.secret' => $invalid]);
            $this->trigger()->assertStatus(503)->assertJson(['error' => 'scheduler_disabled']);
        }
    }

    public function test_fractional_fixture_consent_can_round_into_the_future_and_is_correctly_excluded(): void
    {
        $supplied = '2026-01-01 00:00:00.750000';
        DB::table('contacts')->where('id', $this->contact->id)->update(['consented_at' => $supplied]);
        $persisted = $this->contact->fresh()->consented_at;
        $this->travelTo(CarbonImmutable::parse('2026-01-01 00:00:00.900000', 'UTC'));
        try {
            $this->assertSame('2026-01-01 00:00:01', $persisted->format('Y-m-d H:i:s'));
            $this->assertTrue($persisted->gt(now('UTC')));
            $draft = $this->draft();
            try {
                $this->service->review($this->account, $this->owner->id, $draft->id, $draft->version);
                $this->fail('Future consent must not enter a reviewed scheduler snapshot.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('audience', $error->errors());
            }
            $this->assertDatabaseCount('campaign_recipients', 0);
            $this->record('fractional-consent-conversion', ['supplied_sql' => $supplied, 'persisted' => $persisted->format('Y-m-d H:i:s.u'), 'php_reference' => now('UTC')->format('Y-m-d H:i:s.u'), 'review_rejected' => true, 'historical_failure_instant_not_captured' => true]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_real_route_rejects_methods_query_content_depth_size_and_arbitrary_input_before_work(): void
    {
        $this->authorize($this->reviewed());
        foreach (['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'TRACE'] as $method) {
            $this->call($method, '/internal/cronicle/trigger', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN])->assertStatus(405)->assertJson(['error' => 'post_required'])->assertHeader('Allow', 'POST');
        }
        $this->call('HEAD', '/internal/cronicle/trigger', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN])->assertStatus(405)->assertHeader('Allow', 'POST');
        $this->withToken(self::TOKEN)->postJson('/internal/cronicle/trigger?limit=100', ['action' => 'dispatch'])->assertStatus(422);
        foreach ([['{', 'application/json', 400], ['{"action":"materialize"}', 'text/plain', 415], [str_repeat(' ', 1025), 'application/json', 413], ['{"action":"dispatch","account_id":1}', 'application/json', 422], ['{"action":"dispatch","limit":100}', 'application/json', 422], ['{"action":"shell","command":"echo fixture"}', 'application/json', 422], ['{"action":{"a":{"b":{"c":1}}}}', 'application/json', 400]] as [$body, $type, $code]) {
            $this->call('POST', '/internal/cronicle/trigger', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.self::TOKEN, 'CONTENT_TYPE' => $type], $body)->assertStatus($code);
        }
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_materialization_uses_sql_due_state_skips_complete_prefix_and_keeps_durable_identity_across_replay(): void
    {
        $draft = $this->draft();
        $reviewed = $this->reviewed();
        $future = $this->authorize($this->reviewed(), now('UTC')->addHours(2)->format('Y-m-d\TH:i'));
        $complete = [];
        for ($i = 0; $i < 5; $i++) {
            $campaign = $this->authorize($this->reviewed());
            app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
            $complete[] = $campaign->id;
        }
        $pending = [];
        for ($i = 0; $i < 3; $i++) {
            $pending[] = $this->authorize($this->reviewed())->id;
        }
        foreach ($pending as $id) {
            $this->trigger()->assertOk()->assertJson(['deliveries_created' => 1, 'outbox_claims' => 0, 'stop_reason' => 'campaign_limit', 'emails_completed' => null]);
            $this->assertTrue(Delivery::where('campaign_id', $id)->exists());
        }
        $identities = DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all();
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
        $this->assertSame($identities, DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all());
        $this->assertSame(0, Delivery::whereIn('campaign_id', [$draft->id, $reviewed->id, $future->id])->count());
        $this->assertDatabaseCount('mail_messages', 0);
        $this->record('prefix-and-replay', compact('complete', 'pending', 'identities'));
    }

    public function test_dispatch_default_batch_publishes_more_than_one_with_real_confirms_and_later_invocations_progress(): void
    {
        $campaign = $this->campaignWithRecipients(23);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 23]);
        $this->brokerUsed = true;
        $first = $this->trigger('dispatch')->assertOk()->assertJson(['outbox_claims' => 20, 'stop_reason' => 'claim_limit', 'emails_completed' => null])->json();
        $this->assertSame(20, DeliveryOutbox::where('status', 'published')->count());
        $second = $this->trigger('dispatch')->assertOk()->assertJson(['outbox_claims' => 3, 'stop_reason' => 'empty'])->json();
        $this->trigger('dispatch')->assertOk()->assertJson(['outbox_claims' => 0, 'stop_reason' => 'empty']);
        $broker = $this->broker();
        try {
            $channel = $broker->channel();
            [, $ready] = $channel->queue_declare($this->topology, true);
            $this->assertSame(23, $ready);
            $seen = [];
            while ($message = $channel->basic_get($this->topology, false)) {
                $body = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);
                $seen[] = $body['event_id'];
                $channel->basic_ack($message->getDeliveryTag());
            }
            $this->assertCount(23, array_unique($seen));
        } finally {
            $broker->close();
        }
        $this->assertSame(23, Delivery::where('campaign_id', $campaign->id)->where('status', 'pending')->count());
        $this->assertDatabaseCount('mail_messages', 0);
        $this->record('native-default-batch', compact('first', 'second', 'seen') + ['topology' => $this->topology, 'acked' => 23]);
    }

    public function test_global_materialization_visits_both_accounts_without_interactive_account_identity(): void
    {
        $first = $this->authorize($this->reviewed());
        $actor = User::factory()->create();
        $other = Account::create(['name' => 'Synthetic scheduler B', 'owner_id' => $actor->id]);
        Membership::create(['account_id' => $other->id, 'user_id' => $actor->id, 'role' => 'admin', 'scopes' => []]);
        $template = $this->template->replicate();
        $template->account_id = $other->id;
        $template->save();
        $sender = $this->sender->replicate();
        $sender->account_id = $other->id;
        $sender->save();
        $contact = $this->contact->replicate();
        $contact->account_id = $other->id;
        $contact->save();
        $draft = $this->service->save($other, $actor->id, null, null, $this->input(['template_id' => $template->id, 'sender_id' => $sender->id, 'contact_ids' => [$contact->id]]));
        $reviewed = $this->service->review($other, $actor->id, $draft->id, $draft->version);
        $second = $this->service->authorizeCampaign($other, $actor->id, $reviewed->id, $reviewed->version, $reviewed->reviewed_revision_id, true, null);
        auth()->forgetGuards();
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 1]);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 1]);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
        $this->assertTrue(Delivery::where('account_id', $this->account->id)->where('campaign_id', $first->id)->exists());
        $this->assertTrue(Delivery::where('account_id', $other->id)->where('campaign_id', $second->id)->exists());
        $this->assertDatabaseCount('delivery_outbox', 2);
        $this->record('accounts-no-session', ['accounts' => [$this->account->id, $other->id], 'same_recipient_email' => $contact->email === $this->contact->email, 'identities' => DeliveryOutbox::pluck('delivery_id', 'id')->all()]);
    }

    public function test_domain_rechecks_a_due_candidate_changed_after_selection_before_the_materialization_lock(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $changed = false;
        DB::listen(function ($query) use (&$changed, $campaign) {
            $sql = str_replace('`', '', strtolower($query->sql));
            if (! $changed && str_starts_with($sql, 'select * from campaigns') && ! str_contains($sql, 'for update')) {
                $changed = true;
                DB::table('campaigns')->where('id', $campaign->id)->update(['effective_at' => DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR)')]);
            }
        });
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0]);
        $this->assertTrue($changed);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->record('candidate-rechecked', ['candidate_changed_after_select' => $changed, 'created' => 0]);
    }

    public function test_stale_or_other_campaign_revision_pointers_cannot_starve_a_valid_due_campaign(): void
    {
        $invalid = $this->authorize($this->reviewed());
        $valid = $this->authorize($this->reviewed());
        // This pointer has no SQL FK; the scanner must correlate revision owner,
        // while the materializer remains the authority for the final lock check.
        DB::table('campaigns')->where('id', $invalid->id)->update(['reviewed_revision_id' => $valid->reviewed_revision_id]);
        $before = Campaign::whereIn('id', [$invalid->id, $valid->id])->orderBy('id')->get(['id', 'account_id', 'reviewed_revision_id'])->toArray();
        $revisionLookups = [];
        DB::listen(function ($query) use (&$revisionLookups) {
            if (str_starts_with($query->sql, 'select *') && str_contains($query->sql, 'campaign_revisions')) {
                $revisionLookups[] = ['bindings' => $query->bindings, 'inside_transaction' => DB::transactionLevel() > 0];
            }
        });
        $response = $this->trigger();
        $this->record('invalid-revision-before-assert', ['before' => $before, 'http_status' => $response->getStatusCode(), 'revision_lookups' => $revisionLookups, 'deliveries_after' => Delivery::all(['id', 'account_id', 'campaign_id', 'campaign_revision_id'])->toArray(), 'outbox_count' => DeliveryOutbox::count()]);
        $response->assertOk()->assertJson(['deliveries_created' => 1]);
        $this->assertSame([$valid->id], Delivery::pluck('campaign_id')->all());
        DB::table('campaigns')->where('id', $invalid->id)->update(['reviewed_revision_id' => 999999999]);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
        $this->assertDatabaseCount('delivery_outbox', 1);
        $this->record('invalid-revision-prefix', ['invalid_campaign' => $invalid->id, 'valid_campaign' => $valid->id, 'created_for_valid_only' => true]);
    }

    public function test_two_real_native_requests_converge_on_one_snapshot_and_stable_delivery_outbox_identities(): void
    {
        $campaign = $this->authorize($this->reviewed());
        $directory = storage_path('phase3-t15/runtime/'.Str::uuid());
        mkdir($directory, 0700, true);
        $token = (string) Str::uuid();
        $processes = [];
        $pids = [];
        try {
            foreach ([0, 1] as $label) {
                $command = [PHP_BINARY, '-d', 'zend.exception_ignore_args=1', base_path('tests/support/scheduler-request.php')];
                $input = ['directory' => $directory, 'label' => $label, 'token' => $token];
                file_put_contents($directory.'/request-'.$label.'.json', json_encode(['command' => $command, 'input' => $input, 'helper_sha256' => hash_file('sha256', base_path('tests/support/scheduler-request.php')), 'utc' => gmdate('c')], JSON_THROW_ON_ERROR));
                $process = new Process($command, base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '', 'XDEBUG_MODE' => 'off']);
                $process->setInput(json_encode($input, JSON_THROW_ON_ERROR))->setTimeout(120)->start();
                $processes[$label] = $process;
                $pids[$label] = $process->getPid();
                file_put_contents($directory.'/process-'.$label.'.json', json_encode(['pid' => $pids[$label]], JSON_THROW_ON_ERROR));
            }
            foreach ($processes as $label => $process) {
                OutboxProtocolBarrier::await($directory.'/ready-'.$label, fn ($data) => ($data['platform'] ?? null) === PHP_OS_FAMILY && isset($data['loaded'], $data['thread_id']), 60, fn () => $process->isRunning());
            }
            OutboxProtocolBarrier::publish($directory.'/start', ['token' => $token]);
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $result = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
                $this->assertSame(200, $result['status']);
                $results[] = $result['body']['deliveries_created'];
            }
            sort($results);
            $this->assertSame([0, 1], $results);
            $this->assertSame($campaign->reviewed_revision_id, (int) $campaign->fresh()->materialized_revision_id);
            $this->assertSame(0, (int) $campaign->fresh()->materialization_pending);
            $this->assertSame(1, Delivery::where('campaign_id', $campaign->id)->count());
            $identities = DeliveryOutbox::pluck('delivery_id', 'id')->all();
            $this->assertCount(1, $identities);
            $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0]);
            $this->assertSame($identities, DeliveryOutbox::pluck('delivery_id', 'id')->all());
            $this->record('concurrent-native-http-kernels', ['directory' => $directory, 'pids' => $pids, 'results' => $results, 'identities' => $identities]);
        } finally {
            foreach ($processes as $label => $process) {
                if ($process->isRunning()) {
                    $process->stop(5);
                }
                file_put_contents($directory.'/stdout-'.$label.'.txt', $process->getOutput());
                file_put_contents($directory.'/stderr-'.$label.'.txt', $process->getErrorOutput());
                file_put_contents($directory.'/receipt-'.$label.'.json', json_encode(['pid' => $pids[$label], 'exit_code' => $process->getExitCode(), 'terminal' => ! $process->isRunning(), 'utc' => gmdate('c'), 'stdout_sha256' => hash_file('sha256', $directory.'/stdout-'.$label.'.txt'), 'stderr_sha256' => hash_file('sha256', $directory.'/stderr-'.$label.'.txt')], JSON_THROW_ON_ERROR));
            }
        }
    }

    public function test_maximum_reviewed_revision_materializes_atomically_and_actual_due_query_plans_are_recorded(): void
    {
        $campaign = $this->campaignWithRecipients(10000);
        $selection = null;
        DB::listen(function ($query) use (&$selection) {
            $sql = str_replace('`', '', strtolower($query->sql));
            if (str_starts_with($sql, 'select * from campaigns') && ! str_contains($sql, 'for update')) {
                $selection = ['sql' => $query->sql, 'bindings' => $query->bindings];
            }
        });
        $result = $this->trigger()->assertOk()->assertJson(['deliveries_created' => 10000, 'stop_reason' => 'campaign_limit'])->json();
        $this->assertNotNull($selection);
        $plans = ['after_full_revision' => json_decode(DB::selectOne('EXPLAIN FORMAT=JSON '.$selection['sql'], $selection['bindings'])->EXPLAIN, true, 512, JSON_THROW_ON_ERROR)];
        $identities = DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all();
        $this->assertCount(10000, $identities);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
        $this->assertSame($identities, DeliveryOutbox::orderBy('id')->pluck('delivery_id', 'id')->all());
        $this->assertSame(10000, Delivery::where('campaign_id', $campaign->id)->count());
        $this->assertSame($campaign->reviewed_revision_id, (int) $campaign->fresh()->materialized_revision_id);
        $this->assertSame(0, (int) $campaign->fresh()->materialization_pending);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->record('large-reviewed-revision', ['synthetic_only' => true, 'not_capacity_benchmark' => true, 'recipient_count' => 10000, 'result' => $result, 'plans' => $plans, 'identities_sha256' => hash('sha256', json_encode($identities, JSON_THROW_ON_ERROR))]);
    }

    public function test_unavailable_broker_counts_claims_without_claiming_publication_and_preserves_durable_backoff(): void
    {
        $this->campaignWithRecipients(3);
        $this->trigger()->assertOk();
        config(['queue.connections.rabbitmq.hosts.0.port' => 1]);
        $result = $this->trigger('dispatch')->assertOk()->assertJson(['outbox_claims' => 3, 'stop_reason' => 'empty', 'emails_completed' => null])->json();
        $this->assertSame(3, DeliveryOutbox::where('status', 'pending')->where('publish_attempts', 1)->where('last_error', 'publish_uncertain')->where('available_at', '>', DB::raw('UTC_TIMESTAMP(6)'))->count());
        $this->assertDatabaseCount('mail_messages', 0);
        $this->record('broker-unavailable', ['result' => $result, 'pending_backoff' => 3]);
    }

    public function test_real_slow_confirm_observation_exhausts_cooperative_budget_without_a_second_claim(): void
    {
        $this->campaignWithRecipients(3);
        $this->trigger()->assertOk();
        config(['cronicle.dispatch_budget_ms' => 50]);
        app()->instance(PublicationObserver::class, new class extends PublicationObserver
        {
            public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
            {
                if ($stage === 'confirmed') {
                    usleep(150000);
                }
            }
        });
        $this->brokerUsed = true;
        $result = $this->trigger('dispatch')->assertOk()->assertJson(['outbox_claims' => 1, 'stop_reason' => 'budget'])->json();
        $this->assertGreaterThanOrEqual(150, $result['elapsed_ms']);
        $this->assertSame(1, DeliveryOutbox::where('status', 'published')->count());
        $this->assertSame(2, DeliveryOutbox::where('publish_attempts', 0)->count());
        $this->record('cooperative-overrun', ['budget_ms' => 50, 'controlled_confirm_observer_delay_ms' => 150, 'result' => $result]);
    }

    public function test_interrupted_backend_reports_unknown_progress_and_replay_keeps_the_committed_work(): void
    {
        $campaign = $this->authorize($this->reviewed());
        app()->bind(DeliveryMaterializer::class, fn () => new class extends DeliveryMaterializer
        {
            public function materialize(int $accountId, int $campaignId): int
            {
                parent::materialize($accountId, $campaignId);
                throw new \RuntimeException('private-connection-fixture');
            }
        });
        $this->trigger()->assertStatus(503)->assertJson(['error' => 'trigger_unavailable', 'work_outcome' => 'unknown', 'emails_completed' => null])->assertDontSee('private-connection-fixture');
        $identities = DeliveryOutbox::pluck('delivery_id', 'id')->all();
        $this->assertCount(1, $identities);
        app()->forgetInstance(SchedulerActions::class);
        app()->bind(DeliveryMaterializer::class, DeliveryMaterializer::class);
        $this->trigger()->assertOk()->assertJson(['deliveries_created' => 0, 'stop_reason' => 'empty']);
        $this->assertSame($identities, DeliveryOutbox::pluck('delivery_id', 'id')->all());
        $this->assertSame($campaign->id, Delivery::sole()->campaign_id);
        $this->record('interrupted-after-commit', ['identities' => $identities, 'replay_created' => 0]);
    }
}
