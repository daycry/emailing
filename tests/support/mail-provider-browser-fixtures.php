<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailProviderConnection;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountMailTransport;
use App\Services\CampaignService;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryMaterializer;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\MailProviderTlsFixture;

ini_set('zend.exception_ignore_args', '1');
try {
    if (getenv('PROVIDER_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing') {
        throw new RuntimeException;
    }
    require __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestingDatabaseGuard::assertSafe();
    $run = getenv('PROVIDER_BROWSER_RUN');
    $directory = getenv('PROVIDER_BROWSER_DIRECTORY');
    if (! preg_match('/^[a-f0-9-]{36}$/D', $run) || realpath($directory) !== realpath(storage_path('providers-email-20261030/browser-'.$run))) {
        throw new RuntimeException;
    }
    $file = $directory.'/fixtures.private.json';
    $action = $argv[1] ?? '';
    if ($action === 'pki') {
        require __DIR__.'/mail-provider-tls-fixture.php';
        MailProviderTlsFixture::create($directory, function (bool $valid): void {
            if (! $valid) {
                throw new RuntimeException;
            }
        });
        exit(0);
    }
    if ($action === 'setup') {
        $other = DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.PROCESSLIST WHERE DB = DATABASE() AND ID <> CONNECTION_ID()');
        if ((int) $other->n !== 0 || is_file($file)) {
            throw new RuntimeException;
        }
        Artisan::call('migrate', ['--force' => true]);
        DB::transaction(function () use ($run, $file): void {
            $cases = [];
            for ($case = 1; $case <= 6; $case++) {
                $email = 'provider-browser-'.$run.'-'.$case.'@example.test';
                $password = bin2hex(random_bytes(24)).'Aa9!';
                $user = app(CreateNewUser::class)->create(['name' => 'Provider browser '.$case, 'account_name' => 'Provider fixture '.$case,
                    'email' => $email, 'password' => $password, 'password_confirmation' => $password]);
                $account = $user->memberships()->sole()->account;
                $other = Account::create(['name' => 'Isolated fixture '.$case, 'owner_id' => $user->id]);
                Membership::create(['account_id' => $other->id, 'user_id' => $user->id, 'role' => 'admin', 'scopes' => []]);
                $reader = User::create(['name' => 'Reader fixture', 'email' => 'provider-reader-'.$run.'-'.$case.'@example.test', 'password' => Hash::make($password), 'email_verified_at' => now()]);
                Membership::create(['account_id' => $account->id, 'user_id' => $reader->id, 'role' => 'read-only', 'scopes' => []]);
                $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Provider sender', 'lab_authorized' => true, 'external_authorized' => true]);
                MailSetting::create(['account_id' => $account->id, 'mode' => 'log']);
                $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
                if ($key === false || ! openssl_pkey_export($key, $private)) {
                    throw new RuntimeException;
                }
                $public = openssl_pkey_get_details($key)['key'];
                $connections = [];
                foreach (['brevo', 'sendgrid'] as $provider) {
                    $connections[$provider] = MailProviderConnection::create(['account_id' => $account->id, 'provider' => $provider, 'enabled' => true,
                        'smtp_security' => 'starttls', 'api_key' => 'synthetic-'.$provider.'-api-secret', 'smtp_username' => 'synthetic-smtp-user',
                        'smtp_password' => 'synthetic-smtp-secret', 'webhook_secret' => $provider === 'brevo' ? str_repeat('synthetic-feedback-', 3) : null,
                        'webhook_public_key' => $provider === 'sendgrid' ? $public : null])->id;
                }
                $contact = Contact::create(['account_id' => $account->id, 'email' => 'recipient@example.test', 'name' => 'Provider recipient',
                    'consent_status' => 'granted', 'consent_source' => 'Synthetic fixture', 'consented_at' => now()->subMinute()]);
                $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'Provider fixture', 'subject' => 'provider-browser-'.$run.'-durable-'.$case, 'text' => 'Synthetic', 'html' => '']);
                $cases[(string) $case] = ['email' => $email, 'password' => $password, 'reader_email' => $reader->email,
                    'users' => [$user->id, $reader->id], 'account_id' => $account->id, 'other_account_id' => $other->id,
                    'sender_id' => $sender->id, 'connections' => $connections, 'sendgrid_private' => $private,
                    'contact_id' => $contact->id, 'template_id' => $template->id];
            }
            file_put_contents($file, json_encode(['run' => $run, 'cases' => $cases], JSON_THROW_ON_ERROR));
        });
        echo "Owned provider fixtures ready.\n";
        exit(0);
    }
    if (! is_file($file)) {
        if ($action === 'cleanup') {
            exit(0);
        }
        throw new RuntimeException;
    }
    $fixture = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    if ($fixture['run'] !== $run) {
        throw new RuntimeException;
    }
    if ($action === 'cleanup') {
        DB::transaction(function () use ($fixture): void {
            $accounts = $users = [];
            foreach ($fixture['cases'] as $case) {
                array_push($accounts, $case['account_id'], $case['other_account_id']);
                array_push($users, ...$case['users']);
            }
            DB::table('mail_messages')->whereIn('account_id', $accounts)->delete();
            DB::table('api_mail_requests')->whereIn('account_id', $accounts)->delete();
            DB::table('campaigns')->whereIn('account_id', $accounts)->delete();
            Account::whereIn('id', $accounts)->delete();
            User::whereIn('id', $users)->delete();
            if (Account::whereIn('id', $accounts)->exists() || User::whereIn('id', $users)->exists()) {
                throw new RuntimeException;
            }
        });
        $io = is_file($directory.'/io.private.jsonl') ? file_get_contents($directory.'/io.private.jsonl') : '';
        if (str_contains($io, 'mailpit')) {
            $prefix = 'provider-browser-'.$run;
            $messages = Http::withoutRedirecting()->timeout(3)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'subject:'.$prefix])->throw()->json('messages');
            $ids = [];
            foreach ($messages as $message) {
                if (! str_starts_with($message['Subject'] ?? '', $prefix)) {
                    throw new RuntimeException;
                }
                $ids[] = $message['ID'];
            }
            if ($ids !== []) {
                Http::withoutRedirecting()->timeout(3)->delete('http://127.0.0.1:18025/api/v1/messages', ['IDs' => $ids])->throw();
            }
            if (Http::withoutRedirecting()->timeout(3)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'subject:'.$prefix])->throw()->json('messages') !== []) {
                throw new RuntimeException;
            }
        }
        echo "Owned provider SQL fixtures removed.\n";
        exit(0);
    }
    $caseId = $argv[2] ?? '';
    if (! isset($fixture['cases'][$caseId])) {
        throw new RuntimeException;
    }
    $case = $fixture['cases'][$caseId];
    $account = Account::whereKey($case['account_id'])->where('owner_id', $case['users'][0])->firstOrFail();
    config(['app.url' => 'https://127.0.0.1:18867', 'emailing.external_enabled' => true,
        'emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => 11025,
        'delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000,
        'filesystems.disks.mail-artifacts.root' => $directory.'/artifacts']);
    if ($action === 'snapshot') {
        echo json_encode(['messages' => MailMessage::where('account_id', $account->id)->orderBy('id')->get()->map(fn ($m) => [
            'id' => $m->id, 'mode' => $m->mode, 'status' => $m->status, 'diagnostic' => $m->diagnostic,
            'reference' => $m->provider_reference, 'provider_status' => $m->provider_status])->all(),
            'events' => DB::table('mail_provider_events')->where('account_id', $account->id)->count(),
            'suppressions' => DB::table('suppressions')->where('account_id', $account->id)->count(),
            'profiles' => MailProviderConnection::where('account_id', $account->id)->get(['provider', 'enabled'])->pluck('enabled', 'provider')->all(),
            'io_count' => is_file($directory.'/io.private.jsonl') ? count(array_filter(file($directory.'/io.private.jsonl'),
                fn ($line) => (int) json_decode($line, true, flags: JSON_THROW_ON_ERROR)['account_id'] === $account->id)) : 0], JSON_THROW_ON_ERROR);
        exit(0);
    }
    if ($action === 'revoke-writer') {
        Membership::where('account_id', $account->id)->where('user_id', $case['users'][0])->sole()->update(['role' => 'restricted', 'scopes' => ['settings.read']]);
        echo '{"revoked":true}';
        exit(0);
    }
    if ($action === 'mailpit-capture') {
        $messages = Http::withoutRedirecting()->timeout(3)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'subject:provider-browser-'.$run])->throw()->json('messages');
        echo json_encode(['count' => count($messages)], JSON_THROW_ON_ERROR);
        exit(0);
    }
    if ($action !== 'work' || ! in_array($argv[3] ?? '', ['brevo_api', 'sendgrid_api', 'brevo_smtp', 'sendgrid_smtp', 'log', 'mailpit'], true)) {
        throw new RuntimeException;
    }
    require __DIR__.'/mail-provider-browser-adapters.php';
    MailSetting::where('account_id', $account->id)->update(['mode' => $argv[3]]);
    $service = app(CampaignService::class);
    $campaign = $service->save($account, $case['users'][0], null, null, ['name' => 'Browser durable fixture', 'template_id' => $case['template_id'],
        'sender_id' => $case['sender_id'], 'contact_ids' => [$case['contact_id']], 'list_ids' => []]);
    $campaign = $service->review($account, $case['users'][0], $campaign->id, $campaign->version);
    $campaign = $service->authorizeCampaign($account, $case['users'][0], $campaign->id, $campaign->version, $campaign->reviewed_revision_id, true);
    app(DeliveryMaterializer::class)->materialize($account->id, $campaign->id);
    $delivery = Delivery::where('campaign_id', $campaign->id)->sole();
    $event = DeliveryOutbox::where('delivery_id', $delivery->id)->sole();
    $claim = app(DeliveryLifecycle::class)->claim($event->payload());
    app(AccountMailTransport::class)->sendDelivery($claim);
    $redelivery = app(DeliveryLifecycle::class)->claim($event->payload());
    $delivery->refresh();
    echo json_encode(['status' => $delivery->status, 'attempts' => $delivery->attempts, 'generation' => $delivery->generation,
        'redelivery_blocked' => $redelivery === null, 'available_at' => $delivery->available_at?->getTimestamp()], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    fwrite(STDERR, "Owned provider fixture operation failed.\n");
    exit(1);
}
