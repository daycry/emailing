<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Crypt;
use stdClass;

/** Authenticated business-data continuation, separate from the tools/list registry. */
final class DataCursor
{
    public function issue(DelegatedContext $context, string $operation, array $filters, array $position): string
    {
        return ($this->issuer($context, $operation, $filters))($position);
    }

    /** Capture once while the page holds the account mutex; cuts need no repeated SQL reads. */
    public function issuer(DelegatedContext $context, string $operation, array $filters): \Closure
    {
        $binding = $this->binding($context, $operation, $filters);

        return fn (array $position): string => $this->encrypt($binding, $position);
    }

    private function encrypt(array $binding, array $position): string
    {
        $issued = now()->getTimestamp();
        $expiry = min($issued + 900, $binding['token_expires']);
        if ($expiry <= $issued) {
            throw new DataFailure('cursor_expired');
        }
        unset($binding['token_expires']);
        $payload = $binding + ['purpose' => 'correo:mcp:cursor:v1', 'version' => 1,
            'issued_at' => $issued, 'expires_at' => $expiry, 'position' => $position,
            'snapshot_revision_id' => null, 'resolved_window' => null];
        $plain = self::canonical((object) $payload);
        if (strlen($plain) > 1024 || ! ContractArguments::valid(json_decode($plain, false, 32, JSON_THROW_ON_ERROR), $this->schema())) {
            throw new \LogicException('Invalid server cursor payload');
        }
        $wire = rtrim(strtr(Crypt::encryptString($plain), '+/', '-_'), '=');
        if (strlen($wire) > 2048) {
            throw new DataFailure('result_too_large');
        }

        return $wire;
    }

    public function after(DelegatedContext $context, string $operation, array $filters, string $wire): array
    {
        // Authority is checked before touching any ciphertext, without masking auth failures.
        $binding = $this->binding($context, $operation, $filters);
        try {
            if (! preg_match('/\A[A-Za-z0-9_-]{16,2048}\z/D', $wire)) {
                throw new DataFailure('cursor_invalid');
            }
            $encoded = strtr($wire, '-_', '+/');
            $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
            $decoded = base64_decode($encoded, true);
            if ($decoded === false || rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $wire) {
                throw new DataFailure('cursor_invalid');
            }
            $plain = Crypt::decryptString($encoded);
            if (strlen($plain) > 1024) {
                throw new DataFailure('cursor_invalid');
            }
            $data = json_decode($plain, false, 32, JSON_THROW_ON_ERROR);
            if (! ContractArguments::valid($data, $this->schema()) || self::canonical($data) !== $plain) {
                throw new DataFailure('cursor_invalid');
            }
            foreach ($binding as $key => $expected) {
                if ($key !== 'token_expires' && (! is_string($data->{$key} ?? null) || ! hash_equals($expected, $data->{$key}))) {
                    throw new DataFailure('cursor_invalid');
                }
            }
            $kind = $operation === 'imports_get' ? 'row_number_asc' : 'id_desc';
            if ($data->position->kind !== $kind || $data->snapshot_revision_id !== null || $data->resolved_window !== null
                || $data->issued_at > now()->getTimestamp() + 30 || $data->expires_at <= $data->issued_at
                || $data->expires_at - $data->issued_at > 900 || $data->expires_at > $binding['token_expires']) {
                throw new DataFailure('cursor_invalid');
            }
            if ($data->expires_at <= now()->getTimestamp()) {
                throw new DataFailure('cursor_expired');
            }

            return (array) $data->position;
        } catch (DataFailure $error) {
            throw $error;
        } catch (\Throwable) {
            throw new DataFailure('cursor_invalid');
        }
    }

    private function binding(DelegatedContext $context, string $operation, array $filters): array
    {
        $authority = $context->cursorAuthority();

        return ['resource_hash' => hash('sha256', config('mcp.resource')),
            'account_id' => (string) $context->accountId(), 'user_id' => (string) $context->actorId(),
            'grant_id' => $authority['grant_id'], 'authorization_hash' => $authority['authorization_hash'],
            'filter_hash' => hash('sha256', self::canonical((object) $filters)),
            'operation' => $operation, 'paged_projection' => 'root', 'token_expires' => $authority['expires_at']];
    }

    private function schema(): array
    {
        return json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR)['cursor']['decrypted_payload_schema'];
    }

    private static function canonical(mixed $value): string
    {
        if ($value instanceof stdClass) {
            $properties = get_object_vars($value);
            ksort($properties, SORT_STRING);
            $pairs = [];
            foreach ($properties as $key => $item) {
                $pairs[] = json_encode((string) $key, JSON_THROW_ON_ERROR).':'.self::canonical($item);
            }

            return '{'.implode(',', $pairs).'}';
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                return self::canonical((object) $value);
            }

            return '['.implode(',', array_map(self::canonical(...), $value)).']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
