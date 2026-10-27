<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ApiMailRequest;
use App\Models\MailSender;
use App\Services\AccountKeyService;
use App\Support\SendingApiInput;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\Support\OutboxProtocolBarrier;
use Tests\TestCase;

/** Real HTTP kernels and MySQL barriers for shared API/MCP acceptance. */
class McpApiIdempotencyTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private array $accounts = [];

    protected function tearDown(): void
    {
        if ($this->accounts !== []) {
            DB::table('api_mail_requests')->whereIn('account_id', $this->accounts)->delete();
        }
        parent::tearDown();
    }

    public function test_api_commit_wins_and_waiting_mcp_replays_one_receipt(): void
    {
        $this->sameKeyRace('api');
    }

    public function test_mcp_commit_wins_and_waiting_api_replays_one_receipt(): void
    {
        $this->sameKeyRace('mcp');
    }

    public function test_conflicting_mcp_payload_waits_for_api_commit_then_fails_without_work(): void
    {
        $fixture = $this->fixture();
        $first = $fixture['api'];
        $second = $fixture['mcp'];
        $second['message']['content']['text'] = 'Different synthetic content';
        [$accepted, $conflict] = $this->race($first, $second, $fixture['account']);
        $this->assertSame(202, $accepted['status']);
        $this->assertFalse($accepted['replayed']);
        $this->assertSame(200, $conflict['status']);
        $this->assertNull($conflict['request']);
        $this->assertSame('idempotency_conflict', $conflict['error_code']);
        $this->oneWork($fixture['account'], $accepted['request']['request_id'], 'api');
    }

    public function test_conflicting_api_payload_waits_for_mcp_commit_then_fails_without_work(): void
    {
        $fixture = $this->fixture();
        $second = $fixture['api'];
        $second['message']['content']['text'] = 'Different synthetic content';
        [$accepted, $conflict] = $this->race($fixture['mcp'], $second, $fixture['account']);
        $this->assertSame(200, $accepted['status']);
        $this->assertFalse($accepted['replayed']);
        $this->assertSame(409, $conflict['status']);
        $this->assertNull($conflict['request']);
        $this->assertSame('idempotency_conflict', $conflict['error_code']);
        $this->oneWork($fixture['account'], $accepted['request']['request_id'], 'mcp');
    }

    public function test_committed_revocation_prevents_waiting_mcp_acceptance(): void
    {
        $fixture = $this->fixture();
        [$revoked, $denied] = $this->race($fixture['revoke'], $fixture['mcp'], $fixture['account']);
        $this->assertSame(204, $revoked['status']);
        $this->assertSame(401, $denied['status']);
        $this->assertNull($denied['request']);
        foreach (['api_mail_requests', 'api_mail_recipients', 'deliveries', 'delivery_outbox', 'mail_messages'] as $table) {
            $this->assertSame(0, DB::table($table)->where('account_id', $fixture['account'])->count());
        }
    }

    public function test_accepted_mcp_work_survives_waiting_revocation_and_api_replay(): void
    {
        $fixture = $this->fixture();
        [$accepted, $revoked] = $this->race($fixture['mcp'], $fixture['revoke'], $fixture['account']);
        $this->assertSame(200, $accepted['status']);
        $this->assertSame(204, $revoked['status']);
        $request = $this->oneWork($fixture['account'], $accepted['request']['request_id'], 'mcp');
        $this->assertNotNull(DB::table('mcp_grants')->where('id', $request->mcp_grant_id)->value('revoked_at'));
        $response = $this->call('POST', '/api/v1/mail/send', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$fixture['api']['secret'],
            'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $fixture['api']['idempotency']],
            json_encode($fixture['api']['message'], JSON_THROW_ON_ERROR));
        $response->assertStatus(202)->assertExactJson($accepted['request'])->assertHeader('Idempotency-Replayed', 'true');
        $this->assertSame($request->getAttributes(), $request->fresh()->getAttributes());
    }

    public function test_same_key_in_another_account_can_commit_while_first_account_is_held(): void
    {
        $first = $this->fixture();
        $second = $this->fixture();
        [$api, $mcp] = $this->race($first['api'], $second['mcp'], $first['account'], false);
        $this->assertSame(202, $api['status']);
        $this->assertSame(200, $mcp['status']);
        $this->assertFalse($api['replayed']);
        $this->assertFalse($mcp['replayed']);
        $this->assertNotSame($api['request']['request_id'], $mcp['request']['request_id']);
        $this->oneWork($first['account'], $api['request']['request_id'], 'api');
        $this->oneWork($second['account'], $mcp['request']['request_id'], 'mcp');
    }

    public function test_committed_revocation_prevents_a_waiting_replay_without_changing_existing_work(): void
    {
        $fixture = $this->fixture();
        $input = $fixture['mcp'];
        $response = $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call',
            'params' => ['name' => 'mail_send', 'arguments' => ['idempotency_key' => $input['idempotency'], 'message' => $input['message']],
                '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]]],
            ['Authorization' => 'Bearer '.$input['secret'], 'Accept' => 'application/json, text/event-stream',
                'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => 'mail_send']);
        $response->assertOk()->assertJsonPath('result.isError', false);
        $request = ApiMailRequest::where('account_id', $fixture['account'])->sole();
        $before = $request->getAttributes();
        [$revoked, $denied] = $this->race($fixture['revoke'], $fixture['mcp'], $fixture['account'], true, 1);
        $this->assertSame(204, $revoked['status']);
        $this->assertSame(401, $denied['status']);
        $this->assertNull($denied['request']);
        $this->assertSame($before, $request->fresh()->getAttributes());
        $this->oneWork($fixture['account'], $request->id, 'mcp');
    }

    private function sameKeyRace(string $origin): void
    {
        $fixture = $this->fixture();
        $other = $origin === 'api' ? 'mcp' : 'api';
        [$first, $second] = $this->race($fixture[$origin], $fixture[$other], $fixture['account']);
        $this->assertSame($origin === 'api' ? 202 : 200, $first['status']);
        $this->assertSame($other === 'api' ? 202 : 200, $second['status']);
        $this->assertFalse($first['replayed']);
        $this->assertTrue($second['replayed']);
        $this->assertNull($first['error_code']);
        $this->assertNull($second['error_code']);
        $this->assertSameReceipt($first['request'], $second['request']);
        $request = $this->oneWork($fixture['account'], $first['request']['request_id'], $origin);
        $this->assertSame($origin === 'mcp' ? $fixture['revoke']['grant_id'] : null, $request->mcp_grant_id);
        $this->assertSame($origin === 'api' ? $fixture['credential'] : null, $request->credential_id);
    }

    private function oneWork(int $account, string $id, string $origin): ApiMailRequest
    {
        foreach (['api_mail_requests', 'api_mail_recipients', 'deliveries', 'delivery_outbox'] as $table) {
            $this->assertSame(1, DB::table($table)->where('account_id', $account)->count(), $table);
        }
        $this->assertSame(0, DB::table('mail_messages')->where('account_id', $account)->count());
        $request = ApiMailRequest::where('account_id', $account)->findOrFail($id);
        $this->assertSame($origin, $request->origin);
        $this->assertSame($id, DB::table('deliveries')->where('account_id', $account)->sole()->api_request_id);

        return $request;
    }

    /** JSON receipt objects are unordered; delivery_ids remains strictly ordered. */
    private function assertSameReceipt(array $expected, array $actual): void
    {
        ksort($expected, SORT_STRING);
        ksort($actual, SORT_STRING);
        $this->assertSame($expected, $actual);
    }

    private function fixture(): array
    {
        $this->mcpConfiguration();
        [$actor, $account] = $this->mcpAccount();
        $this->accounts[] = $account->id;
        [, $grant, $bearer] = $this->mcpCredential($actor, $account);
        $key = DB::transaction(function () use ($account): array {
            Account::whereKey($account->id)->lockForUpdate()->firstOrFail();

            return app(AccountKeyService::class)->create($account->id, ['name' => 'Synthetic race key', 'scopes' => SendingApiInput::SCOPES], SendingApiInput::SCOPES);
        });
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Synthetic sender', 'lab_authorized' => true]);
        $common = ['idempotency' => 'synthetic-race-key-0001', 'message' => ['sender_id' => $sender->id,
            'recipients' => [['email' => 'recipient@example.test', 'name' => 'Synthetic recipient']],
            'content' => ['type' => 'inline', 'subject' => '  Synthetic 7e1  ', 'text' => "Body\r\n{{ untouched }}"]]];

        return ['account' => $account->id, 'credential' => (int) explode('|', $key['secret'], 2)[0],
            'api' => $common + ['operation' => 'api', 'secret' => $key['secret']],
            'mcp' => $common + ['operation' => 'mcp', 'secret' => $bearer],
            'revoke' => ['operation' => 'revoke', 'grant_id' => $grant->id, 'actor_id' => $actor->id]];
    }

    private function race(array $first, array $second, int $account, bool $sameAccount = true, int $beforeCount = 0): array
    {
        $directory = storage_path('app/private/mcp-mail-race-'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        $processes = [];
        $signal = static fn (string $event) => OutboxProtocolBarrier::publish($directory.'/'.$event.'.json', ['run' => basename($directory), 'event' => $event]);
        $await = function (string $event) use ($directory, &$processes): void {
            $deadline = microtime(true) + 90;
            while (! is_file($directory.'/'.$event.'.json')) {
                foreach ($processes as $process) {
                    $this->assertTrue($process->isRunning(), 'Owned mail race child terminated before barrier.');
                }
                $this->assertLessThan($deadline, microtime(true), 'Mail race barrier timed out: '.$event);
                usleep(10000);
            }
            $marker = json_decode(file_get_contents($directory.'/'.$event.'.json'), true, 32, JSON_THROW_ON_ERROR);
            $this->assertSame(basename($directory), $marker['run']);
        };
        try {
            foreach ([$first, $second] as $side => $input) {
                $process = new Process([PHP_BINARY, base_path('tests/support/mcp-api-concurrency.php')], base_path(),
                    ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '', 'XDEBUG_MODE' => 'off']);
                $process->setInput(json_encode($input + ['directory' => $directory, 'side' => (string) $side, 'hold' => $side === 0], JSON_THROW_ON_ERROR));
                $process->setTimeout(180);
                $process->start();
                $processes[] = $process;
            }
            $await('0-ready');
            $await('1-ready');
            $signal('0-start');
            $await('0-held');
            // A separate connection cannot see the uncommitted first acceptance.
            $this->assertSame($beforeCount, DB::table('api_mail_requests')->where('account_id', $account)->count());
            $signal('1-start');
            $await('1-before-account-lock');
            if ($sameAccount) {
                usleep(300000);
                $this->assertTrue($processes[1]->isRunning());
                $this->assertFileDoesNotExist($directory.'/1-terminal.json');
            } else {
                $await('1-terminal');
                $this->assertSame($beforeCount, DB::table('api_mail_requests')->where('account_id', $account)->count());
            }
            $signal('release');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), 'Mail concurrency child failed; inspect sanitized streams.');
                $result = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
                $this->assertSame(0, $result['transaction_level']);
                $results[] = $result;
            }
            $this->assertTrue($results[0]['held']);
            $this->assertFalse($results[1]['held']);

            return $results;
        } finally {
            // Release only these owned children before any stop or migration teardown.
            $signal('release');
            foreach ($processes as $side => $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                file_put_contents($directory.'/'.$side.'-stdout.txt', $process->getOutput());
                file_put_contents($directory.'/'.$side.'-stderr.txt', $process->getErrorOutput());
                file_put_contents($directory.'/'.$side.'-exit.json', json_encode(['exit_code' => $process->getExitCode()], JSON_THROW_ON_ERROR));
            }
            $evidence = getenv('MCP_EVIDENCE_DIRECTORY');
            if ($evidence) {
                File::copyDirectory($directory, $evidence.'/'.basename($directory));
            }
            $root = realpath(storage_path('app/private'));
            $resolved = realpath($directory);
            if ($root && $resolved && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)
                && preg_match('/^mcp-mail-race-[a-f0-9-]{36}$/D', basename($resolved))) {
                File::deleteDirectory($resolved);
            }
        }
    }
}
