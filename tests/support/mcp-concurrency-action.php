<?php

use App\Services\Mcp\Concurrency;
use App\Services\Mcp\ProtocolError;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, 8, JSON_THROW_ON_ERROR);
$root = realpath(storage_path('app/private'));
$directory = realpath($input['directory']);
if (! $root || ! $directory || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR) || ! preg_match('/\Amcp-transport-[a-f0-9-]{36}\z/D', basename($directory))) {
    throw new RuntimeException('Unowned concurrency barrier');
}
config(['mcp.concurrency_per_account' => 1]);
try {
    app(Concurrency::class)->run((int) $input['account'], function () use ($input, $directory): void {
        if ($input['action'] === 'hold') {
            file_put_contents($directory.'/locked.json.tmp', '{"locked":true}');
            rename($directory.'/locked.json.tmp', $directory.'/locked.json');
            $deadline = microtime(true) + 20;
            while (! is_file($directory.'/release.json')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Concurrency barrier timed out');
                }
                usleep(10000);
            }
        }
    });
    echo json_encode(['status' => 200, 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
} catch (ProtocolError $error) {
    echo json_encode(['status' => $error->status, 'transaction_level' => DB::transactionLevel()], JSON_THROW_ON_ERROR);
}
