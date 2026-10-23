<?php

use App\Models\Account;
use App\Services\CampaignService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$write = static function (string $file, array $facts): void {
    $temporary = $file.'.tmp';
    file_put_contents($temporary, json_encode(['pid' => getmypid(), 'monotonic_ns' => hrtime(true), 'at_utc' => gmdate('c'), 'helper_sha256' => hash_file('sha256', __FILE__)] + $facts, JSON_THROW_ON_ERROR));
    if (! rename($temporary, $file)) {
        throw new RuntimeException('Owned campaign fixture marker could not be committed.');
    }
};
if (isset($input['loaded'])) {
    $write($input['loaded'], ['stage' => 'loaded']);
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$account = Account::findOrFail($input['account_id']);
$thread = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
$write($input['ready'], ['stage' => 'ready', 'connection_id' => $thread]);
$deadline = microtime(true) + 15;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Campaign action barrier timed out.');
    }
    usleep(10000);
}
if (isset($input['attempting'])) {
    $write($input['attempting'], ['stage' => 'attempting', 'connection_id' => $thread]);
}
try {
    $service = app(CampaignService::class);
    $result = match ($input['action']) {
        'authorize' => $service->authorizeCampaign($account, $input['actor_id'], $input['id'], $input['version'], $input['revision_id'], true),
        'save' => $service->save($account, $input['actor_id'], $input['id'], $input['version'], $input['input']),
        'pause' => $service->pause($account, $input['actor_id'], $input['id'], $input['version']),
        default => throw new InvalidArgumentException('Unsupported campaign test action.'),
    };
    echo $result->status;
} catch (HttpException $error) {
    echo 'rejected:'.$error->getStatusCode();
}
