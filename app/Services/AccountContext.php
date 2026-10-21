<?php
namespace App\Services;
use App\Models\Membership;

class AccountContext
{
    public function membership(): Membership
    {
        abort_unless(auth()->check(), 401);
        $id = session('account_id');
        $query = auth()->user()->memberships()->with('account');
        $member = $id ? $query->where('account_id', $id)->first() : $query->orderBy('id')->first();
        abort_unless($member, 403, 'Ya no tienes acceso a esta cuenta. Selecciona otra cuenta disponible.');
        session(['account_id'=>$member->account_id]);
        return $member;
    }
    public function account(): \App\Models\Account { return $this->membership()->account; }
    public function authorizeAdmin(): Membership
    {
        $member = $this->membership();
        abort_unless($member->role === 'admin', 403, 'No tienes permiso para administrar este equipo.');
        return $member;
    }
}
