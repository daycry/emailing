<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

// Temporary QA-only server: environment is scoped to this process and its child.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.qa');
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
TestingDatabaseGuard::assertSafe();
if (config('emailing.registration_enabled')) {
    throw new RuntimeException('Registration must be disabled');
}
$kernel->call('serve', ['--host' => '127.0.0.1', '--port' => 8084, '--env' => 'qa', '--no-reload' => true]);
