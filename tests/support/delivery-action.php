<?php

use App\Models\Delivery;
use App\Services\AccountMailTransport;
use App\Services\DeliveryConsumer;
use App\Services\DeliveryEnvelope;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryObserver;
use App\Services\DeliveryQuota;
use App\Services\ObservedSmtpTransport;
use App\Services\SmtpConnector;
use App\Services\SmtpProtocolObserver;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\OutboxProtocolBarrier;

ini_set('zend.exception_ignore_args', '1');
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
config($input['config']);
TestingDatabaseGuard::assertSafe();
$directory = $input['directory'];
$captureRuntime = require __DIR__.'/runtime-inventory-capture.php';
$captureRuntime($directory, $input['worker']);
$write = static function (string $name, array $facts) use ($directory): void {
    OutboxProtocolBarrier::publish($directory.'/'.$name, $facts);
};
$hashes = [];
foreach ([DeliveryConsumer::class, DeliveryLifecycle::class, AccountMailTransport::class, SmtpConnector::class, ObservedSmtpTransport::class, DeliveryQuota::class, DeliveryEnvelope::class, SmtpProtocolObserver::class] as $class) {
    $hashes[$class] = hash_file('sha256', (new ReflectionClass($class))->getFileName());
}
$write('loaded-'.$input['worker'], ['platform' => PHP_OS_FAMILY, 'pid' => getmypid(), 'hashes' => $hashes, 'monotonic_ns' => hrtime(true), 'at_utc' => gmdate('c')]);
if ($input['bootstrap_gate'] ?? false) {
    OutboxProtocolBarrier::await($directory.'/bootstrap-release-'.$input['worker'], fn ($data) => ($data['worker'] ?? null) === $input['worker'], $input['bootstrap_gate_seconds'] ?? 90);
}

$app->instance(SmtpProtocolObserver::class, new class($input) extends SmtpProtocolObserver
{
    public function __construct(private array $input) {}

    public function observe(string $stage, array $facts): void
    {
        file_put_contents($this->input['directory'].'/smtp-'.$this->input['worker'].'.jsonl', json_encode(['stage' => $stage, 'monotonic_ns' => hrtime(true)] + $facts, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    }
});
if (($input['action'] ?? null) === 'lock-pool') {
    DB::transaction(function () use ($input, $write) {
        $epoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
        $bucket = intdiv($epoch, 60) * 60;
        $key = 'pool:'.config('delivery.relay_pool').':minute:'.$bucket;
        DB::table('delivery_quota_windows')->insertOrIgnore(['id' => $key, 'used' => 0, 'ends_at' => gmdate('Y-m-d H:i:s', $bucket + 60)]);
        DB::table('delivery_quota_windows')->where('id', $key)->lockForUpdate()->firstOrFail();
        $write('pool-locked', ['pool_key' => $key, 'epoch' => $epoch, 'bucket' => $bucket, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true), 'at_utc' => gmdate('c')]);
        OutboxProtocolBarrier::await($input['directory'].'/release-lock', fn ($data) => ($data['pool_key'] ?? null) === $key || (($data['stop'] ?? false) === true && ($data['worker'] ?? null) === $input['worker']), 90);
    });
    exit(0);
}
$app->instance(DeliveryObserver::class, new class($input, $write) extends DeliveryObserver
{
    public function __construct(private array $input, private Closure $write) {}

    public function observe(string $stage, Delivery $delivery, array $facts = []): void
    {
        if ($delivery->id !== $this->input['delivery_id']) {
            throw new RuntimeException('Foreign fixture delivery.');
        }
        if ($stage === 'before_quota') {
            $facts['db_epoch'] = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
        }
        ($this->write)($stage.'-'.$this->input['worker'], ['stage' => $stage, 'delivery_id' => $delivery->id, 'generation' => $delivery->generation, 'lease_token' => $delivery->lease_token, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true)] + $facts);
        if (($this->input['pause_at'] ?? null) === $stage) {
            OutboxProtocolBarrier::await($this->input['directory'].'/release-'.$this->input['worker'], fn ($data) => ($data['delivery_id'] ?? null) === $delivery->id, $this->input['barrier_seconds'] ?? 35);
        }
    }
});
try {
    $app->make(DeliveryConsumer::class)->run(! ($input['continuous'] ?? false), 1);
    $write('terminal-'.$input['worker'], ['exit' => 0]);
    exit(0);
} catch (Throwable $error) {
    $write('terminal-'.$input['worker'], ['exit' => 42, 'exception_class' => get_class($error)]);
    fwrite(STDERR, "Controlled worker failed; see sanitized receipt.\n");
    exit(42);
}
