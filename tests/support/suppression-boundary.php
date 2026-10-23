<?php

use App\Models\Account;
use App\Services\AccountMailTransport;
use App\Services\SuppressionService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$account = Account::findOrFail($input['account_id']);
file_put_contents($input['directory'].'/ready', 'ready');
$deadline = microtime(true) + 10;
while (! is_file($input['directory'].'/start')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Boundary start timed out.');
    }
    usleep(10000);
}
file_put_contents($input['directory'].'/attempting', 'attempting');
if ($input['action'] === 'send') {
    config(['filesystems.disks.mail-artifacts.root' => $input['directory'].'/artifacts']);
    $record = app(AccountMailTransport::class)->send($account, $input['sender_id'], ['recipient' => 'recipient@example.test', 'subject' => 'Prueba de orden', 'text' => 'Laboratorio']);
    echo json_encode(['status' => $record->status, 'started' => $record->transport_started_at !== null]);
} else {
    app(SuppressionService::class)->suppress($account, $input['actor_id'], ['email' => 'recipient@example.test']);
    echo json_encode(['suppressed' => true]);
}
