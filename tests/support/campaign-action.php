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
$readyDelay = $input['fixture_ready_delay_ms'] ?? 0;
$holdAfterStart = $input['fixture_hold_after_start'] ?? false;
if (! is_int($readyDelay) || $readyDelay < 0 || $readyDelay > 20_000 || ! is_bool($holdAfterStart)) {
    throw new InvalidArgumentException('Owned campaign fixture controls are outside their finite budgets.');
}
if ($readyDelay > 0) {
    usleep($readyDelay * 1000);
}
$account = Account::findOrFail($input['account_id']);
$thread = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
$deadline = $input['readiness_deadline_ns'] ?? (hrtime(true) + 15_000_000_000);
$now = hrtime(true);
if (! is_int($deadline) || $deadline <= $now || $deadline > $now + 60_000_000_000) {
    throw new RuntimeException('Owned campaign readiness deadline is invalid or expired.');
}
$write($input['ready'], ['stage' => 'ready', 'connection_id' => $thread]);
while (! is_file($input['start'])) {
    if (hrtime(true) > $deadline) {
        throw new RuntimeException('Campaign action barrier timed out.');
    }
    usleep(10000);
}
if (isset($input['attempting'])) {
    $write($input['attempting'], ['stage' => 'attempting', 'connection_id' => $thread]);
}
if ($holdAfterStart) {
    $holdDeadline = hrtime(true) + 10_000_000_000;
    while (! is_file($input['start'].'.work-release')) {
        if (hrtime(true) > $holdDeadline) {
            throw new RuntimeException('Owned campaign fixture action hold timed out.');
        }
        usleep(10000);
    }
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
