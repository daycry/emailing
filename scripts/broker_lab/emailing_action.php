<?php

declare(strict_types=1);

// Isolated laboratory actions; syntax/dry-run never bootstrap Laravel or open SQL.
use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\ApiMailRequest;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\DeliveryConsumer;
use App\Services\DeliveryObserver;
use App\Services\OutboxDispatcher;
use App\Services\PublicationObserver;
use App\Services\SendingApiService;
use App\Support\SendingApiInput;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity): never {
    throw new ErrorException('Owned Emailing action failed.', 0, $severity);
});
$status = 2;
$phase = 'descriptor';
$attempted = $accepted = 0;
try {
    $raw = stream_get_contents(STDIN, 16385);
    if (strlen($raw) > 16384) {
        throw new InvalidArgumentException('Descriptor exceeds its bound.');
    }
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (! is_array($input) || ($input['execute'] ?? false) !== true) {
        echo json_encode(['mode' => 'dry-run', 'sql_used' => false, 'broker_used' => false], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $allowed = ['execute', 'mode', 'run_id', 'password', 'broker_port', 'directory', 'worker', 'seconds', 'sequence', 'limit', 'rate', 'cut_at', 'quota_policy'];
    if (array_diff(array_keys($input), $allowed) !== []
        || ! in_array($input['mode'] ?? null, ['schema', 'init', 'produce', 'dispatch', 'worker', 'probe', 'cleanup'], true)
        || ! is_string($input['run_id'] ?? null)
        || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $input['run_id'])
        || ! is_string($input['password'] ?? null) || ! preg_match('/\A[0-9a-f]{64}\z/D', $input['password'])
        || ! is_int($input['broker_port'] ?? null) || $input['broker_port'] < 1 || $input['broker_port'] > 65535
        || ! is_string($input['directory'] ?? null)) {
        throw new InvalidArgumentException('Invalid owned Emailing descriptor.');
    }
    $root = dirname(__DIR__, 2);
    $run = $input['run_id'];
    $mode = $input['mode'];
    $directory = realpath($input['directory']);
    $expected = realpath($root.'/storage/broker-lab-'.$run.'/a');
    if ($expected === false || $expected !== $directory || is_link($input['directory'])) {
        throw new InvalidArgumentException('Unowned action directory refused.');
    }
    if ($mode === 'worker'
        && (! is_string($input['worker'] ?? null) || ! preg_match('/\Aa-[0-9]{1,2}\z/D', $input['worker'])
            || ! is_int($input['seconds'] ?? null) || $input['seconds'] < 1 || $input['seconds'] > 1800)) {
        throw new InvalidArgumentException('Invalid worker bounds.');
    }
    if ($mode === 'produce' && (! is_int($input['sequence'] ?? null) || $input['sequence'] < 1
        || ! is_int($input['limit'] ?? null) || $input['limit'] < 1 || $input['limit'] > 500
        || $input['sequence'] + $input['limit'] - 1 > 10000
        || ! is_int($input['rate'] ?? null) || $input['rate'] < 1 || $input['rate'] > 25)) {
        throw new InvalidArgumentException('Invalid production sequence.');
    }
    if ($mode === 'dispatch' && (! is_int($input['limit'] ?? null) || $input['limit'] < 1 || $input['limit'] > 100)) {
        throw new InvalidArgumentException('Invalid dispatch bound.');
    }
    if (in_array($mode, ['produce', 'dispatch', 'worker', 'probe'], true)) {
        $policy = $input['quota_policy'] ?? null;
        $policyKeys = is_array($policy) ? array_keys($policy) : [];
        sort($policyKeys);
        if ($policyKeys !== ['account', 'pool']) {
            throw new InvalidArgumentException('Frozen quota policy required.');
        }
        foreach (['account', 'pool'] as $scope) {
            $windowKeys = is_array($policy[$scope]) ? array_keys($policy[$scope]) : [];
            sort($windowKeys);
            if ($windowKeys !== ['day', 'minute']) {
                throw new InvalidArgumentException('Invalid frozen quota windows.');
            }
            foreach ($policy[$scope] as $limit) {
                if (! is_int($limit) || $limit < 1 || $limit > 1_000_000_000) {
                    throw new InvalidArgumentException('Invalid frozen quota value.');
                }
            }
        }
    }
    $requestedCut = null;
    if (array_key_exists('cut_at', $input)) {
        if ($mode !== 'probe' || ! is_string($input['cut_at'])
            || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/D', $input['cut_at'])) {
            throw new InvalidArgumentException('Invalid observation cut.');
        }
        $parsedCut = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.u\Z', $input['cut_at'], new DateTimeZone('UTC'));
        if ($parsedCut === false || $parsedCut->format('Y-m-d\TH:i:s.u\Z') !== $input['cut_at']) {
            throw new InvalidArgumentException('Invalid observation cut calendar.');
        }
        $requestedCut = $parsedCut->format('Y-m-d H:i:s.u');
    }
    if (getenv('APP_ENV') !== 'testing' || getenv('DB_DATABASE') !== 'emailing_testing'
        || getenv('DB_USERNAME') !== 'emailing_testing' || (getenv('DB_URL') ?: '') !== '') {
        throw new RuntimeException('Explicit testing runtime required before application bootstrap.');
    }
    $phase = 'bootstrap';
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestingDatabaseGuard::assertSafe();
    $phase = 'guarded_sql';
    if (! app()->environment('testing')) {
        throw new RuntimeException('Resolved application environment refused.');
    }
    if (in_array($mode, ['schema', 'init', 'cleanup'], true)) {
        $other = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()');
        if ((int) $other->n !== 0 || DB::transactionLevel() !== 0) {
            throw new RuntimeException('Exclusive test ownership not established.');
        }
    }
    if ($mode === 'schema') {
        $phase = 'guarded_schema';
        foreach (['users', 'accounts', 'deliveries', 'delivery_outbox', 'mail_messages', 'account_api_keys', 'api_mail_requests'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Existing test business data must be settled before schema preparation.');
            }
        }
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Guarded test schema preparation failed.');
        }
        foreach (['users', 'accounts', 'account_api_keys', 'api_mail_requests', 'deliveries', 'delivery_outbox', 'delivery_quota_windows', 'mail_provider_connections'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Incomplete guarded test schema.');
            }
        }
        echo json_encode(['mode' => 'schema', 'schema_ready' => true, 'database' => 'emailing_testing',
            'database_user' => 'emailing_testing', 'other_testing_connections' => 0, 'business_tables_empty_before_schema' => true], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $topology = 'lab.t19.'.$run.'.a';
    // Only owned topology/storage are overridden. The resolved quota policy is preserved.
    $quotas = config('delivery.quotas');
    if (in_array($mode, ['produce', 'dispatch', 'worker', 'probe'], true) && $policy != $quotas) {
        throw new RuntimeException('Resolved quota policy differs from the pre-load manifest.');
    }
    config([
        'queue.connections.rabbitmq.hosts.0' => ['host' => '127.0.0.1', 'port' => $input['broker_port'], 'user' => $topology, 'password' => $input['password'], 'vhost' => $topology],
        'queue.connections.rabbitmq.queue' => $topology.'.jobs',
        'delivery.exchange' => $topology.'.events',
        'delivery.queue' => $topology.'.ready',
        'delivery.failure_queue' => $topology.'.failed',
        'delivery.routing_key' => 'delivery.ready',
        'delivery.binding_key' => 'delivery.ready',
        'delivery.failure_routing_key' => 'delivery.failed',
        'delivery.relay_pool' => 't19-'.$run,
        'filesystems.disks.mail-artifacts.root' => $directory.'/mail-artifacts',
    ]);
    if ($quotas !== config('delivery.quotas')) {
        throw new LogicException('The action must not change quota policy.');
    }
    $email = 'broker-lab-'.$run.'@example.test';
    $name = 'Broker laboratory '.$run;
    $attribution = ['run_id' => $run, 'user_email' => $email, 'account_name' => $name,
        'database' => 'emailing_testing', 'database_user' => 'emailing_testing', 'absence_checked_before_first_write' => true];
    $attributionPath = $directory.'/sql-attribution.public.json';
    if ($mode === 'init') {
        // Complete creation is transactional; deterministic identities also attribute a partial attempt.
        if (User::where('email', $email)->exists() || Account::where('name', $name)->exists()) {
            throw new RuntimeException('Existing fixture identity refused.');
        }
        // A timeout after commit can still be reconciled; a collision never creates this receipt.
        $handle = fopen($attributionPath, 'xb');
        if ($handle === false) {
            throw new RuntimeException('Existing or unavailable SQL attribution refused.');
        }
        try {
            $bytes = json_encode($attribution, JSON_THROW_ON_ERROR)."\n";
            if (fwrite($handle, $bytes) !== strlen($bytes) || ! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('SQL attribution could not be persisted.');
            }
        } finally {
            fclose($handle);
        }
        DB::transaction(function () use ($email, $name): void {
            $user = User::create(['name' => 'Synthetic broker laboratory', 'email' => $email,
                'password' => Hash::make(Str::random(64)), 'email_verified_at' => now()]);
            $account = Account::create(['name' => $name, 'owner_id' => $user->id]);
            Membership::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'admin', 'scopes' => []]);
            MailSetting::create(['account_id' => $account->id, 'mode' => 'log']);
            MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Synthetic sender', 'lab_authorized' => true]);
            // The secret is deliberately discarded: domain load does not claim HTTP admission throughput.
            app(AccountKeyService::class)->create($account->id, ['name' => 'Synthetic load key', 'scopes' => ['mail.send']], ['mail.send'], actor: $user->id);
        }, 3);
    }
    clearstatcache(true, $attributionPath);
    if (! is_file($attributionPath) || is_link($attributionPath) || filesize($attributionPath) > 1024
        || json_decode(file_get_contents($attributionPath), true, 8, JSON_THROW_ON_ERROR) !== $attribution) {
        throw new RuntimeException('Owned SQL attribution missing or mismatched.');
    }
    $user = User::where('email', $email)->first();
    $account = $user ? Account::where('name', $name)->where('owner_id', $user->id)->first() : null;
    if ($mode === 'cleanup') {
        if ($user) {
            if ($account === null || Account::where('owner_id', $user->id)->count() !== 1) {
                throw new RuntimeException('Cleanup ownership does not match.');
            }
            DB::transaction(function () use ($account, $user, $run): void {
                DB::table('delivery_quota_windows')->where('id', 'like', 'account:'.$account->id.':%')->delete();
                DB::table('delivery_quota_windows')->where('id', 'like', 'pool:t19-'.$run.':%')->delete();
                $account->delete();
                $user->delete();
            }, 3);
        }
        if (User::where('email', $email)->exists() || Account::where('name', $name)->exists()) {
            throw new RuntimeException('Fixture SQL cleanup not complete.');
        }
        $result = ['mode' => 'cleanup', 'owned_sql_absent' => true, 'private_evidence_retained' => true];
    } else {
        if ($account === null || $user === null) {
            throw new RuntimeException('Owned fixture identity missing.');
        }
        if (MailSetting::where('account_id', $account->id)->sole()->mode !== 'log') {
            throw new RuntimeException('This load action only permits the log transport.');
        }
        $result = ['mode' => $mode, 'account_id' => $account->id, 'run_id' => $run, 'transport' => 'log', 'quotas' => $quotas];
        if ($mode === 'produce') {
            $key = AccountApiKey::where('account_id', $account->id)->sole();
            $sender = MailSender::where('account_id', $account->id)->sole();
            $data = SendingApiInput::send((object) ['sender_id' => $sender->id,
                'recipients' => [(object) ['email' => 'recipient@example.test']],
                'content' => (object) ['type' => 'inline', 'subject' => 'Synthetic broker load', 'text' => str_repeat('x', 2048)]]);
            $next = hrtime(true);
            for ($offset = 0; $offset < $input['limit']; $offset++) {
                $sequence = $input['sequence'] + $offset;
                while (($wait = $next - hrtime(true)) > 0) {
                    usleep(max(1, (int) ceil($wait / 1000)));
                }
                $at = hrtime(true);
                $attempted++;
                echo json_encode(['phase' => 'attempt', 'sequence' => $sequence, 'begin_ns' => $at], JSON_THROW_ON_ERROR)."\n";
                flush();
                [$receipt, $replayed] = app(AccountKeyService::class)->operation($key, ['mail.send'],
                    fn ($current) => app(SendingApiService::class)->accept($current, 't19:'.$run.':'.$sequence, $data));
                $accepted++;
                echo json_encode(['phase' => 'accepted', 'sequence' => $sequence, 'replayed' => $replayed, 'request_id' => $receipt['request_id'],
                    'delivery_ids' => $receipt['delivery_ids'], 'accepted_at' => $receipt['accepted_at'],
                    'begin_ns' => $at, 'committed_return_ns' => hrtime(true)], JSON_THROW_ON_ERROR)."\n";
                flush();
                // No catch-up bursts: every start is separated by at least the configured interval.
                $next = $at + (int) ceil(1_000_000_000 / $input['rate']);
            }
            $result['http_admission_exercised'] = false;
            $result['body_text_bytes'] = 2048;
            $result['acceptance_attempts'] = $attempted;
            $result['accepted'] = $accepted;
        } elseif ($mode === 'dispatch') {
            $app->instance(PublicationObserver::class, new class($directory, $account->id) extends PublicationObserver
            {
                public function __construct(private string $directory, private int $account) {}

                public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void
                {
                    if ((int) $event->account_id !== $this->account) {
                        throw new RuntimeException('Foreign publication reached an owned dispatcher.');
                    }
                    $row = ['stage' => $stage, 'event_id' => $event->id, 'delivery_id' => $event->delivery_id,
                        'generation' => $event->generation, 'monotonic_ns' => hrtime(true),
                        'envelope_bytes' => strlen(json_encode($event->payload(), JSON_THROW_ON_ERROR))];
                    $line = json_encode($row, JSON_THROW_ON_ERROR)."\n";
                    $path = $this->directory.'/publication-events.jsonl';
                    clearstatcache(true, $path);
                    if (is_link($path) || (is_file($path) && filesize($path) > 8 * 1048576)
                        || file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
                        throw new RuntimeException('Owned publication event could not be recorded.');
                    }
                }
            });
            $claimed = 0;
            $events = DeliveryOutbox::where('account_id', $account->id)->where('eligible_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))
                ->orderBy('eligible_at')->orderBy('id')->limit($input['limit'])->pluck('id');
            foreach ($events as $event) {
                if (app(OutboxDispatcher::class)->dispatch($event)) {
                    $claimed++;
                }
            }
            $result['claims'] = $claimed;
        } elseif ($mode === 'worker') {
            $worker = $input['worker'];
            $app->instance(DeliveryObserver::class, new class($directory, $worker, $account->id) extends DeliveryObserver
            {
                public function __construct(private string $directory, private string $worker, private int $account) {}

                public function observe(string $stage, Delivery $delivery, array $facts = []): void
                {
                    if ((int) $delivery->account_id !== $this->account) {
                        throw new RuntimeException('Foreign delivery reached an owned worker.');
                    }
                    $row = ['worker' => $this->worker, 'stage' => $stage, 'delivery_id' => $delivery->id,
                        'generation' => $delivery->generation, 'monotonic_ns' => hrtime(true)];
                    $line = json_encode($row, JSON_THROW_ON_ERROR)."\n";
                    $path = $this->directory.'/'.$this->worker.'-events.jsonl';
                    clearstatcache(true, $path);
                    if (is_link($path) || (is_file($path) && filesize($path) > 8 * 1048576)
                        || file_put_contents($path, $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
                        throw new RuntimeException('Owned worker event could not be recorded.');
                    }
                }
            });
            echo json_encode(['phase' => 'worker_starting', 'worker' => $worker, 'run_id' => $run,
                'queue' => config('delivery.queue'), 'monotonic_ns' => hrtime(true)], JSON_THROW_ON_ERROR)."\n";
            flush();
            $deadline = hrtime(true) + $input['seconds'] * 1_000_000_000;
            $processed = app(DeliveryConsumer::class)->run(false, 0,
                fn () => is_file($directory.'/stop') || hrtime(true) >= $deadline);
            if (! is_file($directory.'/stop')) {
                throw new RuntimeException('Owned worker reached its deadline.');
            }
            $result += ['worker' => $worker, 'processed' => $processed, 'outcome' => 'stopped'];
        } elseif ($mode === 'probe') {
            $result += DB::transaction(function () use ($account, $requestedCut): array {
                // Share the domain mutex and one SQL clock cut; observation must be coherent.
                Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
                $at = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS at_utc')->at_utc;
                $cut = $requestedCut ?? $at;
                $actualClock = new DateTimeImmutable($at, new DateTimeZone('UTC'));
                if ($cut > $at || $cut < $actualClock->modify('-120 seconds')->format('Y-m-d H:i:s.u')) {
                    throw new RuntimeException('Observation cut outside the bounded stop interval.');
                }
                $probe = ['observed_at' => AccountKeyService::utc($at), 'eligibility_cut_at' => AccountKeyService::utc($cut)];
                $probe['requests'] = ApiMailRequest::where('account_id', $account->id)->count();
                $probe['status_counts'] = Delivery::where('account_id', $account->id)->groupBy('status')->selectRaw('status, COUNT(*) AS n')
                    ->pluck('n', 'status')->map(static fn ($count): int => (int) $count)->all();
                $probe['messages'] = MailMessage::where('account_id', $account->id)->count();
                $probe['duplicate_delivery_messages'] = DB::table('mail_messages')->where('account_id', $account->id)
                    ->whereNotNull('delivery_id')->groupBy('delivery_id')->havingRaw('COUNT(*) > 1')->get(['delivery_id'])->count();
                $probe['eligible_pending'] = Delivery::where('account_id', $account->id)->where('status', 'pending')
                    ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', $cut))->count();
                $future = Delivery::where('account_id', $account->id)->where('status', 'pending')->where('available_at', '>', $cut);
                $probe['future_pending'] = (clone $future)->count();
                $probe['minimum_future_available_at'] = AccountKeyService::utc((clone $future)->min('available_at'));
                $probe['quota_deferred'] = Delivery::where('account_id', $account->id)->where('status', 'pending')->where('diagnostic', 'quota_wait')->count();
                $probe['outbox_due'] = DeliveryOutbox::where('account_id', $account->id)->where('eligible_at', '<=', $cut)->count();
                $probe['unpaired_pending'] = DB::table('deliveries as delivery')->where('delivery.account_id', $account->id)
                    ->where('delivery.status', 'pending')->whereNotExists(function ($query) use ($cut): void {
                        $query->selectRaw('1')->from('delivery_outbox as event')
                            ->whereColumn('event.account_id', 'delivery.account_id')->whereColumn('event.delivery_id', 'delivery.id')
                            ->whereColumn('event.generation', 'delivery.generation')->where('event.type', 'delivery.ready')
                            ->where('event.status', 'pending')->where('event.available_at', '>', $cut);
                    })->count();
                $probe['deliveries'] = Delivery::where('account_id', $account->id)->orderBy('id')
                    ->get(['id', 'status', 'generation', 'attempts', 'diagnostic', 'available_at', 'started_at', 'completed_at'])->toArray();

                return $probe;
            }, 3);
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
    $status = 0;
} catch (Throwable $error) {
    echo json_encode(['outcome' => 'failed', 'phase' => $phase, 'exception_class' => get_class($error),
        'acceptance_attempts' => $attempted, 'accepted' => $accepted], JSON_THROW_ON_ERROR)."\n";
}
exit($status);
