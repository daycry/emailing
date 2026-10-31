<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ApiMailRecipient;
use App\Models\ApiMailRequest;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Delivery;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\McpGrant;
use App\Models\UnsubscribeToken;
use App\Services\MailProviders\MailProviderTransport;
use App\Services\MailProviders\MailTransportPolicy;
use App\Support\DeliveryLeaseLost;
use App\Support\MailInput;
use App\Support\MailTransportConfiguration;
use App\Support\SmtpPreAcceptanceFailure;
use App\Support\SmtpRejected;
use App\Support\TransportOutcome;
use App\Support\TrustedUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class AccountMailTransport
{
    public function __construct(private SmtpConnector $smtp, private DeliveryLifecycle $lifecycle, private DeliveryQuota $quota,
        private DeliveryObserver $observer, private MailTransportPolicy $policy, private MailProviderTransport $providers) {}

    public function send(Account $account, int $senderId, array $input, ?int $actorId = null, string $origin = 'unknown'): MailMessage
    {
        if (! in_array($origin, ['unknown', 'test'], true)) {
            throw new \InvalidArgumentException('Unrecorded mail origin.');
        }

        return $this->execute($account, $senderId, MailInput::message($input), $actorId, origin: $origin);
    }

    public function sendDelivery(Delivery $claim): ?MailMessage
    {
        $this->independent();
        if ($claim->api_request_id !== null) {
            $source = ApiMailRequest::where('account_id', $claim->account_id)->findOrFail($claim->api_request_id);
            $recipient = ApiMailRecipient::where('account_id', $claim->account_id)->where('api_request_id', $source->id)->findOrFail($claim->api_recipient_id);

            return $this->execute(Account::findOrFail($claim->account_id), $source->sender_snapshot['id'], MailInput::message(['recipient' => $recipient->email] + $recipient->only(['subject', 'html', 'text'])), null, $claim);
        }
        $revision = CampaignRevision::where('account_id', $claim->account_id)->where('campaign_id', $claim->campaign_id)->findOrFail($claim->campaign_revision_id);
        $recipient = CampaignRecipient::where('account_id', $claim->account_id)->where('campaign_revision_id', $revision->id)->findOrFail($claim->campaign_recipient_id);
        $data = app(CampaignService::class)->renderContent($revision->only(['subject', 'html', 'text']), ['contact.name' => $recipient->name, 'contact.email' => $recipient->email, 'account.name' => $revision->account_name]);

        return $this->execute(Account::findOrFail($claim->account_id), $revision->sender_snapshot['id'], MailInput::message(['recipient' => $recipient->email] + $data), null, $claim);
    }

    private function independent(): void
    {
        if (DBTransactionGuard::active()) {
            throw new \LogicException('Mail transport requires a committed independent start.');
        }
    }

    private function execute(Account $account, int $senderId, array $data, ?int $actorId, ?Delivery $claim = null, string $origin = 'unknown'): ?MailMessage
    {
        $this->independent();
        // Same account mutex as completed suppression; sole durable transport start.
        // No caller-held transaction and no SMTP/storage IO while locks are held.
        try {
            $start = DB::transaction(function () use ($account, $senderId, $data, $actorId, $claim, $origin) {
                Account::whereKey($account->id)->lockForUpdate()->firstOrFail();
                $query = MailSender::where('account_id', $account->id)->lockForUpdate();
                $sender = $claim ? $query->find($senderId) : $query->findOrFail($senderId);
                $mode = MailSetting::where('account_id', $account->id)->lockForUpdate()->value('mode') ?? 'log';
                if ($claim === null) {
                    abort_unless(MailTransportPolicy::allowsSender($sender, $mode), 403, 'El remitente no está autorizado para este transporte.');
                }
                $configuration = $this->policy->resolve($account->id, $mode);
                $suppressed = app(SuppressionService::class)->isSuppressed($account, $data['recipient']);
                $delivery = $claim ? $this->lifecycle->beforeStart($claim, $sender, $suppressed, $mode, $configuration->blockedReason) : null;
                if ($claim && ! $delivery) {
                    return null;
                }
                $enabled = $configuration->blockedReason === null;
                $status = $suppressed ? 'suppressed' : ($enabled ? 'sending' : 'blocked');
                $diagnostic = $suppressed ? 'recipient_suppressed' : $configuration->blockedReason;
                if ($delivery) {
                    $this->observer->observe('before_quota', $claim);
                }
                if ($status === 'sending' && ($wait = $this->quota->take($account->id))) {
                    if ($delivery) {
                        $this->lifecycle->defer($delivery, 'quota_wait', $wait);

                        return null;
                    }
                    $status = 'blocked';
                    $diagnostic = 'quota_wait';
                }
                if ($delivery) {
                    $token = bin2hex(random_bytes(32));
                    UnsubscribeToken::create(['account_id' => $account->id, 'normalized_email' => $data['recipient'], 'token_hash' => hash('sha256', $token)]);
                    $url = TrustedUrl::signedRoute('unsubscribe.show', ['token' => $token]);
                    $data['text'] .= "\n\nBaja: ".$url;
                    if ($data['html'] !== '') {
                        $data['html'] .= '<p><a href="'.htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'">Darse de baja</a></p>';
                    }
                }
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
                $email->ensureValidity();
                $apiSource = $delivery?->api_request_id ? ApiMailRequest::where('account_id', $account->id)->findOrFail($delivery->api_request_id) : null;
                $campaignActor = $delivery && ! $apiSource ? Campaign::where('account_id', $account->id)->whereKey($delivery->campaign_id)->where('reviewed_revision_id', $delivery->campaign_revision_id)->value('authorized_by') : null;
                $requestActor = $apiSource?->origin === 'mcp' ? McpGrant::whereKey($apiSource->mcp_grant_id)->where('account_id', $account->id)->where('client_id', $apiSource->mcp_client_id)->value('user_id') : null;
                $record = MailMessage::create(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $delivery ? ($apiSource ? $requestActor : $campaignActor) : $actorId, 'delivery_id' => $delivery?->id, 'delivery_generation' => $delivery?->generation, 'origin' => $apiSource ? $apiSource->origin : ($delivery ? 'campaign' : $origin), 'api_credential_id' => $apiSource?->credential_id, 'api_request_id' => $apiSource?->id, 'mcp_grant_id' => $apiSource?->mcp_grant_id, 'mcp_client_id' => $apiSource?->mcp_client_id, 'mode' => $mode, 'status' => $status, 'diagnostic' => $diagnostic, 'transport_started_at' => $status === 'sending' ? DB::raw('UTC_TIMESTAMP(6)') : null, 'sender_email' => $sender->email, 'recipient' => $data['recipient'], 'subject' => $data['subject']]);
                if ($delivery) {
                    $this->lifecycle->started($delivery, $record);
                }

                if ($status === 'sending' && $configuration->mode?->isExternal()) {
                    $record->update(['provider_reference' => (string) Str::uuid()]);
                }

                return [$record, $email, $configuration];
            }, 3);
        } catch (DeliveryLeaseLost) {
            if ($claim) {
                $this->lifecycle->readyToSettle($claim);
            }

            return null;
        }
        if ($start === null) {
            return null;
        }
        [$record, $email, $configuration] = $start;
        if ($record->status !== 'sending') {
            return $record;
        }
        if ($claim) {
            $this->observer->observe('sending', $claim, ['message_id' => $record->id]);
            if (! $this->lifecycle->renew($claim)) {
                return $record;
            }
        }
        $outcome = $this->perform($account, $record, $email, $claim, $configuration);
        if ($claim) {
            $this->observer->observe('transport_result', $claim, ['status' => $outcome->status]);
            if ($this->lifecycle->finish($claim, $record, $outcome)) {
                $this->observer->observe('durable_result', $claim, ['status' => $claim->fresh()->status]);
            }
        } else {
            $record->update($outcome->attributes());
        }

        return $record;
    }

    private function perform(Account $account, MailMessage $record, Email $email, ?Delivery $claim, MailTransportConfiguration $configuration): TransportOutcome
    {
        if ($record->mode === 'log') {
            $path = $account->id.'/'.($claim ? $claim->id.'-'.$claim->generation : Str::uuid()).'.eml';
            try {
                if (! Storage::disk('mail-artifacts')->put($path, $email->toString())) {
                    throw new \RuntimeException('Artifact write failed.');
                }

                return new TransportOutcome('logged', artifactPath: $path);
            } catch (\Throwable) {
                return new TransportOutcome('local_failed', 'artifact_failed', retryable: true);
            }
        }
        try {
            $heartbeat = $claim ? function () use ($claim) {
                if (! $this->lifecycle->renew($claim)) {
                    throw new \RuntimeException('Delivery lease no longer valid.');
                }
            } : null;
            if ($configuration->credentials !== null) {
                return $this->providers->send($configuration->credentials, $email, $record->provider_reference, $heartbeat);
            }
            $this->smtp->send(['host' => (string) config('emailing.mailpit.host'), 'port' => (int) config('emailing.mailpit.port'), 'security' => 'plain'], $email, $heartbeat);

            return new TransportOutcome('smtp_accepted');
        } catch (SmtpRejected $error) {
            return new TransportOutcome('smtp_rejected', 'smtp_negative_'.$error->stage, retryable: true);
        } catch (SmtpPreAcceptanceFailure) {
            return new TransportOutcome('smtp_rejected', 'smtp_before_terminator', retryable: true);
        } catch (\Throwable) {
            $status = $configuration->mode?->isApi() ? 'api_unconfirmed' : 'smtp_unconfirmed';

            return new TransportOutcome($status, $status);
        }
    }
}
