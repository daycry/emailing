<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function registration(): array
    {
        return ['name' => 'Equipo de prueba', 'account_name' => 'Cuenta de prueba', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123'];
    }

    public function test_registration_creates_one_owner_and_login_logout_work(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/register', $this->registration())->assertRedirect('/dashboard');
        $user = User::sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, Account::sole()->owner_id);
        $this->assertSame('admin', Membership::sole()->role);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $user->id, 'action' => 'account.created']);
        $this->get('/dashboard')->assertOk()->assertSee('Cuenta de prueba');
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Correct-password-123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_registration_rejects_direct_post(): void
    {
        config(['emailing.registration_enabled' => false]);
        $this->post('/register', $this->registration())->assertForbidden();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('accounts', 0);
    }

    public function test_registration_rolls_back_all_records_if_account_creation_fails(): void
    {
        Account::creating(fn () => throw new \RuntimeException('Controlled failure'));
        try {
            app(CreateNewUser::class)->create($this->registration());
            $this->fail('Expected transaction rollback.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Controlled failure', $error->getMessage());
        } finally {
            Account::flushEventListeners();
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('accounts', 0);
        $this->assertDatabaseCount('memberships', 0);
    }

    public function test_selector_requires_current_membership_and_rechecks_revocation(): void
    {
        $owner = app(CreateNewUser::class)->create($this->registration());
        $first = Account::sole();
        $other = User::factory()->create();
        $second = Account::create(['name' => 'Segunda cuenta', 'owner_id' => $other->id]);
        $membership = Membership::create(['account_id' => $second->id, 'user_id' => $owner->id, 'role' => 'read-only', 'scopes' => []]);
        $foreign = Account::create(['name' => 'Cuenta ajena', 'owner_id' => $other->id]);
        $this->actingAs($owner)->post('/accounts/switch', ['account_id' => $second->id])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertSee('Solo lectura');
        $this->post('/accounts/switch', ['account_id' => $foreign->id])->assertForbidden();
        $this->assertSame($second->id, session('account_id'));
        $membership->delete();
        $this->get('/dashboard')->assertForbidden();
        $this->post('/accounts/switch', ['account_id' => $first->id])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
    }

    public function test_csrf_is_enforced_outside_test_bypass(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/register', $this->registration())->assertStatus(419);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_reset_password_is_single_use_and_delivered_through_mailpit(): void
    {
        $email = 'reset-'.Str::uuid().'@example.test';
        $user = User::factory()->create(['email' => $email]);
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => env('MAIL_HOST', '127.0.0.1'), 'mail.mailers.smtp.port' => (int) env('MAIL_PORT', 11025), 'mail.mailers.smtp.scheme' => null]);
        $this->post('/forgot-password', ['email' => $email])->assertSessionHasNoErrors();
        $messages = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/search', ['query' => 'to:'.$email])->throw()->json('messages');
        $this->assertCount(1, $messages);
        $message = Http::timeout(5)->get('http://127.0.0.1:18025/api/v1/message/'.$messages[0]['ID'])->throw()->json();
        $body = html_entity_decode(($message['Text'] ?? '').' '.($message['HTML'] ?? ''));
        $this->assertSame(1, preg_match('~/reset-password/([a-f0-9]{64})~', $body, $matches));
        $reset = ['token' => $matches[1], 'email' => $email, 'password' => 'New-correct-password-456', 'password_confirmation' => 'New-correct-password-456'];
        $this->post('/reset-password', $reset)->assertRedirect('/login')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($reset['password'], $user->fresh()->password));
        $this->post('/reset-password', $reset)->assertSessionHasErrors('email');
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }
}
