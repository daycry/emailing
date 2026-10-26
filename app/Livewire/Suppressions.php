<?php

namespace App\Livewire;

use App\Models\UnsubscribeToken;
use App\Services\AccountContext;
use App\Services\Permissions;
use App\Services\SuppressionService;
use Livewire\Component;
use Livewire\WithPagination;

class Suppressions extends Component
{
    use WithPagination;

    public string $email = '';

    public function boot(AccountContext $context): void
    {
        $context->authorize('suppressions.read');
    }

    public function suppress(AccountContext $context, SuppressionService $service): void
    {
        $service->suppress($context->account(), auth()->id(), ['email' => $this->email]);
        $this->resetValidation();
        $this->reset('email');
        $this->resetPage();
        session()->flash('success', 'Supresión guardada. Los transportes que todavía no han empezado omitirán esta dirección.');
    }

    public function remove(int $id, AccountContext $context, SuppressionService $service): void
    {
        $service->remove($context->account(), auth()->id(), $id);
        $this->resetValidation();
        $this->resetPage();
        session()->flash('success', 'Supresión retirada explícitamente. Esto no concede consentimiento.');
    }

    public function revoke(int $id, AccountContext $context, SuppressionService $service): void
    {
        $service->revoke($context->account(), auth()->id(), $id);
        $this->resetValidation();
        session()->flash('success', 'Enlace revocado. Las supresiones existentes se conservan.');
    }

    public function render(AccountContext $context, SuppressionService $service)
    {
        $member = $context->authorize('suppressions.read');

        return view('livewire.suppressions', [
            'account' => $member->account,
            'canWrite' => Permissions::allows($member, 'suppressions.write'),
            'suppressions' => $service->suppressions($member->account, auth()->id())->paginate(15),
            'capabilities' => UnsubscribeToken::where('account_id', $member->account_id)->select(['id', 'normalized_email', 'created_at', 'revoked_at'])->orderByDesc('id')->paginate(10, ['*'], 'capabilitiesPage'),
        ])->layout('components.app-layout', ['title' => 'Bajas y supresiones']);
    }
}
