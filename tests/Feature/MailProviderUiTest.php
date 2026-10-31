<?php

namespace Tests\Feature;

use App\Livewire\MailSettings;
use App\Models\MailProviderConnection;
use App\Models\MailSetting;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Concerns\MailProviderFixtures;
use Tests\TestCase;

class MailProviderUiTest extends TestCase
{
    use MailProviderFixtures, RefreshDatabase;

    public function test_profiles_show_all_modes_mask_secrets_and_clear_only_the_selected_provider(): void
    {
        [, $account] = $this->providerOwner();
        Http::preventStrayRequests();
        $brevo = $this->providerConnection($account, 'brevo');
        $sendgrid = $this->providerConnection($account, 'sendgrid');
        $component = Livewire::test(MailSettings::class)->assertSee('Brevo')->assertSee('SendGrid')->assertSee('deshabilitado');
        foreach (['brevo_api', 'sendgrid_api', 'brevo_smtp', 'sendgrid_smtp'] as $mode) {
            $component->assertSeeHtml('value="'.$mode.'"');
        }
        foreach (['synthetic-brevo-api-secret', 'synthetic-sendgrid-api-secret', 'synthetic-smtp-user'] as $secret) {
            $component->assertDontSee($secret);
            $this->assertStringNotContainsString($secret, json_encode($component->snapshot, JSON_THROW_ON_ERROR));
        }
        $component->call('saveProviderSettings', 'brevo', ['clear_credentials' => true])->assertHasNoErrors();
        $this->assertFalse($brevo->refresh()->enabled);
        foreach (MailProviderConnection::SECRET_FIELDS as $field) {
            $this->assertNull($brevo->$field);
        }
        $this->assertSame('synthetic-sendgrid-api-secret', $sendgrid->refresh()->api_key);
        Http::assertNothingSent();
    }

    public function test_invalid_profile_is_correctable_and_external_authorization_is_independent(): void
    {
        [, $account] = $this->providerOwner();
        $component = Livewire::test(MailSettings::class)->call('saveProviderSettings', 'brevo', ['enabled' => true, 'api_key' => 'synthetic-incomplete-key'])
            ->assertHasErrors()->assertDontSee('synthetic-incomplete-key');
        $component->call('saveProviderSettings', 'brevo', ['enabled' => true, 'api_key' => 'synthetic-corrected-key',
            'webhook_secret' => str_repeat('synthetic-feedback-', 3)])->assertHasNoErrors()->assertDontSee('synthetic-corrected-key');
        $component->call('newSender')->set('senderEmail', 'external@example.test')->set('senderName', 'External sender')
            ->set('labAuthorized', false)->set('externalAuthorized', true)->call('saveSender')->assertHasNoErrors();
        $this->assertDatabaseHas('mail_senders', ['account_id' => $account->id, 'email' => 'external@example.test', 'lab_authorized' => false, 'external_authorized' => true]);
        MailSetting::create(['account_id' => $account->id, 'mode' => 'brevo_api']);
        $this->assertSame(1, app(TemplateService::class)->senders()->count());
        MailSetting::where('account_id', $account->id)->update(['mode' => 'log']);
        $this->assertSame(0, app(TemplateService::class)->senders()->count());
    }

    public function test_read_only_teammate_sees_safe_status_but_cannot_modify_provider_or_sender(): void
    {
        [$user, $account] = $this->providerOwner();
        $this->providerConnection($account, 'brevo');
        $user->memberships()->where('account_id', $account->id)->update(['role' => 'read-only', 'scopes' => []]);
        Livewire::test(MailSettings::class)->assertSee('Brevo')->assertDontSee('Guardar Brevo')->assertDontSee('synthetic-brevo-api-secret')
            ->call('saveProviderSettings', 'brevo', ['clear_credentials' => true])->assertForbidden();
        Livewire::test(MailSettings::class)->call('newSender')->assertForbidden();
        $this->assertDatabaseHas('mail_provider_connections', ['account_id' => $account->id, 'provider' => 'brevo', 'enabled' => true]);
    }
}
