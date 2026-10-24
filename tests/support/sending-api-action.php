<?php

use App\Services\AccountKeyService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Tests\Support\OutboxProtocolBarrier;

ini_set('zend.exception_ignore_args', '1');
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
$directory = $input['directory'];
$worker = $input['worker'];
$capture = require __DIR__.'/runtime-inventory-capture.php';
$capture($directory, $worker);
$write = static fn ($name, $data) => OutboxProtocolBarrier::publish($directory.'/'.$name.'-'.$worker, ['worker' => $worker, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true)] + $data);
try {
    $write('ready', ['stage' => 'ready']);
    OutboxProtocolBarrier::await($directory.'/go-'.$worker, fn ($data) => ($data['worker'] ?? null) === $worker, 90);
    $request = Request::create($input['url'], $input['method'], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$input['secret'], 'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $input['idempotency']], $input['body']);
    $operation = function () use ($app, $request, $input, $write, $directory, $worker) {
        $kernel = $app->make(HttpKernel::class);
        $write('attempting', ['stage' => 'attempting']);
        $response = $kernel->handle($request);
        $result = ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true), 'replayed' => $response->headers->get('Idempotency-Replayed')];
        if (is_array($result['body'])) {
            unset($result['body']['secret']);
        }
        if ($input['hold'] ?? false) {
            $write('held', ['stage' => 'held', 'status' => $result['status']]);
            OutboxProtocolBarrier::await($directory.'/release-'.$worker, fn ($data) => ($data['worker'] ?? null) === $worker, 90);
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
    $write('result', $result);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $error) {
    $write('failure', ['exception_class' => get_class($error)]);
    fwrite(STDERR, "API fixture failed; inspect sanitized evidence.\n");
    exit(42);
}
