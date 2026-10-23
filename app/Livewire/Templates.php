<?php

namespace App\Livewire;

use App\Services\AccountContext;
use App\Services\Permissions;
use App\Services\TemplateService;
use Livewire\Component;
use Livewire\WithPagination;

class Templates extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $editing = null;

    public bool $editorOpen = false;

    public string $name = '';

    public string $subject = 'Hola {{ contact.name }}';

    public string $html = '<h1>Hola {{ contact.name }}</h1><p>Un mensaje de {{ account.name }}.</p>';

    public string $text = 'Hola {{ contact.name }}. Un mensaje de {{ account.name }}.';

    public string $sampleName = 'Ana';

    public string $sampleEmail = 'ana@example.test';

    public bool $previewOpen = false;

    public ?int $senderId = null;

    public string $recipient = '';

    private function fields(): array
    {
        return ['name' => $this->name, 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text];
    }

    private function sample(): array
    {
        return ['name' => $this->sampleName, 'email' => $this->sampleEmail ?: null];
    }

    public function updatedSearch(): void
    {
        app(AccountContext::class)->authorize('templates.read');
        $this->resetPage();
    }

    public function create(): void
    {
        app(AccountContext::class)->authorize('templates.read');
        app(AccountContext::class)->authorize('templates.write');
        $this->resetValidation();
        $this->reset('editing', 'name', 'subject', 'html', 'text', 'previewOpen', 'senderId', 'recipient');
        $this->editorOpen = true;
    }

    public function open(int $id, TemplateService $templates): void
    {
        $template = $templates->template($id);
        $this->resetValidation();
        $this->editing = $template->id;
        $this->name = $template->name;
        $this->subject = $template->subject;
        $this->html = $template->html;
        $this->text = $template->text;
        $this->previewOpen = false;
        $this->editorOpen = true;
    }

    public function save(TemplateService $templates): void
    {
        $template = $templates->save($this->editing, $this->fields());
        $this->editing = $template->id;
        $this->previewOpen = false;
        $this->resetValidation();
        session()->flash('success', 'Plantilla guardada y compartida con la cuenta.');
    }

    public function delete(int $id, TemplateService $templates): void
    {
        $templates->delete($id);
        $this->resetValidation();
        if ($this->editing === $id) {
            $this->reset('editing', 'editorOpen', 'previewOpen', 'name', 'subject', 'html', 'text');
        }
        $this->resetPage();
        session()->flash('success', 'Plantilla eliminada. Los mensajes creados anteriormente se conservan.');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['name', 'subject', 'html', 'text', 'sampleName', 'sampleEmail'], true)) {
            app(AccountContext::class)->authorize('templates.read');
            $this->previewOpen = false;
        }
    }

    public function preview(TemplateService $templates): void
    {
        $this->previewOpen = false;
        $templates->preview($this->editing, $this->fields(), $this->sample());
        $this->previewOpen = true;
        $this->resetValidation();
    }

    public function test(TemplateService $templates): void
    {
        app(AccountContext::class)->authorize('templates.read');
        app(AccountContext::class)->authorize('mail.send');
        $this->validate(['editing' => ['required', 'integer'], 'senderId' => ['required', 'integer']]);
        $message = $templates->test($this->editing, $this->senderId, $this->recipient, $this->sample());
        $this->resetValidation();
        session()->flash('success', match ($message->status) {
            'logged' => 'Prueba guardada en log privado. No se conectó a SMTP.',
            'smtp_accepted' => 'SMTP aceptado por Mailpit. No acredita entrega al destinatario.',
            'smtp_unconfirmed' => 'Aceptación SMTP incierta. No reenvíes automáticamente.',
            'blocked' => 'Correo externo bloqueado. Usa log o Mailpit.',
            'suppressed' => 'Destinatario suprimido. No se inició el transporte.',
            default => 'No se pudo guardar la prueba en el archivo privado.',
        });
    }

    public function render(TemplateService $templates, AccountContext $context)
    {
        $member = $context->authorize('templates.read');
        if ($this->editing !== null) {
            $templates->template($this->editing);
        }
        $canSend = Permissions::allows($member, 'mail.send');

        return view('livewire.templates', [
            'templates' => $templates->templates($this->search)->paginate(10),
            'canWrite' => Permissions::allows($member, 'templates.write'),
            'canSend' => $canSend,
            'senders' => $canSend && $this->editorOpen ? $templates->senders()->paginate(10, ['*'], 'sendersPage') : null,
            'preview' => $this->previewOpen ? $templates->preview($this->editing, $this->fields(), $this->sample()) : null,
        ])->layout('components.app-layout', ['title' => 'Plantillas']);
    }
}
