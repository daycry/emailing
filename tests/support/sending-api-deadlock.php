<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\OutboxProtocolBarrier;

ini_set('zend.exception_ignore_args', '1');
require __DIR__.'/../../vendor/autoload.php';
require_once __DIR__.'/OutboxProtocolBarrier.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$input = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
$directory = $input['directory'];
$worker = $input['worker'];
$capture = require __DIR__.'/runtime-inventory-capture.php';
$capture($directory, $worker);
$write = static fn ($name, $facts) => OutboxProtocolBarrier::publish($directory.'/'.$name.'-'.$worker, ['worker' => $worker, 'pid' => getmypid(), 'monotonic_ns' => hrtime(true)] + $facts);
try {
    if ($input['account_id'] === $input['foreign_account_id'] || ! DB::table('accounts')->where('id', $input['account_id'])->where('name', 'like', 'API fixture %')->exists()
        || ! DB::table('accounts')->where('id', $input['foreign_account_id'])->where('name', 'like', 'Foreign fixture %')->exists()) {
        throw new RuntimeException('Owned deadlock fixture identity mismatch.');
    }
    $write('ready', ['stage' => 'ready']);
    OutboxProtocolBarrier::await($directory.'/go-'.$worker, fn ($row) => ($row['worker'] ?? null) === $worker, 90);
    DB::transaction(function () use ($input, $directory, $worker, $write) {
        // Deliberately invert the production lock order in this isolated SQL
        // fixture. Weight this transaction so the HTTP transaction is the victim.
        $changed = DB::table('contacts')->where('account_id', $input['foreign_account_id'])->where('name', 'Deadlock fixture')->update(['name' => 'Weighted deadlock fixture']);
        if ($changed !== 1000) {
            throw new RuntimeException('Deadlock fixture weight mismatch.');
        }
        $write('foreign-rows-locked', ['changed_owned_rows' => $changed]);
        OutboxProtocolBarrier::await($directory.'/attempt-account-'.$worker, fn ($row) => ($row['worker'] ?? null) === $worker, 90);
        DB::table('accounts')->where('id', $input['account_id'])->lockForUpdate()->firstOrFail();
        $write('account-acquired', ['stage' => 'account-acquired']);
    });
    $result = ['status' => 0, 'body' => ['fixture_transaction_committed' => true, 'changed_owned_rows' => 1000]];
    $write('result', $result);
    echo json_encode($result, JSON_THROW_ON_ERROR);
    exit(0);
} catch (Throwable $error) {
    $write('failure', ['exception_class' => get_class($error), 'sqlstate' => $error instanceof \Illuminate\Database\QueryException ? (string) $error->getCode() : null]);
    fwrite(STDERR, "Owned deadlock fixture failed; see sanitized evidence.\n");
    exit(42);
}
