<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\Suppression;
use App\Models\UnsubscribeToken;
use App\Support\ExecutionContext;
use App\Support\MailInput;
use App\Support\TrustedUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Explicit account and actor; no HTTP/session dependency in the domain service.
class SuppressionService
{
    public function __construct(private ?ExecutionContext $execution = null) {}

    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private function email(array $input): string
    {
        Validator::make($input, ['email' => ['bail', 'required', 'string', 'max:254']])->validate();
        $email = self::normalize($input['email']);
        Validator::make(['email' => $email], ['email' => MailInput::emailRules()])->validate();

        return $email;
    }

    public function authorize(Account $account, int $actorId, bool $write = false): void
    {
        if ($this->execution !== null) {
            abort_unless($this->execution->accountId() === $account->id && $this->execution->actorId() === $actorId, 403);
            $this->execution->authorize($write ? ['suppressions.read', 'suppressions.write'] : ['suppressions.read'], $write);

            return;
        }
        $member = Membership::where('account_id', $account->id)->where('user_id', $actorId)->when($write, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($member && Permissions::allows($member, 'suppressions.read') && (! $write || Permissions::allows($member, 'suppressions.write')), 403);
    }

    private function writable(Account $account, int $actorId): void
    {
        Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
        $this->authorize($account, $actorId, true);
    }

    public function suppressions(Account $account, int $actorId): Builder
    {
        $this->authorize($account, $actorId);

        return Suppression::where('account_id', $account->id)->orderByDesc('id');
    }

    public function isSuppressed(Account $account, string $email): bool
    {
        return Suppression::where('account_id', $account->id)->where('normalized_email', self::normalize($email))->exists();
    }

    private function apply(Account $account, string $email, string $source, ?int $actorId): Suppression
    {
        $suppression = Suppression::firstOrCreate(['account_id' => $account->id, 'normalized_email' => $email], ['source' => $source]);
        if ($suppression->wasRecentlyCreated) {
            $this->audit($account, $actorId, 'suppression.created', ['suppression_id' => $suppression->id, 'source' => $source]);
        }

        return $suppression;
    }

    public function suppress(Account $account, int $actorId, array $input): Suppression
    {
        return DB::transaction(function () use ($account, $actorId, $input) {
            $this->writable($account, $actorId);

            return $this->apply($account, $this->email($input), 'manual', $actorId);
        });
    }

    public function remove(Account $account, int $actorId, int $id): void
    {
        DB::transaction(function () use ($account, $actorId, $id) {
            $this->writable($account, $actorId);
            Suppression::where('account_id', $account->id)->findOrFail($id)->delete();
            $this->audit($account, $actorId, 'suppression.removed', ['suppression_id' => $id]);
        });
    }

    public function issue(Account $account, int $actorId, array $input): string
    {
        return DB::transaction(function () use ($account, $actorId, $input) {
            $this->writable($account, $actorId);
            $email = $this->email($input);
            $token = bin2hex(random_bytes(32));
            $record = UnsubscribeToken::create(['account_id' => $account->id, 'normalized_email' => $email, 'token_hash' => hash('sha256', $token)]);
            $this->audit($account, $actorId, 'unsubscribe.issued', ['capability_id' => $record->id]);

            return TrustedUrl::signedRoute('unsubscribe.show', ['token' => $token]);
        });
    }

    public function revoke(Account $account, int $actorId, int $id): void
    {
        DB::transaction(function () use ($account, $actorId, $id) {
            $this->writable($account, $actorId);
            $token = UnsubscribeToken::where('account_id', $account->id)->findOrFail($id);
            if ($token->revoked_at === null) {
                $token->update(['revoked_at' => now()]);
                $this->audit($account, $actorId, 'unsubscribe.revoked', ['capability_id' => $id]);
            }
        });
    }

    public function available(string $token): bool
    {
        return UnsubscribeToken::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->exists();
    }

    public function unsubscribe(string $token): bool
    {
        $reference = UnsubscribeToken::where('token_hash', hash('sha256', $token))->first();
        if (! $reference) {
            return false;
        }

        return DB::transaction(function () use ($reference) {
            $account = Account::whereKey($reference->account_id)->lockForUpdate()->first();
            $current = UnsubscribeToken::whereKey($reference->id)->whereNull('revoked_at')->lockForUpdate()->first();
            if (! $account || ! $current) {
                return false;
            }
            $this->apply($account, $current->normalized_email, 'unsubscribe', null);

            return true;
        });
    }

    private function audit(Account $account, ?int $actorId, string $action, array $metadata): void
    {
        AuditEvent::create(['account_id' => $account->id, 'actor_id' => $actorId, 'action' => $action, 'metadata' => $metadata + ($this->execution?->provenance() ?? [])]);
    }
}
