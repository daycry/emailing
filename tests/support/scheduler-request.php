<?php

// Only a guarded test process reads this stdin fixture; this is not a route.
use App\Http\Controllers\SchedulerTriggerController;
use App\Http\Middleware\CronicleToken;
use App\Services\DeliveryMaterializer;
use App\Services\SchedulerActions;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\OutboxProtocolBarrier;

require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR);
$directory = realpath($input['directory']);
if (! $directory || ! str_starts_with($directory, realpath(__DIR__.'/../../storage/phase3-t15/runtime').DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Own scheduler test directory required.');
}
config(['cronicle.secret' => str_repeat('a', 64)]);
$loaded = [];
foreach ([SchedulerActions::class, DeliveryMaterializer::class, CronicleToken::class, SchedulerTriggerController::class] as $class) {
    $source = (new ReflectionClass($class))->getFileName();
    $loaded[$class] = ['origin' => $source, 'sha256' => hash_file('sha256', $source)];
}
OutboxProtocolBarrier::publish($directory.'/ready-'.$input['label'], ['pid' => getmypid(), 'platform' => PHP_OS_FAMILY, 'thread_id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id, 'loaded' => $loaded, 'helper_sha256' => hash_file('sha256', __FILE__)]);
OutboxProtocolBarrier::await($directory.'/start', fn ($data) => $data === ['token' => $input['token']], 60);
$request = Request::create('/internal/cronicle/trigger', 'POST', server: ['HTTP_AUTHORIZATION' => 'Bearer '.str_repeat('a', 64), 'CONTENT_TYPE' => 'application/json'], content: '{"action":"materialize"}');
$response = $kernel->handle($request);
OutboxProtocolBarrier::publish($directory.'/completed-'.$input['label'], ['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR), 'utc' => gmdate('c'), 'monotonic_ns' => hrtime(true)]);
$kernel->terminate($request, $response);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true, 16, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);
