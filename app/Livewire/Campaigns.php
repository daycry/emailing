<?php

namespace App\Livewire;

use App\Models\CampaignRecipient;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Services\AccountContext;
use App\Services\CampaignService;
use App\Services\Permissions;
use App\Support\TemplatePreview;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Campaigns extends Component
{
    use WithPagination;

    #[Locked]
    public int $accountId;

    #[Locked]
    public ?int $editing = null;

    #[Locked]
    public int $version = 0;

    #[Locked]
    public ?int $revisionId = null;

    #[Locked]
    public bool $compositionChanged = false;

    public bool $editorOpen = false;

    public string $name = '';

    public ?int $templateId = null;

    public ?int $senderId = null;

    public array $listIds = [];

    public array $contactIds = [];

    public bool $confirmed = false;

    // Request-local only: an explicit confirmation in this batch must survive
    // the deferred updated hooks for its timing fields.
    private bool $confirmationSubmitted = false;

    public string $timing = 'now';

    public string $effectiveUtc = '';

    public function mount(AccountContext $context): void
    {
        $this->accountId = $context->authorize('campaigns.read')->account_id;
    }

    private function identity(): array
    {
        $member = app(AccountContext::class)->authorize('campaigns.read');
        abort_unless($member->account_id === $this->accountId, 409, 'La cuenta activa cambió. Vuelve a abrir Campañas.');

        return [$member->account, (int) auth()->id()];
    }

    public function create(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        $service->access($account, $actor, ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']);
        $this->reset('editing', 'version', 'revisionId', 'compositionChanged', 'name', 'templateId', 'senderId', 'listIds', 'contactIds', 'confirmed', 'timing', 'effectiveUtc');
        $this->resetValidation();
        $this->resetPage('recipientsPage');
        $this->editorOpen = true;
    }

    public function open(int $id, CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        $campaign = $service->campaign($account, $actor, $id);
        $member = $service->access($account, $actor);
        $this->editing = $campaign->id;
        $this->version = $campaign->version;
        $this->revisionId = $campaign->reviewed_revision_id;
        $this->name = $campaign->name;
        $this->templateId = Permissions::allows($member, 'templates.read') ? $campaign->template_id : null;
        $this->senderId = Permissions::allows($member, 'mail.send') ? $campaign->sender_id : null;
        $this->listIds = Permissions::allows($member, 'contacts.read') ? $campaign->list_ids : [];
        $this->contactIds = Permissions::allows($member, 'contacts.read') ? $campaign->contact_ids : [];
        $this->reset('confirmed', 'compositionChanged', 'timing', 'effectiveUtc');
        $this->resetPage('recipientsPage');
        $this->resetValidation();
        $this->editorOpen = true;
    }

    public function save(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        $input = ['name' => $this->name, 'template_id' => $this->templateId, 'list_ids' => $this->listIds, 'contact_ids' => $this->contactIds];
        if (Permissions::allows($service->access($account, $actor), 'mail.send')) {
            $input['sender_id'] = $this->senderId;
        }
        $campaign = $service->save($account, $actor, $this->editing, $this->version, $input);
        $this->open($campaign->id, $service);
        session()->flash('success', 'Borrador guardado. Revisa la versión guardada antes de autorizar.');
    }

    public function clearAudience(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        $service->access($account, $actor, ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']);
        if ($this->editing !== null) {
            $campaign = $service->campaign($account, $actor, $this->editing);
            abort_unless($campaign->version === $this->version && in_array($campaign->status, ['draft', 'reviewed'], true), 409, 'La campaña cambió. Vuelve a abrirla antes de cambiar la selección.');
        }
        // Explicit form change only; the saved campaign and its revision remain intact.
        $this->reset('listIds', 'contactIds', 'confirmed');
        $this->compositionChanged = true;
    }

    public function review(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        abort_unless($this->editing !== null, 409, 'Guarda el borrador antes de revisar.');
        $campaign = $service->review($account, $actor, $this->editing, $this->version);
        $this->open($campaign->id, $service);
        session()->flash('success', 'Resumen de la versión guardada preparado. Comprueba contenido, audiencia y consentimiento antes de autorizar.');
    }

    public function authorizeCampaign(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        abort_unless($this->editing !== null && $this->revisionId !== null, 409);
        abort_unless(! $this->compositionChanged, 409, 'Guarda y revisa los cambios o vuelve a abrir la versión guardada.');
        $this->validate(['timing' => ['required', 'in:now,future'], 'confirmed' => ['accepted']]);
        $campaign = $service->authorizeCampaign($account, $actor, $this->editing, $this->version, $this->revisionId, $this->confirmed, $this->timing === 'future' ? $this->effectiveUtc : null);
        $this->open($campaign->id, $service);
        session()->flash('success', 'Autorización registrada con fecha efectiva UTC. Consulta el estado para seguir su procesamiento.');
    }

    public function pause(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        abort_unless($this->editing !== null, 409);
        $campaign = $service->pause($account, $actor, $this->editing, $this->version);
        $this->open($campaign->id, $service);
        session()->flash('success', 'Campaña pausada. La pausa impide nuevos arranques; un transporte ya iniciado puede terminar.');
    }

    public function resume(CampaignService $service): void
    {
        [$account, $actor] = $this->identity();
        abort_unless($this->editing !== null, 409);
        $campaign = $service->resume($account, $actor, $this->editing, $this->version);
        $this->open($campaign->id, $service);
        session()->flash('success', 'Campaña reanudada con su audiencia autorizada y fecha efectiva originales.');
    }

    public function updatingConfirmed(): void
    {
        $this->confirmationSubmitted = true;
    }

    public function updated(string $property): void
    {
        $this->identity();
        if (preg_match('/^(name|templateId|senderId|listIds|contactIds)(\.|$)/', $property)
            || (! $this->confirmationSubmitted && preg_match('/^(timing|effectiveUtc)(\.|$)/', $property))) {
            $this->confirmed = false;
        }
        if (preg_match('/^(name|templateId|senderId|listIds|contactIds)(\.|$)/', $property)) {
            $this->compositionChanged = true;
        }
    }

    public function render(CampaignService $service)
    {
        [$account, $actor] = $this->identity();
        $member = $service->access($account, $actor);
        $contactsRead = Permissions::allows($member, 'contacts.read');
        $templatesRead = Permissions::allows($member, 'templates.read');
        $canSend = Permissions::allows($member, 'mail.send');
        $canWrite = Permissions::allows($member, 'campaigns.write');
        $canCompose = $canWrite && $contactsRead && $templatesRead;
        $campaign = $this->editing !== null ? $service->campaign($account, $actor, $this->editing) : null;
        // Do not silently refresh the expected version: stale actions must fail.
        $stale = $campaign !== null && $campaign->version !== $this->version;
        $revision = $campaign?->reviewed_revision_id ? $service->revision($account, $campaign) : null;
        $sample = null;
        if ($revision && $templatesRead && $contactsRead) {
            $first = CampaignRecipient::where('account_id', $account->id)->where('campaign_revision_id', $revision->id)->orderBy('id')->firstOrFail();
            $sample = $service->renderContent($revision->only(['subject', 'html', 'text']), ['contact.name' => $first->name, 'contact.email' => $first->email, 'account.name' => $revision->account_name]);
            $sample['document'] = app(TemplatePreview::class)->document($sample['html']);
        }

        return view('livewire.campaigns', [
            'campaigns' => $service->campaigns($account, $actor)->paginate(10),
            'campaign' => $campaign, 'revision' => $revision, 'sample' => $sample, 'stale' => $stale,
            'canWrite' => $canWrite, 'canSend' => $canSend, 'canCompose' => $canCompose,
            'contactsRead' => $contactsRead, 'templatesRead' => $templatesRead,
            'templates' => $templatesRead && $canCompose && $this->editorOpen ? EmailTemplate::where('account_id', $account->id)->orderByDesc('id')->paginate(10, ['id', 'name'], 'templatesPage') : null,
            'senders' => $canSend && $canCompose && $this->editorOpen ? MailSender::where('account_id', $account->id)->where('lab_authorized', true)->orderByDesc('id')->paginate(10, ['id', 'name', 'email'], 'sendersPage') : null,
            'lists' => $contactsRead && $canCompose && $this->editorOpen ? ContactList::where('account_id', $account->id)->orderByDesc('id')->paginate(10, ['id', 'name'], 'listsPage') : null,
            'contacts' => $contactsRead && $canCompose && $this->editorOpen ? Contact::where('account_id', $account->id)->orderByDesc('id')->paginate(10, ['id', 'name', 'email', 'consent_status', 'consent_source', 'consented_at'], 'contactsPage') : null,
            'recipients' => $revision && $contactsRead ? CampaignRecipient::where('account_id', $account->id)->where('campaign_revision_id', $revision->id)->orderBy('id')->paginate(10, ['*'], 'recipientsPage') : null,
        ])->layout('components.app-layout', ['title' => 'Campañas']);
    }
}
