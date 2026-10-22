<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\Membership;
use App\Support\MailInput;
use App\Support\TemplatePreview;
use App\Support\TemplateRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TemplateService
{
    public function __construct(private AccountContext $context, private TemplateRenderer $renderer, private TemplatePreview $preview) {}

    public function templates(string $search = ''): Builder
    {
        $accountId = $this->context->authorize('templates.read')->account_id;
        Validator::make(['search' => $search], ['search' => ['string', 'max:150']])->validate();

        return EmailTemplate::where('account_id', $accountId)->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))->orderByDesc('id');
    }

    public function template(int $id): EmailTemplate
    {
        return $this->templates()->findOrFail($id);
    }

    private function writableAccount(): Account
    {
        $member = $this->context->authorize('templates.write');
        $this->context->authorize('templates.read');
        $account = Account::whereKey($member->account_id)->lockForUpdate()->firstOrFail();
        $current = Membership::where('account_id', $account->id)->where('user_id', auth()->id())->lockForUpdate()->first();
        abort_unless($current && Permissions::allows($current, 'templates.read') && Permissions::allows($current, 'templates.write'), 403);

        return $account;
    }

    private function fields(array $input, bool $saving): array
    {
        $rules = [
            'subject' => ['bail', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'html' => ['bail', 'required_without:text', 'nullable', 'string', 'max:100000'],
            'text' => ['bail', 'required_without:html', 'nullable', 'string', 'max:100000'],
        ];
        if ($saving) {
            $rules['name'] = ['bail', 'required', 'string', 'max:150'];
        }
        $data = Validator::make($input, $rules)->validate();
        $data['html'] = $data['html'] ?? '';
        $data['text'] = $data['text'] ?? '';
        // Validate every expression now, without executing a template language.
        foreach (['subject', 'html', 'text'] as $field) {
            $this->renderer->render($data[$field], array_fill_keys(TemplateRenderer::VARIABLES, 'valor'), $field, $field === 'html');
        }

        return $data;
    }

    public function save(?int $id, array $input): EmailTemplate
    {
        return DB::transaction(function () use ($id, $input) {
            $account = $this->writableAccount();
            $template = $id === null ? new EmailTemplate(['account_id' => $account->id]) : EmailTemplate::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            $template->fill($this->fields($input, true))->save();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'template.saved', 'metadata' => ['template_id' => $template->id]]);

            return $template;
        });
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->writableAccount();
            EmailTemplate::where('account_id', $account->id)->lockForUpdate()->findOrFail($id)->delete();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'template.deleted', 'metadata' => ['template_id' => $id]]);
        });
    }

    private function values(Account $account, array $sample): array
    {
        $data = Validator::make($sample, ['name' => ['bail', 'nullable', 'string', 'max:150'], 'email' => MailInput::emailRules(false)])->validate();

        return ['contact.name' => $data['name'] ?? '', 'contact.email' => $data['email'] ?? '', 'account.name' => $account->name];
    }

    private function renderFields(array $fields, array $values): array
    {
        $rendered = [];
        foreach (['subject', 'html', 'text'] as $field) {
            $rendered[$field] = $this->renderer->render($fields[$field], $values, $field, $field === 'html');
        }
        if ($rendered['text'] === '') {
            $rendered['text'] = html_entity_decode(strip_tags($rendered['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return $rendered;
    }

    public function preview(?int $id, array $input, array $sample): array
    {
        $member = $this->context->authorize('templates.read');
        if ($id !== null) {
            $this->template($id);
        }
        $fields = $this->fields($input, false);
        $rendered = $this->renderFields($fields, $this->values($member->account, $sample));
        $rendered['document'] = $this->preview->document($rendered['html']);

        return $rendered;
    }

    public function senders(): Builder
    {
        $member = $this->context->authorize('templates.read');
        $this->context->authorize('mail.send');

        return MailSender::where('account_id', $member->account_id)->where('lab_authorized', true)->orderByDesc('id');
    }

    public function test(int $id, int $senderId, string $recipient, array $sample): MailMessage
    {
        $member = $this->context->authorize('templates.read');
        $this->context->authorize('mail.send');
        $template = $this->template($id);
        $fields = $this->fields($template->only(['subject', 'html', 'text']), false);
        $rendered = $this->renderFields($fields, $this->values($member->account, $sample));

        return app(AccountMailTransport::class)->send($member->account, $senderId, ['recipient' => $recipient] + $rendered, auth()->id());
    }
}
