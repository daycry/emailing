<?php

use App\Models\Account;
use App\Models\McpGrant;
use App\Models\User;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\TeamService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
try {
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestingDatabaseGuard::assertSafe();
    config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $directory = realpath($input['directory']);
    $root = realpath(storage_path('app/private'));
    if (! $directory || ! $root || dirname($directory) !== $root || ! preg_match('/\Amcp-member-[a-f0-9-]{36}\z/D', basename($directory))) {
        throw new RuntimeException('Owned membership barrier directory required.');
    }
    $publish = function (string $event) use ($directory): void {
        $path = $directory.'/'.$event.'.json';
        file_put_contents($path.'.tmp', json_encode(['run' => basename($directory), 'event' => $event], JSON_THROW_ON_ERROR));
        rename($path.'.tmp', $path);
    };
    $wait = function (string $event) use ($directory): void {
        $deadline = microtime(true) + 20;
        while (! is_file($directory.'/'.$event.'.json')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Membership barrier timed out: '.$event);
            }
            usleep(10000);
        }
        $marker = json_decode(file_get_contents($directory.'/'.$event.'.json'), true, flags: JSON_THROW_ON_ERROR);
        if ($marker !== ['run' => basename($directory), 'event' => $event]) {
            throw new RuntimeException('Foreign membership marker.');
        }
    };
    DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    $isolation = DB::selectOne('SELECT @@session.transaction_isolation AS isolation_level')->isolation_level;
    if ($isolation !== 'REPEATABLE-READ') {
        throw new RuntimeException('Real REPEATABLE READ is required.');
    }
    if ($input['operation'] === 'issue') {
        $actor = User::findOrFail($input['actor_id']);
        $request = Request::create('/oauth/authorize', 'GET');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $actor);
        $verifier = str_repeat('v', 64);
        $pending = app(AuthorizationServer::class)->start($request, ['client_id' => $input['client_id'], 'redirect_uri' => 'http://127.0.0.1:18743/callback', 'resource' => config('mcp.resource'), 'response_type' => 'code', 'scope' => 'contacts.read', 'code_challenge_method' => 'S256', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')]);
        $issued = DB::transaction(function () use ($input, $publish, $wait, $request, $pending, $verifier): array {
            Account::whereKey($input['account_id'])->lockForUpdate()->firstOrFail();
            $publish('issuer-locked');
            $wait('remove-lockwait-observed');
            $location = app(AuthorizationServer::class)->decision($request, ['request_id' => $pending->id, 'decision' => 'allow', 'account_id' => $input['account_id'], 'scopes' => ['contacts.read']]);
            parse_str(parse_url($location, PHP_URL_QUERY), $query);
            $response = app(AuthorizationServer::class)->token(['grant_type' => 'authorization_code', 'client_id' => $input['client_id'], 'redirect_uri' => 'http://127.0.0.1:18743/callback', 'resource' => config('mcp.resource'), 'code' => $query['code'], 'code_verifier' => $verifier]);
            $tokenId = (int) explode('|', $response['access_token'], 2)[0];
            unset($query, $response, $location);

            return ['token_id' => $tokenId];
        });
        $publish('issuer-committed');
        $result = ['operation' => 'issue', 'issued_reference' => $issued];
    } else {
        $wait('issuer-locked');
        Auth::login(User::findOrFail($input['owner_id']));
        session(['account_id' => $input['account_id']]);
        DB::beginTransaction();
        try {
            // A real caller transaction already owns a consistent snapshot. Moving
            // one authorization read inside removeMember cannot refresh this view.
            $seen = McpGrant::where('account_id', $input['account_id'])->count();
            $publish('remove-snapshot');
            try {
                app(TeamService::class)->removeMember($input['membership_id']);
                throw new RuntimeException('Expected real SQL mutex contention was absent.');
            } catch (Throwable $error) {
                $driverError = $error;
                while (! $driverError instanceof QueryException && $driverError->getPrevious() !== null) {
                    $driverError = $driverError->getPrevious();
                }
                if (! $driverError instanceof QueryException || ($driverError->errorInfo[1] ?? null) !== 1205 || DB::transactionLevel() !== 1) {
                    throw $error;
                }
            }
            // The bounded SQL lock wait proves actual contention, rather than a
            // timing sleep. Retry retains the caller's original RR snapshot.
            $publish('remove-lockwait-observed');
            $wait('issuer-committed');
            app(TeamService::class)->removeMember($input['membership_id']);
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }
        $result = ['operation' => 'remove', 'snapshot_grants_before_issue' => $seen, 'observed_mysql_lock_wait_code' => 1205];
    }
    echo json_encode($result + ['isolation' => $isolation, 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
} catch (Throwable $failure) {
    try {
        DB::rollBack(0);
    } catch (Throwable) {
        // The failing process owns this connection; disconnect also releases it.
    }
    fwrite(STDERR, json_encode(['error_class' => $failure::class, 'error_code' => (string) $failure->getCode()], JSON_THROW_ON_ERROR));
    exit(1);
}
