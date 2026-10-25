<?php

namespace Tests\Feature;

use App\Models\McpAuditEvent;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\Mcp\DelegatedContext;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpMembershipRemovalRegressionTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    public function test_removal_after_prior_rr_snapshot_revokes_authority_issued_while_mutex_is_held(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        $actor = User::factory()->create();
        $member = Membership::create(['account_id' => $account->id, 'user_id' => $actor->id, 'role' => 'restricted', 'scopes' => ['contacts.read']]);
        $client = McpClient::create(['id' => (string) Str::uuid(), 'issuer' => config('mcp.issuer'), 'name' => 'SQL race client', 'redirect_uris' => ['http://127.0.0.1:18743/callback']]);
        $directory = storage_path('app/private/mcp-member-'.Str::uuid());
        File::makeDirectory($directory, 0700, true);
        $processes = [];
        $results = [];
        try {
            foreach (['issue', 'remove'] as $operation) {
                $process = new Process([PHP_BINARY, base_path('tests/support/mcp-membership-race.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode(['operation' => $operation, 'directory' => $directory, 'account_id' => $account->id, 'owner_id' => $owner->id, 'actor_id' => $actor->id, 'membership_id' => $member->id, 'client_id' => $client->id], JSON_THROW_ON_ERROR));
                $process->setTimeout(50);
                $process->start();
                $processes[$operation] = $process;
            }
            foreach ($processes as $operation => $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Owned membership process failed: '.$process->getErrorOutput());
                $results[$operation] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertSame('REPEATABLE-READ', $results[$operation]['isolation']);
                $this->assertSame(0, $results[$operation]['transaction_level']);
            }
        } finally {
            foreach ($processes as $operation => $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
                file_put_contents($directory.'/'.$operation.'-stdout.txt', $process->getOutput());
                file_put_contents($directory.'/'.$operation.'-stderr.txt', $process->getErrorOutput());
            }
            if ($evidence = getenv('MCP_EVIDENCE_DIRECTORY')) {
                File::copyDirectory($directory, $evidence.'/'.basename($directory));
            }
            if (dirname(realpath($directory)) === realpath(storage_path('app/private')) && preg_match('/\Amcp-member-[a-f0-9-]{36}\z/D', basename($directory))) {
                File::deleteDirectory($directory);
            }
        }
        $this->assertSame(0, $results['remove']['snapshot_grants_before_issue']);
        $this->assertSame(1205, $results['remove']['observed_mysql_lock_wait_code']);
        $this->assertDatabaseMissing('memberships', ['id' => $member->id]);
        Membership::create(['account_id' => $account->id, 'user_id' => $actor->id, 'role' => 'restricted', 'scopes' => ['contacts.read']]);
        $token = PersonalAccessToken::findOrFail($results['issue']['issued_reference']['token_id']);
        $accepted = false;
        try {
            DelegatedContext::verified($token);
            $accepted = true;
        } catch (HttpException $error) {
            $this->assertSame(401, $error->getStatusCode());
        }
        $this->assertFalse($accepted, 'Authority issued during the old RR snapshot was reactivated by a new membership.');
        $this->assertNotNull($token->fresh()->revoked_at);
        $this->assertNotNull(McpGrant::findOrFail($token->grant_id)->revoked_at);
    }

    public function test_removal_skips_600_historical_tombstones_and_revokes_live_authority_with_bounded_queries(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        $actor = User::factory()->create();
        $member = Membership::create(['account_id' => $account->id, 'user_id' => $actor->id, 'role' => 'admin', 'scopes' => []]);
        [$live, $liveGrant, , $client] = $this->mcpCredential($actor, $account);
        [$otherActor] = $this->mcpCredential($owner, $account, client: $client);
        [$actorAgain, $otherAccount] = $this->mcpAccount($actor);
        [$otherAccountToken] = $this->mcpCredential($actorAgain, $otherAccount, client: $client);
        $this->actingAs($owner)->withSession(['account_id' => $account->id]);
        $keys = app(AccountKeyService::class);
        $key = $keys->create($account->id, ['name' => 'SQL regression account key', 'scopes' => ['mail.send']], ['mail.send'], actor: $actor->id);
        $candidate = $keys->candidate(Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$key['secret']]));
        $this->assertSame($account->id, $keys->operation($candidate, ['mail.send'], fn ($current) => $current->account_id));
        $old = now()->subDays(10)->startOfSecond();
        $grants = [];
        $tokens = [];
        for ($index = 0; $index < 600; $index++) {
            $id = (string) Str::uuid();
            $grants[] = array_replace($liveGrant->getAttributes(), ['id' => $id, 'revoked_at' => $old->format('Y-m-d H:i:s'), 'expires_at' => $old->format('Y-m-d H:i:s')]);
            $row = array_replace($live->getAttributes(), ['grant_id' => $id, 'token' => hash('sha256', (string) Str::uuid()), 'revoked_at' => $old->format('Y-m-d H:i:s'), 'expires_at' => $old->format('Y-m-d H:i:s')]);
            unset($row['id']);
            $tokens[] = $row;
        }
        foreach (array_chunk($grants, 100) as $chunk) {
            DB::table('mcp_grants')->insert($chunk);
        }
        foreach (array_chunk($tokens, 100) as $chunk) {
            DB::table('personal_access_tokens')->insert($chunk);
        }
        $queries = [];
        $record = true;
        DB::listen(function ($event) use (&$queries, &$record): void {
            if ($record && preg_match('/\Aselect\b/i', $event->sql) && preg_match('/\b(mcp_grants|mcp_clients|personal_access_tokens|mcp_sessions)\b/', $event->sql)) {
                $queries[] = $event->sql;
            }
        });
        app(TeamService::class)->removeMember($member->id);
        $record = false;
        if ($evidence = getenv('MCP_EVIDENCE_DIRECTORY')) {
            file_put_contents($evidence.'/removal-query-budget.json', json_encode(['historical_grants' => 600, 'historical_tokens' => 600, 'live_grants' => 1, 'relevant_selects' => count($queries), 'select_budget' => 8, 'query_signatures' => array_count_values($queries)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        // Two discovery reads plus client/grant/token reads for one live grant
        // fit in eight. This budget is independent of the 600 retained rows.
        $this->assertLessThanOrEqual(8, count($queries), 'Revoked history must not add per-grant SQL round trips.');
        $this->assertSame(600, McpGrant::where('user_id', $actor->id)->where('account_id', $account->id)->where('revoked_at', $old)->count());
        $this->assertSame(600, PersonalAccessToken::whereIn('grant_id', array_column($grants, 'id'))->where('revoked_at', $old)->count());
        $this->assertNotNull($liveGrant->fresh()->revoked_at);
        $this->assertNotNull($live->fresh()->revoked_at);
        $this->assertSame(1, McpAuditEvent::where('grant_id', $liveGrant->id)->where('operation', 'connection.revoke')->where('actor_id', $owner->id)->count());
        $this->assertNull($otherActor->fresh()->revoked_at);
        $this->assertNull($otherAccountToken->fresh()->revoked_at);
        $this->assertSame($account->id, $keys->operation($candidate, ['mail.send'], fn ($current) => $current->account_id));
    }

    public function test_removal_preserves_the_callers_transaction_and_sees_its_uncommitted_authority(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        $actor = User::factory()->create();
        $member = Membership::create(['account_id' => $account->id, 'user_id' => $actor->id, 'role' => 'restricted', 'scopes' => ['contacts.read']]);
        $this->actingAs($owner)->withSession(['account_id' => $account->id]);
        $connection = DB::connection();
        $pdo = $connection->getPdo();
        $connectionCount = count(DB::getConnections());
        $this->assertSame(0, DB::transactionLevel());
        DB::beginTransaction();
        try {
            [$token, $grant] = $this->mcpCredential($actor, $account);
            app(TeamService::class)->removeMember($member->id);
            $this->assertSame(1, DB::transactionLevel());
            $this->assertTrue($pdo === DB::connection()->getPdo(), 'Removing a member must not replace the callers connection.');
            $this->assertTrue(count(DB::getConnections()) === $connectionCount, 'Temporary connections must be released.');
            $this->assertDatabaseMissing('memberships', ['id' => $member->id]);
            $this->assertNotNull($grant->fresh()->revoked_at);
            $this->assertNotNull($token->fresh()->revoked_at);
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, DB::transactionLevel());
        $this->assertDatabaseHas('memberships', ['id' => $member->id]);
        $this->assertDatabaseMissing('mcp_grants', ['id' => $grant->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }
}
