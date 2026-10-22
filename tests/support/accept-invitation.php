<?php

use App\Services\TeamService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
auth()->loginUsingId($input['user_id']);
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 10;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrent test barrier timed out.');
    }
    usleep(10000);
}
try {
    app(TeamService::class)->accept($input['token'], auth()->user());
    echo 'accepted';
} catch (HttpException $error) {
    echo 'rejected:'.$error->getStatusCode();
}
