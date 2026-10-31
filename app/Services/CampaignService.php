<?php

namespace App\Services;

use App\Exceptions\DomainConflict;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\Suppression;
use App\Services\MailProviders\MailTransportPolicy;
use App\Support\ExecutionContext;
use App\Support\TemplateRenderer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

// The domain takes an explicit account and actor; only the Livewire facade uses a session.
class CampaignService
{
    private const COMPOSE = ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read'];

    public function __construct(private TemplateRenderer $renderer, private ?ExecutionContext $execution = null) {}

    public function access(Account $account, int $actorId, array $scopes = ['campaigns.read'], bool $lock = false): Membership
    {
        if ($this->execution !== null) {
            abort_unless($this->execution->accountId() === $account->id && $this->execution->actorId() === $actorId, 403);

            return $this->execution->authorize($scopes, $lock);
        }
        $member = Membership::where('account_id', $account->id)->where('user_id', $actorId)->when($lock, fn ($q) => $q->lockForUpdate())->first();
        foreach ($scopes as $scope) {
            abort_unless($member && Permissions::allows($member, $scope), 403, 'No tienes permiso para realizar esta acción.');
        }

        return $member;
    }

    private function writable(Account $account, int $actorId, array $scopes): Account
    {
        $current = Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
        $this->access($current, $actorId, $scopes, true);

        return $current;
    }

    public function campaigns(Account $account, int $actorId): Builder
    {
        $this->access($account, $actorId);

        return Campaign::where('account_id', $account->id)->orderByDesc('id');
    }

    public function campaign(Account $account, int $actorId, int $id): Campaign
    {
        return $this->campaigns($account, $actorId)->findOrFail($id);
    }

    private function current(Account $account, int $id, int $version, array $states): Campaign
    {
        $campaign = Campaign::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
        if ($campaign->version !== $version) {
            throw new DomainConflict('version_conflict', 'La campaña cambió. Vuelve a abrirla y revisa su versión actual.');
        }
        if (! in_array($campaign->status, $states, true)) {
            throw new DomainConflict('state_conflict', 'Esta acción no está disponible en el estado actual.');
        }

        return $campaign;
    }

    private function ids(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);

