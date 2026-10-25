<?php

namespace App\Services\Auth;

use App\Models\ExternalIdentity;
use App\Models\IdentityAuditEvent;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleIdentityResolver
{
    public const ISSUER = 'https://accounts.google.com';

    public function resolve(array $identity): ?User
    {
        $key = $this->key($identity);

        return DB::transaction(function () use ($key) {
            $candidate = ExternalIdentity::where($key)->first();
            if (! $candidate) {
                return null; // Never search users by provider email.
            }
            $user = User::whereKey($candidate->user_id)->lockForUpdate()->first();
            $linked = ExternalIdentity::where($key)->where('user_id', $user?->id)->lockForUpdate()->first();
            if (! $user || ! $linked) {
                return null;
            }
            $linked->update(['last_used_at' => now()]);
            $this->audit($user, 'resolved');

            return $user;
        }, 5);
    }

    /** The actor must come from the authenticated web guard, never an account selector. */
    public function link(User $actor, array $identity): ExternalIdentity
    {
        $key = $this->key($identity);
        try {
            return DB::transaction(function () use ($actor, $key) {
                $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
                $linked = ExternalIdentity::where($key)->lockForUpdate()->first();
                if ($linked && $linked->user_id !== $user->id) {
                    $this->reject();
                }
                if ($linked) {
                    return $linked;
                }
                $linked = ExternalIdentity::create($key + ['user_id' => $user->id]);
                $this->audit($user, 'linked');

                return $linked;
            }, 5);
        } catch (UniqueConstraintViolationException) {
            // A competing owner won the unique key. The failed transaction has rolled back.
            $this->reject();
        }
    }

    public function unlink(User $actor, int $identityId): void
    {
        DB::transaction(function () use ($actor, $identityId) {
            $user = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $linked = ExternalIdentity::whereKey($identityId)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $linked || ! $user->local_password_enabled) {
                $this->reject();
            }
            $linked->delete();
            $this->audit($user, 'unlinked');
        }, 5);
    }

    private function key(array $identity): array
    {
        if (($identity['iss'] ?? null) !== self::ISSUER || ! is_string($identity['sub'] ?? null)
            || ! preg_match('/\A[!-~]{1,255}\z/D', $identity['sub'])) {
            $this->reject();
        }

        return ['provider' => 'google', 'issuer' => self::ISSUER, 'subject' => $identity['sub']];
    }

    private function audit(User $user, string $action): void
    {
        IdentityAuditEvent::create(['user_id' => $user->id, 'provider' => 'google', 'issuer' => self::ISSUER,
            'action' => $action, 'outcome' => 'success', 'correlation_id' => (string) Str::uuid()]);
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['google' => 'No se pudo completar la operación con Google. Comprueba tu acceso local e inténtalo de nuevo.']);
    }
}
