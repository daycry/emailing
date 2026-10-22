<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Support\MailInput;
use Illuminate\Database\Eloquent\Builder;
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
                'mode' => ['bail', 'required', 'string', Rule::in(['log', 'mailpit'])],
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
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.settings_changed', 'metadata' => ['mode' => $settings->mode, 'external_enabled' => false]]);
        });
    }

    public function saveSender(?int $id, array $input): void
    {
        DB::transaction(function () use ($id, $input) {
            $account = $this->writableAccount();
            $sender = $id === null ? new MailSender(['account_id' => $account->id]) : MailSender::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            Validator::make($input, ['email' => ['bail', 'required', 'string', 'max:254', 'not_regex:/[\r\n]/']])->validate();
            $input['email'] = mb_strtolower(trim($input['email']));
            $data = Validator::make($input, ['email' => MailInput::emailRules(), 'name' => ['bail', 'present', 'string', 'max:150', 'not_regex:/[\r\n]/'], 'reply_to' => MailInput::emailRules(false), 'lab_authorized' => ['required', 'boolean']])->validate();
            if (MailSender::where('account_id', $account->id)->where('email', $data['email'])->when($sender->exists, fn ($query) => $query->whereKeyNot($sender->id))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['email' => 'Este remitente ya existe en la cuenta.']);
            }
            $data['reply_to'] = empty($data['reply_to']) ? null : mb_strtolower(trim($data['reply_to']));
            $sender->fill($data)->save();
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'mail.sender_saved', 'metadata' => ['sender_id' => $sender->id, 'lab_authorized' => $sender->lab_authorized]]);
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

        return app(AccountMailTransport::class)->send($member->account, $senderId, $input, auth()->id());
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
