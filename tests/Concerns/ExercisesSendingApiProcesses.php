<?php

namespace Tests\Concerns;

use App\Models\AccountApiKey;
use App\Models\ApiMailRequest;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\MailMessage;
use App\Models\MailSetting;
use App\Models\Suppression;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryConsumer;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryObserver;
use App\Services\OutboxDispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Process\Process;
use Tests\Support\OutboxProtocolBarrier;

trait ExercisesSendingApiProcesses
{
    private ?Process $apiRelay = null;

    private ?int $apiRelayPid = null;

    private ?string $apiTopology = null;

    private ?string $apiPool = null;

    private function apiChild(string $name, array $key, array $options = []): Process
    {
        $this->evidence('process-fixture', ['fixture' => $name]);
        $source = base_path(($options['fixture_action'] ?? '') === 'external-deadlock' ? 'tests/support/sending-api-deadlock.php' : 'tests/support/sending-api-action.php');
        $process = new Process([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', $source], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
        $input = $options + ['directory' => $this->apiDirectory, 'worker' => $name, 'method' => 'POST', 'url' => '/api/v1/mail/send', 'secret' => $key['secret'], 'body' => json_encode($this->payload(), JSON_THROW_ON_ERROR), 'idempotency' => 'concurrent-api-0000001', 'hold' => false];
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR));
        $process->setTimeout(180);
        $this->evidence('request-'.$name, ['command' => $process->getCommandLine(), 'source_sha256' => hash_file('sha256', $source), 'timeout_seconds' => 180, 'input_recorded' => false]);
        $process->start();
        $pid = $process->getPid();
        $this->apiProcesses[] = [$process, $name, $pid];
        $this->evidence('started-'.$name, ['pid' => $pid, 'command' => $process->getCommandLine()]);
        OutboxProtocolBarrier::await($this->apiDirectory.'/ready-'.$name, fn ($row) => ($row['worker'] ?? null) === $name, 60, fn () => $process->isRunning());

        return $process;
    }

    private function signalApi(string $type, string $worker): void
    {
        OutboxProtocolBarrier::publish($this->apiDirectory.'/'.$type.'-'.$worker, ['worker' => $worker]);
    }

    public function test_real_mysql_deadlock_rolls_back_acceptance_and_allows_same_key_retry(): void
    {
        $this->exerciseRealApiDeadlock(false);
    }

    public function test_real_mysql_deadlock_aborts_an_owned_outer_transaction_without_continuing_callback(): void
    {
        $this->exerciseRealApiDeadlock(true);
    }

