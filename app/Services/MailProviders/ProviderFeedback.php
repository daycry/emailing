<?php

namespace App\Services\MailProviders;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/** Normalized, minimal event. Provider payloads are never persisted. */
final readonly class ProviderFeedback
{
    public function __construct(public string $reference, public string $recipient, public string $key,
        public string $status, public CarbonImmutable $at, public ?string $suppression) {}

    public static function rank(?string $status): int
    {
        return match ($status) {
            'processed' => 10, 'deferred' => 20, 'delivered' => 50, 'blocked' => 80,
            'bounced' => 90, 'complained', 'unsubscribed' => 100, default => 0,
        };
    }

    public static function from(string $provider, array $event): ?self
    {
        $kind = $event['event'] ?? null;
        $status = $provider === 'brevo' ? match ($kind) {
            'request' => 'processed', 'deferred', 'soft_bounce' => 'deferred', 'delivered' => 'delivered',
            'hard_bounce' => 'bounced', 'spam' => 'complained', 'unsubscribed' => 'unsubscribed',
            'blocked', 'invalid', 'error' => 'blocked', default => null,
        } : match ($kind) {
            'processed' => 'processed', 'deferred' => 'deferred', 'delivered' => 'delivered',
            'bounce' => ($event['type'] ?? 'bounce') === 'blocked' ? 'blocked' : 'bounced',
            'dropped' => 'blocked', 'spamreport' => 'complained', 'unsubscribe', 'group_unsubscribe' => 'unsubscribed', default => null,
        };
        if ($status === null) {
            return null;
        }
        $reference = $provider === 'brevo' ? ($event['X-Mailin-custom'] ?? null) : ($event['emailing_reference'] ?? null);
        if ($provider === 'brevo' && is_string($reference) && str_starts_with($reference, 'emailing_reference:')) {
            $reference = substr($reference, strlen('emailing_reference:'));
        }
        $timestamp = $provider === 'brevo' ? ($event['ts_event'] ?? $event['ts'] ?? null) : ($event['timestamp'] ?? null);
        $recipient = $event['email'] ?? null;
        if (! is_string($reference) || ! Str::isUuid($reference) || ! is_string($recipient) || strlen($recipient) > 254
            || ! is_int($timestamp) || $timestamp < 946684800 || $timestamp > time() + 300) {
            return null;
        }
        $identity = $provider === 'sendgrid' ? ($event['sg_event_id'] ?? null) : null;
        if ($provider === 'sendgrid' && (! is_string($identity) || $identity === '' || strlen($identity) > 255)) {
            return null;
        }
        // Brevo's id identifies the webhook, not an occurrence. Hash only canonical primitives.
        if ($provider === 'brevo') {
            $message = $event['message-id'] ?? '';
            if (! is_string($message) || strlen($message) > 255) {
                return null;
            }
            $identity = json_encode([$reference, $kind, $timestamp, $message], JSON_THROW_ON_ERROR);
        }

        return new self(strtolower($reference), mb_strtolower(trim($recipient)), hash('sha256', $identity), $status,
            CarbonImmutable::createFromTimestampUTC($timestamp), match ($status) {
                'bounced' => 'provider_bounce', 'complained' => 'provider_complaint', 'unsubscribed' => 'provider_unsubscribe', default => null,
            });
    }
}
