<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\McpClient;
use App\Models\McpGrant;
use App\Models\Membership;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Services\Mcp\Audit;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function __construct(private AccountContext $context) {}

    public function grants(string $role, array $scopes): array
    {
        Validator::make(['role' => $role, 'scopes' => $scopes], [
            'role' => ['required', Rule::in(['admin', 'read-only', 'restricted'])],
            'scopes' => ['array', 'max:'.count(Permissions::SCOPES)],
            'scopes.*' => ['string', 'distinct', Rule::in(array_keys(Permissions::SCOPES))],
        ])->validate();

        return $role === 'restricted' ? array_values($scopes) : [];
    }

    private function adminAccount(): Account
    {
        $membership = $this->context->authorizeAdmin();
        // The account lock serializes ownership, role changes and invitation acceptance.
        $account = Account::whereKey($membership->account_id)->lockForUpdate()->firstOrFail();
        // Locking reads observe committed changes under MySQL REPEATABLE READ.
        $current = Membership::where('account_id', $account->id)->where('user_id', auth()->id())->lockForUpdate()->first();
        abort_unless($current && $current->role === 'admin', 403, 'Ya no tienes permiso para administrar este equipo.');

        return $account;
    }

    private function audit(Account $account, string $action, array $metadata = []): void
    {
        AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => $action, 'metadata' => $metadata]);
    }

    public function invite(string $email, string $role, array $scopes): Invitation
    {
        $scopes = $this->grants($role, $scopes);
        $email = mb_strtolower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:254']])->validate();
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($email, $role, $scopes, $token) {
            $account = $this->adminAccount();
            if ($account->memberships()->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
                throw ValidationException::withMessages(['email' => 'Esta persona ya pertenece al equipo.']);
            }
            Invitation::where('account_id', $account->id)->where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $invitation = Invitation::create(['account_id' => $account->id, 'invited_by' => auth()->id(), 'email' => $email, 'role' => $role, 'scopes' => $scopes, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            $this->audit($account, 'teammate.invited', ['invitation_id' => $invitation->id, 'role' => $role, 'scopes' => $scopes]);

            return $invitation;
        });
        Notification::route('mail', $email)->notify(new TeamInvitation($invitation->account->name, $token));

        return $invitation;
    }

    public function resend(int $id): void
    {
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($id, $token) {
            $account = $this->adminAccount();
            $invitation = Invitation::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            abort_if($invitation->accepted_at || $invitation->revoked_at, 409, 'Esta invitación ya no está disponible.');
            $invitation->update(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7)]);
            $this->audit($account, 'invitation.resent', ['invitation_id' => $id]);

            return $invitation;
        });
        Notification::route('mail', $invitation->email)->notify(new TeamInvitation($invitation->account->name, $token));
    }

    public function revokeInvitation(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->adminAccount();
            $invitation = Invitation::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            abort_if($invitation->accepted_at, 409);
            if (! $invitation->revoked_at) {
                $invitation->update(['revoked_at' => now()]);
                $this->audit($account, 'invitation.revoked', ['invitation_id' => $id]);
            }
        });
    }

    public function pending(string $token): Invitation
    {
        abort_unless(strlen($token) === 64, 404);
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->with('account')->firstOrFail();
        abort_unless($invitation->pending(), 410, 'La invitación ha caducado o ya no está disponible.');

        return $invitation;
    }

    public function accept(string $token, User $user): Membership
    {
        $candidate = $this->pending($token);

        return DB::transaction(function () use ($candidate, $token, $user) {
            $account = Account::whereKey($candidate->account_id)->lockForUpdate()->firstOrFail();
            $invitation = Invitation::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)) && $invitation->pending(), 410);
            abort_unless(hash_equals($invitation->email, mb_strtolower($user->email)), 403, 'Accede con la dirección que recibió la invitación.');
            $membership = Membership::firstOrCreate(['account_id' => $account->id, 'user_id' => $user->id], ['role' => $invitation->role, 'scopes' => $invitation->scopes]);
            $invitation->update(['accepted_at' => now()]);
            $this->audit($account, 'invitation.accepted', ['invitation_id' => $invitation->id, 'membership_id' => $membership->id]);

            return $membership;
        });
    }

    public function updateMember(int $id, string $role, array $scopes): void
    {
        $scopes = $this->grants($role, $scopes);
        DB::transaction(function () use ($id, $role, $scopes) {
            $account = $this->adminAccount();
            $member = Membership::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            if ($member->user_id === $account->owner_id || ($member->role === 'admin' && $role !== 'admin' && $account->memberships()->where('role', 'admin')->count() <= 1)) {
                throw ValidationException::withMessages(['member' => 'No puedes reducir los permisos del propietario ni del último administrador.']);
            }
            $member->update(['role' => $role, 'scopes' => $scopes]);
            $this->audit($account, 'teammate.permissions_changed', ['membership_id' => $id, 'role' => $role, 'scopes' => $scopes]);
        });
    }

    public function removeMember(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->adminAccount();
            $member = Membership::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            if ($member->user_id === $account->owner_id || ($member->role === 'admin' && $account->memberships()->where('role', 'admin')->count() <= 1)) {
                throw ValidationException::withMessages(['member' => 'No puedes eliminar al propietario ni al último administrador.']);
            }
            $member->delete();
            // Membership removal is terminal for these grants, even if re-invited later.
            // Account and membership locks above precede client -> grant -> token.
            foreach ($this->grantClientsForRemoval($account->id, $member->user_id) as $clientId) {
                if (! McpClient::whereKey($clientId)->lockForUpdate()->first()) {
                    continue;
                }
                $grants = McpGrant::where('account_id', $account->id)->where('user_id', $member->user_id)
                    ->where('client_id', $clientId)->whereNull('revoked_at')->orderBy('id')->lockForUpdate()->get();
                if ($grants->isEmpty()) {
                    continue;
                }
                $tokens = PersonalAccessToken::whereIn('grant_id', $grants->modelKeys())->orderBy('id')->lockForUpdate()->get();
                $revokedAt = now();
                McpGrant::whereKey($grants->modelKeys())->update(['revoked_at' => $revokedAt]);
                if ($tokens->isNotEmpty()) {
                    PersonalAccessToken::whereKey($tokens->modelKeys())->whereNull('revoked_at')->update(['revoked_at' => $revokedAt]);
                    DB::table('mcp_sessions')->whereIn('token_id', $tokens->modelKeys())->delete();
                }
                foreach ($grants as $grant) {
                    app(Audit::class)->record($this->context->actorId(), $account->id, $grant->client_id, $grant->id, 'connection.revoke', 'revoked');
                }
            }
            $this->audit($account, 'teammate.removed', ['membership_id' => $id, 'user_id' => $member->user_id]);
        });
    }

    private function grantClientsForRemoval(int $accountId, int $userId): array
    {
        $clients = static fn (ConnectionInterface $connection): array => $connection->table('mcp_grants')
            ->where('account_id', $accountId)->where('user_id', $userId)->whereNull('revoked_at')->distinct()->pluck('client_id')->all();
        $main = DB::connection();
        // Keep the caller's own uncommitted grants, even inside an older RR view.
        $ids = $clients($main);
        $configuration = $main->getConfig();
        $name = $main->getName().'-mcp-membership-current';
        $configuration['name'] = $name;
        unset($configuration['read'], $configuration['write'], $configuration['sticky']);
        try {
            // A fresh primary read sees grants committed while the account lock
            // was awaited. It takes no grant locks before the ordered client lock.
            $ids = array_merge($ids, $clients(DB::build($configuration)));
        } finally {
            DB::purge($name);
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }

    public function transfer(int $id, string $password): void
    {
        DB::transaction(function () use ($id, $password) {
            $account = $this->adminAccount();
            abort_unless($account->owner_id === auth()->id(), 403);
            if (! Hash::check($password, auth()->user()->password)) {
                throw ValidationException::withMessages(['transferPassword' => 'La contraseña no es correcta.']);
            }
            $member = Membership::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            abort_if($member->user_id === auth()->id(), 422);
            $member->update(['role' => 'admin', 'scopes' => []]);
            $previous = $account->owner_id;
            $account->update(['owner_id' => $member->user_id]);
            $this->audit($account, 'account.ownership_transferred', ['previous_owner_id' => $previous, 'owner_id' => $member->user_id]);
        });
    }
}
