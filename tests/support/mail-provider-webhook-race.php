<?php

use App\Services\MailProviders\ProviderWebhookService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

// Child of the bounded testing tree. Never reachable from application routes.
ini_set('zend.exception_ignore_args', '1');
try {
    if (fgets(STDIN, 16) !== "GATE\n" || getenv('APP_ENV') !== 'testing') {
        exit(2);
    }
    require __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestingDatabaseGuard::assertSafe();
    $file = realpath($argv[1] ?? '');
    $root = realpath(__DIR__.'/../.runtime');
    if (! $file || ! $root || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || basename($file) !== 'event.private.json') {
        exit(2);
    }
    $input = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    app(ProviderWebhookService::class)->consume('brevo', $input['connection'], $input['body'], ['authorization' => $input['authorization']]);
    echo 'ok';
} catch (Throwable) {
    echo 'refused';
    exit(1);
}
