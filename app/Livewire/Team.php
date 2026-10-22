<?php

namespace App\Livewire;

use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Services\AccountContext;
use App\Services\Permissions;
use App\Services\TeamService;
use Livewire\Component;

class Team extends Component
{
    public string $email = '';

    public string $role = 'read-only';

    public array $scopes = [];

    public string $preset = '';

    public ?int $editing = null;

    public string $memberRole = 'read-only';

    public array $memberScopes = [];

    public string $transferPassword = '';

    public ?int $transferMember = null;

    public function updatedPreset(string $value): void
    {
        app(AccountContext::class)->authorizeAdmin();
        $this->scopes = Permissions::PRESETS[$value] ?? [];
        $this->role = 'restricted';
    }

    public function invite(TeamService $teams): void
    {
        $teams->invite($this->email, $this->role, $this->scopes);
        $this->resetValidation();
        $this->reset('email', 'role', 'scopes', 'preset');
        session()->flash('success', 'Invitación enviada. El enlace caduca en siete días.');
    }

    public function resend(int $id, TeamService $teams): void
    {
        $teams->resend($id);
        session()->flash('success', 'Se ha enviado un nuevo enlace. El anterior ya no es válido.');
    }

    public function revoke(int $id, TeamService $teams): void
    {
        $teams->revokeInvitation($id);
        session()->flash('success', 'Invitación revocada.');
    }

    public function edit(int $id, AccountContext $context): void
    {
        $member = $context->authorizeAdmin()->account->memberships()->findOrFail($id);
        $this->editing = $member->id;
        $this->memberRole = $member->role;
        $this->memberScopes = $member->scopes ?? [];
    }

    public function saveMember(TeamService $teams): void
    {
        abort_unless($this->editing, 422);
        $teams->updateMember($this->editing, $this->memberRole, $this->memberScopes);
        $this->resetValidation();
        $this->reset('editing', 'memberRole', 'memberScopes');
        session()->flash('success', 'Permisos actualizados. Se aplican en la siguiente acción.');
    }

    public function remove(int $id, TeamService $teams): void
    {
        $teams->removeMember($id);
        session()->flash('success', 'Acceso retirado. Los recursos de la cuenta se conservan.');
    }

    public function transfer(TeamService $teams): void
    {
        abort_unless($this->transferMember, 422);
        $password = $this->transferPassword;
        $this->reset('transferPassword');
        $teams->transfer($this->transferMember, $password);
        $this->resetValidation();
        $this->reset('transferMember');
        session()->flash('success', 'Propiedad transferida. Tu acceso de administrador se conserva.');
    }

    public function render(AccountContext $context)
    {
        $membership = $context->membership();
        $account = $membership->account;
        $admin = $membership->role === 'admin';

        return view('livewire.team', [
            'account' => $account,
            'admin' => $admin,
            'members' => $account->memberships()->with('user')->orderBy('id')->get(),
            'invitations' => $admin ? Invitation::where('account_id', $account->id)->latest()->limit(50)->get() : collect(),
            'events' => $admin ? AuditEvent::where('account_id', $account->id)->with('actor')->latest('id')->limit(30)->get() : collect(),
            'catalog' => Permissions::SCOPES,
        ])->layout('components.app-layout', ['title' => 'Teammates']);
    }
}
