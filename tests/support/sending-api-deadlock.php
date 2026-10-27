<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$directory = $input['directory'];
$worker = $input['worker'];
$write = static fn ($name, $facts) => OutboxProtocolBarrier::publish($directory.'/'.$name.'-'.$worker, ['worker' => $worker, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true)] + $facts);
try {
    ApiProcessProtocol::validate($directory, $worker);
    if (! is_int($input['startup_deadline_ns'] ?? null) || ! is_int($input['control_deadline_ns'] ?? null)) {
        throw new RuntimeException('Owned deadlock protocol input is invalid.');
    }
    ApiProcessProtocol::capture($directory, $worker, $input['startup_deadline_ns'], $projectRoot.'/tests/support/runtime-source-inventory.php');
    if ($input['account_id'] === $input['foreign_account_id'] || ! DB::table('accounts')->where('id', $input['account_id'])->where('name', 'like', 'API fixture %')->exists()
        || ! DB::table('accounts')->where('id', $input['foreign_account_id'])->where('name', 'like', 'Foreign fixture %')->exists()) {
        throw new RuntimeException('Owned deadlock fixture identity mismatch.');
    }
    $write('ready', ['stage' => 'ready', 'helper_sha256' => hash_file('sha256', __FILE__)]);
    ApiProcessProtocol::awaitSignal($directory, $worker, 'go', $input['control_deadline_ns']);
    DB::transaction(function () use ($input, $directory, $worker, $write) {
        ApiProcessProtocol::assertNotCancelled($directory, $worker);
        $changed = DB::table('contacts')->where('account_id', $input['foreign_account_id'])->where('name', 'Deadlock fixture')->update(['name' => 'Weighted deadlock fixture']);
        if ($changed !== 1000) {
            throw new RuntimeException('Deadlock fixture weight mismatch.');
        }
        $write('foreign-rows-locked', ['changed_owned_rows' => $changed]);
        ApiProcessProtocol::awaitSignal($directory, $worker, 'attempt-account', $input['control_deadline_ns']);
        DB::table('accounts')->where('id', $input['account_id'])->lockForUpdate()->firstOrFail();
        $write('account-acquired', ['stage' => 'account-acquired']);
    });
    $result = ['status' => 0, 'body' => ['fixture_transaction_committed' => true, 'changed_owned_rows' => 1000]];
    $write('result', $result);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $error) {
    $write('failure', ['exception_class' => get_class($error), 'sqlstate' => $error instanceof QueryException ? (string) $error->getCode() : null]);
    fwrite(STDERR, "Owned deadlock fixture failed; see sanitized evidence.\n");
    exit(42);
}
