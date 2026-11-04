<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\ContactList;
use App\Models\Invitation;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\Auth\GoogleIdentityResolver;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/GoogleBrowserBoundary.php';
try {
    $descriptor = GoogleBrowserBoundary::cli(getenv(), ['root' => dirname(__DIR__, 2), 'sapi' => PHP_SAPI,
        'os' => PHP_OS_FAMILY, 'docker' => is_file('/.dockerenv'),
        'uid' => function_exists('posix_geteuid') ? posix_geteuid() : null], $argv[1] ?? '');
} catch (RuntimeException) {
    fwrite(STDERR, "Browser fixture descriptor refused before bootstrap.\n");
    exit(2);
}
if ($descriptor['profile'] === 'docker' && in_array($argv[1] ?? '', ['expire-mcp', 'read-fixture'], true)) {
    require_once __DIR__.'/GoogleBrowserLease.php';
    $googleBrowserCliLease = new GoogleBrowserLease($descriptor);
    $googleBrowserCliLease->enterHttp();
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
if (getenv('GOOGLE_BROWSER_TEST') !== '1') {
    throw new RuntimeException('Explicit browser fixture owner required.');
}
$runtime = storage_path('mcp-google-phase3/browser-runtime.json');
$fixturePath = storage_path('mcp-google-phase3/browser-fixtures.json');
if ($descriptor['profile'] === 'docker' && ($argv[1] ?? '') !== 'setup'
    && (is_link($fixturePath) || ! is_file($fixturePath) || (stat($fixturePath)['nlink'] ?? 0) !== 1
        || filesize($fixturePath) === 0 || filesize($fixturePath) > 65536)) {
    throw new RuntimeException('Owned regular Docker browser manifest required.');
}
if (($argv[1] ?? '') === 'read-fixture') {
    if ($descriptor['profile'] !== 'docker' || ! is_file($fixturePath) || is_link($fixturePath)
        || (stat($fixturePath)['nlink'] ?? 0) !== 1 || filesize($fixturePath) > 65536) {
        throw new RuntimeException('Owned Docker browser fixture required.');
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    GoogleBrowserBoundary::dockerManifest($descriptor, $fixture);
    if (($fixture['run'] ?? null) !== $descriptor['run'] || ($fixture['directory'] ?? null) !== $descriptor['directory']
        || realpath($fixture['directory']) !== $descriptor['directory']
        || ! is_file($descriptor['directory'].'/fixtures.private.json')) {
        throw new RuntimeException('Owned Docker browser fixture required.');
    }
    // Private DTO for the local test process only; never capture as public evidence.
    echo json_encode($fixture, JSON_THROW_ON_ERROR);
    exit(0);
}
if (($argv[1] ?? '') === 'expire-mcp') {
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    if ($descriptor['profile'] === 'docker') {
        GoogleBrowserBoundary::dockerManifest($descriptor, $fixture);
    }
    $expected = storage_path('mcp-google-phase3/browser-'.$fixture['run']);
    if (! preg_match('/\A[a-f0-9-]{36}\z/D', $fixture['run']) || realpath($fixture['directory']) !== realpath($expected) || ! is_file($expected.'/fixtures.private.json')) {
        throw new RuntimeException('Owned fixture directory required.');
    }
    if (($fixture['run'] ?? null) !== getenv('GOOGLE_BROWSER_RUN')) {
        throw new RuntimeException('Fixture ownership mismatch.');
    }
    DB::transaction(function () use ($fixture) {
        $actor = User::where('email', $fixture['emails']['owner'])->firstOrFail();
        $tokenId = getenv('MCP_BROWSER_TOKEN_ID');
        if (! is_string($tokenId) || ! preg_match('/\A[1-9][0-9]{0,15}\z/D', $tokenId)) {
            throw new RuntimeException('Owned token reference required.');
        }
        $candidate = PersonalAccessToken::whereKey($tokenId)->where('purpose', 'mcp')->where('client_id', $fixture['mcp_client_id'])->where('account_id', $fixture['account_b'])->where('tokenable_id', $actor->id)->firstOrFail();
        Account::whereKey($fixture['account_b'])->lockForUpdate()->firstOrFail();
        Membership::where('account_id', $fixture['account_b'])->where('user_id', $actor->id)->lockForUpdate()->firstOrFail();
        McpClient::whereKey($fixture['mcp_client_id'])->lockForUpdate()->firstOrFail();
        $grant = McpGrant::whereKey($candidate->grant_id)->where('client_id', $fixture['mcp_client_id'])->where('account_id', $fixture['account_b'])->where('user_id', $actor->id)->whereNull('revoked_at')->lockForUpdate()->firstOrFail();
        if (! $grant->expires_at->isFuture()) {
            throw new RuntimeException('Clock fixture requires a live grant.');
        }
        $token = PersonalAccessToken::whereKey($candidate->id)->where('grant_id', $grant->id)->lockForUpdate()->firstOrFail();
        $token->update(['expires_at' => now()->subSecond()]);
        if (! $grant->fresh()->expires_at->isFuture()) {
            throw new RuntimeException('Clock fixture must preserve grant validity.');
        }
    });
    echo "Owned MCP clock fixture applied.\n";
    exit(0);
}
if (($argv[1] ?? '') === 'cleanup') {
    if (! is_file($fixturePath)) {
        exit(0);
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    if ($descriptor['profile'] === 'docker') {
        GoogleBrowserBoundary::dockerManifest($descriptor, $fixture);
        require_once __DIR__.'/GoogleBrowserLease.php';
        $googleBrowserCleanupLease = new GoogleBrowserLease($descriptor);
        $googleBrowserCleanupLease->quiesce();
        $connections = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()');
        if ((int) $connections->n !== 0 || DB::transactionLevel() !== 0) {
            throw new RuntimeException('Quiet exclusive browser database required before cleanup.');
        }
    }
    $expected = storage_path('mcp-google-phase3/browser-'.$fixture['run']);
    if (! preg_match('/\A[a-f0-9-]{36}\z/D', $fixture['run']) || realpath($fixture['directory']) !== realpath($expected) || ! is_file($expected.'/fixtures.private.json')) {
        throw new RuntimeException('Owned fixture directory required.');
    }
    $ownedForVerification = ['attempts' => [], 'codes' => [], 'windows' => []];
    DB::transaction(function () use ($fixture, &$ownedForVerification) {
        $users = User::whereIn('email', array_values($fixture['emails']))->pluck('id');
        $clients = $fixture['mcp_client_ids'];
        $grants = DB::table('mcp_grants')->whereIn('client_id', $clients)->pluck('id');
        $tokens = DB::table('personal_access_tokens')->whereIn('grant_id', $grants)->pluck('id');
        $resourcesPath = $fixture['directory'].'/cleanup-resources.json';
        if (! is_file($resourcesPath)) {
            file_put_contents($resourcesPath, json_encode([
                'run' => $fixture['run'],
                'users' => $users->all(),
                'accounts' => Account::whereIn('owner_id', $users)->pluck('id')->all(),
                'clients' => $clients,
                'grants' => $grants->all(),
                'tokens' => $tokens->all(),
            ], JSON_THROW_ON_ERROR));
        }
        DB::table('mcp_sessions')->whereIn('token_id', $tokens)->delete();
        DB::table('personal_access_tokens')->whereIn('id', $tokens)->delete();
        DB::table('mcp_authorization_codes')->whereIn('grant_id', $grants)->delete();
        DB::table('mcp_authorization_requests')->whereIn('client_id', $clients)->delete();
        // Requests retain their first grant: remove only this fixture's work
        // before its grants, rather than weakening production provenance FKs.
        $accounts = Account::whereIn('owner_id', $users)->pluck('id');
        DB::table('api_mail_requests')->whereIn('account_id', $accounts)->delete();
        DB::table('mcp_grants')->whereIn('id', $grants)->delete();
        DB::table('mcp_clients')->whereIn('id', $clients)->delete();
        DB::table('mcp_audit_events')->whereIn('actor_id', $users)->delete();
        Account::whereIn('owner_id', $users)->delete();
        User::whereIn('id', $users)->delete();
        $ownedFile = $fixture['directory'].'/google-owned-rows.json';
        $owned = is_file($ownedFile) ? json_decode(file_get_contents($ownedFile), true, flags: JSON_THROW_ON_ERROR) : ['attempts' => [], 'codes' => []];
        $ownedForVerification = $owned;
        DB::table('google_login_attempts')->whereIn('id', $owned['attempts'])->delete();
        DB::table('google_login_codes')->whereIn('code_hash', $owned['codes'])->delete();
        DB::table('mcp_admission_windows')->whereIn('key', $owned['windows'] ?? [])->delete();
    });
    if ($descriptor['profile'] === 'docker'
        && (User::whereIn('email', array_values($fixture['emails']))->exists()
            || McpClient::whereIn('id', $fixture['mcp_client_ids'])->exists()
            || DB::table('google_login_attempts')->whereIn('id', $ownedForVerification['attempts'])->exists()
            || DB::table('google_login_codes')->whereIn('code_hash', $ownedForVerification['codes'])->exists()
            || DB::table('mcp_admission_windows')->whereIn('key', $ownedForVerification['windows'] ?? [])->exists()
            || DB::transactionLevel() !== 0)) {
        throw new RuntimeException('Owned browser SQL cleanup incomplete.');
    }
    echo "Owned Google browser SQL fixtures removed.\n";
    exit(0);
}
if ($descriptor['profile'] === 'docker') {
    $connections = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()');
    $tables = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
    if ((int) $connections->n !== 0 || (int) $tables->n !== 0 || DB::transactionLevel() !== 0) {
        throw new RuntimeException('Fresh exclusive Docker browser database required before migration.');
    }
}
Artisan::call('migrate', ['--force' => true]);
$run = getenv('GOOGLE_BROWSER_RUN');
if (! is_string($run) || ! preg_match('/\A[a-f0-9-]{36}\z/D', $run)) {
    throw new RuntimeException('Invalid fixture run.');
}
config(['emailing.registration_enabled' => true]);
$emails = [];
foreach (['owner', 'other', 'collision', 'recipient', 'signup'] as $name) {
    $emails[$name] = 'google-browser-'.$name.'-'.$run.'@example.test';
}
$baseline = (int) DB::table('google_login_attempts')->max('id');
$codes = DB::table('google_login_codes')->pluck('code_hash')->all();
// Partial startup cannot leave committed fixtures without their owner manifest.
DB::beginTransaction();
$users = [];
foreach (['owner', 'other', 'collision', 'recipient'] as $name) {
    $users[$name] = app(CreateNewUser::class)->create(['name' => 'Browser '.ucfirst($name), 'account_name' => 'Browser '.$name.' own', 'email' => $emails[$name], 'password' => 'Synthetic-password-1234', 'password_confirmation' => 'Synthetic-password-1234']);
}
$second = Account::create(['name' => 'Browser second own', 'owner_id' => $users['other']->id]);
Membership::create(['account_id' => $second->id, 'user_id' => $users['owner']->id, 'role' => 'read-only', 'scopes' => []]);
$foreign = Account::create(['name' => 'Browser foreign account', 'owner_id' => $users['other']->id]);
foreach (['owner', 'recipient'] as $name) {
    app(GoogleIdentityResolver::class)->link($users[$name], ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'browser-'.$name.'-'.$run]);
}
$invitation = str_repeat('Ab9Z', 16);
Invitation::create(['account_id' => $foreign->id, 'invited_by' => $users['other']->id, 'email' => $emails['recipient'], 'role' => 'read-only', 'scopes' => [], 'token_hash' => hash('sha256', $invitation), 'expires_at' => now()->addHour()]);
$delegated = Account::create(['name' => 'Browser delegated B', 'owner_id' => $users['other']->id]);
Membership::create(['account_id' => $delegated->id, 'user_id' => $users['other']->id, 'role' => 'admin', 'scopes' => []]);
Membership::create(['account_id' => $delegated->id, 'user_id' => $users['owner']->id, 'role' => 'restricted', 'scopes' => ['contacts.read']]);
$own = $users['owner']->memberships()->firstOrFail()->account;
$listA = ContactList::create(['account_id' => $own->id, 'name' => 'Browser list A', 'description' => 'Only A']);
$listB = ContactList::create(['account_id' => $delegated->id, 'name' => 'Browser list B', 'description' => 'Only B']);
config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
$client = app(AuthorizationServer::class)->register(['client_name' => 'Browser MCP client', 'redirect_uris' => ['http://127.0.0.1:18743/callback'], 'token_endpoint_auth_method' => 'none'], false);
$apiKey = app(AccountKeyService::class)->create($own->id, ['name' => 'Browser negative key', 'scopes' => ['mail.send']], ['mail.send'], actor: $users['owner']->id);
$fixture = ['run' => $run, 'emails' => $emails, 'invitation' => $invitation, 'foreign_account_id' => $foreign->id,
    'attempt_baseline' => $baseline, 'code_baseline' => $codes, 'directory' => getenv('GOOGLE_BROWSER_DIRECTORY'), 'mcp_client_ids' => [$client->id], 'mcp_client_id' => $client->id, 'account_a' => $own->id, 'account_b' => $delegated->id, 'list_a' => $listA->id, 'list_b' => $listB->id, 'api_key' => $apiKey['secret']];
file_put_contents($fixturePath, json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents(getenv('GOOGLE_BROWSER_DIRECTORY').'/fixtures.private.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
DB::commit();
if ($descriptor['profile'] === 'docker') {
    require_once __DIR__.'/GoogleBrowserLease.php';
    $googleBrowserEnableLease = new GoogleBrowserLease($descriptor);
    $googleBrowserEnableLease->enable();
}
echo "Owned Google browser SQL fixtures ready.\n";
