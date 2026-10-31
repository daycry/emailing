<?php

namespace App\Livewire;

use App\Enums\MailTransportMode;
use App\Models\MailProviderConnection;
use App\Services\AccountContext;
use App\Services\MailSettingsService;
use App\Services\Permissions;
use App\Support\MailResult;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\WithPagination;

class MailSettings extends Component
{
    use WithPagination;

    public ?int $editingSender = null;

    public bool $senderForm = false;

    public string $senderEmail = '';

    public string $senderName = '';

    public string $senderReplyTo = '';

    public bool $labAuthorized = false;

    public bool $externalAuthorized = false;

    public ?int $testSender = null;

    public string $testRecipient = '';

    public string $testSubject = 'Prueba del laboratorio';

    public ?int $lastMessage = null;

    public function saveSettings($input, MailSettingsService $settings): void
    {
        $this->authorizeWrite();
        // Credentials exist only in action arguments, never in hydrated component state.
        Validator::make(['settings' => $input], ['settings' => ['required', 'array']])->validate();
        try {
            $settings->saveSettings($input);
            $this->resetValidation();
            session()->flash('success', 'Configuración guardada. El envío usa el modo seleccionado si está preparado y habilitado.');
        } finally {
            unset($input['smtp_username'], $input['smtp_password']);
        }
    }

    private function authorizeWrite(): void
    {
        app(AccountContext::class)->authorize('settings.read');
        app(AccountContext::class)->authorize('settings.write');
    }

    public function saveProviderSettings(string $provider, #[\SensitiveParameter] $input, MailSettingsService $settings): void
    {
        $this->authorizeWrite();
        Validator::make(['settings' => $input], ['settings' => ['required', 'array']])->validate();
        try {
            $settings->saveProviderSettings($provider, $input);
            $this->resetValidation();
            session()->flash('success', 'Configuración del proveedor guardada. El envío requiere el canal preparado y un remitente autorizado.');
        } finally {
            foreach (MailProviderConnection::SECRET_FIELDS as $field) {
                unset($input[$field]);
            }
        }
    }

    public function newSender(): void
    {
        $this->authorizeWrite();
        $this->resetValidation();
        $this->reset('editingSender', 'senderEmail', 'senderName', 'senderReplyTo', 'labAuthorized', 'externalAuthorized');
        $this->senderForm = true;
    }

    public function editSender(int $id, MailSettingsService $settings): void
    {
        $this->authorizeWrite();
        $sender = $settings->senders()->findOrFail($id);
        $this->editingSender = $sender->id;
        $this->senderEmail = $sender->email;
        $this->senderName = $sender->name;
        $this->senderReplyTo = $sender->reply_to ?? '';
        $this->labAuthorized = $sender->lab_authorized;
        $this->externalAuthorized = $sender->external_authorized;
        $this->senderForm = true;
        $this->resetValidation();
    }

    public function saveSender(MailSettingsService $settings): void
    {
        $settings->saveSender($this->editingSender, ['email' => $this->senderEmail, 'name' => $this->senderName, 'reply_to' => $this->senderReplyTo ?: null, 'lab_authorized' => $this->labAuthorized, 'external_authorized' => $this->externalAuthorized]);
        $this->resetValidation();
        $this->reset('senderForm', 'editingSender', 'senderEmail', 'senderName', 'senderReplyTo', 'labAuthorized', 'externalAuthorized');
        session()->flash('success', 'Remitente guardado. La autorización interna no implica verificación del dominio por el proveedor.');
    }

    public function deleteSender(int $id, MailSettingsService $settings): void
    {
        $settings->deleteSender($id);
        $this->resetValidation();
        $this->reset('senderForm', 'editingSender');
        session()->flash('success', 'Remitente retirado. Sus mensajes anteriores se conservan.');
    }

    public function test(MailSettingsService $settings): void
    {
        app(AccountContext::class)->authorize('settings.read');
        app(AccountContext::class)->authorize('mail.send');
        $this->validate(['testSender' => ['required', 'integer']]);
        $message = $settings->test($this->testSender, ['recipient' => $this->testRecipient, 'subject' => $this->testSubject, 'text' => 'Prueba de transporte del laboratorio.']);
        $this->lastMessage = $message->id;
        $this->resetValidation();
        session()->flash('success', MailResult::notice($message));
    }

    public function render(MailSettingsService $settings, AccountContext $context)
    {
        $member = $context->authorize('settings.read');
        $connections = $settings->providerConnections()->keyBy('provider');
        $profiles = [];
        foreach (MailProviderConnection::PROVIDERS as $provider) {
            $connection = $connections->get($provider);
            $ready = [];
            foreach (['api', 'smtp'] as $channel) {
                try {
                    $ready[$channel] = $connection?->isConfiguredFor(MailTransportMode::from($provider.'_'.$channel)) ?? false;
                } catch (DecryptException) {
                    $ready[$channel] = false;
                }
            }
            $profiles[$provider] = ['id' => $connection?->id, 'enabled' => $connection?->enabled ?? false,
                'smtp_security' => $connection?->smtp_security ?? 'starttls', 'ready' => $ready,
                'has_secrets' => $connection !== null && collect(MailProviderConnection::SECRET_FIELDS)->contains(fn ($field) => $connection->getRawOriginal($field) !== null)];
        }

        return view('livewire.mail-settings', [
            'settings' => $settings->settings(),
            'providerProfiles' => $profiles,
            'externalEnabled' => (bool) config('emailing.external_enabled'),
            'modes' => MailTransportMode::labels(),
            'senders' => $settings->senders()->paginate(10, ['*'], 'sendersPage'),
            'canWrite' => Permissions::allows($member, 'settings.write'),
            'canSend' => Permissions::allows($member, 'mail.send'),
            'canReadActivity' => Permissions::allows($member, 'activity.read'),
            'messages' => Permissions::allows($member, 'activity.read') ? $settings->messages()->paginate(10, ['*'], 'messagesPage') : null,
        ])->layout('components.app-layout', ['title' => 'Transporte y remitentes']);
    }
}
