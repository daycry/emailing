<?php

use App\Models\Account;
use App\Services\CampaignService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$account = Account::findOrFail($input['account_id']);
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 15;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Campaign action barrier timed out.');
    }
    usleep(10000);
}
if (isset($input['attempting'])) {
    file_put_contents($input['attempting'], 'attempting');
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
