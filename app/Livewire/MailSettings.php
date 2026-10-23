<?php

namespace App\Livewire;

use App\Services\AccountContext;
use App\Services\MailSettingsService;
use App\Services\Permissions;
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
            session()->flash('success', 'Configuración guardada. El correo externo sigue bloqueado.');
        } finally {
            unset($input['smtp_username'], $input['smtp_password']);
        }
    }

    private function authorizeWrite(): void
    {
        app(AccountContext::class)->authorize('settings.read');
        app(AccountContext::class)->authorize('settings.write');
    }

    public function newSender(): void
    {
        $this->authorizeWrite();
        $this->resetValidation();
        $this->reset('editingSender', 'senderEmail', 'senderName', 'senderReplyTo', 'labAuthorized');
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
        $this->senderForm = true;
        $this->resetValidation();
    }

    public function saveSender(MailSettingsService $settings): void
    {
        $settings->saveSender($this->editingSender, ['email' => $this->senderEmail, 'name' => $this->senderName, 'reply_to' => $this->senderReplyTo ?: null, 'lab_authorized' => $this->labAuthorized]);
        $this->resetValidation();
        $this->reset('senderForm', 'editingSender', 'senderEmail', 'senderName', 'senderReplyTo', 'labAuthorized');
        session()->flash('success', 'Remitente guardado para el laboratorio. No implica verificación de dominio externo.');
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
        session()->flash('success', match ($message->status) {
            'logged' => 'Mensaje registrado en un archivo privado. No se conectó a SMTP.',
            'smtp_accepted' => 'SMTP aceptado por Mailpit. No acredita entrega al destinatario.',
            'smtp_unconfirmed' => 'Aceptación SMTP no confirmada. No reenvíes automáticamente.',
            'blocked' => 'Correo externo bloqueado. Configura log o Mailpit.',
            'suppressed' => 'Destinatario suprimido. No se inició el transporte.',
            default => 'No se pudo guardar el archivo privado del mensaje.',
        });
    }

    public function render(MailSettingsService $settings, AccountContext $context)
    {
        $member = $context->authorize('settings.read');

        return view('livewire.mail-settings', [
            'settings' => $settings->settings(),
            'senders' => $settings->senders()->paginate(10, ['*'], 'sendersPage'),
            'canWrite' => Permissions::allows($member, 'settings.write'),
            'canSend' => Permissions::allows($member, 'mail.send'),
            'canReadActivity' => Permissions::allows($member, 'activity.read'),
            'messages' => Permissions::allows($member, 'activity.read') ? $settings->messages()->paginate(10, ['*'], 'messagesPage') : null,
        ])->layout('components.app-layout', ['title' => 'Transporte y remitentes']);
    }
}
