<?php

namespace App\Services\MailProviders;

use App\Services\SmtpConnector;
use App\Support\SmtpPolicyViolation;
use App\Support\SmtpPreAcceptanceFailure;
use App\Support\SmtpRejected;
use App\Support\TransportOutcome;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

final class ProviderSmtpTransport
{
    public function __construct(private SmtpConnector $smtp) {}

    public function send(#[\SensitiveParameter] ProviderCredentials $credentials, Email $email, string $reference, ?\Closure $heartbeat = null): TransportOutcome
    {
        if (! Str::isUuid($reference) || $credentials->mode->isApi()) {
            throw new \InvalidArgumentException('Invalid SMTP provider dispatch.');
        }
        if ($credentials->mode->provider() === 'brevo') {
            $email->getHeaders()->addTextHeader('X-Mailin-custom', 'emailing_reference:'.$reference);
        } else {
            $email->getHeaders()->addTextHeader('X-SMTPAPI', json_encode(['unique_args' => ['emailing_reference' => $reference]], JSON_THROW_ON_ERROR));
        }
        try {
            $this->smtp->send($credentials->smtpOptions(), $email, $heartbeat);

            return new TransportOutcome('smtp_accepted');
        } catch (SmtpPolicyViolation) {
            return new TransportOutcome('smtp_rejected', 'smtp_tls_required');
        } catch (SmtpRejected $error) {
            return new TransportOutcome('smtp_rejected', 'smtp_negative_'.$error->stage,
                retryable: $error->replyCode >= 400 && $error->replyCode < 500);
        } catch (SmtpPreAcceptanceFailure) {
            return new TransportOutcome('smtp_rejected', 'smtp_before_terminator', retryable: true);
        } catch (\Throwable) {
            return new TransportOutcome('smtp_unconfirmed', 'smtp_unconfirmed');
        }
    }
}
