<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Read-only QA checks: no FastCGI payload, migrations, credential output or SMTP mail.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();

function check(bool $condition, string $label): void
{
    if (! $condition) {
        throw new RuntimeException('FAIL: '.$label);
    }
    echo 'PASS: '.$label.PHP_EOL;
}

$labIp = $argv[1] ?? null;
if ($labIp) {
    $connection = @fsockopen($labIp, 9000, $error, $message, 3);
    check($connection === false, 'FPM TCP 9000 unreachable through laboratory network');
    check(! file_exists('/run/emailing-fpm/php.sock'), 'worker has no shared FPM socket');
} else {
    $socket = @stream_socket_client('unix:///run/emailing-fpm/php.sock', $error, $message, 3);
    check($socket !== false, 'local FPM Unix socket reachable');
    fclose($socket);
    $tcp = @fsockopen('127.0.0.1', 9000, $error, $message, 3);
    check($tcp === false, 'FPM TCP 9000 closed on loopback');
    check(posix_geteuid() !== 0, 'PHP runtime is non-root');
}

check(DB::selectOne('SELECT DATABASE() AS name')->name === 'emailing_testing', 'exclusive testing MySQL reachable');
$smtp = @fsockopen(config('mail.mailers.smtp.host'), (int) config('mail.mailers.smtp.port'), $error, $message, 3);
check($smtp !== false, 'SMTP reachable without sending mail');
fclose($smtp);
check(config('queue.connections.rabbitmq.queue') === 'emailing.qa.jobs', 'Docker QA uses its independent effective queue');