    private function exerciseRealApiDeadlock(bool $outer): void
    {
        [$foreign] = $this->foreign();
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['account_id' => $foreign->id, 'email' => 'deadlock-'.$i.'@example.test', 'name' => 'Deadlock fixture'];
        }
        DB::table('contacts')->insert($rows);
        $contact = DB::table('contacts')->where('account_id', $foreign->id)->min('id');
        $child = $this->apiChild('deadlock-locker', $this->key, ['fixture_action' => 'external-deadlock', 'account_id' => $this->account->id, 'foreign_account_id' => $foreign->id]);
        $this->signalApi('go', 'deadlock-locker');
        OutboxProtocolBarrier::await($this->apiDirectory.'/foreign-rows-locked-deadlock-locker', fn ($row) => ($row['changed_owned_rows'] ?? 0) === 1000, 60, fn () => $child->isRunning());
        $failure = null;
        $event = 'eloquent.creating: '.DeliveryOutbox::class;
        \Illuminate\Support\Facades\Event::listen($event, function () use ($foreign, $contact, &$failure): void {
            $this->signalApi('attempt-account', 'deadlock-locker');
            try {
                DB::table('contacts')->where('account_id', $foreign->id)->where('id', $contact)->lockForUpdate()->first();
            } catch (\Illuminate\Database\QueryException $error) {
                $failure = ['sqlstate' => $error->getCode(), 'driver_code' => $error->errorInfo[1] ?? null];
                $detector = new \Illuminate\Database\ConcurrencyErrorDetector;
                $this->evidence('deadlock-causal-detector', ['driver_recognized' => $detector->causedByConcurrencyError($error), 'translated_error_recognized' => $detector->causedByConcurrencyError(new \App\Support\ApiError(503)), 'vendor_sha256' => hash_file('sha256', base_path('vendor/laravel/framework/src/Illuminate/Database/ConcurrencyErrorDetector.php'))]);
                throw $error;
            }
        });
        try {
            $this->assertSame(0, DB::transactionLevel());
            if ($outer) {
                $continued = false;
                $entry = null;
                $caught = false;
                $originalName = $this->account->name;
                try {
                    DB::transaction(function () use (&$continued, &$entry): void {
                        DB::table('accounts')->where('id', $this->account->id)->update(['name' => 'Outer transaction rollback sentinel']);
                        $entry = DB::transactionLevel();
                        $candidate = AccountApiKey::where('public_id', $this->key['key']['id'])->sole();
                        app(\App\Services\AccountKeyService::class)->admittedOperation($candidate, function ($key): void {
                            try {
                                $data = \App\Support\SendingApiInput::send(\App\Support\StrictApiJson::decode(json_encode($this->payload(), JSON_THROW_ON_ERROR)));
                                app(\App\Services\SendingApiService::class)->accept($key, 'fixture-idempotency-0001', $data);
                            } catch (\Throwable) {
                                // Same safe translation as the HTTP controller, without
                                // allowing a kernel to turn this outer exception into a response.
                                throw new \App\Support\ApiError(503);
                            }
                        });
                        $continued = true;
                    });
                } catch (\Throwable) {
                    $caught = true;
                }
                $this->assertTrue($caught);
                $this->assertSame(1, $entry);
                $this->assertFalse($continued);
                $this->assertSame($originalName, $this->account->fresh()->name);
                $this->evidence('deadlock-outer-boundary', ['entry_level' => $entry, 'exception_propagated' => $caught, 'callback_continued' => $continued, 'original_write_rolled_back' => $this->account->fresh()->name === $originalName]);
            } else {
                $this->api()->assertStatus(503);
            }
        } finally {
            \Illuminate\Support\Facades\Event::forget($event);
        }
        $result = $this->apiResult($child, 'deadlock-locker');
        $this->assertTrue($result['body']['fixture_transaction_committed']);
        $this->assertSame(['sqlstate' => '40001', 'driver_code' => 1213], $failure);
        $this->evidence('deadlock-transaction-state', ['failure' => $failure, 'laravel_level' => DB::transactionLevel(), 'pdo_in_transaction' => DB::connection()->getPdo()->inTransaction(), 'accepted_requests' => ApiMailRequest::count()]);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        $this->assertSame(0, ApiMailRequest::count());
        $this->assertSame(0, \App\Models\ApiMailRecipient::count());
        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, DeliveryOutbox::count());
        $this->assertSame(0, (int) DB::table('api_admission_windows')->where('account_id', $this->account->id)->sum('used'));
        $this->evidence('real-deadlock', ['failure' => $failure, 'transaction_level' => DB::transactionLevel(), 'accepted_work' => 0, 'authenticated_counter' => 0, 'inverse_lock_order_is_fixture_only' => true]);
        $this->api()->assertStatus(202)->assertHeader('Idempotency-Replayed', 'false');
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(1, Delivery::count());
    }

    private function apiResult(Process $process, string $worker): array
    {
        $code = $process->wait();
        file_put_contents($this->apiDirectory.'/'.$worker.'-stdout.txt', $process->getOutput());
        file_put_contents($this->apiDirectory.'/'.$worker.'-stderr.txt', $process->getErrorOutput());
        $this->evidence('receipt-'.$worker, ['exit' => $code, 'stdout_sha256' => hash('sha256', $process->getOutput()), 'stderr_sha256' => hash('sha256', $process->getErrorOutput()), 'command' => $process->getCommandLine()]);
        $this->assertSame(0, $code, 'Controlled API child failed.');

        return json_decode($process->getOutput(), true, 64, JSON_THROW_ON_ERROR);
    }

    private function settleApiResources(): void
    {
        $facts = ['processes' => [], 'broker_topology' => $this->apiTopology, 'mailpit_messages_created' => 0, 'containers_created' => 0];
        foreach ($this->apiProcesses as [$process, $name, $pid]) {
            if ($process->isRunning()) {
                $this->signalApi('go', $name);
                $this->signalApi('release', $name);
                try {
                    $process->wait();
                } finally {
                    if ($process->isRunning()) {
                        $process->stop(1);
                    }
                }
            }
            $facts['processes'][] = ['worker' => $name, 'pid' => $pid, 'exit' => $process->getExitCode(), 'running' => $process->isRunning()];
        }
        if ($this->apiRelay) {
            file_put_contents($this->apiDirectory.'/relay-stop', 'stop');
            $this->apiRelay->wait();
            file_put_contents($this->apiDirectory.'/relay-stdout.txt', $this->apiRelay->getOutput());
            file_put_contents($this->apiDirectory.'/relay-stderr.txt', $this->apiRelay->getErrorOutput());
            $facts['relay'] = ['pid' => $this->apiRelayPid, 'exit' => $this->apiRelay->getExitCode(), 'running' => $this->apiRelay->isRunning()];
        }
        if ($this->apiTopology) {
            $connection = $this->apiBroker();
            try {
                $channel = $connection->channel();
                $facts['broker_ready_removed_messages'] = $channel->queue_delete($this->apiTopology);
                $facts['broker_failed_removed_messages'] = $channel->queue_delete($this->apiTopology.'.failed');
                $channel->exchange_delete($this->apiTopology);
                $channel->close();
            } finally {
                $connection->close();
            }
        }
        if ($this->apiPool) {
            DB::table('delivery_quota_windows')->where('id', 'like', 'pool:'.$this->apiPool.':%')->delete();
        }
        if ($this->apiDirectory !== null) {
            $this->evidence('resource-settlement', $facts);
        }
    }

    public function test_separate_php_mysql_processes_share_idempotency_and_conflict_ownership(): void
    {
        $second = $this->mint();
        $children = [];
        foreach (['same-a' => $this->key, 'same-b' => $this->key, 'other-key-a' => $second, 'other-key-b' => $second] as $name => $key) {
            $children[$name] = $this->apiChild($name, $key);
        }
        foreach (array_keys($children) as $name) {
            $this->signalApi('go', $name);
        }
        $results = [];
        foreach ($children as $name => $process) {
            $results[] = $this->apiResult($process, $name);
        }
        $this->assertSame([202, 202, 202, 202], array_column($results, 'status'));
        $this->assertCount(1, array_unique(array_map(fn ($result) => \App\Support\SendingApiInput::fingerprint($result['body']), $results)));
        $this->assertSame(1, count(array_filter($results, fn ($result) => $result['replayed'] === 'false')));
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(1, Delivery::count());
        $this->assertSame(1, DeliveryOutbox::count());
        $changed = $this->payload();
        $changed['content']['subject'] = 'Different concurrent source';
        $a = $this->apiChild('conflict-a', $this->key, ['idempotency' => 'concurrent-conflict-0001']);
        $b = $this->apiChild('conflict-b', $second, ['idempotency' => 'concurrent-conflict-0001', 'body' => json_encode($changed, JSON_THROW_ON_ERROR)]);
        $this->signalApi('go', 'conflict-a');
        $this->signalApi('go', 'conflict-b');
        $statuses = [$this->apiResult($a, 'conflict-a')['status'], $this->apiResult($b, 'conflict-b')['status']];
        sort($statuses);
        $this->assertSame([202, 409], $statuses);
        $this->assertSame(2, ApiMailRequest::count());
        $this->assertSame(6, (int) DB::table('api_admission_windows')->where('credential_id', 0)->sum('used'));
        $this->evidence('concurrent-idempotency', ['same_payload_statuses' => array_column($results, 'status'), 'conflicting_statuses' => $statuses, 'requests' => ApiMailRequest::count(), 'deliveries' => Delivery::count(), 'outbox' => DeliveryOutbox::count()]);
    }

    public function test_revocation_serializes_with_admission_replay_and_grants_between_processes(): void
    {
        $manager = $this->mint();
        $target = $this->mint();
        $holder = $this->apiChild('revoke-first', $manager, ['method' => 'DELETE', 'url' => '/api/v1/keys/'.$target['key']['id'], 'body' => '', 'hold' => true]);
        $this->signalApi('go', 'revoke-first');
        OutboxProtocolBarrier::await($this->apiDirectory.'/held-revoke-first', fn ($row) => ($row['status'] ?? null) === 204, 60, fn () => $holder->isRunning());
        $sender = $this->apiChild('after-revoke', $target);
        $this->signalApi('go', 'after-revoke');
        OutboxProtocolBarrier::await($this->apiDirectory.'/attempting-after-revoke', fn ($row) => ($row['worker'] ?? null) === 'after-revoke', 60, fn () => $sender->isRunning());
        $this->signalApi('release', 'revoke-first');
        $this->assertSame(204, $this->apiResult($holder, 'revoke-first')['status']);
        $this->assertSame(401, $this->apiResult($sender, 'after-revoke')['status']);
        $this->assertSame(0, ApiMailRequest::count());
        $target = $this->mint();
        $holder = $this->apiChild('accept-first', $target, ['hold' => true]);
        $this->signalApi('go', 'accept-first');
        OutboxProtocolBarrier::await($this->apiDirectory.'/held-accept-first', fn ($row) => ($row['status'] ?? null) === 202, 60, fn () => $holder->isRunning());
        $revoker = $this->apiChild('after-accept', $manager, ['method' => 'DELETE', 'url' => '/api/v1/keys/'.$target['key']['id'], 'body' => '']);
        $this->signalApi('go', 'after-accept');
        OutboxProtocolBarrier::await($this->apiDirectory.'/attempting-after-accept', fn ($row) => ($row['worker'] ?? null) === 'after-accept', 60, fn () => $revoker->isRunning());
        $this->signalApi('release', 'accept-first');
        $this->assertSame(202, $this->apiResult($holder, 'accept-first')['status']);
        $this->assertSame(204, $this->apiResult($revoker, 'after-accept')['status']);
        $this->api(key: $target)->assertStatus(401);
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(1, Delivery::count());
        $this->evidence('revocation-linearization', ['revoked_before_admission' => 401, 'admitted_before_revocation' => 202, 'revocation' => 204, 'replay_after_revocation' => 401, 'accepted_work_retained' => true]);
    }

    public function test_grants_and_shared_http_limits_serialize_in_separate_mysql_processes(): void
    {
        $manager = $this->mint();
        $parent = $this->mint(\App\Support\SendingApiInput::SCOPES, gmdate('Y-m-d H:i:s', time() + 3600).'.000000');
        $grantBody = json_encode(['name' => 'Concurrent child', 'scopes' => ['mail.send']], JSON_THROW_ON_ERROR);
        $grant = $this->apiChild('grant-first', $parent, ['method' => 'POST', 'url' => '/api/v1/keys', 'body' => $grantBody, 'hold' => true]);
        $this->signalApi('go', 'grant-first');
        OutboxProtocolBarrier::await($this->apiDirectory.'/held-grant-first', fn ($row) => ($row['status'] ?? null) === 201, 60, fn () => $grant->isRunning());
        $revoker = $this->apiChild('revoke-after-grant', $manager, ['method' => 'DELETE', 'url' => '/api/v1/keys/'.$parent['key']['id'], 'body' => '']);
        $this->signalApi('go', 'revoke-after-grant');
        OutboxProtocolBarrier::await($this->apiDirectory.'/attempting-revoke-after-grant', fn ($row) => ($row['worker'] ?? null) === 'revoke-after-grant', 60, fn () => $revoker->isRunning());
        $this->signalApi('release', 'grant-first');
        $child = $this->apiResult($grant, 'grant-first');
        $this->assertSame(201, $child['status']);
        $this->assertSame($parent['key']['expires_at'], $child['body']['key']['expires_at']);
        $this->assertArrayNotHasKey('secret', $child['body']);
        $this->assertSame(204, $this->apiResult($revoker, 'revoke-after-grant')['status']);
        $this->assertNull(AccountApiKey::where('public_id', $child['body']['key']['id'])->value('revoked_at'));

        $parent = $this->mint();
        $before = AccountApiKey::count();
        $revoker = $this->apiChild('revoke-before-grant', $manager, ['method' => 'DELETE', 'url' => '/api/v1/keys/'.$parent['key']['id'], 'body' => '', 'hold' => true]);
        $this->signalApi('go', 'revoke-before-grant');
        OutboxProtocolBarrier::await($this->apiDirectory.'/held-revoke-before-grant', fn ($row) => ($row['status'] ?? null) === 204, 60, fn () => $revoker->isRunning());
        $grant = $this->apiChild('grant-after-revoke', $parent, ['method' => 'POST', 'url' => '/api/v1/keys', 'body' => $grantBody]);
        $this->signalApi('go', 'grant-after-revoke');
        OutboxProtocolBarrier::await($this->apiDirectory.'/attempting-grant-after-revoke', fn ($row) => ($row['worker'] ?? null) === 'grant-after-revoke', 60, fn () => $grant->isRunning());
        $this->signalApi('release', 'revoke-before-grant');
        $this->assertSame(204, $this->apiResult($revoker, 'revoke-before-grant')['status']);
        $this->assertSame(401, $this->apiResult($grant, 'grant-after-revoke')['status']);
        $this->assertSame($before, AccountApiKey::count());

        $a = $this->apiChild('rate-a', $manager, ['method' => 'GET', 'url' => '/api/v1/keys', 'body' => '']);
        $b = $this->apiChild('rate-b', $this->key, ['method' => 'GET', 'url' => '/api/v1/keys', 'body' => '']);
        while ((int) DB::selectOne('SELECT SECOND(UTC_TIMESTAMP()) AS second')->second > 40) {
            usleep(100000);
        }
        $this->resetBudget();
        $window = DB::selectOne("SELECT DATE_FORMAT(UTC_TIMESTAMP(), '%Y-%m-%d %H:%i:00') AS start")->start;
        DB::table('api_admission_windows')->insert(['account_id' => $this->account->id, 'credential_id' => 0, 'starts_at' => $window, 'used' => 59]);
        $this->signalApi('go', 'rate-a');
        $this->signalApi('go', 'rate-b');
        $statuses = [$this->apiResult($a, 'rate-a')['status'], $this->apiResult($b, 'rate-b')['status']];
        sort($statuses);
        $this->assertSame([200, 429], $statuses);
        $this->assertSame(60, (int) DB::table('api_admission_windows')->where('credential_id', 0)->where('starts_at', $window)->value('used'));
        $this->assertSame(1, (int) DB::table('api_admission_windows')->where('credential_id', '<>', 0)->where('starts_at', $window)->sum('used'));
        $this->evidence('grant-rate-races', ['grant_before_revoke' => 201, 'grant_after_revoke' => 401, 'child_retained_after_parent_revoke' => true, 'account_counter' => 60, 'key_counter_increments' => 1, 'parallel_limit_statuses' => $statuses, 'sql_window_utc' => $window]);
    }

    private function apiBroker(): AMQPStreamConnection
    {
        $host = config('queue.connections.rabbitmq.hosts.0');

        return new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost'], false, 'AMQPLAIN', null, 'en_US', 5, 10, null, false, 0);
    }

    private function apiRelay(): void
    {
        $this->evidence('broker-fixture', ['purpose' => 'API pipeline, reserved synthetic recipients only']);
        $this->apiTopology = 'emailing.api-test.'.Str::uuid();
        $this->apiPool = 'api-'.Str::uuid();
        config(['delivery.exchange' => $this->apiTopology, 'delivery.queue' => $this->apiTopology, 'delivery.failure_queue' => $this->apiTopology.'.failed', 'delivery.relay_pool' => $this->apiPool, 'delivery.backoff_seconds' => 1, 'delivery.max_attempts' => 2]);
        $broker = $this->apiBroker();
        try {
            $channel = $broker->channel();
            $channel->exchange_declare($this->apiTopology, 'direct', false, true, false);
            $channel->queue_declare($this->apiTopology, false, true, false, false);
            $channel->queue_bind($this->apiTopology, $this->apiTopology, config('delivery.binding_key'));
            $channel->queue_declare($this->apiTopology.'.failed', false, true, false, false);
            $channel->queue_bind($this->apiTopology.'.failed', $this->apiTopology, config('delivery.failure_routing_key'));
        } finally {
            $broker->close();
        }
        file_put_contents($this->apiDirectory.'/mode', 'accept');
        $source = base_path('tests/support/delivery-relay.py');
        $this->apiRelay = new Process(['python', $source, $this->apiDirectory], base_path());
        $this->apiRelay->setTimeout(360);
        $this->evidence('relay-request', ['command' => $this->apiRelay->getCommandLine(), 'source_sha256' => hash_file('sha256', $source)]);
        $this->apiRelay->start();
        $this->apiRelayPid = $this->apiRelay->getPid();
        $ready = OutboxProtocolBarrier::await($this->apiDirectory.'/relay-ready', fn ($row) => is_int($row['port'] ?? null), 10, fn () => $this->apiRelay->isRunning());
        config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => $ready['port']]);
        $this->evidence('relay-started', ['pid' => $this->apiRelayPid, 'port' => $ready['port']]);
    }

    private function apiPublishConsume(string $delivery, bool $publish = true): void
    {
        $event = DeliveryOutbox::where('delivery_id', $delivery)->where('type', 'delivery.ready')->orderByDesc('generation')->firstOrFail();
        DeliveryOutbox::whereKey($event->id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        Delivery::whereKey($delivery)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        if ($publish) {
            $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
        }
        $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
    }

    public function test_api_outbox_real_broker_consumer_quota_suppression_retries_and_ambiguous_recovery(): void
    {
        $this->apiRelay();
        $receipt = $this->api()->assertStatus(202)->json();
        $id = $receipt['delivery_ids'][0];
        $event = DeliveryOutbox::where('delivery_id', $id)->sole();
        $port = config('queue.connections.rabbitmq.hosts.0.port');
        config(['queue.connections.rabbitmq.hosts.0.port' => 1, 'delivery.confirm_timeout' => 1]);
        // dispatch means a claimed event was processed, not broker confirmation.
        $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
        $this->assertSame('pending', $event->fresh()->status);
        $this->assertSame('publish_uncertain', $event->fresh()->last_error);
        $this->assertNull($event->fresh()->published_at);
        $this->assertSame(0, MailMessage::count());
        config(['queue.connections.rabbitmq.hosts.0.port' => $port]);
        $this->apiPublishConsume($id);
        $this->assertSame('logged', Delivery::findOrFail($id)->status);
        $this->assertTrue(app(ConfirmedDeliveryPublisher::class)->publish($event->fresh())->confirmed);
        // Actual broker redelivery of duplicate logical work, then the same consumer.
        $broker = $this->apiBroker();
        try {
            $channel = $broker->channel();
            $message = $channel->basic_get($this->apiTopology, false);
            $this->assertNotNull($message);
            $message->nack(true);
        } finally {
            $broker->close();
        }
        $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
        $this->assertSame(1, MailMessage::count());
        foreach (['quota', 'suppressed', 'safe-retry', 'lost-final', 'lost-result'] as $index => $scenario) {
            $this->resetBudget();
            config(['delivery.quotas.account.minute' => 1000]);
            Suppression::where('account_id', $this->account->id)->delete();
            MailSetting::updateOrCreate(['account_id' => $this->account->id], ['mode' => in_array($scenario, ['safe-retry', 'lost-final', 'lost-result'], true) ? 'mailpit' : 'log']);
            file_put_contents($this->apiDirectory.'/mode', match ($scenario) { 'safe-retry' => 'reject', 'lost-final' => 'lost', default => 'accept' });
            $receipt = $this->api(headers: ['HTTP_IDEMPOTENCY_KEY' => 'broker-api-case-00000'.$index])->assertStatus(202)->json();
            $id = $receipt['delivery_ids'][0];
            $previousDayLimit = config('delivery.quotas.account.day');
            if ($scenario === 'quota') {
                // Explicit own SQL precondition, independent of a prior minute's send.
                $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
                $day = intdiv($epoch, 86400) * 86400;
                $this->assertGreaterThan(60, $day + 86400 - $epoch, 'Quota fixture requires room before UTC midnight.');
                $counter = 'account:'.$this->account->id.':day:'.$day;
                DB::table('delivery_quota_windows')->updateOrInsert(['id' => $counter], ['used' => 1, 'ends_at' => gmdate('Y-m-d H:i:s', $day + 86400)]);
                config(['delivery.quotas.account.day' => 1]);
                $this->evidence('quota-precondition', ['counter' => $counter, 'used' => 1, 'sql_epoch' => $epoch]);
            }
            if ($scenario === 'suppressed') {
                Suppression::create(['account_id' => $this->account->id, 'normalized_email' => 'direct@example.test', 'source' => 'manual']);
            }
            if ($scenario === 'lost-result') {
                $this->app->instance(DeliveryObserver::class, new class extends DeliveryObserver
                {
                    public function observe(string $stage, ?Delivery $delivery, array $facts = []): void
                    {
                        if ($stage === 'transport_result') {
                            throw new \RuntimeException('Controlled loss before durable result.');
                        }
                    }
                });
                try {
                    $this->apiPublishConsume($id);
                    $this->fail('Controlled durable-result loss did not occur.');
                } catch (\RuntimeException $error) {
                    $this->assertSame('Controlled loss before durable result.', $error->getMessage());
                }
                $this->app->instance(DeliveryObserver::class, new DeliveryObserver);
                Delivery::whereKey($id)->update(['lease_expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
                $this->assertSame(1, app(DeliveryLifecycle::class)->recover());
                $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            } else {
                $this->apiPublishConsume($id);
            }
            $delivery = Delivery::findOrFail($id);
            if ($scenario === 'quota') {
                $this->assertSame('quota_wait', $delivery->diagnostic);
                $this->assertSame(0, $delivery->attempts);
                config(['delivery.quotas.account.day' => $previousDayLimit]);
                $this->apiPublishConsume($id);
                $this->assertSame('logged', $delivery->fresh()->status);
            } elseif ($scenario === 'suppressed') {
                $this->assertSame('suppressed', $delivery->status);
                $this->assertSame(0, $delivery->attempts);
            } elseif ($scenario === 'safe-retry') {
                $this->assertSame('pending', $delivery->status);
                $this->assertSame(1, $delivery->attempts);
                file_put_contents($this->apiDirectory.'/mode', 'accept');
                $this->apiPublishConsume($id);
                $this->assertSame('smtp_accepted', $delivery->fresh()->status);
                $this->assertSame(2, $delivery->fresh()->attempts);
            } else {
                $this->assertSame('ambiguous', $delivery->status);
                $this->assertSame(1, $delivery->attempts);
                $before = MailMessage::count();
                $event = DeliveryOutbox::where('delivery_id', $id)->where('type', 'delivery.ready')->firstOrFail();
                $this->assertTrue(app(ConfirmedDeliveryPublisher::class)->publish($event)->confirmed);
                $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
                $this->assertSame($before, MailMessage::count());
            }
            $this->evidence('pipeline-'.$scenario, ['delivery' => $delivery->fresh()->only(['id', 'status', 'attempts', 'generation', 'diagnostic']), 'logical_recipient_count' => 1, 'origin' => 'api']);
        }
        $this->assertSame(6, ApiMailRequest::count());
        $this->assertSame(6, Delivery::count());
        $this->assertSame(6, MailMessage::where('origin', 'api')->count());
        $this->assertSame(0, MailMessage::whereNotNull('actor_id')->count());
        $metrics = app(\App\Services\ActivityService::class)->metrics([], $this->account->id);
        $this->assertSame(6, $metrics['api']['total']);
        $this->assertSame(0, $metrics['unknown']['total']);
        AccountApiKey::where('public_id', $this->key['key']['id'])->update(['name' => '=FORMULA("synthetic")']);
        $csv = $this->get('/activity/export?population=api')->assertOk()->streamedContent();
        $rows = array_map(fn ($row) => str_getcsv($row, ',', '"', ''), explode("\n", trim($csv)));
        $this->assertCount(7, $rows);
        foreach (array_slice($rows, 1) as $row) {
            $this->assertCount(count($rows[0]), $row);
            $this->assertSame("'=FORMULA(\"synthetic\")", $row[16]);
        }
    }
}
