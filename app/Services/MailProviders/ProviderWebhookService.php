<?php

namespace App\Services\MailProviders;

use App\Models\Account;
use App\Models\MailProviderConnection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ProviderWebhookService
{
    public function __construct(private WebhookAuthentication $authentication) {}

    public function consume(string $provider, string $connectionId, #[\SensitiveParameter] string $body, #[\SensitiveParameter] array $headers): void
    {
        $accountId = MailProviderConnection::whereKey($connectionId)->where('provider', $provider)->value('account_id');
        if ($accountId === null) {
            throw new HttpException(401);
        }
        DB::transaction(function () use ($provider, $connectionId, $accountId, $body, $headers): void {
            // All writers use this order. Authentication is rechecked against current keys under the mutex.
            Account::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $connection = MailProviderConnection::whereKey($connectionId)->where('account_id', $accountId)->where('provider', $provider)->lockForUpdate()->first();
            if (! $connection || ! $this->authentication->verify($connection, $body, $headers)) {
                throw new HttpException(401);
            }
            try {
                $payload = json_decode($body, false, 32, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new HttpException(422);
            }
            if ($provider === 'brevo') {
                if (! $payload instanceof \stdClass) {
                    throw new HttpException(422);
                }
                $payload = [$payload];
            }
            if (! is_array($payload) || $payload === [] || count($payload) > 1000) {
                throw new HttpException(422);
            }
            $events = [];
            foreach ($payload as $raw) {
                if (! $raw instanceof \stdClass) {
                    throw new HttpException(422);
                }
                $event = ProviderFeedback::from($provider, (array) $raw);
                if ($event !== null) {
                    $events[$event->key] = $event;
                }
            }
            $this->apply((int) $accountId, $provider, $events);
        }, 3);
    }

    /** Bounded, set-based processing: no per-event SQL inside a batch of up to 1000. */
    private function apply(int $accountId, string $provider, array $events): void
    {
        if ($events === []) {
            return;
        }
        $messages = DB::table('mail_messages')->where('account_id', $accountId)
            ->whereIn('mode', [$provider.'_api', $provider.'_smtp'])->whereNotNull('transport_started_at')
            ->whereIn('provider_reference', array_unique(array_map(fn ($e) => $e->reference, $events)))
            ->get(['id', 'provider_reference', 'recipient', 'provider_status', 'provider_status_at'])->keyBy('provider_reference');
        $existing = DB::table('mail_provider_events')->where('account_id', $accountId)->where('provider', $provider)
            ->whereIn('event_key', array_keys($events))->pluck('event_key')->flip();
        $now = now('UTC')->format('Y-m-d H:i:s');
        $receipts = $updates = $suppressions = $audits = [];
        foreach ($events as $key => $event) {
            $message = $messages->get($event->reference);
            if ($existing->has($key) || ! $message || ! hash_equals(mb_strtolower(trim($message->recipient)), $event->recipient)) {
                continue;
            }
            $at = $event->at->format('Y-m-d H:i:s.u');
            $receipts[] = ['account_id' => $accountId, 'mail_message_id' => $message->id, 'provider' => $provider,
                'event_key' => $key, 'status' => $event->status, 'occurred_at' => $at, 'created_at' => $now, 'updated_at' => $now];
            if (ProviderFeedback::rank($event->status) > ProviderFeedback::rank($message->provider_status)
                || (ProviderFeedback::rank($event->status) === ProviderFeedback::rank($message->provider_status) && $at > ($message->provider_status_at ?? ''))) {
                $message->provider_status = $event->status;
                $message->provider_status_at = $at;
                $updates[$message->id] = [$event->status, $at];
            }
            if ($event->suppression !== null) {
                $suppressions[$event->recipient] = ['account_id' => $accountId, 'normalized_email' => $event->recipient,
                    'source' => $event->suppression, 'created_at' => $now, 'updated_at' => $now];
            }
            $audits[] = ['account_id' => $accountId, 'actor_id' => null, 'action' => 'mail.provider_feedback',
                'metadata' => json_encode(['message_id' => $message->id, 'provider' => $provider, 'status' => $event->status], JSON_THROW_ON_ERROR), 'created_at' => $now, 'updated_at' => $now];
        }
        if ($receipts !== []) {
            DB::table('mail_provider_events')->insert($receipts);
            DB::table('audit_events')->insert($audits);
        }
        if ($updates !== []) {
            $statusCase = $atCase = '';
            $statusBindings = $atBindings = [];
            foreach ($updates as $id => [$status, $at]) {
                $statusCase .= ' WHEN ? THEN ?';
                array_push($statusBindings, $id, $status);
                $atCase .= ' WHEN ? THEN ?';
                array_push($atBindings, $id, $at);
            }
            $placeholders = implode(',', array_fill(0, count($updates), '?'));
            // Only feedback columns change. Actor, acceptance and attempt provenance stay immutable.
            DB::update('UPDATE mail_messages SET provider_status = CASE id'.$statusCase.' END,
                provider_status_at = CASE id'.$atCase.' END WHERE account_id = ? AND id IN ('.$placeholders.')',
                [...$statusBindings, ...$atBindings, $accountId, ...array_keys($updates)]);
        }
        if ($suppressions !== []) {
            DB::table('suppressions')->insertOrIgnore(array_values($suppressions));
        }
    }
}
