<?php

namespace Tests\Feature;

use App\Models\Membership;
use App\Models\User;
use App\Services\Mcp\DelegatedContext;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpRevocationConcurrencyTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    public function test_mutation_commit_precedes_revocation_then_new_mutations_are_denied(): void
    {
        $this->mcpConfiguration();
        [$actor, $account] = $this->mcpAccount();
        [$token, $grant] = $this->mcpCredential($actor, $account);
        $common = ['token_id' => $token->id, 'grant_id' => $grant->id, 'actor_id' => $actor->id];
        $result = $this->orderedRace($common + ['operation' => 'mutate'], $common + ['operation' => 'revoke']);
        $this->assertSame('accepted', $result[0]['result']);
        $this->assertSame('accepted', $result[1]['result']);
        $this->assertDatabaseCount('contact_lists', 1);
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertNotNull($token->fresh()->revoked_at);
        try {
            DelegatedContext::verified($token);
            $this->fail('Revoked grant accepted.');
        } catch (HttpException $error) {
            $this->assertSame(401, $error->getStatusCode());
        }
    }

    public function test_revocation_commit_precedes_waiting_mutation_and_prevents_its_effect(): void
    {
        $this->mcpConfiguration();
        [$actor, $account] = $this->mcpAccount();
        [$token, $grant] = $this->mcpCredential($actor, $account);
        $common = ['token_id' => $token->id, 'grant_id' => $grant->id, 'actor_id' => $actor->id];
        $result = $this->orderedRace($common + ['operation' => 'revoke'], $common + ['operation' => 'mutate']);
        $this->assertSame('accepted', $result[0]['result']);
        $this->assertSame('rejected:401', $result[1]['result']);
        $this->assertDatabaseCount('contact_lists', 0);
    }

    public function test_membership_removal_uses_same_mutex_as_a_delegated_mutation(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        $actor = User::factory()->create(['email' => 'race-delegate@example.test']);
        $member = Membership::create(['user_id' => $actor->id, 'account_id' => $account->id, 'role' => 'admin', 'scopes' => []]);
        [$token, $grant] = $this->mcpCredential($actor, $account);
        $result = $this->orderedRace(['operation' => 'remove', 'owner_id' => $owner->id, 'account_id' => $account->id, 'membership_id' => $member->id], ['operation' => 'mutate', 'token_id' => $token->id]);
        $this->assertSame('accepted', $result[0]['result']);
        $this->assertSame('rejected:401', $result[1]['result']);
        $this->assertDatabaseCount('contact_lists', 0);
    }

    private function marker(string $directory, string $event): bool
    {
        $path = $directory.'/'.$event.'.json';
        if (! is_file($path)) {
            return false;
        }
        $value = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(basename($directory), $value['run']);

        return true;
    }

    private function waitMarker(string $directory, string $event, array $processes): void
    {
        $deadline = microtime(true) + 15;
        while (! $this->marker($directory, $event)) {
            foreach ($processes as $process) {
                if (! $process->isRunning()) {
                    $this->fail('Authority process terminated before barrier: '.$process->getErrorOutput().$process->getOutput());
                }
            }
            if (microtime(true) > $deadline) {
                $this->fail('SQL authority barrier not reached: '.$event);
            }
            usleep(10000);
        }
    }

    private function signal(string $directory, string $event): void
    {
        file_put_contents($directory.'/'.$event.'.tmp', json_encode(['run' => basename($directory), 'event' => $event], JSON_THROW_ON_ERROR));
        rename($directory.'/'.$event.'.tmp', $directory.'/'.$event.'.json');
    }

    private function orderedRace(array $first, array $second): array
    {
        $directory = storage_path('app/private/mcp-authority-'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        $processes = [];
        try {
            foreach ([$first, $second] as $side => $input) {
                $process = new Process([PHP_BINARY, base_path('tests/support/mcp-authority-action.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode($input + ['directory' => $directory, 'side' => (string) $side, 'hold' => $side === 0], JSON_THROW_ON_ERROR));
                $process->setTimeout(35);
                $process->start();
                $processes[] = $process;
            }
            $this->waitMarker($directory, '0-ready', $processes);
            $this->waitMarker($directory, '1-ready', $processes);
            $this->signal($directory, '0-start');
            $this->waitMarker($directory, '0-locked', $processes);
            $this->signal($directory, '1-start');
            $this->waitMarker($directory, '1-attempting', $processes);
            usleep(300000);
            $this->assertFalse($this->marker($directory, '1-terminal'), 'Contender passed authority while the account mutex was held.');
            $this->signal($directory, 'release');
            $results = [];
            foreach ($processes as $side => $process) {
                $process->wait();
                file_put_contents($directory.'/'.$side.'-stdout.txt', $process->getOutput());
                file_put_contents($directory.'/'.$side.'-stderr.txt', $process->getErrorOutput());
                $this->assertTrue($process->isSuccessful(), 'Authority process failed: '.$process->getErrorOutput());
                $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame(0, $result['transaction_level']);
                $results[] = $result;
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
            $evidence = getenv('MCP_EVIDENCE_DIRECTORY');
            if ($evidence) {
                File::copyDirectory($directory, $evidence.'/'.basename($directory));
            }
            // Only the UUID directory created by this invocation is removed.
            $root = realpath(storage_path('app/private'));
            $resolved = realpath($directory);
            if ($root && $resolved && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && preg_match('/^mcp-authority-[a-f0-9-]{36}$/D', basename($resolved))) {
                File::deleteDirectory($resolved);
            }
        }
    }
}
