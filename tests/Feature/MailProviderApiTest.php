<?php

namespace Tests\Feature;

use App\Models\MailSetting;
use App\Services\AccountMailTransport;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderApiTest extends TestCase
{
    use DatabaseMigrations, MailProviderFixtures;

    public function test_brevo_api_persists_its_own_acceptance_and_provider_receipt_without_smtp(): void
    {
        [, $account] = $this->providerOwner();
        $sender = $this->providerSender($account);
        $this->providerConnection($account, 'brevo');
        MailSetting::create(['account_id' => $account->id, 'mode' => 'brevo_api']);
        config(['emailing.external_enabled' => true, 'app.url' => 'https://emailing.example.test']);
        Http::preventStrayRequests();
        Http::fake(['https://api.brevo.com/v3/smtp/email' => Http::response(['messageId' => 'synthetic-brevo-message-id'], 201)]);
        $message = app(AccountMailTransport::class)->send($account, $sender->id, [
            'recipient' => 'recipient@example.test', 'subject' => 'Proveedor API', 'text' => 'Sintético',
        ]);
        $this->assertSame('api_accepted', $message->status);
        $this->assertSame('synthetic-brevo-message-id', $message->provider_message_id);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'synthetic-brevo-api-secret')
            && $request['headers']['X-Mailin-custom'] === 'emailing_reference:'.$message->provider_reference);
    }
}
