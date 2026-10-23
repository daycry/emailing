<?php

namespace App\Services;

final class DeliveryEnvelope
{
    public static function decode(string $body): ?array
    {
        if (strlen($body) > 2048) {
            return null;
        }
        try {
            $data = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (! is_array($data) || count($data) !== 6 || ($data['v'] ?? null) !== 1 || ($data['type'] ?? null) !== 'delivery.ready'
            || ! is_int($data['account_id'] ?? null) || $data['account_id'] < 1
            || ! is_int($data['generation'] ?? null) || $data['generation'] < 1 || $data['generation'] > 2147483647) {
            return null;
        }
        foreach (['event_id', 'delivery_id'] as $key) {
            if (! is_string($data[$key] ?? null) || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $data[$key])) {
                return null;
            }
        }

        return $data;
    }
}
