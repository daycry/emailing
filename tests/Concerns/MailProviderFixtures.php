<?php

namespace Tests\Concerns;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\MailProviderConnection;
use App\Models\MailSender;
use Illuminate\Support\Str;

trait MailProviderFixtures
{
    private function providerOwner(): array
    {
        $user = app(CreateNewUser::class)->create([
            'name' => 'Propietario', 'account_name' => 'Equipo de proveedores',
            'email' => 'provider-'.Str::uuid().'@example.test', 'password' => 'Correct-password-123',
            'password_confirmation' => 'Correct-password-123',
        ]);
        $account = $user->memberships()->sole()->account;
        $this->actingAs($user)->withSession(['account_id' => $account->id]);

        return [$user, $account];
    }

    private function providerConnection(Account $account, string $provider, array $override = []): MailProviderConnection
    {
        return MailProviderConnection::create(array_replace([
            'account_id' => $account->id, 'provider' => $provider, 'enabled' => true, 'smtp_security' => 'starttls',
            'api_key' => 'synthetic-'.$provider.'-api-secret',
            'smtp_username' => $provider === 'sendgrid' ? null : 'synthetic-smtp-user',
            'smtp_password' => 'synthetic-'.$provider.'-smtp-secret',
            'webhook_secret' => $provider === 'brevo' ? str_repeat('synthetic-webhook-', 3) : null,
            'webhook_public_key' => $provider === 'sendgrid' ? $this->providerSigningKey()['public'] : null,
        ], $override));
    }

    private function providerSender(Account $account, array $override = []): MailSender
    {
        return MailSender::create(array_replace([
            'account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Proveedor sintético',
            'reply_to' => 'reply@example.test', 'lab_authorized' => true, 'external_authorized' => true,
        ], $override));
    }

    private function providerSigningKey(): array
    {
        $private = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->assertNotFalse($private);
        $details = openssl_pkey_get_details($private);
        $this->assertIsArray($details);

        return ['private' => $private, 'public' => $details['key']];
    }
}