        return $ids;
    }

    private function sources(Account $account, array $data): void
    {
        EmailTemplate::where('account_id', $account->id)->findOrFail($data['template_id']);
        if ($data['sender_id'] !== null) {
            $sender = MailSender::where('account_id', $account->id)->findOrFail($data['sender_id']);
            abort_unless(MailTransportPolicy::allowsSender($sender, MailSetting::where('account_id', $account->id)->value('mode') ?? 'log'), 403, 'El remitente no está autorizado para este transporte.');
        }
        abort_unless(ContactList::where('account_id', $account->id)->whereIn('id', $data['list_ids'])->count() === count($data['list_ids']), 404);
        abort_unless(Contact::where('account_id', $account->id)->whereIn('id', $data['contact_ids'])->count() === count($data['contact_ids']), 404);
    }

    public function save(Account $account, int $actorId, ?int $id, ?int $version, array $input): Campaign
    {
        return DB::transaction(function () use ($account, $actorId, $id, $version, $input) {
            $account = $this->writable($account, $actorId, self::COMPOSE);
            $campaign = $id === null ? null : $this->current($account, $id, $version ?? 0, ['draft', 'reviewed']);
            $data = Validator::make($input, [
                'name' => ['required', 'string', 'max:150'],
                'template_id' => ['required', 'integer', 'min:1'],
                'sender_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'list_ids' => ['present', 'array', 'max:100'],
                'list_ids.*' => ['integer', 'min:1', 'distinct'],
                'contact_ids' => ['present', 'array', 'max:1000'],
                'contact_ids.*' => ['integer', 'min:1', 'distinct'],
            ])->validate();
            if (array_key_exists('sender_id', $data) && $data['sender_id'] !== null) {
                $this->access($account, $actorId, ['mail.send'], true);
            }
            $data['sender_id'] = array_key_exists('sender_id', $data) ? $data['sender_id'] : $campaign?->sender_id;
            $data['list_ids'] = $this->ids($data['list_ids']);
            $data['contact_ids'] = $this->ids($data['contact_ids']);
            $this->sources($account, $data);
            if ($data['list_ids'] === [] && $data['contact_ids'] === []) {
                throw ValidationException::withMessages(['audience' => 'Selecciona al menos una lista o contacto.']);
            }
            if ($campaign) {
                $campaign->fill($data + ['status' => 'draft', 'reviewed_revision_id' => null, 'version' => $campaign->version + 1])->save();
            } else {
                $campaign = Campaign::create($data + ['account_id' => $account->id, 'created_by' => $actorId]);
            }
            $this->audit($campaign, $actorId, 'campaign.saved');

            return $campaign->fresh();
        });
    }

    private function composition(Account $account, Campaign $campaign): array
    {
        $this->sources($account, $campaign->only(['template_id', 'sender_id', 'list_ids', 'contact_ids']));
        if ($campaign->sender_id === null) {
            throw ValidationException::withMessages(['senderId' => 'Selecciona un remitente autorizado antes de revisar.']);
        }
        $template = EmailTemplate::where('account_id', $account->id)->findOrFail($campaign->template_id);
        $sender = MailSender::where('account_id', $account->id)->findOrFail($campaign->sender_id);
        $contacts = Contact::where('account_id', $account->id)->where(function ($query) use ($account, $campaign) {
            $query->whereIn('id', $campaign->contact_ids)->orWhereHas('lists', fn ($lists) => $lists->where('contact_lists.account_id', $account->id)->whereIn('contact_lists.id', $campaign->list_ids)->where('contact_list_memberships.account_id', $account->id));
        })->orderBy('id')->limit(10001)->lockForUpdate()->get();
        if ($contacts->count() > 10000) {
            throw ValidationException::withMessages(['audience' => 'Máximo 10.000 contactos por campaña. Divide la selección en varias campañas.']);
        }
        $suppressed = Suppression::where('account_id', $account->id)->whereIn('normalized_email', $contacts->pluck('email'))->pluck('normalized_email')->flip();
        $all = [];
        $recipients = [];
        foreach ($contacts as $contact) {
            $snapshot = ['contact_id' => $contact->id, 'email' => $contact->email, 'name' => $contact->name, 'consent_source' => $contact->consent_source, 'consented_at' => $contact->consented_at?->utc()->format('Y-m-d H:i:s')];
            $all[] = $snapshot + ['consent_status' => $contact->consent_status, 'suppressed' => $suppressed->has($contact->email)];
            if ($contact->consent_status === 'granted' && filled($contact->consent_source) && $contact->consented_at !== null && $contact->consented_at->lte(now('UTC')) && ! $suppressed->has($contact->email)) {
                $recipients[] = $snapshot;
            }
        }
        if ($recipients === []) {
            throw ValidationException::withMessages(['audience' => 'No hay destinatarios elegibles con consentimiento trazable y sin supresión.']);
        }
        $content = $template->only(['subject', 'html', 'text']);
        if ($content['html'] === '' && $content['text'] === '') {
            throw ValidationException::withMessages(['templateId' => 'La plantilla necesita contenido.']);
        }
        $this->validateBatch($content, $account->name, $recipients);
        $senderSnapshot = $sender->only(['id', 'email', 'name', 'reply_to']);
        $hash = hash('sha256', json_encode([$content, $senderSnapshot, $account->name, $all], JSON_THROW_ON_ERROR));

        return ['content' => $content, 'sender' => $senderSnapshot, 'recipients' => $recipients, 'excluded' => $contacts->count() - count($recipients), 'hash' => $hash];
    }

    public function renderContent(array $content, array $values): array
    {
        $result = [];
        foreach (['subject', 'html', 'text'] as $field) {
            $result[$field] = $this->renderer->render($content[$field], $values, $field, $field === 'html');
        }
        if ($result['text'] === '') {
            $result['text'] = html_entity_decode(strip_tags($result['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $result;
    }

    public function validateBatch(array $content, string $accountName, iterable $recipients): void
    {
        $compiled = [];
        foreach (['subject', 'html', 'text'] as $field) {
            $compiled[$field] = $this->renderer->compile($content[$field], $field, $field === 'html');
        }
        foreach ($recipients as $recipient) {
            $values = ['contact.name' => $recipient['name'], 'contact.email' => $recipient['email'], 'account.name' => $accountName];
            foreach ($compiled as $render) {
                $render($values);
            }
        }
    }

    public function review(Account $account, int $actorId, int $id, int $version): Campaign
    {
        return DB::transaction(function () use ($account, $actorId, $id, $version) {
            $account = $this->writable($account, $actorId, self::COMPOSE);
            $campaign = $this->current($account, $id, $version, ['draft', 'reviewed']);
            $composition = $this->composition($account, $campaign);
            $revision = CampaignRevision::create($composition['content'] + [
                'account_id' => $account->id, 'campaign_id' => $id, 'version' => $campaign->version + 1,
                'reviewed_by' => $actorId, 'account_name' => $account->name, 'sender_snapshot' => $composition['sender'],
                'source_hash' => $composition['hash'], 'recipient_count' => count($composition['recipients']), 'excluded_count' => $composition['excluded'], 'created_at' => now('UTC'),
            ]);
            foreach (array_chunk($composition['recipients'], 500) as $chunk) {
                CampaignRecipient::insert(array_map(fn ($recipient) => $recipient + ['account_id' => $account->id, 'campaign_revision_id' => $revision->id], $chunk));
            }
            $campaign->update(['status' => 'reviewed', 'version' => $revision->version, 'reviewed_revision_id' => $revision->id]);
            $this->audit($campaign, $actorId, 'campaign.reviewed', ['revision_id' => $revision->id, 'recipient_count' => $revision->recipient_count, 'source_hash' => $revision->source_hash]);

            return $campaign->fresh();
        });
    }

    private function effective(?string $utc): CarbonImmutable
    {
        if ($utc === null) {
            return CarbonImmutable::now('UTC')->startOfSecond();
        }
        Validator::make(['effectiveUtc' => $utc], ['effectiveUtc' => ['required', 'string', 'regex:/\A[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}\z/D']])->validate();
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $utc, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($parsed === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw ValidationException::withMessages(['effectiveUtc' => 'Introduce una fecha futura válida en UTC.']);
        }
        $date = CarbonImmutable::instance($parsed);
        if ($date->format('Y-m-d\TH:i') !== $utc || $date->year < 1970 || $date->year > 9999 || $date->lte(CarbonImmutable::now('UTC'))) {
            throw ValidationException::withMessages(['effectiveUtc' => 'Introduce una fecha futura válida en UTC.']);
        }

        return $date;
    }

    public function authorizeCampaign(Account $account, int $actorId, int $id, int $version, int $revisionId, bool $confirmed, ?string $effectiveUtc = null): Campaign
    {
        return DB::transaction(function () use ($account, $actorId, $id, $version, $revisionId, $confirmed, $effectiveUtc) {
            $account = $this->writable($account, $actorId, [...self::COMPOSE, 'mail.send']);
            $campaign = $this->current($account, $id, $version, ['reviewed']);
            if (! $confirmed || $campaign->reviewed_revision_id !== $revisionId) {
                throw new DomainConflict('version_conflict', 'Revisa y confirma expresamente esta versión antes de autorizar.');
            }
            $revision = $this->revision($account, $campaign);
            $composition = $this->composition($account, $campaign);
            if (! hash_equals($revision->source_hash, $composition['hash'])) {
                throw new DomainConflict('version_conflict', 'Cambió la plantilla, el remitente o la audiencia. Genera y revisa un resumen nuevo.');
            }
            $effective = $this->effective($effectiveUtc);
            $campaign->update(['status' => $effectiveUtc === null ? 'authorized' : 'scheduled', 'version' => $campaign->version + 1, 'authorized_at' => now('UTC'), 'authorized_by' => $actorId, 'effective_at' => $effective]);
            $this->audit($campaign, $actorId, 'campaign.authorized', ['revision_id' => $revisionId, 'source_hash' => $revision->source_hash, 'effective_at' => $effective->toIso8601String()]);

            return $campaign->fresh();
        });
    }

    public function pause(Account $account, int $actorId, int $id, int $version): Campaign
    {
        return DB::transaction(function () use ($account, $actorId, $id, $version) {
            $this->writable($account, $actorId, ['campaigns.read', 'campaigns.write']);
            $campaign = $this->current($account, $id, $version, ['authorized', 'scheduled']);
            $campaign->update(['status' => 'paused', 'version' => $campaign->version + 1]);
            $this->audit($campaign, $actorId, 'campaign.paused');

            return $campaign->fresh();
        });
    }

    public function resume(Account $account, int $actorId, int $id, int $version): Campaign
    {
        return DB::transaction(function () use ($account, $actorId, $id, $version) {
            $this->writable($account, $actorId, ['campaigns.read', 'campaigns.write', 'mail.send']);
            $campaign = $this->current($account, $id, $version, ['paused']);
            $revision = $this->revision($account, $campaign);
            $sender = MailSender::where('account_id', $account->id)->findOrFail($campaign->sender_id);
            $currentSender = $sender->only(['id', 'email', 'name', 'reply_to']);
            $snapshotSender = $revision->sender_snapshot;
            ksort($currentSender);
            ksort($snapshotSender);
            if (! MailTransportPolicy::allowsSender($sender, MailSetting::where('account_id', $account->id)->value('mode') ?? 'log') || $currentSender !== $snapshotSender) {
                throw new DomainConflict('version_conflict', 'El remitente cambió o dejó de estar autorizado. Crea otra campaña revisada.');
            }
            $campaign->update(['status' => $campaign->effective_at->isFuture() ? 'scheduled' : 'authorized', 'version' => $campaign->version + 1]);
            $this->audit($campaign, $actorId, 'campaign.resumed');

            return $campaign->fresh();
        });
    }

    public function revision(Account $account, Campaign $campaign): CampaignRevision
    {
        abort_unless($campaign->account_id === $account->id, 404);

        return CampaignRevision::where('account_id', $account->id)->where('campaign_id', $campaign->id)->findOrFail($campaign->reviewed_revision_id);
    }

    public function recipients(Account $account, int $actorId, Campaign $campaign, CampaignRevision $revision): Builder
    {
        $this->access($account, $actorId, ['campaigns.read', 'contacts.read']);
        abort_unless($campaign->account_id === $account->id && $revision->account_id === $account->id && $revision->campaign_id === $campaign->id, 404);

        return CampaignRecipient::where('account_id', $account->id)->where('campaign_revision_id', $revision->id)->orderBy('id');
    }

    // Internal eligibility query for T12, not a dispatcher or proof that a worker sent safely.
    public function due(Account $account): Builder
    {
        return Campaign::where('account_id', $account->id)->whereIn('status', ['authorized', 'scheduled'])->whereNotNull('authorized_at')->whereNotNull('reviewed_revision_id')->where('effective_at', '<=', now('UTC'));
    }

    private function audit(Campaign $campaign, int $actorId, string $action, array $metadata = []): void
    {
        AuditEvent::create(['account_id' => $campaign->account_id, 'actor_id' => $actorId, 'action' => $action, 'metadata' => ['campaign_id' => $campaign->id, 'version' => $campaign->version] + $metadata + ($this->execution?->provenance() ?? [])]);
    }
}
