<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Services\AccountMailTransport;
use App\Services\SmtpConnector;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderSmtpTest extends TestCase
{
    use DatabaseMigrations, MailProviderFixtures;

    public function test_enabled_brevo_smtp_uses_its_account_profile_and_a_durable_opaque_reference(): void
    {
        [, $account] = $this->providerOwner();
        $sender = $this->providerSender($account);
        $this->providerConnection($account, 'brevo');
        MailSetting::create(['account_id' => $account->id, 'mode' => 'brevo_smtp', 'smtp_host' => 'untrusted.example.test']);
        config(['emailing.external_enabled' => true, 'app.url' => 'https://emailing.example.test']);
        Http::preventStrayRequests();
        $calls = [];
        $this->mock(SmtpConnector::class)->shouldReceive('send')->andReturnUsing(function (array $config, Email $email) use (&$calls): void {
            $calls[] = ['config' => $config, 'reference' => $email->getHeaders()->get('X-Mailin-custom')?->getBodyAsString()];
        });
        $message = app(AccountMailTransport::class)->send($account, $sender->id, [
            'recipient' => 'recipient@example.test', 'subject' => 'Proveedor SMTP', 'text' => 'Contenido sintético',
        ]);
        $this->assertSame('smtp_accepted', $message->status);
        $this->assertCount(1, $calls);
        $this->assertSame('smtp-relay.brevo.com', $calls[0]['config']['host']);
        $this->assertSame('starttls', $calls[0]['config']['security']);
        $this->assertSame('synthetic-smtp-user', $calls[0]['config']['username']);
        $this->assertSame('synthetic-brevo-smtp-secret', $calls[0]['config']['password']);
        $this->assertTrue(Str::isUuid($message->provider_reference));
        $this->assertSame('emailing_reference:'.$message->provider_reference, $calls[0]['reference']);
        Http::assertNothingSent();
    }
}
