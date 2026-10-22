<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetSessionTest extends TestCase
{
    use RefreshDatabase;

    private function switchBrowser(array $state = []): void
    {
        $session = app('session')->driver();
        $session->invalidate();
        $session->replace($state);
        $session->save();
        app('auth')->forgetGuards();
    }

    public function test_reset_rejects_preexisting_registration_and_login_sessions_including_before_their_first_navigation(): void
    {
        $email = 'session-reset@example.test';
        $password = 'Correct-password-123';
        $this->post('/register', ['name' => 'Prueba', 'account_name' => 'Equipo', 'email' => $email, 'password' => $password, 'password_confirmation' => $password])->assertRedirect('/dashboard');
        $registrationBrowser = session()->all();
        $this->assertArrayHasKey('password_hash_web', $registrationBrowser);

        $this->switchBrowser();
        $this->post('/login', compact('email', 'password'))->assertRedirect('/dashboard');
        $loginBrowser = session()->all();
        $this->assertArrayHasKey('password_hash_web', $loginBrowser);
        $this->get('/dashboard')->assertOk();
        $navigatedBrowser = session()->all();

        $this->switchBrowser();
        $token = Password::broker()->createToken(User::sole());
        $newPassword = 'New-correct-password-456';
        $this->post('/reset-password', ['token' => $token, 'email' => $email, 'password' => $newPassword, 'password_confirmation' => $newPassword])->assertRedirect('/login');

        foreach ([$registrationBrowser, $loginBrowser, $navigatedBrowser] as $browser) {
            $this->switchBrowser($browser);
            $this->get('/dashboard')->assertRedirect('/login');
            $this->assertGuest();
        }

        $this->switchBrowser();
        $this->post('/login', ['email' => $email, 'password' => $newPassword])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk();
        $this->assertAuthenticatedAs(User::sole());
    }
}
