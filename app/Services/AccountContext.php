<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Membership;
use App\Support\ExecutionContext;
use Illuminate\Support\Facades\DB;

class AccountContext
{
    public function __construct(private ?ExecutionContext $execution = null) {}

    public function actorId(): int
    {
        if ($this->execution !== null) {
            return $this->execution->actorId();
        }
        abort_unless(auth()->check(), 401);

        return (int) auth()->id();
    }

    public function provenance(): array
    {
        return $this->execution?->provenance() ?? [];
    }

    /** Caller owns a transaction; account is always locked before current authority. */
    public function writable(array $scopes): Account
    {
        abort_unless(DB::transactionLevel() > 0, 500);
        if ($this->execution !== null) {
            return $this->execution->authorize($scopes, true)->account;
        }
        $member = $this->membership();
        $account = Account::whereKey($member->account_id)->lockForUpdate()->firstOrFail();
        $current = Membership::where('account_id', $account->id)->where('user_id', $this->actorId())->lockForUpdate()->first();
        foreach ($scopes as $scope) {
            abort_unless($current && Permissions::allows($current, $scope), 403);
        }

        return $account;
    }

    public function membership(): Membership
    {
        if ($this->execution !== null) {
            return $this->execution->authorize();
        }
        abort_unless(auth()->check(), 401);
        $id = session('account_id');
        $query = auth()->user()->memberships()->with('account');
        $member = $id ? $query->where('account_id', $id)->first() : $query->orderBy('id')->first();
        abort_unless($member, 403, 'Ya no tienes acceso a esta cuenta. Selecciona otra cuenta disponible.');
        session(['account_id' => $member->account_id]);

        return $member;
    }

    public function account(): Account
    {
        return $this->membership()->account;
    }

    public function authorize(string $scope): Membership
    {
        if ($this->execution !== null) {
            return $this->execution->authorize([$scope]);
        }
        $member = $this->membership();
        abort_unless(Permissions::allows($member, $scope), 403, 'No tienes permiso para realizar esta acción.');

        return $member;
    }

    public function authorizeAdmin(): Membership
    {
        // MCP never delegates team/settings/API-key administration.
        abort_if($this->execution !== null, 403);
        $member = $this->membership();
        abort_unless($member->role === 'admin', 403, 'No tienes permiso para administrar este equipo.');

        return $member;
    }
}
