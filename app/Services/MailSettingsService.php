<?php

namespace App\Services;

use App\Enums\MailTransportMode;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\MailMessage;
use App\Models\MailProviderConnection;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Services\MailProviders\SendGridWebhookKey;
use App\Support\MailInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MailSettingsService
{
    public function __construct(private AccountContext $context) {}

    public function settings(): MailSetting
    {
        $accountId = $this->context->authorize('settings.read')->account_id;

        return MailSetting::where('account_id', $accountId)->first() ?? new MailSetting(['account_id' => $accountId, 'mode' => 'log', 'smtp_port' => 587, 'smtp_security' => 'starttls']);
    }

    public function senders(): Builder
    {
        return MailSender::where('account_id', $this->context->authorize('settings.read')->account_id)->orderByDesc('id');
    }

    private function writableAccount(): Account
    {
        $member = $this->context->authorize('settings.write');
        $this->context->authorize('settings.read');
        $account = Account::whereKey($member->account_id)->lockForUpdate()->firstOrFail();
        $current = Membership::where('account_id', $account->id)->where('user_id', auth()->id())->lockForUpdate()->first();
        abort_unless($current && Permissions::allows($current, 'settings.write') && Permissions::allows($current, 'settings.read'), 403);

        return $account;
    }

    public function saveSettings(array $input): void
    {
        DB::transaction(function () use ($input) {
            $account = $this->writableAccount();
            $data = Validator::make($input, [
                'mode' => ['bail', 'required', 'string', Rule::in(array_keys(MailTransportMode::labels()))],
                'smtp_host' => ['bail', 'nullable', 'string', 'max:253', 'regex:/^[A-Za-z0-9.\[\]:-]+$/D'],
                'smtp_port' => ['bail', 'required', 'integer', 'between:1,65535'],
                'smtp_security' => ['bail', 'required', 'string', Rule::in(['starttls', 'smtps'])],
                'smtp_username' => ['bail', 'nullable', 'string', 'max:1024', 'not_regex:/[\r\n]/'],
                'smtp_password' => ['bail', 'nullable', 'string', 'max:4096', 'not_regex:/[\r\n]/'],
                'clear_credentials' => ['sometimes', 'boolean'],
            ])->validate();
            $settings = MailSetting::where('account_id', $account->id)->lockForUpdate()->first() ?? new MailSetting(['account_id' => $account->id]);
            $settings->fill(['mode' => $data['mode'], 'smtp_host' => $data['smtp_host'] ?? null, 'smtp_port' => $data['smtp_port'], 'smtp_security' => $data['smtp_security']]);
            foreach (['smtp_username', 'smtp_password'] as $field) {
                if ($data['clear_credentials'] ?? false) {
                    $settings->{$field} = null;
                } elseif (isset($data[$field]) && $data[$field] !== '') {
                    $settings->{$field} = $data[$field];
                }
            }
            $settings->save();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.settings_changed', 'metadata' => ['mode' => $settings->mode, 'external_enabled' => (bool) config('emailing.external_enabled')]]);
        });
    }

    public function providerConnections(): Collection
    {
        $accountId = $this->context->authorize('settings.read')->account_id;

        return MailProviderConnection::where('account_id', $accountId)->orderBy('provider')->get();
    }

    public function saveProviderSettings(string $provider, #[\SensitiveParameter] array $input): void
    {
        DB::transaction(function () use ($provider, $input): void {
            $account = $this->writableAccount();
            Validator::make(['provider' => $provider], ['provider' => ['required', Rule::in(MailProviderConnection::PROVIDERS)]])->validate();
            $connection = MailProviderConnection::where('account_id', $account->id)->where('provider', $provider)->lockForUpdate()->first()
                ?? new MailProviderConnection(['account_id' => $account->id, 'provider' => $provider]);
            $data = Validator::make($input + ['enabled' => $connection->enabled ?? false, 'smtp_security' => $connection->smtp_security ?? 'starttls'], [
                'enabled' => ['required', 'boolean'],
                'smtp_security' => ['required', Rule::in(['starttls', 'smtps'])],
                'api_key' => ['nullable', 'string', 'max:4096', 'not_regex:/[\x00-\x20\x7f]/'],
                'smtp_username' => ['nullable', 'string', 'max:1024', 'not_regex:/[\r\n\x00]/'],
                'smtp_password' => ['nullable', 'string', 'max:4096', 'not_regex:/[\r\n\x00]/'],
                'webhook_secret' => ['nullable', 'string', 'min:32', 'max:512', 'not_regex:/[\x00-\x20\x7f]/'],
                'webhook_public_key' => ['nullable', 'string', 'max:4096', function ($attribute, $value, $fail) use ($provider): void {
                    if ($value !== '' && ($provider !== 'sendgrid' || SendGridWebhookKey::parse($value) === false)) {
                        $fail('Introduce la clave pública ECDSA P-256 del webhook SendGrid.');
                    }
                }],
                'clear_credentials' => ['sometimes', 'boolean'],
                'clear_fields' => ['sometimes', 'array', 'max:5'],
                'clear_fields.*' => ['required', 'string', Rule::in(MailProviderConnection::SECRET_FIELDS)],
            ])->validate();
            $clear = ($data['clear_credentials'] ?? false) ? MailProviderConnection::SECRET_FIELDS : ($data['clear_fields'] ?? []);
            foreach (MailProviderConnection::SECRET_FIELDS as $field) {
                if (in_array($field, $clear, true)) {
                    $connection->{$field} = null;
                } elseif (isset($data[$field]) && $data[$field] !== '') {
                    $connection->{$field} = $data[$field];
                }
            }
            $connection->enabled = (bool) $data['enabled'];
            $connection->smtp_security = $data['smtp_security'];
            $modes = $provider === 'brevo' ? [MailTransportMode::BrevoSmtp, MailTransportMode::BrevoApi]
                : [MailTransportMode::SendGridSmtp, MailTransportMode::SendGridApi];
            if ($connection->enabled && ! $connection->isConfiguredFor($modes[0]) && ! $connection->isConfiguredFor($modes[1])) {
                if ($clear !== []) {
                    $connection->enabled = false;
                } else {
                    throw ValidationException::withMessages(['enabled' => 'Configura las credenciales del canal y la autenticación del webhook antes de habilitar el proveedor.']);
                }
            }
            $connection->save();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.provider_saved',
                'metadata' => ['provider' => $provider, 'enabled' => $connection->enabled, 'credentials_cleared' => $clear !== []]]);
        });
    }

    public function saveSender(?int $id, array $input): void
    {
        DB::transaction(function () use ($id, $input) {
            $account = $this->writableAccount();
            $sender = $id === null ? new MailSender(['account_id' => $account->id]) : MailSender::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            Validator::make($input, ['email' => ['bail', 'required', 'string', 'max:254', 'not_regex:/[\r\n]/']])->validate();
            $input['email'] = mb_strtolower(trim($input['email']));
            $data = Validator::make($input, ['email' => MailInput::emailRules(), 'name' => ['bail', 'present', 'string', 'max:150', 'not_regex:/[\r\n]/'], 'reply_to' => MailInput::emailRules(false), 'lab_authorized' => ['required', 'boolean'], 'external_authorized' => ['sometimes', 'boolean']])->validate();
            if (MailSender::where('account_id', $account->id)->where('email', $data['email'])->when($sender->exists, fn ($query) => $query->whereKeyNot($sender->id))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => 'Este remitente ya existe en la cuenta.']);
            }
            $data['reply_to'] = empty($data['reply_to']) ? null : mb_strtolower(trim($data['reply_to']));
            $data['external_authorized'] ??= $sender->email === $data['email'] && $sender->external_authorized;
            $sender->fill($data)->save();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.sender_saved', 'metadata' => ['sender_id' => $sender->id, 'lab_authorized' => $sender->lab_authorized, 'external_authorized' => $sender->external_authorized]]);
        });
    }

    public function deleteSender(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->writableAccount();
            MailSender::where('account_id', $account->id)->lockForUpdate()->findOrFail($id)->delete();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.sender_deleted', 'metadata' => ['sender_id' => $id]]);
        });
    }

    public function test(int $senderId, array $input): MailMessage
    {
        $member = $this->context->authorize('settings.read');
        $this->context->authorize('mail.send');

        return app(AccountMailTransport::class)->send($member->account, $senderId, $input, auth()->id(), origin: 'test');
    }

    public function messages(): Builder
    {
        return MailMessage::where('account_id', $this->context->authorize('activity.read')->account_id)->orderByDesc('id');
    }

    public function download(int $id): StreamedResponse
    {
        $message = $this->messages()->findOrFail($id);
        abort_unless($message->artifact_path !== null, 404);

        return response()->streamDownload(function () use ($id) {
            $message = $this->messages()->findOrFail($id);
            abort_unless($message->artifact_path !== null, 404);
            $stream = Storage::disk('mail-artifacts')->readStream($message->artifact_path);
            fpassthru($stream);
            fclose($stream);
        }, 'mensaje-'.$id.'.eml', ['Content-Type' => 'message/rfc822', 'Cache-Control' => 'private, no-store']);
    }
}
