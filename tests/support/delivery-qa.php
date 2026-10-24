<?php

// Explicitly owned synthetic browser fixtures. Never prints credentials or links.
use App\Jobs\RuntimeProbe;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\User;
use App\Services\CampaignService;
use App\Services\DeliveryMaterializer;
use App\Services\OutboxDispatcher;
use App\Services\SuppressionService;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use PhpAmqpLib\Connection\AMQPStreamConnection;

ini_set('zend.exception_ignore_args', '1');
try {
    require __DIR__.'/../../vendor/autoload.php';
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    TestingDatabaseGuard::assertSafe();
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $owner = $input['owner'] ?? '';
    if (! preg_match('/\A[a-f0-9]{32}\z/', $owner)) {
        throw new RuntimeException('Fixture owner required.');
    }
    $statePath = storage_path('app/private/t16-'.$owner.'.json');
    $identity = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS db_user');
    $mode = $input['mode'];
    if ($mode === 'schema') {
        Artisan::call('migrate', ['--force' => true]);
    } elseif ($mode === 'init') {
        if (is_file($statePath)) {
            throw new RuntimeException('Existing fixture must be settled first.');
        }
        foreach (['users', 'memberships', 'contacts', 'campaigns', 'campaign_recipients', 'delivery_outbox', 'delivery_quota_windows'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Complete QA schema required.');
            }
        }
        $state = ['owner' => $owner, 'identity' => (array) $identity, 'accounts' => [], 'users' => [], 'campaigns' => [], 'password' => $input['password'], 'fixture_status' => 'initializing'];
        // Attribution exists before the first SQL write, including partial failure.
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
        $state = DB::transaction(function () use ($state, $owner, $input): array {
            foreach (['ownerA', 'ownerB', 'reader', 'restricted', 'foreign'] as $role) {
                $user = User::create(['name' => 'QA '.$role, 'email' => strtolower($role).'-'.$owner.'@example.test', 'password' => Hash::make($input['password']), 'email_verified_at' => now()]);
                $state['users'][$role] = $user->id;
            }
            foreach (['A', 'B'] as $label) {
                $account = Account::create(['name' => 'QA T16 '.substr($owner, 0, 8).' '.$label, 'owner_id' => $state['users']['owner'.$label]]);
                $state['accounts'][$label] = $account->id;
                Membership::create(['account_id' => $account->id, 'user_id' => $account->owner_id, 'role' => 'admin', 'scopes' => []]);
                MailSetting::create(['account_id' => $account->id, 'mode' => 'mailpit']);
                $sender = MailSender::create(['account_id' => $account->id, 'email' => 'sender-'.strtolower($label).'@example.test', 'name' => 'QA laboratorio '.$label, 'lab_authorized' => true]);
                $contact = Contact::create(['account_id' => $account->id, 'email' => 't16-'.$owner.'@example.test', 'name' => '=2+2', 'consent_status' => 'granted', 'consent_source' => 'Fixture sintética T16', 'consented_at' => now('UTC')->subDay()]);
                $template = EmailTemplate::create(['account_id' => $account->id, 'name' => 'QA plantilla '.$label, 'subject' => 'QA T16 '.$label, 'text' => 'Mensaje sintético para {{ contact.email }}', 'html' => '<p>Mensaje sintético</p>']);
                $service = app(CampaignService::class);
                foreach (['draft', 'ready', 'future'] as $scenario) {
                    $campaign = $service->save($account, $account->owner_id, null, null, ['name' => 'QA '.$label.' '.$scenario, 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]);
                    // Future remains a draft for natural-time UI programming in formal QA.
                    $state['campaigns'][$label][$scenario] = $campaign->id;
                }
                $state['contacts'][$label] = $contact->id;
            }
            Membership::create(['account_id' => $state['accounts']['B'], 'user_id' => $state['users']['ownerA'], 'role' => 'read-only', 'scopes' => []]);
            Membership::create(['account_id' => $state['accounts']['A'], 'user_id' => $state['users']['reader'], 'role' => 'read-only', 'scopes' => []]);
            Membership::create(['account_id' => $state['accounts']['A'], 'user_id' => $state['users']['restricted'], 'role' => 'restricted', 'scopes' => ['campaigns.read', 'activity.read']]);
            $state['fixture_status'] = 'ready';

            return $state;
        });
        file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
    } else {
        $state = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        if ($state['owner'] !== $owner || $state['identity'] !== (array) $identity) {
            throw new RuntimeException('Fixture attribution mismatch.');
        }
        if ($mode === 'enqueue') {
            foreach (['A', 'B'] as $label) {
                $account = Account::findOrFail($state['accounts'][$label]);
                $service = app(CampaignService::class);
                $campaign = Campaign::findOrFail($state['campaigns'][$label]['ready']);
                $reviewed = $service->review($account, $account->owner_id, $campaign->id, $campaign->version);
                $service->authorizeCampaign($account, $account->owner_id, $reviewed->id, $reviewed->version, $reviewed->reviewed_revision_id, true);
                app(DeliveryMaterializer::class)->materialize($account->id, $campaign->id);
                $event = DeliveryOutbox::where('account_id', $account->id)->where('delivery_id', Delivery::where('campaign_id', $campaign->id)->value('id'))->sole();
                if (! app(OutboxDispatcher::class)->dispatch($event->id)) {
                    throw new RuntimeException('Owned publication failed.');
                }
            }
            $state['unsubscribe_url'] = app(SuppressionService::class)->issue(Account::findOrFail($state['accounts']['A']), $state['users']['ownerA'], ['email' => 't16-'.$owner.'@example.test']);
            file_put_contents($statePath, json_encode($state, JSON_THROW_ON_ERROR));
        } elseif ($mode === 'job') {
            Queue::connection('rabbitmq')->push(new RuntimeProbe($owner));
        } elseif ($mode === 'revoke-reader') {
            Membership::where('account_id', $state['accounts']['A'])->where('user_id', $state['users']['reader'])->delete();
        } elseif ($mode === 'cleanup') {
            $emails = array_map(static fn (string $role): string => strtolower($role).'-'.$owner.'@example.test', ['ownerA', 'ownerB', 'reader', 'restricted', 'foreign']);
            $attributedUsers = User::whereIn('email', $emails)->pluck('id');
            $accounts = Account::whereIn('owner_id', $attributedUsers)->whereIn('name', ['QA T16 '.substr($owner, 0, 8).' A', 'QA T16 '.substr($owner, 0, 8).' B'])->get();
            foreach ($accounts as $account) {
                if (! str_starts_with($account->name, 'QA T16 '.substr($owner, 0, 8).' ')) {
                    throw new RuntimeException('Foreign account cleanup refused.');
                }
                DB::table('delivery_quota_windows')->where('id', 'like', 'account:'.$account->id.':%')->delete();
                $account->delete();
            }
            DB::table('delivery_quota_windows')->where('id', 'like', 'pool:t16-'.$owner.':%')->delete();
            // Transaction rollback can leave attribution without returned IDs.
            foreach (User::whereIn('email', $emails)->get() as $user) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
                $user->delete();
            }
            if (is_file(storage_path('app/private/probe-'.$owner))) {
                unlink(storage_path('app/private/probe-'.$owner));
            }
            unlink($statePath);
        } elseif ($mode !== 'probe') {
            throw new RuntimeException('Unknown owned fixture operation.');
        }
    }
    $result = ['identity' => (array) $identity, 'mode' => $mode, 'owner' => $owner, 'required_tables_present' => Schema::hasTable('deliveries') && Schema::hasTable('delivery_quota_windows')];
    if (is_file($statePath)) {
        $state = json_decode(file_get_contents($statePath), true, flags: JSON_THROW_ON_ERROR);
        $ids = array_values($state['accounts']);
        $result['deliveries'] = Delivery::whereIn('account_id', $ids)->orderBy('account_id')->get(['id', 'account_id', 'campaign_id', 'status', 'generation', 'attempts', 'diagnostic'])->toArray();
        $result['messages'] = MailMessage::whereIn('account_id', $ids)->get(['id', 'account_id', 'status', 'sender_email'])->toArray();
        $result['accounts'] = $state['accounts'];
        $result['job_completed'] = is_file(storage_path('app/private/probe-'.$owner));
    }
    if (in_array($mode, ['probe', 'enqueue', 'cleanup'], true)) {
        $host = config('queue.connections.rabbitmq.hosts.0');
        $connection = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost']);
        try {
            $channel = $connection->channel();
            foreach (['delivery.queue', 'delivery.failure_queue', 'queue.connections.rabbitmq.queue'] as $key) {
                $name = config($key);
                if (! str_contains($name, $owner)) {
                    throw new RuntimeException('Unowned topology refused.');
                }
                if ($mode === 'cleanup') {
                    $channel->queue_delete($name);
                } else {
                    [, $ready, $consumers] = $channel->queue_declare($name, false, true, false, false);
                    $result['queues'][$key] = ['ready' => $ready, 'consumers' => $consumers];
                }
            }
            if ($mode === 'cleanup') {
                if (! str_contains(config('delivery.exchange'), $owner)) {
                    throw new RuntimeException('Unowned exchange refused.');
                }
                $channel->exchange_delete(config('delivery.exchange'));
            }
        } finally {
            $connection->close();
        }
    }
    echo json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode(['error' => 'owned_delivery_qa_fixture_failed', 'exception_class' => get_class($error)], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
