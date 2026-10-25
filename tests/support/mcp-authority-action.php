<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\AccountContext;
use App\Services\ContactService;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\GrantService;
use App\Services\TeamService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$directory = realpath($input['directory']);
$root = realpath(storage_path('app/private'));
if (! $directory || ! $root || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR) || ! preg_match('/^mcp-authority-[a-f0-9-]{36}$/D', basename($directory))) {
    throw new RuntimeException('Unowned authority barrier directory.');
}
$publish = function (string $event) use ($directory, $input): void {
    $path = $directory.'/'.$input['side'].'-'.$event.'.json';
    file_put_contents($path.'.tmp', json_encode(['run' => basename($directory), 'side' => $input['side'], 'event' => $event], JSON_THROW_ON_ERROR));
    rename($path.'.tmp', $path);
};
$wait = function (string $event) use ($directory): void {
    $deadline = microtime(true) + 15;
    while (true) {
        $path = $directory.'/'.$event.'.json';
        if (is_file($path)) {
            $value = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            if (($value['run'] ?? null) !== basename($directory) || ($value['event'] ?? null) !== $event) {
                throw new RuntimeException('Foreign or incomplete authority marker.');
            }

            return;
        }
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Authority barrier timed out.');
        }
        usleep(10000);
    }
};
$publish('ready');
$wait($input['side'].'-start');
$held = false;
DB::listen(function ($event) use ($input, $publish, $wait, &$held): void {
    if (! $held && $input['hold'] && DB::transactionLevel() > 0 && str_contains($event->sql, 'for update') && str_contains($event->sql, 'personal_access_tokens')) {
        $held = true;
        $publish('locked');
        $wait('release');
    }
});
try {
    $publish('attempting');
    if ($input['operation'] === 'mutate') {
        $context = DelegatedContext::verified(PersonalAccessToken::findOrFail($input['token_id']));
        (new ContactService(new AccountContext($context)))->saveList(null, ['name' => 'Barrier mutation', 'description' => '']);
    } elseif ($input['operation'] === 'revoke') {
        app(GrantService::class)->revoke($input['grant_id'], $input['actor_id']);
    } else {
        Auth::login(User::findOrFail($input['owner_id']));
        session(['account_id' => $input['account_id']]);
        if ($input['operation'] === 'remove') {
            app(TeamService::class)->removeMember($input['membership_id']);
        } else {
            app(TeamService::class)->updateMember($input['membership_id'], 'read-only', []);
        }
    }
    $result = 'accepted';
} catch (HttpException $error) {
    $result = 'rejected:'.$error->getStatusCode();
}
$publish('terminal');
echo json_encode(['result' => $result, 'held' => $held, 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
