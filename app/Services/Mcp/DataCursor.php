<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Crypt;
use stdClass;

/** Authenticated continuation bound to current delegated authority and a paged projection. */
final class DataCursor
{
    public function issue(DelegatedContext $context, string $operation, array $filters, array $position, string $projection = 'root', ?string $snapshot = null, ?array $window = null): string
    {
        return ($this->issuer($context, $operation, $filters, $projection, $snapshot, $window))($position);
    }

    /** Capture authority once under the account mutex, including for repeated byte-budget cuts. */
    public function issuer(DelegatedContext $context, string $operation, array $filters, string $projection = 'root', ?string $snapshot = null, ?array $window = null): \Closure
    {
        $binding = $this->binding($context, $operation, $projection) + ['filter_hash' => $this->filterHash($filters)];

        return fn (array $position): string => $this->encrypt($binding, $position, $snapshot, $window);
    }

    private function encrypt(array $binding, array $position, ?string $snapshot, ?array $window): string
    {
        $issued = now()->getTimestamp();
        $expiry = min($issued + 900, $binding['token_expires']);
        if ($expiry <= $issued) {
            throw new DataFailure('cursor_expired');
        }
        unset($binding['token_expires']);
        $payload = $binding + ['purpose' => 'correo:mcp:cursor:v1', 'version' => 1,
            'issued_at' => $issued, 'expires_at' => $expiry, 'position' => $position,
            'snapshot_revision_id' => $snapshot, 'resolved_window' => $window];
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
        return $this->read($context, $operation, $filters, $wire)['position'];
    }

    /** A trusted filter resolver may reuse the authenticated activity window before hashing. */
    public function read(DelegatedContext $context, string $operation, array|\Closure $filters, string $wire, string $projection = 'root', ?string $snapshot = null): array
    {
        // Re-read authority before processing ciphertext; authentication failures remain transport failures.
        $binding = $this->binding($context, $operation, $projection);
        $data = $this->decrypt($wire);
        foreach ($binding as $key => $expected) {
            if ($key !== 'token_expires' && (! is_string($data->{$key} ?? null) || ! hash_equals($expected, $data->{$key}))) {
                throw new DataFailure('cursor_invalid');
            }
        }
        $kind = match (true) {
            $operation === 'imports_get' && $projection === 'root' => 'row_number_asc',
            $operation === 'campaigns_get' && $projection === 'recipients' => 'recipient_id_asc',
            $operation === 'activity_list' && $projection === 'root' => 'activity_desc',
            default => 'id_desc',
        };
        $recipients = $kind === 'recipient_id_asc';
        $activity = $kind === 'activity_desc';
        if ($data->position->kind !== $kind
            || ($recipients ? $data->snapshot_revision_id === null : $data->snapshot_revision_id !== null)
            || ($activity ? $data->resolved_window === null : $data->resolved_window !== null)) {
            throw new DataFailure('cursor_invalid');
        }
        $window = $data->resolved_window === null ? null : (array) $data->resolved_window;
        if ($window !== null && $window['from_utc'] > $window['to_utc']) {
            throw new DataFailure('cursor_invalid');
        }
        $resolved = $filters instanceof \Closure ? $filters($window) : $filters;
        if (! hash_equals($this->filterHash($resolved), $data->filter_hash)) {
            throw new DataFailure('cursor_invalid');
        }
        // Only an otherwise bound cursor can disclose that this owned campaign changed revision.
        if ($recipients && $data->snapshot_revision_id !== $snapshot) {
            throw new DataFailure('version_conflict');
        }
        if ($data->issued_at > now()->getTimestamp() + 30 || $data->expires_at <= $data->issued_at
            || $data->expires_at - $data->issued_at > 900 || $data->expires_at > $binding['token_expires']) {
            throw new DataFailure('cursor_invalid');
        }
        if ($data->expires_at <= now()->getTimestamp()) {
            throw new DataFailure('cursor_expired');
        }

        return ['position' => (array) $data->position, 'filters' => $resolved,
            'snapshot_revision_id' => $data->snapshot_revision_id, 'resolved_window' => $window];
    }

    private function decrypt(string $wire): stdClass
    {
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

            return $data;
        } catch (DataFailure $error) {
            throw $error;
        } catch (\Throwable) {
            throw new DataFailure('cursor_invalid');
        }
    }

    private function binding(DelegatedContext $context, string $operation, string $projection): array
    {
        $authority = $context->cursorAuthority();

        return ['resource_hash' => hash('sha256', config('mcp.resource')),
            'account_id' => (string) $context->accountId(), 'user_id' => (string) $context->actorId(),
            'grant_id' => $authority['grant_id'], 'authorization_hash' => $authority['authorization_hash'],
            'operation' => $operation, 'paged_projection' => $projection, 'token_expires' => $authority['expires_at']];
    }

    private function filterHash(array $filters): string
    {
        return hash('sha256', self::canonical((object) $filters));
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
