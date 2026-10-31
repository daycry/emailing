<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\MailSettings;
use App\Models\AuditEvent;
use App\Models\MailProviderConnection;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Services\AccountMailTransport;
use App\Services\MailSettingsService;
use App\Services\SmtpConnector;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderSettingsTest extends TestCase
{
    use DatabaseMigrations;
    use MailProviderFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        config(['emailing.external_enabled' => false]);
        Http::preventStrayRequests();
    }

    public function test_an_external_mode_can_be_prepared_without_enabling_or_connecting_it(): void
    {
        $owner = app(CreateNewUser::class)->create([
            'name' => 'Propietario', 'account_name' => 'Equipo de proveedores',
            'email' => 'provider-owner@example.test', 'password' => 'Correct-password-123',
            'password_confirmation' => 'Correct-password-123',
        ]);
        $account = $owner->memberships()->sole()->account;
        $this->actingAs($owner)->withSession(['account_id' => $account->id]);
        Http::preventStrayRequests();
        $this->mock(SmtpConnector::class)->shouldNotReceive('send');

        app(MailSettingsService::class)->saveSettings([
            'mode' => 'brevo_smtp', 'smtp_host' => null, 'smtp_port' => 587,
            'smtp_security' => 'starttls', 'smtp_username' => '', 'smtp_password' => '',
        ]);

        $this->assertSame('brevo_smtp', MailSetting::where('account_id', $account->id)->sole()->mode);
        $this->assertFalse((bool) config('emailing.external_enabled', false));
        Http::assertNothingSent();
    }

    public function test_provider_credentials_are_encrypted_separated_and_hidden_from_serialization_and_audit(): void
    {
        [, $account] = $this->providerOwner();
        Http::preventStrayRequests();
        $this->mock(SmtpConnector::class)->shouldNotReceive('send');
        $settings = app(MailSettingsService::class);
        foreach (['brevo', 'sendgrid'] as $provider) {
            $settings->saveProviderSettings($provider, ['enabled' => false, 'api_key' => 'synthetic-'.$provider.'-api-secret',
                'smtp_password' => 'synthetic-'.$provider.'-smtp-secret']);
        }
        $rows = $settings->providerConnections()->keyBy('provider');
        $this->assertCount(2, $rows);
        foreach (['brevo', 'sendgrid'] as $provider) {
            $row = $rows[$provider];
            $this->assertSame($account->id, $row->account_id);
            $this->assertSame('synthetic-'.$provider.'-api-secret', $row->api_key);
            $this->assertStringNotContainsString('synthetic-', $row->getRawOriginal('api_key'));
            $this->assertStringNotContainsString('synthetic-', $row->getRawOriginal('smtp_password'));
            foreach (MailProviderConnection::SECRET_FIELDS as $field) {
                $this->assertArrayNotHasKey($field, $row->toArray());
            }
        }
        $this->assertStringNotContainsString('synthetic-', AuditEvent::where('account_id', $account->id)->get()->toJson());
        Http::assertNothingSent();
    }

    public function test_blank_values_preserve_secrets_and_clear_is_scoped_to_provider_and_selected_fields(): void
    {
        [, $account] = $this->providerOwner();
        $brevo = $this->providerConnection($account, 'brevo');
        $sendgrid = $this->providerConnection($account, 'sendgrid');
        $before = $sendgrid->refresh()->getRawOriginal();
        $settings = app(MailSettingsService::class);
        $settings->saveProviderSettings('brevo', ['api_key' => '', 'smtp_password' => '']);
        $this->assertSame('synthetic-brevo-api-secret', $brevo->fresh()->api_key);
        $settings->saveProviderSettings('brevo', ['clear_fields' => ['api_key']]);
        $this->assertNull($brevo->fresh()->api_key);
        $this->assertSame('synthetic-brevo-smtp-secret', $brevo->fresh()->smtp_password);
        $settings->saveProviderSettings('brevo', ['clear_credentials' => true]);
        $this->assertFalse($brevo->fresh()->enabled);
        foreach (MailProviderConnection::SECRET_FIELDS as $field) {
            $this->assertNull($brevo->fresh()->{$field});
        }
        $this->assertSame($before, $sendgrid->fresh()->getRawOriginal());
    }

    public function test_accounts_cannot_read_or_mutate_other_provider_credentials(): void
    {
        [$first, $account] = $this->providerOwner();
        $owned = $this->providerConnection($account, 'brevo');
        [$second, $foreign] = $this->providerOwner();
        $other = $this->providerConnection($foreign, 'brevo', ['api_key' => 'synthetic-foreign-api-secret']);
        $this->actingAs($first)->withSession(['account_id' => $account->id]);
        $service = app(MailSettingsService::class);
        $this->assertSame([$owned->id], $service->providerConnections()->pluck('id')->all());
        $service->saveProviderSettings('brevo', ['account_id' => $foreign->id, 'id' => $other->id, 'api_key' => 'synthetic-updated-own-secret']);
        $this->assertSame('synthetic-updated-own-secret', $owned->fresh()->api_key);
        $this->assertSame('synthetic-foreign-api-secret', $other->fresh()->api_key);
        $this->assertSame($account->id, $owned->fresh()->account_id);
        $this->actingAs($second)->withSession(['account_id' => $account->id]);
        $this->expectException(HttpException::class);
        $service->saveProviderSettings('brevo', ['api_key' => 'synthetic-forbidden-secret']);
    }

    public function test_revoked_settings_permission_blocks_service_and_livewire_without_changing_credentials(): void
    {
        [$owner, $account] = $this->providerOwner();
        $connection = $this->providerConnection($account, 'brevo');
        $component = Livewire::test(MailSettings::class);
        $owner->memberships()->where('account_id', $account->id)->update(['role' => 'restricted', 'scopes' => ['settings.read']]);
        $component->call('saveProviderSettings', 'brevo', ['api_key' => 'synthetic-forbidden-secret'])->assertForbidden();
        $this->assertSame('synthetic-brevo-api-secret', $connection->fresh()->api_key);
        try {
            app(MailSettingsService::class)->saveProviderSettings('brevo', ['clear_credentials' => true]);
            $this->fail('Se aceptó una escritura tras retirar el permiso.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame('synthetic-brevo-api-secret', $connection->fresh()->api_key);
    }

    public function test_livewire_keeps_credentials_out_of_public_state_html_and_persisted_audit(): void
    {
        [, $account] = $this->providerOwner();
        $component = Livewire::test(MailSettings::class)->call('saveProviderSettings', 'brevo', [
            'api_key' => 'synthetic-livewire-api-secret', 'smtp_password' => 'synthetic-livewire-smtp-secret',
        ])->assertHasNoErrors()->assertDontSee('synthetic-livewire-api-secret')->assertDontSee('synthetic-livewire-smtp-secret');
        $state = json_encode($component->snapshot, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic-livewire-', $state);
        $this->assertStringNotContainsString('synthetic-livewire-', AuditEvent::where('account_id', $account->id)->get()->toJson());
    }

    public function test_enabling_an_incomplete_provider_or_using_an_unknown_provider_is_rejected_atomically(): void
    {
        [, $account] = $this->providerOwner();
        foreach ([['brevo', ['enabled' => true, 'api_key' => 'synthetic-api-secret']],
            ['sendgrid', ['enabled' => true, 'smtp_password' => 'synthetic-smtp-secret']],
            ['other', ['enabled' => false]], ['brevo', ['smtp_security' => 'plain']],
            ['brevo', ['api_key' => "synthetic-secret\r\ninjection"]],
            ['sendgrid', ['webhook_public_key' => 'synthetic-invalid-public-key']]] as [$provider, $input]) {
            try {
                app(MailSettingsService::class)->saveProviderSettings($provider, $input);
                $this->fail('Se guardó una configuración inválida.');
            } catch (ValidationException) {
                $this->assertSame(0, MailProviderConnection::where('account_id', $account->id)->count());
            }
        }
    }

    public function test_external_sender_authorization_is_distinct_and_survives_legacy_edits_only_for_the_same_address(): void
    {
        [, $account] = $this->providerOwner();
        $service = app(MailSettingsService::class);
        $service->saveSender(null, ['email' => 'sender@example.test', 'name' => 'Sintético', 'reply_to' => null, 'lab_authorized' => true]);
        $sender = MailSender::where('account_id', $account->id)->sole();
        $this->assertFalse($sender->external_authorized);
        $service->saveSender($sender->id, ['email' => $sender->email, 'name' => $sender->name, 'reply_to' => null, 'lab_authorized' => false, 'external_authorized' => true]);
        $this->assertTrue($sender->fresh()->external_authorized);
        $this->assertFalse($sender->fresh()->lab_authorized);
        $service->saveSender($sender->id, ['email' => 'changed@example.test', 'name' => $sender->name, 'reply_to' => null, 'lab_authorized' => false]);
        $this->assertFalse($sender->fresh()->external_authorized);
    }

    public function test_a_prepared_external_mode_stays_blocked_with_the_default_deployment_gate(): void
    {
        [, $account] = $this->providerOwner();
        $sender = $this->providerSender($account);
        $this->providerConnection($account, 'brevo');
        MailSetting::create(['account_id' => $account->id, 'mode' => 'brevo_smtp']);
        Http::preventStrayRequests();
        $this->mock(SmtpConnector::class)->shouldNotReceive('send');
        $message = app(AccountMailTransport::class)->send($account, $sender->id, [
            'recipient' => 'recipient@example.test', 'subject' => 'Salida cerrada', 'text' => 'Sintético',
        ]);
        $this->assertSame('blocked', $message->status);
        $this->assertSame('external_not_enabled', $message->diagnostic);
        Http::assertNothingSent();
    }
}
