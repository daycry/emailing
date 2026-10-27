<?php

use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Services\CampaignService;
use App\Services\ConfirmedDeliveryPublisher;
use App\Services\DeliveryMaterializer;
use App\Services\OutboxDispatcher;
use App\Services\PublicationObserver;
use App\Support\TestingDatabaseGuard;
use Composer\InstalledVersions;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\OutboxProtocolBarrier;

$progress = static function (string $stage, array $facts = []): void {
    if ($path = getenv('T12_PROGRESS')) {
        file_put_contents($path, json_encode(['stage' => $stage, 'utc' => gmdate('c')] + $facts, JSON_THROW_ON_ERROR)."\n", FILE_APPEND);
    }
};
$progress('fixture_start', ['platform' => PHP_OS_FAMILY, 'pid' => getmypid(), 'source_sha256' => hash_file('sha256', __FILE__)]);
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$progress('autoloaded');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$progress('bootstrapped');
TestingDatabaseGuard::assertSafe();
$progress('guarded_before_stdin');
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$progress('input_received', ['action' => $input['action']]);
if ($input['action'] === 'barrier-consumer') {
    file_put_contents($input['ready'], 'ready');
    $data = OutboxProtocolBarrier::await($input['barrier'], fn ($data) => $data === $input['expected'], 10);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit(0);
}
if ($input['action'] === 'crash-after-confirm') {
    $directory = dirname($input['confirmed']);
    if (! preg_match('/^outbox-crash-[0-9a-f-]{36}$/D', basename($directory))
        || realpath($directory) === false || is_link($directory)) {
        throw new RuntimeException('Controlled dispatcher directory is invalid.');
    }
    $identity = ['fixture_id' => basename($directory), 'event_id' => $input['event_id'],
        'generation' => $input['generation'], 'helper_sha256' => hash_file('sha256', __FILE__)];
    $checkAbort = static function () use ($directory): void {
        clearstatcache(true, $directory.'/abort');
        if (is_file($directory.'/abort')) {
            throw new RuntimeException('Controlled dispatcher was cancelled.');
        }
    };
    $delay = static function (int $milliseconds, int $deadline, bool $action = false) use ($directory, $identity, $checkAbort): void {
        if ($milliseconds < 0 || $milliseconds > 90000) {
            throw new RuntimeException('Controlled dispatcher delay is invalid.');
        }
        $finish = hrtime(true) + $milliseconds * 1000000;
        do {
            $checkAbort();
            if (hrtime(true) >= $deadline) {
                if ($action) {
                    OutboxProtocolBarrier::publish($directory.'/action-expired', ['stage' => 'action-expired'] + $identity);
                }
                throw new RuntimeException('Controlled dispatcher phase expired.');
            }
            if (hrtime(true) >= $finish) {
                return;
            }
            usleep(10000);
        } while (true);
    };
    $preparationStart = hrtime(true);
    $preparationDeadline = $preparationStart + 180 * 1000000000;
    $checkAbort();
    OutboxProtocolBarrier::publish($directory.'/preparing', ['stage' => 'preparing'] + $identity);
    $delay($input['preparation_delay_ms'] ?? 0, $preparationDeadline);
    $captureRuntime = require __DIR__.'/runtime-inventory-capture.php';
    $captureRuntime($directory, 'dispatcher');
    $delay(0, $preparationDeadline);
    $inventoryBytes = file_get_contents($directory.'/runtime-dispatcher.json');
    $inventory = json_decode($inventoryBytes, true, 512, JSON_THROW_ON_ERROR);
    $sourceHashes = [];
    foreach ([DeliveryMaterializer::class, OutboxDispatcher::class, PublicationObserver::class, ConfirmedDeliveryPublisher::class, DeliveryOutbox::class, Delivery::class, CampaignService::class] as $class) {
        $sourceHashes[$class] = hash_file('sha256', (new ReflectionClass($class))->getFileName());
    }
    $progress('loaded_sources', ['hashes' => $sourceHashes, 'composer_lock_sha256' => hash_file('sha256', base_path('composer.lock')), 'amqp_version' => InstalledVersions::getPrettyVersion('php-amqplib/php-amqplib')]);
    $readyAt = hrtime(true);
    $prepared = ['stage' => 'prepared'] + $identity + ['ready_monotonic_ns' => $readyAt,
        'preparation_elapsed_ns' => $readyAt - $preparationStart,
        'preparation_delay_ms' => $input['preparation_delay_ms'] ?? 0,
        'action_delay_ms' => $input['action_delay_ms'] ?? 0,
        'inventory_sha256' => hash('sha256', $inventoryBytes), 'inventory_files' => count($inventory['files'])];
    $checkAbort();
    OutboxProtocolBarrier::publish($directory.'/prepared', $prepared);
    $go = OutboxProtocolBarrier::await($directory.'/dispatch', static function (array $candidate) use ($prepared): bool {
        if (($candidate['stage'] ?? null) !== 'dispatch' || ! is_int($candidate['action_budget_seconds'] ?? null)
            || $candidate['action_budget_seconds'] < 1 || $candidate['action_budget_seconds'] > 40) {
            return false;
        }
        unset($candidate['action_budget_seconds']);
        $candidate['stage'] = 'prepared';

        return $candidate === $prepared;
    }, 40, static function () use ($directory): bool {
        clearstatcache(true, $directory.'/abort');

        return ! is_file($directory.'/abort');
    });
    // Parent carries the child's ready timestamp back unchanged. No comparison
    // between Windows and Linux clocks, and an old go cannot restart its budget.
    $actionDeadline = $readyAt + $go['action_budget_seconds'] * 1000000000;
    $delay($input['action_delay_ms'] ?? 0, $actionDeadline, true);
    $app->instance(PublicationObserver::class, new class($input, $progress) extends PublicationObserver
    {
        private array $stages = [];

        public function __construct(private array $input, private Closure $progress) {}

        public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
        {
            $this->stages[] = $stage;
            ($this->progress)($stage, ['event_id' => $event->id]);
            if ($stage === 'confirmed' && $event->id === $this->input['event_id']) {
                OutboxProtocolBarrier::publish($this->input['confirmed'], ['stage' => $stage, 'barriers' => $this->stages, 'event_id' => $event->id, 'generation' => $event->generation, 'pid' => getmypid()]);
                // Parent kills exactly this owned Linux process/container at this barrier.
                while (true) {
                    usleep(10000);
                }
            }
        }
    });
    config(['delivery.exchange' => $input['exchange'], 'delivery.queue' => $input['queue']]);
    $delay(0, $actionDeadline, true);
    $progress('before_dispatch', ['event_id' => $input['event_id']]);
    $app->make(OutboxDispatcher::class)->dispatch($input['event_id']);
    $progress('dispatch_returned');
    exit(0);
}
file_put_contents($input['ready'], 'ready');
$deadline = microtime(true) + 20;
while (! is_file($input['start'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Materializer barrier timed out.');
    }
    usleep(10000);
}
if ($input['action'] === 'claim-global') {
    $claim = $app->make(OutboxDispatcher::class)->claim();
    if (! $claim || $claim->account_id !== $input['account_id']) {
        throw new RuntimeException('Global claim must belong to the owned fixture.');
    }
    echo json_encode(['event_id' => $claim->id, 'generation' => $claim->generation, 'lease_token' => $claim->lease_token], JSON_THROW_ON_ERROR);
} else {
    echo $app->make(DeliveryMaterializer::class)->materialize($input['account_id'], $input['campaign_id']);
}
