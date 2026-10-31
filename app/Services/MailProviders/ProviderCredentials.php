<?php

namespace App\Services\MailProviders;

use App\Enums\MailTransportMode;

/** In-process credential snapshot, created while holding the account mutex. */
final readonly class ProviderCredentials
{
    public function __construct(
        public MailTransportMode $mode,
        #[\SensitiveParameter] private string $apiKey = '',
        #[\SensitiveParameter] private string $smtpUsername = '',
        #[\SensitiveParameter] private string $smtpPassword = '',
        public string $smtpSecurity = 'starttls',
    ) {
        if (! $mode->isExternal() || ! in_array($smtpSecurity, ['starttls', 'smtps'], true)
            || strlen($apiKey) > 4096 || strlen($smtpPassword) > 4096 || strlen($smtpUsername) > 1024
            || preg_match('/[\r\n\x00]/', $apiKey.$smtpUsername.$smtpPassword)
            || ($mode->isApi() && ($apiKey === '' || preg_match('/[\x00-\x20\x7f]/', $apiKey)))
            || (! $mode->isApi() && ($smtpPassword === '' || ($mode->provider() === 'brevo' && $smtpUsername === '')))) {
            throw new \InvalidArgumentException('Invalid provider credential snapshot.');
        }
    }

    public function apiKey(): string
    {
        if (! $this->mode->isApi()) {
            throw new \LogicException('An SMTP snapshot cannot authenticate an API request.');
        }

        return $this->apiKey;
    }

    public function apiEndpoint(): string
    {
        return match ($this->mode) {
            MailTransportMode::BrevoApi => 'https://api.brevo.com/v3/smtp/email',
            MailTransportMode::SendGridApi => 'https://api.sendgrid.com/v3/mail/send',
            default => throw new \LogicException('An SMTP snapshot has no API endpoint.'),
        };
    }

    public function smtpOptions(): array
    {
        if ($this->mode->isApi()) {
            throw new \LogicException('An API snapshot cannot authenticate an SMTP session.');
        }

        return ['host' => $this->mode->provider() === 'brevo' ? 'smtp-relay.brevo.com' : 'smtp.sendgrid.net',
            'port' => $this->smtpSecurity === 'smtps' ? 465 : 587, 'security' => $this->smtpSecurity,
            'username' => $this->mode->provider() === 'sendgrid' ? 'apikey' : $this->smtpUsername,
            'password' => $this->smtpPassword];
    }

    public function __debugInfo(): array
    {
        return ['mode' => $this->mode->value, 'credentials' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Provider credential snapshots must remain in-process.');
    }
}
