<?php

namespace Tests\Unit\MailProviders;

use App\Enums\MailTransportMode;
use App\Services\MailProviders\ProviderCredentials;
use App\Support\MailTransportConfiguration;
use PHPUnit\Framework\TestCase;

class ProviderCredentialsTest extends TestCase
{
    public function test_smtp_profiles_have_fixed_provider_hosts_verified_tls_modes_and_separate_credentials(): void
    {
        $brevo = new ProviderCredentials(MailTransportMode::BrevoSmtp, smtpUsername: 'synthetic-brevo-user', smtpPassword: 'synthetic-brevo-secret');
        $sendgrid = new ProviderCredentials(MailTransportMode::SendGridSmtp, smtpPassword: 'synthetic-sendgrid-secret', smtpSecurity: 'smtps');
        $this->assertSame(['host' => 'smtp-relay.brevo.com', 'port' => 587, 'security' => 'starttls',
            'username' => 'synthetic-brevo-user', 'password' => 'synthetic-brevo-secret'], $brevo->smtpOptions());
        $this->assertSame(['host' => 'smtp.sendgrid.net', 'port' => 465, 'security' => 'smtps',
            'username' => 'apikey', 'password' => 'synthetic-sendgrid-secret'], $sendgrid->smtpOptions());
    }

    public function test_snapshots_do_not_disclose_secrets_in_debug_or_json_and_cannot_be_serialized_into_jobs(): void
    {
        $snapshot = new ProviderCredentials(MailTransportMode::BrevoApi, apiKey: 'synthetic-api-secret');
        ob_start();
        var_dump($snapshot);
        $debug = ob_get_clean();
        $this->assertStringNotContainsString('synthetic-api-secret', $debug);
        $this->assertStringNotContainsString('synthetic-api-secret', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $this->expectException(\LogicException::class);
        serialize($snapshot);
    }

    public function test_an_api_snapshot_cannot_be_used_for_smtp(): void
    {
        $snapshot = new ProviderCredentials(MailTransportMode::SendGridApi, apiKey: 'synthetic-api-secret');
        $this->expectException(\LogicException::class);
        $snapshot->smtpOptions();
    }

    public function test_api_endpoints_are_chosen_by_the_channel_and_not_by_account_input(): void
    {
        $brevo = new ProviderCredentials(MailTransportMode::BrevoApi, apiKey: 'synthetic-api-secret');
        $sendgrid = new ProviderCredentials(MailTransportMode::SendGridApi, apiKey: 'synthetic-api-secret');
        $this->assertSame('https://api.brevo.com/v3/smtp/email', $brevo->apiEndpoint());
        $this->assertSame('https://api.sendgrid.com/v3/mail/send', $sendgrid->apiEndpoint());
    }

    public function test_unconfigured_or_mismatched_resolutions_cannot_appear_ready(): void
    {
        $credentials = new ProviderCredentials(MailTransportMode::SendGridApi, apiKey: 'synthetic-api-secret');
        $this->expectException(\InvalidArgumentException::class);
        new MailTransportConfiguration(MailTransportMode::BrevoApi, $credentials);
    }
}
