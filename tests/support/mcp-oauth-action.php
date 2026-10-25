<?php

use App\Services\Mcp\Admission;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\Mcp\OAuth\OAuthError;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$directory = realpath($input['directory']);
$root = realpath(storage_path('app/private'));
if (! $directory || ! $root || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR) || ! preg_match('/^mcp-oauth-[a-f0-9-]{36}$/D', basename($directory))) {
    throw new RuntimeException('Unowned OAuth barrier directory.');
}
$publish = function (string $event) use ($directory, $input): void {
    $path = $directory.'/'.$input['side'].'-'.$event.'.json';
    file_put_contents($path.'.tmp', json_encode(['run' => basename($directory), 'event' => $event], JSON_THROW_ON_ERROR));
    rename($path.'.tmp', $path);
};
$wait = function (string $event) use ($directory): void {
    $deadline = microtime(true) + 15;
    while (! is_file($directory.'/'.$event.'.json')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('OAuth SQL barrier timed out.');
        }
        usleep(10000);
    }
    $marker = json_decode(file_get_contents($directory.'/'.$event.'.json'), true, flags: JSON_THROW_ON_ERROR);
    if (($marker['run'] ?? null) !== basename($directory) || ($marker['event'] ?? null) !== $event) {
        throw new RuntimeException('Invalid OAuth barrier.');
    }
};
$publish('ready');
$wait($input['side'].'-start');
$held = false;
DB::listen(function ($event) use ($input, $publish, $wait, &$held): void {
    $table = $input['operation'] === 'token' ? 'mcp_authorization_codes' : 'mcp_admission_windows';
    if (! $held && $input['hold'] && DB::transactionLevel() > 0 && str_contains($event->sql, 'for update') && str_contains($event->sql, $table)) {
        $held = true;
        $publish('locked');
        $wait('release');
    }
});
$publish('attempting');
try {
    if ($input['operation'] === 'token') {
        $response = app(AuthorizationServer::class)->token($input['token_input']);
        // The emitted bearer never enters stdout or an artifact.
        unset($response);
    } else {
        app(Admission::class)->take($input['dimensions']);
    }
    $result = 'accepted';
    $retry = null;
} catch (OAuthError $error) {
    $result = $error->error;
    $retry = $error->retryAfter;
}
$publish('terminal');
echo json_encode(['result' => $result, 'retry_after' => $retry, 'held' => $held, 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
