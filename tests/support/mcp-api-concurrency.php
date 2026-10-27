<?php

// Owned HTTP/MySQL concurrency child; inputs remain in stdin, never evidence files.
use App\Services\Mcp\GrantService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\OutboxProtocolBarrier;

ini_set('zend.exception_ignore_args', '1');
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
$input = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
$directory = realpath($input['directory']);
$root = realpath(storage_path('app/private'));
$side = $input['side'];
if (! $root || ! $directory || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR)
    || ! preg_match('/^mcp-mail-race-[a-f0-9-]{36}$/D', basename($directory))
    || ! in_array($side, ['0', '1'], true) || ! in_array($input['operation'], ['api', 'mcp', 'revoke'], true)) {
    throw new RuntimeException('Unowned mail concurrency fixture.');
}
$write = static fn (string $event, array $data = []) => OutboxProtocolBarrier::publish($directory.'/'.$side.'-'.$event.'.json',
    ['run' => basename($directory), 'side' => $side, 'event' => $event, 'monotonic_ns' => hrtime(true)] + $data);
$wait = static fn (string $event) => OutboxProtocolBarrier::await($directory.'/'.$event.'.json',
    static fn (array $data): bool => ($data['run'] ?? null) === basename($directory) && ($data['event'] ?? null) === $event, 90);
$held = false;
$beforeLock = false;
DB::connection()->beforeExecuting(function (string $sql) use (&$beforeLock, $write): void {
    if (! $beforeLock && str_contains(strtolower($sql), 'for update') && preg_match('/\bfrom\s+[`"]?accounts[`"]?\b/i', $sql)) {
        $beforeLock = true;
        $write('before-account-lock');
    }
});
DB::listen(function ($event) use ($input, $write, $wait, &$held): void {
    $sql = strtolower($event->sql);
    $acceptedWork = str_starts_with($sql, 'insert into `delivery_outbox`');
    $revocation = $input['operation'] === 'revoke' && str_starts_with($sql, 'update `mcp_grants`') && str_contains($sql, '`revoked_at`');
    if (! $held && $input['hold'] && DB::transactionLevel() > 0 && ($acceptedWork || $revocation)) {
        $held = true;
        $write('held', ['boundary' => $revocation ? 'revocation-before-commit' : 'outbox-before-commit']);
        $wait('release');
    }
});
try {
    $write('ready');
    $wait($side.'-start');
    $write('attempting');
    if ($input['operation'] === 'revoke') {
        app(GrantService::class)->revoke($input['grant_id'], $input['actor_id']);
        $result = ['status' => 204, 'request' => null, 'replayed' => null, 'error_code' => null];
    } else {
        $mcp = $input['operation'] === 'mcp';
        $params = ['name' => 'mail_send', 'arguments' => ['idempotency_key' => $input['idempotency'], 'message' => $input['message']],
            '_meta' => ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []]];
        $body = $mcp ? ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => $params] : $input['message'];
        $headers = ['HTTP_AUTHORIZATION' => 'Bearer '.$input['secret'], 'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json, text/event-stream', 'HTTP_IDEMPOTENCY_KEY' => $input['idempotency']];
        if ($mcp) {
            $headers += ['HTTP_MCP_PROTOCOL_VERSION' => '2026-07-28', 'HTTP_MCP_METHOD' => 'tools/call', 'HTTP_MCP_NAME' => 'mail_send'];
        }
        $request = Request::create($mcp ? '/mcp' : '/api/v1/mail/send', 'POST', server: $headers,
            content: json_encode($body, JSON_THROW_ON_ERROR));
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($request);
        $decoded = json_decode($response->getContent(), true, 64, JSON_THROW_ON_ERROR);
        $dto = $mcp ? ($decoded['result']['structuredContent']['data'] ?? null) : null;
        $error = $mcp && ($decoded['result']['isError'] ?? false)
            ? json_decode($decoded['result']['content'][0]['text'], true, 32, JSON_THROW_ON_ERROR) : null;
        // Deliberately retain only the public receipt, status and closed error code.
        // No bearer, request body, headers or framework debug response is persisted.
        $receipt = $mcp ? ($dto['request'] ?? null) : ($response->getStatusCode() === 202 ? $decoded : null);
        if ($receipt !== null) {
            if (! is_array($receipt) || array_diff(array_keys($receipt), ['request_id', 'delivery_ids', 'accepted_at', 'state']) !== []) {
                throw new RuntimeException('Unexpected receipt shape.');
            }
            $receipt = array_intersect_key($receipt, array_flip(['request_id', 'delivery_ids', 'accepted_at', 'state']));
        }
        $result = ['status' => $response->getStatusCode(), 'request' => $receipt,
            'replayed' => $mcp ? ($dto['replayed'] ?? null) : $response->headers->get('Idempotency-Replayed') === 'true',
            'error_code' => $mcp ? ($error['error']['code'] ?? $decoded['error']['code'] ?? null) : ($decoded['error']['code'] ?? null)];
        $kernel->terminate($request, $response);
    }
    $result += ['held' => $held, 'transaction_level' => DB::transactionLevel()];
    $write('terminal', $result);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $error) {
    // Preserve the failed process; do not turn a fixture exception into a success.
    $write('failure', ['exception_class' => get_class($error), 'transaction_level' => DB::transactionLevel()]);
    fwrite(STDERR, "Mail concurrency fixture failed; inspect sanitized evidence.\n");
    exit(42);
}
