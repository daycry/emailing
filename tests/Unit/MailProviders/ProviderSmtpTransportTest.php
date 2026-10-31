<?php

namespace Tests\Unit\MailProviders;

use App\Enums\MailTransportMode;
use App\Services\MailProviders\ProviderCredentials;
use App\Services\MailProviders\ProviderSmtpTransport;
use App\Services\SmtpConnector;
use App\Support\SmtpPreAcceptanceFailure;
use App\Support\SmtpRejected;
use App\Support\SmtpUnconfirmed;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class ProviderSmtpTransportTest extends TestCase
{
    private const REFERENCE = '00000000-0000-4000-8000-000000000001';

    #[DataProvider('profiles')]
    public function test_profiles_authenticate_the_correct_provider_and_only_send_opaque_correlation(MailTransportMode $mode, string $security): void
    {
        $connector = $this->createMock(SmtpConnector::class);
        $credentials = new ProviderCredentials($mode, smtpUsername: 'synthetic-smtp-user', smtpPassword: 'synthetic-smtp-secret', smtpSecurity: $security);
        $connector->expects($this->once())->method('send')->willReturnCallback(function (array $config, Email $email) use ($mode, $security): void {
            $this->assertSame($mode->provider() === 'brevo' ? 'smtp-relay.brevo.com' : 'smtp.sendgrid.net', $config['host']);
            $this->assertSame($security === 'smtps' ? 465 : 587, $config['port']);
            $this->assertSame($security, $config['security']);
            $this->assertSame($mode->provider() === 'brevo' ? 'synthetic-smtp-user' : 'apikey', $config['username']);
            $this->assertSame('synthetic-smtp-secret', $config['password']);
            if ($mode->provider() === 'brevo') {
                $this->assertSame('emailing_reference:'.self::REFERENCE, $email->getHeaders()->get('X-Mailin-custom')->getBodyAsString());
            } else {
                $header = json_decode($email->getHeaders()->get('X-SMTPAPI')->getBodyAsString(), true, 512, JSON_THROW_ON_ERROR);
                $this->assertSame(['unique_args' => ['emailing_reference' => self::REFERENCE]], $header);
            }
        });
        $result = (new ProviderSmtpTransport($connector))->send($credentials, $this->email(), self::REFERENCE);
        $this->assertSame('smtp_accepted', $result->status);
        $this->assertFalse($result->retryable);
    }

    public static function profiles(): array
    {
        return [[MailTransportMode::BrevoSmtp, 'starttls'], [MailTransportMode::BrevoSmtp, 'smtps'],
            [MailTransportMode::SendGridSmtp, 'starttls'], [MailTransportMode::SendGridSmtp, 'smtps']];
    }

    #[DataProvider('replies')]
    public function test_only_observed_transient_negatives_are_retryable(int $code, bool $retryable): void
    {
        $connector = $this->createMock(SmtpConnector::class);
        $connector->expects($this->once())->method('send')->willThrowException(new SmtpRejected('data_final', $code));
        $credentials = new ProviderCredentials(MailTransportMode::SendGridSmtp, smtpPassword: 'synthetic-smtp-secret');
        $result = (new ProviderSmtpTransport($connector))->send($credentials, $this->email(), self::REFERENCE);
        $this->assertSame('smtp_rejected', $result->status);
        $this->assertSame($retryable, $result->retryable);
        $this->assertSame('smtp_negative_data_final', $result->diagnostic);
    }

    public static function replies(): array
    {
        return [[450, true], [451, true], [535, false], [550, false]];
    }

    public function test_a_demonstrated_failure_before_the_terminator_is_safe_to_retry(): void
    {
        $connector = $this->createMock(SmtpConnector::class);
        $connector->expects($this->once())->method('send')->willThrowException(new SmtpPreAcceptanceFailure);
        $credentials = new ProviderCredentials(MailTransportMode::SendGridSmtp, smtpPassword: 'synthetic-smtp-secret');
        $result = (new ProviderSmtpTransport($connector))->send($credentials, $this->email(), self::REFERENCE);
        $this->assertSame('smtp_before_terminator', $result->diagnostic);
        $this->assertTrue($result->retryable);
    }

    public function test_an_unknown_transport_result_does_not_retry_or_expose_vendor_errors(): void
    {
        $connector = $this->createMock(SmtpConnector::class);
        $connector->expects($this->once())->method('send')->willThrowException(new SmtpUnconfirmed);
        $credentials = new ProviderCredentials(MailTransportMode::SendGridSmtp, smtpPassword: 'synthetic-smtp-secret');
        $result = (new ProviderSmtpTransport($connector))->send($credentials, $this->email(), self::REFERENCE);
        $this->assertSame(['status' => 'smtp_unconfirmed', 'diagnostic' => 'smtp_unconfirmed'], $result->attributes());
        $this->assertFalse($result->retryable);
    }

    private function email(): Email
    {
        return (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('Proveedor SMTP')->text('Sintético');
    }
}
