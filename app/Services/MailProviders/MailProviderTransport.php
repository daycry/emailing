<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;
use App\Support\TransportOutcome;
use Symfony\Component\Mime\Email;

final class MailProviderTransport
{
    public function __construct(private ProviderSmtpTransport $smtp, private BrevoApiTransport $brevo, private SendGridApiTransport $sendgrid) {}

    public function send(#[\SensitiveParameter] ProviderCredentials $credentials, Email $email, string $reference, ?\Closure $heartbeat = null): TransportOutcome
    {
        return match ($credentials->mode) {
            MailTransportMode::BrevoApi => $this->brevo->send($credentials, $email, $reference, $heartbeat),
            MailTransportMode::SendGridApi => $this->sendgrid->send($credentials, $email, $reference, $heartbeat),
            default => $this->smtp->send($credentials, $email, $reference, $heartbeat),
        };
    }
}
