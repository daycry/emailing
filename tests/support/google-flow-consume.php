<?php

use App\Services\Auth\GoogleLoginFlow;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 12;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Flow barrier timed out.');
    }
    usleep(10000);
}
try {
    app(GoogleLoginFlow::class)->consume($input['state'], $input['binding'], $input['code'], $input['actor_id'] ?? null);
    echo 'accepted';
} catch (ValidationException) {
    echo 'rejected';
}
