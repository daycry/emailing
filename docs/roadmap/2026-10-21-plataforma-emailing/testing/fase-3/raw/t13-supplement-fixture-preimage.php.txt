<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Services\AccountMailTransport;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryConsumer;
use App\Services\DeliveryEnvelope;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryMaterializer;
use App\Services\DeliveryQuota;
use App\Services\OutboxDispatcher;
use App\Services\PublicationObserver;
use App\Services\SuppressionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

class WorkerRecoveryTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCampaigns { setUp as private campaignSetUp; }

    private array $owned = [];

    private string $directory;

    private string $relative;

    private string $topology;

    private ?Process $relay = null;

    private int $port = 0;

    private array $proxies = [];

    protected function setUp(): void
    {
        $this->campaignSetUp();
        $this->relative = 'storage/phase3-t13/implementation/runtime-'.Str::uuid();
        $this->directory = base_path($this->relative);
        mkdir($this->directory, 0700, true);
        $this->topology = 'emailing.worker-test.'.Str::uuid();
        config(['delivery.exchange' => $this->topology, 'delivery.queue' => $this->topology, 'delivery.failure_queue' => $this->topology.'.failed', 'delivery.backoff_seconds' => 1, 'delivery.pause_poll_seconds' => 1]);
        Storage::fake('mail-artifacts');
        $broker = $this->broker();
        $channel = $broker->channel();
        $channel->exchange_declare($this->topology, 'direct', false, true, false);
        foreach ([$this->topology, $this->topology.'.failed'] as $queue) {
            $channel->queue_declare($queue, false, true, false, false);
        }
        $broker->close();
    }

    protected function tearDown(): void
    {
        foreach ($this->owned as [$process, $name, $pid]) {
            if ($process->isRunning()) {
                $kill = new Process(['docker', 'kill', '--signal=KILL', $name]);
                $this->recordRequest('kill-'.$name, $kill);
                $kill->run();
                $this->record('kill-'.$name, $kill, null);
                $process->wait();
            }
            $this->record($name, $process, $pid);
        }
        if ($this->relay) {
            file_put_contents($this->directory.'/relay-stop', 'stop');
            $this->relay->wait();
            $this->record('relay', $this->relay, null);
        }
        foreach ($this->proxies as $proxy) {
            file_put_contents($this->directory.'/cut-sql', 'stop');
            if ($proxy->isRunning()) {
                $proxy->wait();
            }
            $this->record('sql-proxy', $proxy, null);
        }
        file_put_contents($this->directory.'/sql-final.json', json_encode([
            'deliveries' => Delivery::orderBy('id')->get(['id', 'account_id', 'status', 'generation', 'attempts', 'mail_message_id', 'diagnostic', 'lease_token', 'lease_expires_at', 'available_at'])->toArray(),
            'outbox' => DeliveryOutbox::orderBy('id')->get(['id', 'delivery_id', 'account_id', 'generation', 'type', 'status', 'available_at', 'publish_attempts', 'last_error'])->toArray(),
            'messages' => MailMessage::orderBy('id')->get(['id', 'account_id', 'status', 'diagnostic', 'sender_email'])->toArray(),
            'quotas' => DB::table('delivery_quota_windows')->orderBy('id')->get()->all(),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        try {
            $broker = $this->broker();
            $channel = $broker->channel();
            $channel->queue_delete($this->topology);
            $channel->queue_delete($this->topology.'.failed');
            $channel->exchange_delete($this->topology);
            $broker->close();
            file_put_contents($this->directory.'/resources-released.json', json_encode(['exchange' => $this->topology, 'queues' => [$this->topology, $this->topology.'.failed'], 'deleted' => true], JSON_THROW_ON_ERROR));
        } finally {
            parent::tearDown();
        }
    }

    private function recordRequest(string $name, Process $process): void
    {
        file_put_contents($this->directory.'/'.$name.'-request.json', json_encode(['command' => $process->getCommandLine(), 'at' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function record(string $name, Process $process, ?int $pid): void
    {
        file_put_contents($this->directory.'/'.$name.'-stdout.txt', $process->getOutput());
        file_put_contents($this->directory.'/'.$name.'-stderr.txt', $process->getErrorOutput());
        file_put_contents($this->directory.'/'.$name.'-receipt.json', json_encode(['pid' => $pid, 'exit' => $process->getExitCode(), 'command' => $process->getCommandLine(), 'at' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function broker(): AMQPStreamConnection
    {
        $host = config('queue.connections.rabbitmq.hosts.0');

        return new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost']);
    }

    private function event(): DeliveryOutbox
    {
        $campaign = $this->authorize($this->reviewed());
        app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
        MailSetting::firstOrCreate(['account_id' => $this->account->id], ['mode' => 'mailpit']);

        return DeliveryOutbox::where('account_id', $this->account->id)->where('delivery_id', Delivery::where('campaign_id', $campaign->id)->value('id'))->sole();
    }

    private function publish(DeliveryOutbox $event): void
    {
        $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
        $this->assertSame('published', $event->fresh()->status);
    }

    private function duplicate(DeliveryOutbox $event): void
    {
        $broker = $this->broker();
        $broker->channel()->basic_publish(new AMQPMessage(json_encode($event->payload(), JSON_THROW_ON_ERROR), ['content_type' => 'application/json', 'message_id' => $event->id, 'delivery_mode' => 2]), $this->topology, 'delivery.ready');
        $broker->close();
    }

    private function relay(string $mode = 'accept'): void
    {
        file_put_contents($this->directory.'/mode', $mode);
        $this->relay = new Process(['python', base_path('tests/support/delivery-relay.py'), $this->directory], base_path());
        $this->relay->setTimeout(120);
        $this->recordRequest('relay', $this->relay);
        $this->relay->start();
        $ready = OutboxProtocolBarrier::await($this->directory.'/relay-ready', fn ($data) => is_int($data['port'] ?? null), 10, fn () => $this->relay->isRunning());
        $this->port = $ready['port'];
    }

    private function worker(DeliveryOutbox $event, string $worker, ?string $pause = null, array $overrides = [], array $fixture = []): Process
    {
        $name = 'emailing-t13-'.Str::uuid();
        $command = ['docker', 'run', '--rm', '-i', '--name', $name, '--network', 'emailing-lab'];
        foreach (['app', 'config', 'database', 'routes', 'tests', 'storage', 'bootstrap/app.php', 'bootstrap/providers.php', '.env.testing'] as $path) {
            array_push($command, '--mount', 'type=bind,source='.base_path($path).',target=/var/www/emailing/'.$path.($path === 'storage' ? '' : ',readonly'));
        }
        array_push($command, '-e', 'APP_ENV=testing', '-e', 'DB_HOST=mysql', '-e', 'DB_PORT=3306', '-e', 'DB_DATABASE=emailing_testing', '-e', 'DB_USERNAME=emailing_testing', '-e', 'DB_URL=', '-e', 'RABBITMQ_HOST=rabbitmq', '-e', 'DELIVERY_EXCHANGE='.$this->topology, '-e', 'DELIVERY_QUEUE='.$this->topology, '-e', 'DELIVERY_FAILURE_QUEUE='.$this->topology.'.failed', '-e', 'MAILPIT_SMTP_HOST=host.docker.internal', '-e', 'MAILPIT_SMTP_PORT='.$this->port, 'emailing-app/php:local', 'php', '-d', 'zend.exception_ignore_args=1');
        if ($fixture['cli'] ?? false) {
            array_push($command, 'artisan', 'deliveries:work');
        } else {
            $command[] = 'tests/support/delivery-action.php';
        }
        $config = ['delivery.exchange' => $this->topology, 'delivery.queue' => $this->topology, 'delivery.failure_queue' => $this->topology.'.failed', 'emailing.mailpit.host' => 'host.docker.internal', 'emailing.mailpit.port' => $this->port, 'delivery.backoff_seconds' => 1, 'delivery.pause_poll_seconds' => 1];
        $process = new Process($command, base_path());
        $input = json_encode(array_replace(['config' => array_replace($config, $overrides), 'delivery_id' => $event->delivery_id, 'worker' => $worker, 'pause_at' => $pause, 'directory' => '/var/www/emailing/'.$this->relative], $fixture), JSON_THROW_ON_ERROR);
        file_put_contents($this->directory.'/'.$name.'-input.json', $input);
        $process->setInput($input);
        $process->setTimeout(150);
        $this->recordRequest($name, $process);
        $process->start();
        $this->owned[] = [$process, $name, $process->getPid()];

        return $process;
    }

    private function stage(string $stage, string $worker, Process $process): array
    {
        $data = OutboxProtocolBarrier::await($this->directory.'/'.$stage.'-'.$worker, fn ($data) => ($data['stage'] ?? null) === $stage, 35, fn () => $process->isRunning());
        $loaded = json_decode(file_get_contents($this->directory.'/loaded-'.$worker), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Linux', $loaded['platform']);
        foreach ($loaded['hashes'] as $class => $hash) {
            $this->assertSame(hash_file('sha256', (new \ReflectionClass($class))->getFileName()), $hash);
        }

        return $data;
    }

    private function release(string $worker, DeliveryOutbox $event): void
    {
        OutboxProtocolBarrier::publish($this->directory.'/release-'.$worker, ['delivery_id' => $event->delivery_id]);
    }

    private function finish(Process $process, int $exit = 0): void
    {
        $process->wait();
        $this->assertSame($exit, $process->getExitCode(), $process->getErrorOutput());
    }

    private function kill(Process $process): void
    {
        foreach ($this->owned as [$owned, $name]) {
            if ($owned === $process) {
                $kill = new Process(['docker', 'kill', '--signal=KILL', $name]);
                $this->recordRequest('kill-'.$name, $kill);
                $kill->mustRun();
                $this->record('kill-'.$name, $kill, null);
                $this->finish($process, 137);

                return;
            }
        }
        $this->fail('Unowned process cannot be killed.');
    }

    private function next(DeliveryOutbox $event): DeliveryOutbox
    {
        return DeliveryOutbox::where('delivery_id', $event->delivery_id)->where('generation', Delivery::findOrFail($event->delivery_id)->generation)->where('type', 'delivery.ready')->sole();
    }

    private function waitAvailable(DeliveryOutbox $event): void
    {
        $deadline = microtime(true) + 70;
        while (! DeliveryOutbox::whereKey($event->id)->where('available_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists()) {
            if (microtime(true) >= $deadline) {
                $this->fail('Actual SQL quota/backoff deadline must become available.');
            }
            usleep(200000);
        }
    }

    private function secondAccountEvent(): DeliveryOutbox
    {
        $original = [$this->account, $this->sender, $this->template, $this->contact];
        try {
            $this->account = Account::create(['name' => 'Otra cuenta', 'owner_id' => $this->owner->id]);
            Membership::create(['account_id' => $this->account->id, 'user_id' => $this->owner->id, 'role' => 'admin', 'scopes' => []]);
            $this->sender = MailSender::create(['account_id' => $this->account->id, 'email' => 'second@example.test', 'name' => 'Segundo', 'lab_authorized' => true]);
            $this->template = EmailTemplate::create(['account_id' => $this->account->id, 'name' => 'Otra', 'subject' => 'Otra cuenta', 'text' => 'Hola {{ contact.email }}', 'html' => '']);
            $this->contact = $this->newContact(['email' => $original[3]->email]);

            return $this->event();
        } finally {
            [$this->account, $this->sender, $this->template, $this->contact] = $original;
        }
    }

    private function expire(DeliveryOutbox $event): void
    {
        Delivery::whereKey($event->delivery_id)->update(['lease_expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
    }

    private function facts(): array
    {
        return is_file($this->directory.'/relay.jsonl') ? array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($this->directory.'/relay.jsonl', FILE_IGNORE_NEW_LINES)) : [];
    }

    private function countStage(string $stage): int
    {
        return count(array_filter($this->facts(), fn ($row) => $row['stage'] === $stage));
    }

    public function test_two_real_linux_consumers_duplicate_event_only_one_transport_start(): void
    {
        $event = $this->event();
        $this->relay();
        $this->publish($event);
        $this->duplicate($event);
        $first = $this->worker($event, 'one', 'reserved', ['delivery.prefetch' => 1], ['continuous' => true]);
        $this->stage('reserved', 'one', $first);
        $second = $this->worker($event, 'two', null, ['delivery.prefetch' => 1], ['continuous' => true]);
        $this->finish($second);
        $this->assertTrue($first->isRunning());
        $this->assertSame(0, $this->countStage('mail'));
        $this->release('one', $event);
        $this->finish($first);
        $this->assertSame(1, $this->countStage('mail'));
        $this->assertSame(1, $this->countStage('accepted_data'));
        $delivery = Delivery::findOrFail($event->delivery_id);
        $this->assertSame('smtp_accepted', $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(1, MailMessage::count());
    }

    public function test_expired_reserved_owner_cannot_start_after_recovery_and_heartbeat_is_fenced(): void
    {
        $event = $this->event();
        $this->relay();
        $this->publish($event);
        $old = $this->worker($event, 'old', 'reserved');
        $barrier = $this->stage('reserved', 'old', $old);
        $claim = Delivery::findOrFail($event->delivery_id);
        $this->assertTrue(app(DeliveryLifecycle::class)->renew($claim));
        $this->expire($event);
        $this->assertFalse(app(DeliveryLifecycle::class)->renew($claim));
        $this->assertSame(1, app(DeliveryLifecycle::class)->recover());
        $next = DeliveryOutbox::where('delivery_id', $event->delivery_id)->where('generation', 2)->sole();
        $this->publish($next);
        $new = $this->worker($next, 'new');
        $this->finish($new);
        $this->release('old', $event);
        $this->finish($old);
        $this->assertSame(1, $this->countStage('mail'));
        $this->assertSame('smtp_accepted', $claim->fresh()->status);
        $this->assertSame(1, $claim->fresh()->attempts);
        $this->assertSame(1, $barrier['generation']);
    }

    public function test_sending_expiry_with_old_process_alive_remains_ambiguous_when_it_completes(): void
    {
        $event = $this->event();
        $this->relay('hold');
        $this->publish($event);
        $old = $this->worker($event, 'old');
        $this->stage('sending', 'old', $old);
        $deadline = microtime(true) + 10;
        while ($this->countStage('accepted_data') === 0 && microtime(true) < $deadline) {
            usleep(10000);
        }
        $this->assertSame(1, $this->countStage('accepted_data'));
        $this->assertTrue($old->isRunning());
        $this->expire($event);
        app(DeliveryLifecycle::class)->recover();
        $this->duplicate($event);
        $second = $this->worker($event, 'second');
        $this->finish($second);
        file_put_contents($this->directory.'/release-final', 'release');
        $this->finish($old);
        $this->assertSame('ambiguous', Delivery::findOrFail($event->delivery_id)->status);
        $this->assertSame('smtp_unconfirmed', MailMessage::sole()->status);
        $this->assertSame(1, $this->countStage('mail'));
        $this->assertGreaterThan(0, DB::table('delivery_quota_windows')->sum('used'));
    }

    public function test_observed_preacceptance_rejection_retries_with_backoff_and_routes_exhaustion_to_failure_queue(): void
    {
        $event = $this->event();
        $this->relay('reject');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->publish($event);
            $process = $this->worker($event, 'attempt'.$attempt);
            $this->finish($process);
            $delivery = Delivery::findOrFail($event->delivery_id);
            $this->assertSame($attempt, $delivery->attempts);
            if ($attempt < 3) {
                $this->assertSame('pending', $delivery->status);
                $this->assertSame($attempt + 1, $delivery->generation);
                $event = DeliveryOutbox::where('delivery_id', $delivery->id)->where('generation', $delivery->generation)->sole();
                $this->assertTrue($event->available_at->gt($event->created_at), 'Retry availability must be durably delayed, even if observation follows its deadline.');
                // Observe actual SQL clock; no fake clock or immediate broker requeue.
                $seconds = $attempt === 1 ? 1 : 2;
                usleep(($seconds + .2) * 1000000);
            }
        }
        $this->assertSame('failed', $delivery->status);
        $failure = DeliveryOutbox::where('delivery_id', $delivery->id)->where('type', 'delivery.failed')->sole();
        $host = config('queue.connections.rabbitmq.hosts.0.port');
        config(['queue.connections.rabbitmq.hosts.0.port' => 1]);
        app(OutboxDispatcher::class)->dispatch($failure->id);
        config(['queue.connections.rabbitmq.hosts.0.port' => $host]);
        $this->assertSame('pending', $failure->fresh()->status);
        DeliveryOutbox::whereKey($failure->id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $this->publish($failure);
        $broker = $this->broker();
        $message = $broker->channel()->basic_get($this->topology.'.failed', true);
        $this->assertNotNull($message);
        $this->assertSame($failure->payload(), json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR));
        $broker->close();
        $this->duplicate($event);
        $this->finish($this->worker($event, 'terminal-replay'));
        $this->assertSame(3, $this->countStage('mail'));
        $this->assertSame(0, $this->countStage('accepted_data'));
        $this->assertSame(['smtp_negative_recipient'], MailMessage::distinct()->pluck('diagnostic')->all());
    }

    public function test_lost_final_reply_is_ambiguous_without_automatic_retry(): void
    {
        $event = $this->event();
        $this->relay('lost');
        $this->publish($event);
        $this->finish($this->worker($event, 'lost'));
        $this->assertSame(1, $this->countStage('accepted_data'));
        $this->assertSame(1, $this->countStage('final_reply_lost'));
        $this->assertSame('ambiguous', Delivery::findOrFail($event->delivery_id)->status);
        $this->duplicate($event);
        $this->finish($this->worker($event, 'replay'));
        $this->assertSame(1, $this->countStage('mail'));
        $this->assertSame(1, DeliveryOutbox::count());
    }

    public function test_outer_transaction_refused_without_message_quota_artifact_or_socket(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        stream_set_blocking($server, false);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        config(['emailing.mailpit.port' => $port]);
        MailSetting::create(['account_id' => $this->account->id, 'mode' => 'mailpit']);
        DB::beginTransaction();
        try {
            try {
                app(AccountMailTransport::class)->send($this->account, $this->sender->id, ['recipient' => 'to@example.test', 'subject' => 'Guard', 'text' => 'Body']);
                $this->fail('Outer transaction was accepted.');
            } catch (\LogicException) {
                $this->assertSame(0, MailMessage::count());
                $this->assertSame(0, DB::table('delivery_quota_windows')->count());
                $this->assertFalse(@stream_socket_accept($server, 0));
            }
        } finally {
            DB::rollBack();
            fclose($server);
        }
    }

    public function test_linux_crashes_after_acceptance_and_after_durable_result_before_ack_are_reconciled_without_resend(): void
    {
        $this->relay();
        foreach (['transport_result' => 'ambiguous', 'durable_result' => 'smtp_accepted'] as $stage => $expected) {
            $event = $this->event();
            $this->publish($event);
            $process = $this->worker($event, $stage, $stage);
            $this->stage($stage, $stage, $process);
            $delivery = Delivery::findOrFail($event->delivery_id);
            $this->assertSame($stage === 'transport_result' ? 'sending' : 'smtp_accepted', $delivery->status);
            $starts = $this->countStage('mail');
            $this->assertGreaterThan(0, $this->countStage('final_250'));
            $this->kill($process);
            if ($stage === 'transport_result') {
                $this->expire($event);
                app(DeliveryLifecycle::class)->recover();
            }
            // RabbitMQ returns the unacknowledged original on that exact connection loss.
            $this->finish($this->worker($event, $stage.'-redelivery'));
            $this->assertSame($expected, $delivery->fresh()->status);
            $this->assertSame($starts, $this->countStage('mail'));
            $broker = $this->broker();
            $this->assertNull($broker->channel()->basic_get($this->topology, true));
            $broker->close();
        }
    }

    public function test_real_sql_connection_loss_after_final_250_keeps_sending_then_ambiguous(): void
    {
        $event = $this->event();
        $this->relay();
        $this->publish($event);
        $database = config('database.connections.mysql');
        $proxy = new Process(['python', base_path('tests/support/delivery-sql-proxy.py'), $this->directory, $database['host'], (string) $database['port']], base_path());
        $proxy->setTimeout(80);
        $this->recordRequest('sql-proxy', $proxy);
        $proxy->start();
        $this->proxies[] = $proxy;
        $ready = OutboxProtocolBarrier::await($this->directory.'/sql-ready', fn ($data) => is_int($data['port'] ?? null), 10, fn () => $proxy->isRunning());
        $process = $this->worker($event, 'sql-loss', 'transport_result', ['database.connections.mysql.host' => 'host.docker.internal', 'database.connections.mysql.port' => $ready['port']]);
        $this->stage('transport_result', 'sql-loss', $process);
        $this->assertSame(1, $this->countStage('final_250'));
        file_put_contents($this->directory.'/cut-sql', $event->delivery_id);
        $proxy->wait();
        $this->assertSame(0, $proxy->getExitCode());
        $this->release('sql-loss', $event);
        $this->finish($process, 42);
        $this->assertSame('sending', Delivery::findOrFail($event->delivery_id)->status);
        $this->expire($event);
        app(DeliveryLifecycle::class)->recover();
        $this->finish($this->worker($event, 'sql-redelivery'));
        $this->assertSame('ambiguous', Delivery::findOrFail($event->delivery_id)->status);
        $this->assertSame(1, $this->countStage('mail'));
    }

    public function test_pause_redelivery_defers_without_attempt_then_actual_resume_sends(): void
    {
        $event = $this->event();
        $this->relay();
        $campaign = Campaign::findOrFail(Delivery::findOrFail($event->delivery_id)->campaign_id);
        $paused = $this->service->pause($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $this->publish($event);
        $this->duplicate($event);
        $this->finish($this->worker($event, 'paused'));
        $this->finish($this->worker($event, 'paused-duplicate'));
        $delivery = Delivery::findOrFail($event->delivery_id);
        $this->assertSame('pending', $delivery->status);
        $this->assertSame('campaign_paused', $delivery->diagnostic);
        $this->assertSame(0, $delivery->attempts);
        $this->assertSame(0, DB::table('delivery_quota_windows')->count());
        $this->assertSame(0, $this->countStage('mail'));
        $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version);
        $next = $this->next($event);
        $this->waitAvailable($next);
        $this->publish($next);
        $this->finish($this->worker($next, 'resumed'));
        $this->assertSame('smtp_accepted', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->attempts);
    }

    public function test_public_unsubscribe_after_enqueue_is_account_scoped_before_real_worker(): void
    {
        $first = $this->event();
        $second = $this->secondAccountEvent();
        $this->relay();
        $url = app(SuppressionService::class)->issue($this->account, $this->owner->id, ['email' => $this->contact->email]);
        auth()->forgetGuards();
        session()->flush();
        $this->post($url)->assertOk();
        foreach ([$first, $second] as $i => $event) {
            $this->publish($event);
            $this->finish($this->worker($event, 'account'.$i));
        }
        $this->assertSame('suppressed', Delivery::findOrFail($first->delivery_id)->status);
        $this->assertSame('smtp_accepted', Delivery::findOrFail($second->delivery_id)->status);
        $this->assertSame(0, Delivery::findOrFail($first->delivery_id)->attempts);
        $this->assertSame($second->account_id, MailMessage::sole()->account_id);
        $this->assertSame('second@example.test', MailMessage::sole()->sender_email);
        $this->assertSame(1, $this->countStage('mail'));
        $this->assertSame([], Storage::disk('mail-artifacts')->allFiles());
    }

    public function test_two_accounts_competing_for_shared_pool_cap_defer_and_resume_on_real_utc_window(): void
    {
        $first = $this->event();
        $second = $this->secondAccountEvent();
        $this->relay();
        $policy = ['delivery.quotas.pool.minute' => 1, 'delivery.quotas.account.minute' => 1];
        foreach ([$first, $second] as $event) {
            $this->publish($event);
        }
        $one = $this->worker($first, 'pool-one', 'reserved', $policy);
        $this->stage('reserved', 'pool-one', $one);
        $two = $this->worker($second, 'pool-two', 'reserved', $policy);
        $this->stage('reserved', 'pool-two', $two);
        $this->release('pool-one', $first);
        $this->release('pool-two', $second);
        $this->finish($one);
        $this->finish($two);
        $deliveries = Delivery::orderBy('id')->get();
        $this->assertSame(1, $deliveries->where('status', 'smtp_accepted')->count());
        $waiting = $deliveries->where('diagnostic', 'quota_wait')->sole();
        $this->assertSame(0, $waiting->attempts);
        $this->assertSame(1, $this->countStage('mail'));
        $next = DeliveryOutbox::where('delivery_id', $waiting->id)->where('generation', 2)->sole();
        $this->assertTrue($next->available_at->gt($next->created_at));
        $this->waitAvailable($next);
        $this->publish($next);
        $this->finish($this->worker($next, 'pool-resume', null, $policy));
        $this->assertSame('smtp_accepted', $waiting->fresh()->status);
        $this->assertSame(1, $waiting->fresh()->attempts);
        $this->assertSame(2, $this->countStage('mail'));
        $this->assertSame([$first->account_id, $second->account_id], MailMessage::orderBy('account_id')->pluck('account_id')->all());
    }

    public function test_legacy_test_send_and_worker_share_atomic_account_and_pool_quota(): void
    {
        $event = $this->event();
        $this->relay();
        $policy = ['delivery.quotas.account.minute' => 10, 'delivery.quotas.account.day' => 1];
        config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => $this->port] + $policy);
        $this->publish($event);
        $worker = $this->worker($event, 'after-legacy', 'reserved', $policy, ['barrier_seconds' => 100]);
        $this->stage('reserved', 'after-legacy', $worker);
        $claim = Delivery::findOrFail($event->delivery_id);
        // Bootstrap precedes charging. A daily cap isolates shared authority from
        // the separately tested, deliberately burstable minute boundary.
        do {
            $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            $this->assertTrue(app(DeliveryLifecycle::class)->renew($claim));
            if ($epoch % 86400 < 86340) {
                break;
            }
            usleep(250000);
        } while (true);
        $day = intdiv($epoch, 86400) * 86400;
        $input = ['recipient' => 'test-send@example.test', 'subject' => 'Prueba', 'text' => 'Fixture'];
        $first = app(AccountMailTransport::class)->send($this->account, $this->sender->id, $input);
        $this->assertSame('smtp_accepted', $first->status);
        $this->assertSame($day, intdiv((int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch, 86400) * 86400);
        $this->release('after-legacy', $event);
        $this->finish($worker);
        $delivery = Delivery::findOrFail($event->delivery_id);
        $this->assertSame('quota_wait', $delivery->diagnostic);
        $this->assertSame(0, $delivery->attempts);
        $second = app(AccountMailTransport::class)->send($this->account, $this->sender->id, $input);
        $this->assertSame('blocked', $second->status);
        $this->assertSame('quota_wait', $second->diagnostic);
        $this->assertSame(1, DB::table('delivery_quota_windows')->where('id', 'account:'.$this->account->id.':day:'.$day)->value('used'));
        $this->assertSame(1, DB::table('delivery_quota_windows')->where('id', 'pool:'.config('delivery.relay_pool').':day:'.$day)->value('used'));
        $this->assertSame(1, $this->countStage('mail'));
    }

    public function test_observed_disconnect_before_terminator_is_safe_but_ehlo_fallback_does_not_mask_lost_acceptance(): void
    {
        $this->relay('cut_before_data');
        $event = $this->event();
        $this->publish($event);
        $this->finish($this->worker($event, 'pre-data'));
        $this->assertSame('pending', Delivery::findOrFail($event->delivery_id)->status);
        $this->assertSame('smtp_before_terminator', MailMessage::sole()->diagnostic);
        $this->assertSame(1, $this->countStage('cut_before_data'));
        $this->assertSame(0, $this->countStage('accepted_data'));
        file_put_contents($this->directory.'/mode', 'fallback_lost');
        $next = $this->next($event);
        $this->waitAvailable($next);
        $this->publish($next);
        $this->finish($this->worker($next, 'fallback-lost'));
        $this->assertSame(1, $this->countStage('ehlo_rejected'));
        $this->assertSame(1, $this->countStage('accepted_data'));
        $this->assertSame('ambiguous', Delivery::findOrFail($event->delivery_id)->status);
        $this->assertSame(2, Delivery::findOrFail($event->delivery_id)->attempts);
    }

    public function test_observed_acceptance_survives_quit_connection_loss(): void
    {
        $event = $this->event();
        $this->relay('cleanup_lost');
        $this->publish($event);
        $this->finish($this->worker($event, 'cleanup'));
        $this->assertSame('smtp_accepted', Delivery::findOrFail($event->delivery_id)->status);
        $this->assertSame(1, $this->countStage('final_250'));
    }

    public function test_envelope_bounds_and_real_missing_properties_reject_without_poison_loop_or_transport(): void
    {
        $event = $this->event();
        $body = json_encode($event->payload(), JSON_THROW_ON_ERROR);
        $this->assertSame($event->payload(), DeliveryEnvelope::decode($body));
        foreach (['a:1:{s:1:"v";i:1;}', str_repeat('x', 2049), '{', json_encode(array_replace($event->payload(), ['v' => 2])), json_encode(array_replace($event->payload(), ['generation' => '1'])), json_encode($event->payload() + ['job' => 'serialized']), json_encode(array_replace($event->payload(), ['account_id' => -1]))] as $invalid) {
            $this->assertNull(DeliveryEnvelope::decode($invalid));
        }
        $this->relay();
        // Establish only the own normal binding; malformed messages never become SQL claims.
        $broker = $this->broker();
        $channel = $broker->channel();
        $channel->queue_bind($this->topology, $this->topology, 'delivery.ready');
        foreach ([[], ['content_type' => 'application/json'], ['content_type' => 'application/x-php', 'message_id' => $event->id], ['content_type' => 'application/json', 'message_id' => $event->id]] as $i => $properties) {
            $payload = $i === 3 ? json_encode(array_replace($event->payload(), ['account_id' => $this->account->id + 10000]), JSON_THROW_ON_ERROR) : $body;
            $channel->basic_publish(new AMQPMessage($payload, $properties), $this->topology, 'delivery.ready');
        }
        $broker->close();
        for ($i = 0; $i < 4; $i++) {
            $this->finish($this->worker($event, 'invalid'.$i));
        }
        $this->assertSame(0, MailMessage::count());
        $this->assertSame(0, $this->countStage('mail'));
        $this->assertSame('pending', Delivery::findOrFail($event->delivery_id)->status);
        $broker = $this->broker();
        $this->assertNull($broker->channel()->basic_get($this->topology, true));
        $broker->close();
    }

    public function test_quota_windows_and_wait_are_utc_even_with_non_utc_mysql_session(): void
    {
        $timezone = DB::selectOne('SELECT @@session.time_zone AS zone')->zone;
        try {
            DB::statement("SET SESSION time_zone = '+05:30'");
            config(['delivery.quotas.account.day' => 1]);
            // Keep the wait assertion within one actual day while allowing the
            // minute to change during SQL/MIME work and measuring both bounds.
            while ((int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch % 86400 >= 86340) {
                usleep(100000);
            }
            $start = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            $input = ['recipient' => 'utc@example.test', 'subject' => 'UTC', 'text' => 'Fixture'];
            $first = app(AccountMailTransport::class)->send($this->account, $this->sender->id, $input);
            $end = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            $this->assertSame('logged', $first->status);
            $row = DB::table('delivery_quota_windows')->where('id', 'like', 'account:'.$this->account->id.':minute:%')->sole();
            $bucket = (int) substr($row->id, strrpos($row->id, ':') + 1);
            $this->assertSame(0, $bucket % 60);
            $this->assertGreaterThanOrEqual(intdiv($start, 60) * 60, $bucket);
            $this->assertLessThanOrEqual(intdiv($end, 60) * 60, $bucket);
            $this->assertSame('account:'.$this->account->id.':minute:'.$bucket, $row->id);
            $this->assertSame(gmdate('Y-m-d H:i:s', $bucket + 60).'.000000', $row->ends_at);
            $second = app(AccountMailTransport::class)->send($this->account, $this->sender->id, $input);
            $this->assertSame('quota_wait', $second->diagnostic);
            $day = intdiv($start, 86400) * 86400;
            $this->assertSame(gmdate('Y-m-d H:i:s', $day + 86400).'.000000', DB::table('delivery_quota_windows')->where('id', 'account:'.$this->account->id.':day:'.$day)->value('ends_at'));
            $this->assertSame($day, intdiv((int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch, 86400) * 86400);
            $beforeWait = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            $wait = DB::transaction(function () {
                Account::whereKey($this->account->id)->lockForUpdate()->firstOrFail();

                return app(DeliveryQuota::class)->take($this->account->id);
            });
            $afterWait = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            $this->assertIsString($wait);
            $waitEpoch = CarbonImmutable::parse($wait, 'UTC')->timestamp;
            $this->assertTrue($wait === gmdate('Y-m-d H:i:s', $day + 86400).'.000000'
                || (intdiv($beforeWait, 60) !== intdiv($afterWait, 60) && $waitEpoch >= $beforeWait + 1 && $waitEpoch <= $afterWait + 1));
            $this->assertSame(1, DB::table('delivery_quota_windows')->where('id', $row->id)->value('used'));
        } finally {
            DB::statement('SET SESSION time_zone = ?', [$timezone]);
        }
    }

    public function test_original_contact_identity_current_consent_sender_and_campaign_are_rechecked_at_start(): void
    {
        $this->relay();
        foreach (['email', 'recreated', 'consent', 'sender_disabled', 'sender_changed', 'future', 'draft'] as $case) {
            $event = $this->event();
            $campaign = Campaign::findOrFail(Delivery::findOrFail($event->delivery_id)->campaign_id);
            $original = $this->contact->email;
            $replacement = null;
            try {
                match ($case) {
                    'email' => $this->contact->update(['email' => 'changed-'.Str::uuid().'@example.test']),
                    'recreated' => $this->contact->delete(),
                    'consent' => $this->contact->update(['consent_status' => 'revoked']),
                    'sender_disabled' => $this->sender->update(['lab_authorized' => false]),
                    'sender_changed' => $this->sender->update(['name' => 'Nuevo nombre']),
                    'future' => $campaign->update(['effective_at' => now('UTC')->addDay()]),
                    'draft' => $campaign->update(['status' => 'draft']),
                };
                if ($case === 'recreated') {
                    $replacement = $this->newContact(['email' => $original]);
                }
                $this->publish($event);
                $this->finish($this->worker($event, $case));
                $delivery = Delivery::findOrFail($event->delivery_id);
                $this->assertSame(0, $delivery->attempts, $case);
                $this->assertSame($case === 'future' ? 'pending' : (str_starts_with($case, 'sender') || $case === 'draft' ? 'blocked' : 'suppressed'), $delivery->status, $case);
                $this->assertSame(0, $this->countStage('mail'), $case);
            } finally {
                $replacement?->delete();
                if ($case === 'recreated') {
                    $this->contact->restore();
                }
                $this->contact->update(['email' => $original, 'consent_status' => 'granted']);
                $this->sender->update(['lab_authorized' => true, 'name' => 'Emisor']);
            }
        }
        $this->assertSame(0, DB::table('delivery_quota_windows')->count());
        $this->assertSame(0, MailMessage::count());
    }

    public function test_real_cli_continuous_consumer_exits_cleanly_on_sigterm_after_durable_result(): void
    {
        $event = $this->event();
        $this->relay();
        $this->publish($event);
        $process = $this->worker($event, 'cli', null, [], ['cli' => true]);
        $deadline = microtime(true) + 35;
        while (Delivery::findOrFail($event->delivery_id)->status !== 'smtp_accepted') {
            if (microtime(true) >= $deadline || ! $process->isRunning()) {
                $this->fail('The actual deliveries:work CLI did not persist acceptance.');
            }
            usleep(100000);
        }
        foreach ($this->owned as [$owned, $name]) {
            if ($owned === $process) {
                $signal = new Process(['docker', 'kill', '--signal=TERM', $name]);
                $this->recordRequest('term-'.$name, $signal);
                $signal->mustRun();
                $this->record('term-'.$name, $signal, null);
            }
        }
        $this->finish($process);
        $this->assertStringContainsString('Processed domain events: 1', $process->getOutput());
        $this->assertSame(1, $this->countStage('mail'));
        $broker = $this->broker();
        $this->assertNull($broker->channel()->basic_get($this->topology, true));
        $broker->close();
    }

    public function test_smtp_timeout_is_clamped_below_lease_even_with_large_server_configuration(): void
    {
        $event = $this->event();
        $this->relay('hold');
        $this->publish($event);
        $process = $this->worker($event, 'timeout', null, ['emailing.smtp_timeout' => 120]);
        $this->stage('sending', 'timeout', $process);
        $this->finish($process);
        $protocol = array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file($this->directory.'/smtp-timeout.jsonl', FILE_IGNORE_NEW_LINES));
        $configured = array_values(array_filter($protocol, fn ($row) => $row['stage'] === 'configured'))[0];
        $final = array_values(array_filter($protocol, fn ($row) => ($row['phase'] ?? null) === 'data_final'))[0];
        $this->assertSame(5, $configured['timeout_seconds']);
        $this->assertTrue($final['failed']);
        $this->assertGreaterThanOrEqual(4.5, $final['elapsed_seconds']);
        $this->assertLessThan(15., $final['elapsed_seconds'], 'The blocking SMTP command must finish below the minimum lease, independently of cleanup and process exit.');
        $this->assertSame(0, $this->countStage('final_250'), 'The relay is still holding its reply when the bounded command has failed.');
        $delivery = Delivery::findOrFail($event->delivery_id);
        $this->assertSame('ambiguous', $delivery->status);
        $this->assertNull($delivery->lease_token);
        $this->assertNull($delivery->lease_expires_at);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(1, $delivery->generation);
        $this->assertSame(1, DeliveryOutbox::count());
        $this->assertSame(1, $this->countStage('accepted_data'));
        file_put_contents($this->directory.'/release-final', 'release');
    }

    public function test_ready_failure_key_collisions_refuse_broker_io_and_pending_outbox_recovers_after_configuration_fix(): void
    {
        $event = $this->event();
        MailSetting::where('account_id', $this->account->id)->update(['mode' => 'log']);
        config(['delivery.retry_seconds' => 1]);
        $broker = $this->broker();
        $channel = $broker->channel();
        $channel->queue_bind($this->topology.'.failed', $this->topology, 'delivery.failed');
        $host = config('queue.connections.rabbitmq.hosts.0');
        $listener = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($listener);
        stream_set_blocking($listener, false);
        $port = (int) substr(strrchr(stream_socket_get_name($listener, false), ':'), 1);
        $phases = [];
        app()->instance(PublicationObserver::class, new class($phases) extends PublicationObserver
        {
            public function __construct(private array &$phases) {}

            public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
            {
                $this->phases[] = $stage;
            }
        });
        try {
            config(['queue.connections.rabbitmq.hosts.0.host' => '127.0.0.1', 'queue.connections.rabbitmq.hosts.0.port' => $port]);
            foreach ([['delivery.failed', 'delivery.ready'], ['delivery.ready', 'delivery.failed']] as [$route, $binding]) {
                config(['delivery.routing_key' => $route, 'delivery.binding_key' => $binding]);
                $result = app(ConfirmedDeliveryPublisher::class)->publish($event);
                $this->assertFalse($result->confirmed);
                $this->assertSame('routing_key_collision', $result->error);
                $refused = false;
                try {
                    app(DeliveryConsumer::class)->run(true);
                } catch (\LogicException) {
                    $refused = true;
                }
                $this->assertTrue($refused, 'Unsafe binding must be rejected before broker connection.');
                $accepted = @stream_socket_accept($listener, 0);
                if (is_resource($accepted)) {
                    fclose($accepted);
                }
                $this->assertFalse($accepted, 'Configuration guard must open zero broker connections.');
            }
            $this->assertSame([], $phases);
            config(['delivery.routing_key' => 'delivery.failed', 'delivery.binding_key' => 'delivery.ready']);
            $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
            $this->assertSame('pending', $event->fresh()->status);
            $this->assertSame('routing_key_collision', $event->fresh()->last_error);
            $this->assertSame(1, $event->fresh()->publish_attempts);
            $this->assertSame(0, Delivery::findOrFail($event->delivery_id)->attempts);
            $this->assertSame(0, MailMessage::count());
            $this->assertNull($channel->basic_get($this->topology, true));
            $this->assertNull($channel->basic_get($this->topology.'.failed', true));
        } finally {
            config(['queue.connections.rabbitmq.hosts.0' => $host, 'delivery.routing_key' => 'delivery.ready', 'delivery.binding_key' => 'delivery.ready']);
            fclose($listener);
            $broker->close();
        }
        $this->waitAvailable($event->fresh());
        $this->publish($event);
        $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
        $delivery = Delivery::findOrFail($event->delivery_id);
        $this->assertSame('logged', $delivery->status);
        $this->assertSame(1, $delivery->generation);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(2, $event->fresh()->publish_attempts);
        file_put_contents($this->directory.'/routing-recovery.json', json_encode(['invalid_keys_connections' => 0, 'valid_keys_publication_phases' => $phases, 'outbox' => $event->fresh()->only(['id', 'status', 'publish_attempts']), 'delivery' => $delivery->only(['id', 'status', 'generation', 'attempts'])], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public function test_real_pool_lock_crossing_utc_minute_defers_without_charging_stale_windows(): void
    {
        $event = $this->event();
        $this->relay();
        $this->publish($event);
        // Choose a fresh minute before either child starts. The locker chooses
        // its key only after bootstrap; release the ready worker near its end.
        do {
            $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
            if ($epoch % 60 <= 5) {
                break;
            }
            usleep(250000);
        } while (true);
        $startingBucket = intdiv($epoch, 60) * 60;
        $locker = new Process([PHP_BINARY, '-d', 'zend.exception_ignore_args=1', base_path('tests/support/delivery-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '', 'XDEBUG_MODE' => 'off']);
        $input = ['action' => 'lock-pool', 'config' => ['delivery.relay_pool' => config('delivery.relay_pool')], 'directory' => $this->directory, 'worker' => 'locker'];
        file_put_contents($this->directory.'/locker-input.json', json_encode($input, JSON_THROW_ON_ERROR));
        $locker->setInput(json_encode($input, JSON_THROW_ON_ERROR))->setTimeout(100);
        $this->recordRequest('locker', $locker);
        $lockerStarted = hrtime(true);
        $locker->start();
        $lockerPid = $locker->getPid();
        file_put_contents($this->directory.'/locker-process.json', json_encode(['pid' => $lockerPid], JSON_THROW_ON_ERROR));
        $key = null;
        try {
            $worker = $this->worker($event, 'crossing', 'reserved', [], ['barrier_seconds' => 100]);
            $this->stage('reserved', 'crossing', $worker);
            $claim = Delivery::findOrFail($event->delivery_id);
            $nextRenewal = 0;
            $alive = function () use ($locker, $claim, &$nextRenewal): bool {
                if (microtime(true) >= $nextRenewal) {
                    $renewed = app(DeliveryLifecycle::class)->renew($claim);
                    $current = $claim->fresh();
                    file_put_contents($this->directory.'/claim-renew.jsonl', json_encode([
                        'renewed' => $renewed,
                        'db_epoch' => (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch,
                        'monotonic_ns' => hrtime(true),
                        'at_utc' => gmdate('c'),
                        'claim' => $current->only(['id', 'generation', 'status', 'lease_token', 'lease_expires_at']),
                    ], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
                    if (! $renewed) {
                        return false;
                    }
                    $nextRenewal = microtime(true) + 5;
                }

                return $locker->isRunning();
            };
            $loaded = OutboxProtocolBarrier::await($this->directory.'/loaded-locker', fn ($data) => ($data['platform'] ?? null) === 'Windows' && is_array($data['hashes'] ?? null), max(0.001, 35 - (hrtime(true) - $lockerStarted) / 1e9), $alive);
            foreach ($loaded['hashes'] as $class => $hash) {
                $this->assertSame(hash_file('sha256', (new \ReflectionClass($class))->getFileName()), $hash);
            }
            $pool = OutboxProtocolBarrier::await($this->directory.'/pool-locked', fn ($data) => ($data['bucket'] ?? null) === $startingBucket && ($data['pool_key'] ?? null) === 'pool:'.config('delivery.relay_pool').':minute:'.$startingBucket, 10, $alive);
            $key = $pool['pool_key'];
            $bucket = $pool['bucket'];
            do {
                $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
                if (! $alive()) {
                    throw new \RuntimeException('Owned locker or reserved claim ended before the quota boundary.');
                }
                if ($epoch >= $bucket + 50) {
                    break;
                }
                usleep(250000);
            } while (true);
            $this->assertLessThan($bucket + 60, $epoch);
            $this->release('crossing', $event);
            $before = $this->stage('before_quota', 'crossing', $worker);
            $this->assertSame($bucket, intdiv($before['db_epoch'], 60) * 60);
            // The owned locker prevents the real take() from finishing in this bucket.
            while ((int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch < $bucket + 60) {
                usleep(100000);
            }
            $boundary = ['pool_key' => $key, 'db_epoch' => (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch, 'monotonic_ns' => hrtime(true), 'at_utc' => gmdate('c')];
            OutboxProtocolBarrier::publish($this->directory.'/release-lock', $boundary);
            $locker->wait();
            $this->assertSame(0, $locker->getExitCode(), $locker->getErrorOutput());
            $this->finish($worker);
            $delivery = $claim->fresh();
            $this->assertSame('quota_wait', $delivery->diagnostic);
            $this->assertSame(0, $delivery->attempts);
            $this->assertSame(0, (int) DB::table('delivery_quota_windows')->sum('used'));
            $this->assertSame(0, $this->countStage('mail'));
            file_put_contents($this->directory.'/crossing-proof.json', json_encode(['old_bucket' => $bucket, 'lock_key' => $key, 'locker_process_pid' => $lockerPid, 'loaded' => $loaded, 'pool_locked' => $pool, 'before_quota' => $before, 'boundary' => $boundary, 'released_epoch' => (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch, 'attempts' => $delivery->attempts, 'charged' => 0], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $next = $this->next($event);
            $this->waitAvailable($next);
            $this->publish($next);
            $this->finish($this->worker($next, 'crossing-resume'));
            $this->assertSame('smtp_accepted', $claim->fresh()->status);
        } finally {
            if ($locker->isRunning()) {
                OutboxProtocolBarrier::publish($this->directory.'/release-lock', ['pool_key' => $key, 'worker' => 'locker', 'stop' => true]);
                $locker->wait();
            }
            $this->record('locker', $locker, $lockerPid);
        }
    }
}
