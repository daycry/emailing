<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthInputAndNotificationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidEmails(): array
    {
        return ['array' => [['nested' => 'owner@example.test']], 'empty array' => [[]], 'number' => [123], 'null' => [null]];
    }

    #[DataProvider('invalidEmails')]
    public function test_http_auth_endpoints_reject_non_string_email_before_fortify_normalization_and_throttling(mixed $email): void
    {
        $this->postJson('/register', ['name' => 'Prueba', 'account_name' => 'Equipo', 'email' => $email, 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', ['email' => $email, 'password' => 'Correct-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_direct_registration_action_also_validates_type_before_normalizing(): void
    {
        $this->expectException(ValidationException::class);
        app(CreateNewUser::class)->create(['email' => ['owner@example.test']]);
    }

    public static function hostileOrigins(): array
    {
        return ['http request to https app' => ['http://attacker.example', 'https://trusted.example.test/base'], 'https request to http app' => ['https://attacker.example:8443', 'http://trusted.example.test:8080']];
    }

    private function assertTrustedMessage(string $base, string $path): void
    {
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->sole()->getOriginalMessage();
        $body = html_entity_decode($message->getHtmlBody().' '.$message->getTextBody());
        $this->assertStringContainsString($base.$path, $body);
        $this->assertStringNotContainsString('attacker.example', $body);
        $this->assertStringNotContainsString('proxy-attacker.example', $body);
    }

    #[DataProvider('hostileOrigins')]
    public function test_real_reset_notification_from_http_request_uses_app_url_despite_host_and_scheme_headers(string $origin, string $base): void
    {
        config(['app.url' => $base, 'mail.default' => 'array']);
        $user = User::factory()->create(['email' => 'reset-origin@example.test']);
        $this->withHeaders(['Host' => parse_url($origin, PHP_URL_HOST), 'X-Forwarded-Host' => 'proxy-attacker.example', 'X-Forwarded-Proto' => 'https'])
            ->postJson($origin.'/forgot-password', ['email' => $user->email])->assertOk();
        $this->assertTrustedMessage($base, '/reset-password/');
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    #[DataProvider('hostileOrigins')]
    public function test_real_invitation_notification_from_livewire_http_update_uses_app_url(string $origin, string $base): void
    {
        config(['app.url' => $base, 'mail.default' => 'array']);
        $owner = app(CreateNewUser::class)->create(['name' => 'Prueba', 'account_name' => 'Equipo', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123']);
        $this->actingAs($owner)->withHeaders(['Host' => parse_url($origin, PHP_URL_HOST), 'X-Forwarded-Host' => 'proxy-attacker.example', 'X-Forwarded-Proto' => 'https']);
        $page = $this->get($origin.'/team')->assertOk();
        $this->assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches));
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $this->withHeader('X-Livewire', 'true')->postJson($origin.Livewire::getUpdateUri(), ['components' => [[
            'snapshot' => $snapshot,
            'updates' => ['email' => 'recipient@example.test'],
            'calls' => [['path' => '', 'method' => 'invite', 'params' => []]],
        ]]])->assertOk();
        $this->assertTrustedMessage($base, '/invitations/');
        $this->assertDatabaseCount('invitations', 1);
    }
}
