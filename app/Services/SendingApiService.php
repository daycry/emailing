<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\ApiMailRecipient;
use App\Models\ApiMailRequest;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Support\ApiError;
use App\Support\SendingApiInput;
use App\Support\TemplateRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendingApiService
{
    /** Invoked inside AccountKeyService's current account/credential transaction. */
    public function accept(AccountApiKey $credential, string $idempotency, array $data): array
    {
        ApiError::require((bool) preg_match('/^[A-Za-z0-9_.:-]{16,128}$/D', $idempotency));
        $hash = hash('sha256', $idempotency);
        $fingerprint = SendingApiInput::fingerprint($data);
        $existing = ApiMailRequest::where('account_id', $credential->account_id)->where('idempotency_hash', $hash)->lockForUpdate()->first();
        if ($existing) {
            ApiError::require($existing->fingerprint_version === 1 || $existing->fingerprint_version == 1, 503);
            ApiError::require(hash_equals($existing->fingerprint, $fingerprint), 409);

            return [$existing->receipt, true];
        }
        $sender = MailSender::where('account_id', $credential->account_id)->find($data['sender_id']);
        ApiError::require($sender !== null, 404);
        ApiError::require($sender->lab_authorized && in_array(MailSetting::where('account_id', $credential->account_id)->value('mode') ?? 'log', ['log', 'mailpit'], true));
        $source = $data['content'];
        if ($source['type'] === 'template') {
            $template = EmailTemplate::where('account_id', $credential->account_id)->find($source['template_id']);
            ApiError::require($template !== null, 404);
            $source = ['type' => 'template'] + $template->only(['subject', 'html', 'text']);
        }
        $accountName = Account::findOrFail($credential->account_id)->name;
        $snapshots = [];
        foreach ($data['recipients'] as $recipient) {
            $ids = app(ApiRecipientPolicy::class)->eligible($credential->account_id, $recipient['email']);
            ApiError::require($ids !== null);
            $content = [];
            foreach (['subject', 'html', 'text'] as $field) {
                $content[$field] = $source['type'] === 'inline' ? $source[$field] : app(TemplateRenderer::class)->render($source[$field], ['contact.name' => $recipient['name'], 'contact.email' => $recipient['email'], 'account.name' => $accountName], $field, $field === 'html');
            }
            // Validate stored templates/expanded values too, without rewriting content.
            SendingApiInput::send((object) ['sender_id' => $data['sender_id'], 'recipients' => [(object) $recipient], 'content' => (object) (['type' => 'inline'] + $content)]);
            $snapshots[] = $recipient + $content + ['contact_ids' => $ids];
        }
        $senderSnapshot = $sender->only(['id', 'email', 'name', 'reply_to']);
        // Stream the private snapshot digest: 100 large inline recipients must
        // not allocate a second complete batch JSON string in PHP memory.
        $digest = hash_init('sha256');
        hash_update($digest, 'api-source-v1:'.json_encode($senderSnapshot, JSON_THROW_ON_ERROR));
        foreach ($snapshots as $snapshot) {
            $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            hash_update($digest, pack('N', strlen($encoded)));
            hash_update($digest, $encoded);
        }
        $sourceHash = hash_final($digest);
        $requestId = (string) Str::uuid();
        $deliveries = array_map(fn () => (string) Str::uuid(), $snapshots);
        $accepted = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc;
        $receipt = ['request_id' => $requestId, 'delivery_ids' => $deliveries, 'accepted_at' => AccountKeyService::utc($accepted), 'state' => 'accepted'];
        ApiMailRequest::create(['id' => $requestId, 'account_id' => $credential->account_id, 'credential_id' => $credential->id, 'idempotency_hash' => $hash, 'fingerprint_version' => 1, 'fingerprint' => $fingerprint, 'source_hash' => $sourceHash, 'sender_snapshot' => $senderSnapshot, 'receipt' => $receipt, 'accepted_at' => $accepted]);
        foreach ($snapshots as $position => $snapshot) {
            $recipient = ApiMailRecipient::create($snapshot + ['id' => (string) Str::uuid(), 'account_id' => $credential->account_id, 'api_request_id' => $requestId, 'position' => $position]);
            Delivery::create(['id' => $deliveries[$position], 'account_id' => $credential->account_id, 'api_request_id' => $requestId, 'api_recipient_id' => $recipient->id, 'source_hash' => $sourceHash, 'status' => 'pending', 'generation' => 1, 'available_at' => $accepted]);
            DeliveryOutbox::create(['id' => (string) Str::uuid(), 'account_id' => $credential->account_id, 'delivery_id' => $deliveries[$position], 'generation' => 1, 'type' => 'delivery.ready', 'status' => 'pending', 'available_at' => $accepted]);
        }

        return [$receipt, false];
    }

    public function status(int $account, string $id): array
    {
        $request = ApiMailRequest::where('account_id', $account)->find($id);
        ApiError::require($request !== null, 404);
        // Worker state writers share the held account mutex: one coherent population.
        $rows = Delivery::where('account_id', $account)->where('api_request_id', $id)->get()->keyBy('id');
        $recipients = ApiMailRecipient::where('account_id', $account)->where('api_request_id', $id)->get(['id', 'email'])->keyBy('id');
        $counts = array_fill_keys(['pending', 'reserved', 'sending', 'logged', 'smtp_accepted', 'failed', 'ambiguous', 'suppressed', 'blocked'], 0);
        $deliveries = [];
        foreach ($request->receipt['delivery_ids'] as $deliveryId) {
            $delivery = $rows->get($deliveryId);
            ApiError::require($delivery !== null && isset($counts[$delivery->status]) && $recipients->has($delivery->api_recipient_id), 503);
            $counts[$delivery->status]++;
            $deliveries[] = ['id' => $delivery->id, 'recipient' => $recipients[$delivery->api_recipient_id]->email, 'status' => $delivery->status, 'attempts' => $delivery->attempts, 'generation' => $delivery->generation, 'diagnostic' => ActivityService::diagnosticCode($delivery->diagnostic), 'completed_at' => AccountKeyService::utc($delivery->completed_at)];
        }

        return ['request_id' => $request->id, 'accepted_at' => $request->receipt['accepted_at'], 'observed_at' => AccountKeyService::utc(DB::selectOne('SELECT UTC_TIMESTAMP(6) AS now_utc')->now_utc), 'deliveries' => $deliveries, 'counts' => $counts, 'delivery_feedback' => 'not_available'];
    }
}
