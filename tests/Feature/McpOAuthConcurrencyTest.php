<?php

namespace Tests\Feature;

use App\Models\McpAuthorizationCode;
use App\Models\McpGrant;
use App\Models\PersonalAccessToken;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpOAuthConcurrencyTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    public function test_parallel_code_exchange_commits_once_and_replay_revokes_only_derived_token(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$unrelated, $otherGrant, , $client] = $this->mcpCredential($user, $account);
        $grant = McpGrant::create(['id' => (string) Str::uuid(), 'user_id' => $user->id, 'account_id' => $account->id, 'client_id' => $client->id, 'issuer' => config('mcp.issuer'), 'resource' => config('mcp.resource'), 'scopes' => ['contacts.read'], 'expires_at' => now()->addHour()]);
        $code = Str::random(64);
        $verifier = Str::random(64);
        McpAuthorizationCode::create(['code_hash' => hash('sha256', $code), 'grant_id' => $grant->id, 'client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'expires_at' => now()->addSeconds(300)]);
        $result = $this->race(['operation' => 'token', 'token_input' => ['grant_type' => 'authorization_code', 'client_id' => $client->id, 'code' => $code, 'code_verifier' => $verifier, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource')]]);
        $this->assertSame('accepted', $result[0]['result']);
        $this->assertSame('invalid_grant', $result[1]['result']);
        $this->assertSame(1, PersonalAccessToken::where('grant_id', $grant->id)->count());
        $this->assertNotNull(PersonalAccessToken::where('grant_id', $grant->id)->sole()->revoked_at);
        $this->assertNull($unrelated->fresh()->revoked_at);
        $this->assertNull($otherGrant->fresh()->revoked_at);
    }

    public function test_parallel_admission_is_atomic_across_dimensions_without_overshoot_or_raw_ip(): void
    {
        $this->mcpConfiguration();
        $subject = (string) Str::uuid();
        $result = $this->race(['operation' => 'admission', 'dimensions' => [['test.token', $subject, 1, 60], ['test.grant', $subject, 1, 60]]]);
        $this->assertSame('accepted', $result[0]['result']);
        $this->assertSame('temporarily_unavailable', $result[1]['result']);
        $this->assertGreaterThanOrEqual(1, $result[1]['retry_after']);
        $this->assertLessThanOrEqual(60, $result[1]['retry_after']);
        $this->assertSame([1, 1], DB::table('mcp_admission_windows')->orderBy('key')->pluck('hits')->all());
        $this->assertSame(['key', 'hits', 'expires_at'], DB::getSchemaBuilder()->getColumnListing('mcp_admission_windows'));
    }

    private function publish(string $directory, string $event): void
    {
        $path = $directory.'/'.$event;
        file_put_contents($path.'.tmp', json_encode(['run' => basename($directory), 'event' => $event], JSON_THROW_ON_ERROR));
        rename($path.'.tmp', $path.'.json');
    }

    private function wait(string $directory, string $event, array $processes): void
    {
        $deadline = microtime(true) + 15;
        while (! is_file($directory.'/'.$event.'.json')) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('OAuth child exited before barrier: '.$process->getErrorOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('OAuth barrier missing: '.$event);
            }
            usleep(10000);
        }
        $marker = json_decode(file_get_contents($directory.'/'.$event.'.json'), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(basename($directory), $marker['run']);
    }

    private function race(array $input): array
    {
        $directory = storage_path('app/private/mcp-oauth-'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        $processes = [];
        try {
            foreach ([0, 1] as $side) {
                $process = new Process([PHP_BINARY, base_path('tests/support/mcp-oauth-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode($input + ['directory' => $directory, 'side' => (string) $side, 'hold' => $side === 0], JSON_THROW_ON_ERROR));
                $process->setTimeout(35);
                $process->start();
                $processes[] = $process;
            }
            $this->wait($directory, '0-ready', $processes);
            $this->wait($directory, '1-ready', $processes);
            $this->publish($directory, '0-start');
            $this->wait($directory, '0-locked', $processes);
            $this->publish($directory, '1-start');
            $this->wait($directory, '1-attempting', $processes);
            usleep(300000);
            $this->assertFileDoesNotExist($directory.'/1-terminal.json');
            $this->publish($directory, 'release');
            $results = [];
            foreach ($processes as $side => $process) {
                $process->wait();
                file_put_contents($directory.'/'.$side.'-stdout.txt', $process->getOutput());
                file_put_contents($directory.'/'.$side.'-stderr.txt', $process->getErrorOutput());
                $this->assertTrue($process->isSuccessful(), 'OAuth child failed: '.$process->getErrorOutput());
                $results[] = $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $result['transaction_level']);
            }
            $this->assertTrue($results[0]['held']);

            return $results;
        } finally {
            foreach ($processes as $side => $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                file_put_contents($directory.'/'.$side.'-stdout.txt', $process->getOutput());
                file_put_contents($directory.'/'.$side.'-stderr.txt', $process->getErrorOutput());
            }
            if ($target = getenv('MCP_EVIDENCE_DIRECTORY')) {
                File::copyDirectory($directory, $target.'/'.basename($directory));
            }
            $root = realpath(storage_path('app/private'));
            $owned = realpath($directory);
            if ($root && $owned && str_starts_with($owned, $root.DIRECTORY_SEPARATOR) && preg_match('/^mcp-oauth-[a-f0-9-]{36}$/D', basename($owned))) {
                File::deleteDirectory($owned);
            }
        }
    }
}
