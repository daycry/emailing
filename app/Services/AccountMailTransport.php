<?php

namespace App\Services;

use App\Models\Account;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Support\MailInput;
use App\Support\SmtpUnconfirmed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AccountMailTransport
{
    public function __construct(private SmtpConnector $smtp) {}

    // No HTTP/session dependency: workers and template facades supply an already validated account.
    public function send(Account $account, int $senderId, array $input, ?int $actorId = null): MailMessage
    {
        $data = MailInput::message($input);
        // This commit is the start boundary, serialized with manual/public suppression.
        // No storage or SMTP IO occurs while holding SQL locks. A completed suppression
        // wins over any later start; it cannot cancel transport already marked sending.
        [$record, $sender] = DB::transaction(function () use ($account, $senderId, $data, $actorId) {
            Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $sender = MailSender::where('account_id', $account->id)->lockForUpdate()->findOrFail($senderId);
            abort_unless($sender->lab_authorized, 403, 'El remitente no está autorizado para el laboratorio.');
            $mode = MailSetting::where('account_id', $account->id)->lockForUpdate()->value('mode') ?? 'log';
            $suppressed = app(SuppressionService::class)->isSuppressed($account, $data['recipient']);
            $enabled = in_array($mode, ['log', 'mailpit'], true);
            $record = MailMessage::create(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $actorId, 'mode' => $mode,
                'status' => $suppressed ? 'suppressed' : ($enabled ? 'sending' : 'blocked'),
                'diagnostic' => $suppressed ? 'recipient_suppressed' : ($enabled ? null : 'external_not_enabled'),
                'transport_started_at' => ! $suppressed && $enabled ? now() : null,
                'sender_email' => $sender->email, 'recipient' => $data['recipient'], 'subject' => $data['subject']]);

            return [$record, $sender];
        });
        if ($record->status !== 'sending') {
            return $record;
        }
        $mode = $record->mode;
        $email = (new Email)->from(new Address($sender->email, $sender->name))->to($data['recipient'])->subject($data['subject']);
        if ($data['text'] !== '') {
            $email->text($data['text']);
        }
        if ($sender->reply_to) {
            $email->replyTo($sender->reply_to);
        }
        if ($data['html'] !== '') {
            $email->html($data['html']);
        }
        if ($mode === 'log') {
            $path = $account->id.'/'.Str::uuid().'.eml';
            try {
                Storage::disk('mail-artifacts')->put($path, $email->toString());
                $record->update(['status' => 'logged', 'artifact_path' => $path]);
            } catch (\Throwable) {
                $record->update(['status' => 'local_failed', 'diagnostic' => 'artifact_failed']);
            }
        } elseif ($mode === 'mailpit') {
            try {
                $this->smtp->send(['host' => (string) config('emailing.mailpit.host'), 'port' => (int) config('emailing.mailpit.port'), 'security' => 'plain'], $email);
                $record->update(['status' => 'smtp_accepted']);
            } catch (SmtpUnconfirmed) {
                $record->update(['status' => 'smtp_unconfirmed', 'diagnostic' => 'smtp_unconfirmed']);
            }
        } else {
            $record->update(['status' => 'blocked', 'diagnostic' => 'external_not_enabled']);
        }

        return $record;
    }
}
