<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Suppression;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryConsumer;
use App\Services\OutboxDispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

class McpPipelineTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private array $owned = [];

    private ?string $topology = null;

    private ?string $directory = null;

    private ?Process $relay = null;

    private array $declared = [];

    protected function tearDown(): void
    {
        $cleanup = ['relay_running' => false, 'broker_removed' => [], 'mailpit_global_delete' => false];
        try {
            if ($this->relay) {
                file_put_contents($this->directory.'/relay-stop', 'stop');
                try {
                    $this->relay->wait();
                } finally {
                    if ($this->relay->isRunning()) {
                        $this->relay->stop(1);
                    }
                }
                $cleanup['relay_running'] = $this->relay->isRunning();
                $cleanup['relay_exit'] = $this->relay->getExitCode();
            }
            if ($this->topology && $this->declared !== []) {
                $this->assertMatchesRegularExpression('/\Aemailing\.mcp-test\.[a-f0-9-]{36}\z/D', $this->topology);
                $connection = $this->broker();
                try {
                    $channel = $connection->channel();
                    foreach (['ready' => $this->topology, 'failed' => $this->topology.'.failed'] as $kind => $name) {
                        if (isset($this->declared[$kind])) {
                            $cleanup['broker_removed'][$kind] = $channel->queue_delete($name);
                        }
                    }
                    if (isset($this->declared['exchange'])) {
                        $channel->exchange_delete($this->topology);
                    }
                } finally {
                    $connection->close();
                }
            }
        } finally {
            try {
                if ($this->owned !== []) {
                    DB::table('api_mail_requests')->whereIn('account_id', $this->owned)->delete();
                }
                if ($this->directory) {
                    $evidence = getenv('MCP_EVIDENCE_DIRECTORY');
                    if (is_string($evidence) && $evidence !== '') {
                        File::copyDirectory($this->directory, $evidence.'/'.basename($this->directory));
                        file_put_contents($evidence.'/'.basename($this->directory).'-resources.json', json_encode($cleanup, JSON_THROW_ON_ERROR));
                    }
                    $root = realpath(storage_path('app/private'));
                    $actual = realpath($this->directory);
                    if ($root && $actual && str_starts_with($actual, $root.DIRECTORY_SEPARATOR)
                        && preg_match('/\Amcp-pipeline-[a-f0-9-]{36}\z/D', basename($actual))) {
                        File::deleteDirectory($actual);
                    }
                }
            } finally {
                parent::tearDown();
            }
        }
    }

    public function test_mcp_work_uses_real_broker_and_shared_quota_suppression_retry_and_uncertainty(): void
    {
        [$account, $bearer, $sender, $grant, $client, $user] = $this->fixture();
        $this->topology();
        file_put_contents($this->directory.'/mode', 'accept');
        $this->relay = new Process(['python', base_path('tests/support/delivery-relay.py'), $this->directory], base_path());
        $this->relay->setTimeout(360);
        $this->relay->start();
        $ready = OutboxProtocolBarrier::await($this->directory.'/relay-ready', fn ($row) => is_int($row['port'] ?? null), 10, fn () => $this->relay->isRunning());
        config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => $ready['port']]);
        foreach (['log', 'quota', 'suppressed', 'safe-retry', 'lost-final'] as $index => $scenario) {
            MailSetting::updateOrCreate(['account_id' => $account->id], ['mode' => in_array($scenario, ['safe-retry', 'lost-final'], true) ? 'mailpit' : 'log']);
            Suppression::where('account_id', $account->id)->delete();
            file_put_contents($this->directory.'/mode', match ($scenario) {
                'safe-retry' => 'reject', 'lost-final' => 'lost', default => 'accept'
            });
            $receipt = $this->callTool($bearer, 'mail_send', ['idempotency_key' => 'synthetic-mcp-pipeline-'.$index,
                'message' => ['sender_id' => $sender->id, 'recipients' => [['email' => 'recipient@example.test']],
                    'content' => ['type' => 'inline', 'subject' => 'Synthetic pipeline', 'text' => 'Synthetic body.']]])['data']['request'];
            $id = $receipt['delivery_ids'][0];
            $event = DeliveryOutbox::where('delivery_id', $id)->sole();
            $this->assertSame('pending', $event->status);
            $dayLimit = config('delivery.quotas.account.day');
            if ($scenario === 'quota') {
                $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
                $day = intdiv($epoch, 86400) * 86400;
                $this->assertGreaterThan(60, $day + 86400 - $epoch);
                DB::table('delivery_quota_windows')->updateOrInsert(['id' => 'account:'.$account->id.':day:'.$day], ['used' => 1, 'ends_at' => gmdate('Y-m-d H:i:s', $day + 86400)]);
                config(['delivery.quotas.account.day' => 1]);
            }
            if ($scenario === 'suppressed') {
                Suppression::create(['account_id' => $account->id, 'normalized_email' => 'recipient@example.test', 'source' => 'manual']);
            }
            $this->dispatch($id);
            $this->assertSame('published', $event->fresh()->status);
            $this->assertNotNull($event->fresh()->published_at);
            $before = $this->callTool($bearer, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request'];
            $this->assertSame(1, $before['counts']['pending']);
            $this->assertSame(0, $before['counts']['smtp_accepted']);
            $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            $delivery = Delivery::findOrFail($id);
            if ($scenario === 'quota') {
                $this->assertSame('quota_wait', $delivery->diagnostic);
                $this->assertSame(0, $delivery->attempts);
                config(['delivery.quotas.account.day' => $dayLimit]);
                $this->dispatch($id);
                $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            } elseif ($scenario === 'suppressed') {
                $this->assertSame(0, $delivery->attempts);
                $this->assertSame(0, MailMessage::where('delivery_id', $id)->count());
            } elseif ($scenario === 'safe-retry') {
                $this->assertSame('pending', $delivery->status);
                $this->assertSame(1, $delivery->attempts);
                file_put_contents($this->directory.'/mode', 'accept');
                $this->dispatch($id);
                $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
                $this->assertSame(2, $delivery->fresh()->attempts);
            }
            $expected = match ($scenario) {
                'safe-retry' => 'smtp_accepted', 'lost-final' => 'ambiguous', 'suppressed' => 'suppressed', default => 'logged'
            };
            $this->assertSame($expected, $delivery->fresh()->status);
            $state = $this->callTool($bearer, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request'];
            $this->assertSame(1, $state['counts'][$expected]);
            $this->assertSame('not_available', $state['delivery_feedback']);
            $count = MailMessage::where('delivery_id', $id)->count();
            $this->assertTrue(app(ConfirmedDeliveryPublisher::class)->publish($event->fresh())->confirmed);
            $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            $this->assertSame($count, MailMessage::where('delivery_id', $id)->count());
            foreach (MailMessage::where('delivery_id', $id)->get() as $attempt) {
                $this->assertSame('mcp', $attempt->origin);
                $this->assertNull($attempt->api_credential_id);
                $this->assertSame($grant->id, $attempt->mcp_grant_id);
                $this->assertSame($client->id, $attempt->mcp_client_id);
                $this->assertSame($user->id, $attempt->actor_id);
            }
        }
        $this->assertDatabaseCount('api_mail_requests', 5);
        $this->assertDatabaseCount('deliveries', 5);
        $this->assertDatabaseCount('mail_messages', 5);
    }

    public function test_mcp_real_mailpit_acceptance_and_duplicate_broker_delivery_have_one_owned_message(): void
    {
        [$account, $bearer, $sender, $grant] = $this->fixture();
        $this->topology();
        $api = rtrim(config('emailing.mailpit.api_url'), '/');
        $this->assertContains(parse_url($api, PHP_URL_HOST), ['127.0.0.1', 'localhost', 'mailpit', '::1']);
        $this->assertContains(config('emailing.mailpit.host'), ['127.0.0.1', 'localhost', 'mailpit', '::1']);
        MailSetting::updateOrCreate(['account_id' => $account->id], ['mode' => 'mailpit']);
        $recipient = 'mcp-mailpit-'.Str::uuid().'@example.test';
        $subject = 'Synthetic MCP mailpit '.Str::uuid();
        $search = fn () => Http::timeout(5)->get($api.'/api/v1/search', ['query' => 'to:'.$recipient])->throw()->json('messages');
        $validateOwned = function (array $message) use ($recipient, $subject, $sender): string {
            $this->assertSame([$recipient], array_column($message['To'], 'Address'));
            $this->assertSame($subject, $message['Subject']);
            $this->assertSame($sender->email, $message['From']['Address']);
            $this->assertMatchesRegularExpression('/\A[a-zA-Z0-9-]+\z/D', $message['ID']);

            return $message['ID'];
        };
        $this->assertCount(0, $search());
        try {
            $receipt = $this->callTool($bearer, 'mail_send', ['idempotency_key' => 'synthetic-real-mailpit-0001',
                'message' => ['sender_id' => $sender->id, 'recipients' => [['email' => $recipient]],
                    'content' => ['type' => 'inline', 'subject' => $subject, 'text' => 'Synthetic MCP mailpit body.']]])['data']['request'];
            $id = $receipt['delivery_ids'][0];
            $this->dispatch($id);
            $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            $this->assertSame('smtp_accepted', Delivery::findOrFail($id)->status);
            $messages = $search();
            $this->assertCount(1, $messages);
            $messageId = $validateOwned($messages[0]);
            $captured = Http::timeout(5)->get($api.'/api/v1/message/'.$messageId)->throw()->json();
            $this->assertSame([$recipient], array_column($captured['To'], 'Address'));
            $this->assertTrue(str_starts_with($captured['Text'], 'Synthetic MCP mailpit body.'));
            $event = DeliveryOutbox::where('delivery_id', $id)->sole();
            $this->assertTrue(app(ConfirmedDeliveryPublisher::class)->publish($event)->confirmed);
            $this->assertSame(1, app(DeliveryConsumer::class)->run(true));
            $this->assertCount(1, $search());
            $this->assertSame(1, MailMessage::where('delivery_id', $id)->count());
            $this->assertSame($grant->id, MailMessage::where('delivery_id', $id)->sole()->mcp_grant_id);
            $state = $this->callTool($bearer, 'mail_request_get', ['request_id' => $receipt['request_id']])['data']['request'];
            $this->assertSame(1, $state['counts']['smtp_accepted']);
            $this->assertSame(1, $state['deliveries'][0]['attempts']);
            $this->assertSame('not_available', $state['delivery_feedback']);
        } finally {
            $deleted = [];
            foreach ($search() as $message) {
                $id = $validateOwned($message);
                Http::timeout(5)->delete($api.'/api/v1/messages', ['IDs' => [$id]])->throw();
                $this->assertSame(404, Http::timeout(5)->get($api.'/api/v1/message/'.$id)->status());
                $deleted[] = $id;
            }
            $remaining = count($search());
            $this->assertSame(0, $remaining);
            file_put_contents($this->directory.'/mailpit-cleanup.json', json_encode(['recipient' => $recipient,
                'subject' => $subject, 'deleted_owned_ids' => $deleted, 'remaining_owned' => $remaining, 'global_delete' => false], JSON_THROW_ON_ERROR));
        }
    }

    private function fixture(): array
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        $this->owned[] = $account->id;
        [, $grant, $bearer, $client] = $this->mcpCredential($user, $account);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Synthetic sender', 'lab_authorized' => true]);
        Storage::fake('mail-artifacts');
        config(['delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000,
            'delivery.backoff_seconds' => 1, 'delivery.max_attempts' => 2, 'delivery.relay_pool' => 'mcp-pipeline-'.Str::uuid()]);

        return [$account, $bearer, $sender, $grant, $client, $user];
    }

    private function topology(): void
    {
        $root = realpath(storage_path('app/private'));
        $base = realpath(base_path());
        $normalize = fn (string $path): string => strtolower(str_replace('\\', '/', $path));
        $this->assertNotFalse($root);
        $this->assertStringStartsWith($normalize($base).'/', $normalize($root).'/');
        $this->directory = $root.DIRECTORY_SEPARATOR.'mcp-pipeline-'.Str::uuid();
        File::makeDirectory($this->directory, 0700);
        $this->topology = 'emailing.mcp-test.'.Str::uuid();
        config(['delivery.exchange' => $this->topology, 'delivery.queue' => $this->topology, 'delivery.failure_queue' => $this->topology.'.failed']);
        $connection = $this->broker();
        try {
            $channel = $connection->channel();
            $channel->exchange_declare($this->topology, 'direct', false, true, false);
            $this->declared['exchange'] = true;
            foreach (['ready' => [$this->topology, config('delivery.binding_key')], 'failed' => [$this->topology.'.failed', config('delivery.failure_routing_key')]] as $kind => [$name, $routing]) {
                $channel->queue_declare($name, false, true, false, false);
                $this->declared[$kind] = true;
                $channel->queue_bind($name, $this->topology, $routing);
            }
        } finally {
            $connection->close();
        }
    }

    private function broker(): AMQPStreamConnection
    {
        $host = config('queue.connections.rabbitmq.hosts.0');

        return new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost'], false, 'AMQPLAIN', null, 'en_US', 5, 10, null, false, 0);
    }

    private function dispatch(string $id): void
    {
        $event = DeliveryOutbox::where('delivery_id', $id)->where('type', 'delivery.ready')->orderByDesc('generation')->firstOrFail();
        DeliveryOutbox::whereKey($event->id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        Delivery::whereKey($id)->update(['available_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
    }

    private function callTool(string $bearer, string $name, array $arguments): array
    {
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => [
            'name' => $name, 'arguments' => (object) $arguments, '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]],
            ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => $name]);
        $response->assertOk()->assertJsonPath('result.isError', false);

        return $response->json('result.structuredContent');
    }
}
