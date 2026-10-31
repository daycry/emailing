<?php

namespace App\Enums;

enum MailTransportMode: string
{
    case Log = 'log';
    case Mailpit = 'mailpit';
    case BrevoSmtp = 'brevo_smtp';
    case BrevoApi = 'brevo_api';
    case SendGridSmtp = 'sendgrid_smtp';
    case SendGridApi = 'sendgrid_api';

    public function provider(): ?string
    {
        return match ($this) {
            self::BrevoSmtp, self::BrevoApi => 'brevo',
            self::SendGridSmtp, self::SendGridApi => 'sendgrid',
            default => null,
        };
    }

    public function isExternal(): bool
    {
        return $this->provider() !== null;
    }

    public function isApi(): bool
    {
        return in_array($this, [self::BrevoApi, self::SendGridApi], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Log => 'Log privado', self::Mailpit => 'Mailpit del laboratorio',
            self::BrevoSmtp => 'Brevo · SMTP', self::BrevoApi => 'Brevo · API',
            self::SendGridSmtp => 'SendGrid · SMTP', self::SendGridApi => 'SendGrid · API',
        };
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $mode) {
            $labels[$mode->value] = $mode->label();
        }

        return $labels;
    }
}
