<?php

use App\Services\AccountKeyService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\ApiFixtureActionExpired;
use Tests\Support\ApiProcessProtocol;
use Tests\Support\OutboxProtocolBarrier;

ini_set('zend.exception_ignore_args', '1');
$projectRoot = dirname(__DIR__, 2);
require $projectRoot.'/vendor/autoload.php';
require_once $projectRoot.'/tests/support/OutboxProtocolBarrier.php';
require_once __DIR__.'/ApiProcessProtocol.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
$directory = $input['directory'];
$worker = $input['worker'];
$write = static fn ($name, $data) => OutboxProtocolBarrier::publish($directory.'/'.$name.'-'.$worker, ['worker' => $worker, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true)] + $data);
$phase = static fn ($name, $data = []) => $write('diagnostic-'.$name, ['stage' => $name, 'helper_sha256' => hash_file('sha256', __FILE__)] + $data);
try {
    ApiProcessProtocol::validate($directory, $worker);
    if (! is_int($input['startup_deadline_ns'] ?? null) || ! is_int($input['control_deadline_ns'] ?? null)
        || $input['control_deadline_ns'] <= $input['startup_deadline_ns']
        || ! is_int($input['diagnostic_ready_delay_ms'] ?? null)
        || ! is_int($input['fixture_action_delay_ms'] ?? null)
        || $input['fixture_action_delay_ms'] < 0 || $input['fixture_action_delay_ms'] > 5000) {
        throw new RuntimeException('Owned API protocol input is invalid.');
    }
    $phase('capture-start');
    ApiProcessProtocol::capture($directory, $worker, $input['startup_deadline_ns'], $projectRoot.'/tests/support/runtime-source-inventory.php');
    $phase('capture-finished');
    $phase('delay-start', ['delay_ms' => $input['diagnostic_ready_delay_ms']]);
    ApiProcessProtocol::delay($directory, $worker, $input['diagnostic_ready_delay_ms'], $input['startup_deadline_ns']);
    $phase('delay-finished');
    ApiProcessProtocol::assertNotCancelled($directory, $worker);
    $phase('ready-about-to-publish');
    $write('ready', ['stage' => 'ready', 'helper_sha256' => hash_file('sha256', __FILE__)]);
    $go = ApiProcessProtocol::awaitSignal($directory, $worker, 'go', $input['control_deadline_ns']);
    if (! is_int($go['action_deadline_ns'] ?? null) || $go['action_deadline_ns'] > $input['control_deadline_ns']) {
        throw new RuntimeException('Owned API action deadline is invalid.');
    }
    // Enforce the launch deadline in the child even when the parent is not polling.
    ApiProcessProtocol::delay($directory, $worker, $input['fixture_action_delay_ms'], $go['action_deadline_ns'], true);
    ApiProcessProtocol::checkDeadline($go['action_deadline_ns'], true);
    ApiProcessProtocol::assertNotCancelled($directory, $worker);
    $request = Request::create($input['url'], $input['method'], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$input['secret'], 'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $input['idempotency']], $input['body']);
    $operation = function () use ($app, $request, $input, $write, $directory, $worker) {
        ApiProcessProtocol::assertNotCancelled($directory, $worker);
        $kernel = $app->make(HttpKernel::class);
        $write('attempting', ['stage' => 'attempting']);
        $response = $kernel->handle($request);
        $result = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'replayed' => $response->headers->get('Idempotency-Replayed')];
        if (is_array($result['body'])) {
            unset($result['body']['secret']);
        }
        if ($input['hold'] ?? false) {
            $write('held', ['stage' => 'held', 'status' => $result['status']]);
            ApiProcessProtocol::awaitSignal($directory, $worker, 'release', $input['control_deadline_ns']);
        }
        $kernel->terminate($request, $response);

        return $result;
    };
    if ($input['hold'] ?? false) {
        $service = $app->make(AccountKeyService::class);
        $result = $service->operation($service->candidate($request), [], fn () => $operation());
    } else {
        $result = $operation();
    }
    $phase('operation-committed', ['status' => $result['status']]);
    $write('result', $result);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $error) {
    $write('failure', [
        'exception_class' => get_class($error),
        'helper_sha256' => hash_file('sha256', __FILE__),
        'protocol_error' => $error instanceof ApiFixtureActionExpired ? 'action_deadline_expired' : null,
    ]);
    fwrite(STDERR, "API fixture failed; inspect sanitized evidence.\n");
    exit(42);
}
