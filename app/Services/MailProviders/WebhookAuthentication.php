<?php

namespace App\Services\MailProviders;

use App\Models\MailProviderConnection;

final class WebhookAuthentication
{
    public function verify(MailProviderConnection $connection, #[\SensitiveParameter] string $body, #[\SensitiveParameter] array $headers): bool
    {
        try {
            if (! $connection->enabled) {
                return false;
            }
            if ($connection->provider === 'brevo') {
                $secret = $connection->webhook_secret;

                return is_string($secret) && strlen($secret) >= 32
                    && hash_equals('Bearer '.$secret, $headers['authorization'] ?? '');
            }
            $timestamp = $headers['timestamp'] ?? '';
            if (! preg_match('/^[0-9]{1,12}$/D', $timestamp)) {
                return false;
            }
            $age = time() - (int) $timestamp;
            $maxAge = max(60, min(86400, (int) config('emailing.provider_webhook_max_age', 86400)));
            if ($age > $maxAge || $age < -300) {
                return false;
            }
            $signature = base64_decode($headers['signature'] ?? '', true);
            $key = SendGridWebhookKey::parse($connection->webhook_public_key ?? '');

            return $connection->provider === 'sendgrid' && $signature !== false && strlen($signature) <= 128 && $key !== false
                && openssl_verify($timestamp.$body, $signature, $key, OPENSSL_ALGO_SHA256) === 1;
        } catch (\Throwable) {
            return false;
        }
    }
}
