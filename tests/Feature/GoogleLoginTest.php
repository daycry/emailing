<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\Account;
use App\Models\ExternalIdentity;
use App\Models\IdentityAuditEvent;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Services\Auth\GoogleIdentityResolver;
use App\Services\Auth\GoogleLoginFlow;
use App\Services\Auth\GoogleTokenVerifier;
use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\TeamService;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use DatabaseMigrations;

    private static $key;

    private static int $browserIp = 10;

    private array $jwks;

    protected function setUp(): void
    {
        parent::setUp();
        self::$key ??= openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details(self::$key);
        $this->jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'synthetic-key',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])]]];
        config(['app.url' => 'https://emailing.example.test', 'services.google.client_id' => 'synthetic-client',
            'services.google.client_secret' => 'synthetic-secret', 'services.google.redirect' => 'https://emailing.example.test/auth/google/callback',
            'emailing.google_workspace_domains' => [], 'emailing.registration_enabled' => true]);
        Http::preventStrayRequests();
        Cache::forget('google-identity-jwks-v1');
    }

    private function browser(): void
    {
        $session = app('session')->driver();
        $session->invalidate();
        $session->save();
        app('auth')->forgetGuards();
    }

    private function start(string $intent = 'login', array $query = []): array
    {
        // Separate synthetic browser actors retain the real per-IP quotas unchanged.
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.(++self::$browserIp)]);
        $response = $this->get('/auth/google/redirect/'.$intent.'?'.http_build_query($query))->assertRedirect();
        $this->assertSame('accounts.google.com', parse_url($response->headers->get('Location'), PHP_URL_HOST));
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->assertSame(['openid', 'email', 'profile'], explode(' ', $parameters['scope']));

        return $parameters;
    }

    private function provider(array $parameters, array $overrides = [], ?string $token = null): void
    {
        // Replace previous HTTP stubs: fake() merges callbacks, which can hide nonce regressions.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        $token ??= $this->token($parameters, $overrides);
        Http::fake([
            'https://www.googleapis.com/oauth2/v4/token' => Http::response(['id_token' => $token, 'access_token' => 'unused-synthetic-access']),
            GoogleTokenVerifier::KEYS_URL => Http::response($this->jwks),
        ]);
    }

    private function token(array $parameters, array $overrides = [], mixed $key = null, string $kid = 'synthetic-key'): string
    {
        $claims = array_replace(['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person', 'aud' => 'synthetic-client',
            'iat' => time(), 'exp' => time() + 300, 'nonce' => $parameters['nonce'], 'email' => 'google-person@example.test',
            'email_verified' => true, 'hd' => 'workspace.example.test', 'name' => 'Google Persona'], $overrides);

        return JWT::encode($claims, $key ?? self::$key, 'RS256', $kid);
    }

    private function finishGoogle(array $parameters, ?string $code = null)
    {
        return $this->get('/auth/google/callback?'.http_build_query(['state' => $parameters['state'], 'code' => $code ?? Str::random(48)]));
    }

    public function test_signed_identity_logs_in_same_local_user_and_preserves_memberships_and_recovery_sessions(): void
    {
        $user = app(CreateNewUser::class)->create(['name' => 'Local Persona', 'account_name' => 'Cuenta propia', 'email' => 'local@example.test', 'password' => 'Local-password-123', 'password_confirmation' => 'Local-password-123']);
        app(GoogleIdentityResolver::class)->link($user, ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person']);
        $other = User::factory()->create();
        $second = Account::create(['name' => 'Segunda propia', 'owner_id' => $other->id]);
        Membership::create(['account_id' => $second->id, 'user_id' => $user->id, 'role' => 'read-only', 'scopes' => []]);
        Account::create(['name' => 'Ajena', 'owner_id' => $other->id]);
        $parameters = $this->start();
        $this->provider($parameters, ['email' => 'changed-google@example.test']);
        $this->finishGoogle($parameters, 'single-use-code')->assertRedirect('https://emailing.example.test/dashboard')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertAuthenticatedAs($user);
        $this->assertSame('local@example.test', $user->fresh()->email);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('memberships', 2);
        $this->get('/accounts')->assertSee('Segunda propia')->assertDontSee('Ajena');
        $this->assertArrayHasKey('password_hash_web', session()->all());
        $saved = session()->all();
        app(ResetUserPassword::class)->reset($user, ['password' => 'New-local-password-456', 'password_confirmation' => 'New-local-password-456']);
        $this->browser();
        $this->withSession($saved)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_signed_claims_and_signature_create_no_session_user_or_link(): void
    {
        foreach ([['iss' => 'https://attacker.example.test'], ['sub' => ''], ['sub' => ['bad']], ['aud' => 'foreign-client'],
            ['exp' => time() - 1], ['iat' => time() + 120], ['nonce' => 'foreign-nonce'], ['email_verified' => false],
            ['email' => ['bad']], ['email' => 'invalid'], ['azp' => 'foreign-client']] as $overrides) {
            $this->browser();
            $parameters = $this->start('register');
            $this->provider($parameters, $overrides);
            $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/login')->assertSessionHasErrors('google');
            $this->assertGuest();
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('external_identities', 0);
        }
        $parameters = $this->start('register');
        $foreign = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $token = JWT::encode(['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person', 'aud' => 'synthetic-client', 'exp' => time() + 300, 'iat' => time(), 'nonce' => $parameters['nonce']], $foreign, 'RS256', 'synthetic-key');
        $this->provider($parameters, [], $token);
        $this->finishGoogle($parameters)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_bad_state_code_replay_and_session_or_actor_switch_never_reuse_a_flow(): void
    {
        $parameters = $this->start('register');
        $this->provider($parameters);
        $bad = $parameters;
        $bad['state'] = 'foreign-state';
        $this->finishGoogle($bad)->assertSessionHasErrors('google');
        Http::assertNothingSent();
        $this->finishGoogle($parameters, 'consumed-code')->assertRedirect('https://emailing.example.test/auth/google/confirm');
        $this->finishGoogle($parameters, 'other-code')->assertSessionHasErrors('google');
        $this->browser();
        $next = $this->start('register');
        $this->provider($next);
        $this->finishGoogle($next, 'consumed-code')->assertSessionHasErrors('google');
        $this->finishGoogle($next, 'different-code')->assertSessionHasErrors('google');
        $this->browser();
        $parameters = $this->start('register');
        $this->provider($parameters);
        $this->browser();
        $this->finishGoogle($parameters)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $response = $this->actingAs($user)->post('/settings/identities/google');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->actingAs($other);
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_email_coincidence_is_not_linked_and_explicit_profile_link_unlink_require_auth_and_csrf(): void
    {
        $user = User::factory()->create(['email' => 'google-person@example.test']);
        $parameters = $this->start();
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('external_identities', 0);
        $this->get('/settings/identities')->assertRedirect('/login');
        $this->delete('/settings/identities/1')->assertRedirect('/login');
        $response = $this->actingAs($user)->post('/settings/identities/google');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/settings/identities');
        $this->get('/settings/identities')->assertOk()->assertSee('Google vinculado');
        $id = ExternalIdentity::sole()->id;
        $this->app->instance('env', 'local');
        $this->delete('/settings/identities/'.$id)->assertStatus(419);
        $this->post('/settings/identities/google')->assertStatus(419);
        $this->assertDatabaseCount('external_identities', 1);
        $this->app->instance('env', 'testing');
        $this->delete('/settings/identities/'.$id)->assertRedirect('https://emailing.example.test/settings/identities');
        $this->assertDatabaseCount('external_identities', 0);
    }

    public function test_google_registration_is_explicit_atomic_and_never_admits_on_email_alone(): void
    {
        $parameters = $this->start('register');
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/auth/google/confirm');
        $this->assertDatabaseCount('users', 0);
        $this->get('/auth/google/confirm')->assertOk()->assertSee('Confirma tu nueva cuenta');
        $input = ['name' => 'Nueva persona', 'account_name' => 'Mi cuenta', 'confirm_registration' => '1'];
        IdentityAuditEvent::creating(fn () => throw new \RuntimeException('Controlled audit failure'));
        try {
            $this->post('/auth/google/confirm', $input)->assertSessionHasErrors('google');
        } finally {
            IdentityAuditEvent::flushEventListeners();
        }
        foreach (['users', 'accounts', 'memberships', 'external_identities', 'audit_events'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $parameters = $this->start('register');
        $this->provider($parameters);
        $this->finishGoogle($parameters);
        $this->post('/auth/google/confirm', $input + ['email' => 'untrusted@example.test', 'role' => 'read-only'])->assertRedirect('https://emailing.example.test/dashboard');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('accounts', 1);
        $this->assertDatabaseCount('memberships', 1);
        $this->assertSame('admin', Membership::sole()->role);
        $this->assertSame('google-person@example.test', User::sole()->email);
        $this->assertFalse(User::sole()->local_password_enabled);
        $this->assertNotNull(User::sole()->email_verified_at);
        $this->delete('/settings/identities/'.ExternalIdentity::sole()->id)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('external_identities', 1);
        $this->browser();
        $this->post('/auth/google/confirm', $input)->assertSessionHasErrors('google');
        $parameters = $this->start('register');
        $this->provider($parameters, ['sub' => 'another-subject']);
        $this->finishGoogle($parameters);
        $this->post('/auth/google/confirm', $input)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('external_identities', 1);
    }

    public function test_registration_form_errors_preserve_verified_identity_and_original_expiry_for_correction(): void
    {
        $parameters = $this->start('register');
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/auth/google/confirm');
        $pending = session('google.registration');
        $pending['expires_at'] = time() + 120;
        $this->withSession(['google.registration' => $pending]);
        $input = ['name' => 'Correctable Persona', 'account_name' => 'Correctable account', 'confirm_registration' => '1',
            'password' => 'Synthetic-password-1234', 'password_confirmation' => 'Different-password-1234'];

        foreach ([['password', $input], ['account_name', array_replace($input, ['account_name' => '', 'password_confirmation' => $input['password']])]] as [$field, $invalid]) {
            $this->post('/auth/google/confirm', $invalid + ['email' => 'untrusted@example.test', 'token' => 'untrusted-input'])
                ->assertRedirect('https://emailing.example.test/auth/google/confirm')->assertSessionHasErrors($field)
                ->assertSessionDoesntHaveErrors('google');
            $this->assertSame($pending, session('google.registration'));
            $this->assertSame([], array_diff(array_keys(session()->getOldInput()), ['name', 'account_name', 'confirm_registration']));
            $this->assertSame($input['name'], session()->getOldInput('name'));
            $this->assertSame($invalid['account_name'], session()->getOldInput('account_name', ''));
            $this->get('/auth/google/confirm')->assertOk()->assertSee('Correctable Persona')->assertDontSee($input['password'])->assertDontSee('untrusted-input');
            $this->assertGuest();
            foreach (['users', 'accounts', 'memberships', 'external_identities', 'audit_events'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        }

        $this->post('/auth/google/confirm', array_replace($input, ['password_confirmation' => $input['password']]))
            ->assertRedirect('https://emailing.example.test/dashboard')->assertSessionMissing('google.registration');
        foreach (['users', 'accounts', 'memberships', 'external_identities'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertAuthenticatedAs(User::sole());
        $this->assertTrue(User::sole()->local_password_enabled);
        $this->browser();
        $this->post('/auth/google/confirm', $input)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 1);
        Http::assertSentCount(2); // One token exchange and one JWKS request for the entire corrected flow.
    }

    public function test_workspace_admission_is_signed_hd_and_registration_disabled_cannot_be_bypassed(): void
    {
        config(['emailing.google_workspace_domains' => ['workspace.example.test']]);
        $parameters = $this->start('register');
        $this->provider($parameters, ['email' => 'person@workspace.example.test', 'hd' => null]);
        $this->finishGoogle($parameters)->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
        $parameters = $this->start('register');
        $this->provider($parameters, ['hd' => 'workspace.example.test']);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/auth/google/confirm');
        config(['emailing.registration_enabled' => false]);
        $this->post('/auth/google/confirm', ['name' => 'Persona', 'account_name' => 'Cuenta', 'confirm_registration' => '1'])->assertSessionHasErrors('google');
        $this->get('/auth/google/redirect/register')->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_signup_requires_authoritative_email_but_external_identity_can_link_and_login(): void
    {
        $input = ['name' => 'Persona sintética', 'account_name' => 'Cuenta propia', 'confirm_registration' => '1'];
        foreach ([['email' => 'synthetic-'.Str::random(32).'@gmail.com', 'hd' => null, 'sub' => 'gmail-signup'],
            ['email' => 'workspace-person@example.test', 'hd' => 'workspace.example.test', 'sub' => 'workspace-signup'],
            ['email' => 'idn-person@example.test', 'hd' => 'example.xn--p1ai', 'sub' => 'idn-workspace-signup']] as $claims) {
            $this->browser();
            $parameters = $this->start('register');
            $this->provider($parameters, $claims);
            $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/auth/google/confirm');
            $this->post('/auth/google/confirm', $input)->assertRedirect('https://emailing.example.test/dashboard');
        }
        $this->browser();
        $external = ['email' => 'third-party@example.test', 'email_verified' => true, 'hd' => null, 'sub' => 'external-person'];
        $parameters = $this->start('register');
        $this->provider($parameters, $external);
        $this->get('/auth/google/callback?'.http_build_query(['state' => $parameters['state'], 'code' => Str::random(48), 'hd' => 'workspace.example.test', 'email_authoritative' => '1']))->assertSessionHasErrors('google');
        $this->post('/auth/google/confirm', $input + ['hd' => 'workspace.example.test', 'email_authoritative' => '1'])->assertSessionHasErrors('google');
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('accounts', 3);
        $this->assertDatabaseCount('external_identities', 3);
        $local = User::factory()->create(['email' => 'local-recovery@example.test']);
        $response = $this->actingAs($local)->post('/settings/identities/google');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $parameters);
        $this->provider($parameters, $external);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/settings/identities')->assertSessionHasNoErrors();
        $this->browser();
        $parameters = $this->start();
        $this->provider($parameters, $external);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/dashboard');
        $this->assertAuthenticatedAs($local);
        $this->assertSame('local-recovery@example.test', $local->fresh()->email);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('accounts', 3);
    }

    public function test_safe_return_uses_trusted_origin_and_server_pending_request_not_host_or_query(): void
    {
        $user = User::factory()->create();
        app(GoogleIdentityResolver::class)->link($user, ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person']);
        foreach (['https://attacker.example.test/path', '//attacker.example.test', '/\\attacker.example.test', '/oauth/authorize?client_id=forged', '/dashboard%0d%0aLocation:evil'] as $return) {
            $this->browser();
            $parameters = $this->start('login', ['return_to' => $return]);
            $this->provider($parameters);
            $this->withServerVariables(['HTTP_HOST' => 'attacker.example.test', 'HTTP_X_FORWARDED_HOST' => 'proxy-attacker.example.test']);
            $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/dashboard');
        }
        $this->browser();
        config(['mcp.enabled' => true, 'mcp.issuer' => 'https://emailing.example.test', 'mcp.resource' => 'https://emailing.example.test/mcp']);
        $client = app(AuthorizationServer::class)->register(['client_name' => 'Synthetic login client', 'redirect_uris' => ['http://127.0.0.1:18743/callback'], 'token_endpoint_auth_method' => 'none'], false);
        $request = Request::create('/');
        $request->setLaravelSession(session()->driver());
        try {
            app(GoogleLoginFlow::class)->rememberMcpReturn($request, (string) Str::uuid());
            $this->fail('An opaque UUID alone must never authorize an OAuth return');
        } catch (ValidationException) {
            $this->assertNull($request->session()->get('google.mcp_return'));
        }
        $pending = app(AuthorizationServer::class)->start($request, ['client_id' => $client->id, 'redirect_uri' => $client->redirect_uris[0], 'resource' => config('mcp.resource'), 'response_type' => 'code', 'scope' => 'contacts.read', 'code_challenge_method' => 'S256', 'code_challenge' => str_repeat('a', 43)])->id;
        app(GoogleLoginFlow::class)->rememberMcpReturn($request, $pending);
        $parameters = $this->start('login', ['return_to' => '/oauth/authorize?pending=attacker']);
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/oauth/authorize?pending='.$pending);
    }

    public function test_invitation_keeps_local_recipient_and_invited_role_after_google_login(): void
    {
        $user = User::factory()->create(['email' => 'recipient@example.test']);
        app(GoogleIdentityResolver::class)->link($user, ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person']);
        $owner = User::factory()->create();
        $account = Account::create(['name' => 'Inviting account', 'owner_id' => $owner->id]);
        Membership::create(['account_id' => $account->id, 'user_id' => $owner->id, 'role' => 'admin', 'scopes' => []]);
        Notification::fake();
        $this->actingAs($owner)->withSession(['account_id' => $account->id]);
        $invitation = app(TeamService::class)->invite($user->email, 'read-only', []);
        $token = null;
        Notification::assertSentOnDemand(TeamInvitation::class, function ($notification, $channels, $notifiable) use (&$token) {
            $token = basename(parse_url($notification->toMail($notifiable)->actionUrl, PHP_URL_PATH));

            return $channels === ['mail'];
        });
        $this->assertMatchesRegularExpression('/\\A[A-Za-z0-9]{64}\\z/D', $token);
        $this->assertSame($invitation->token_hash, hash('sha256', $token));
        $this->browser();
        $parameters = $this->start('login', ['return_to' => '/invitations/'.$token]);
        $this->provider($parameters, ['email' => 'changed-provider@example.test', 'role' => 'admin']);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/invitations/'.$token);
        $this->post('/invitations/'.$token)->assertRedirect('/team');
        $this->assertSame('read-only', Membership::where('user_id', $user->id)->sole()->role);
        $this->browser();
        $foreign = User::factory()->create();
        $this->actingAs($foreign)->post('/invitations/'.$token)->assertStatus(410);
        $otherToken = str_repeat('b', 64);
        Invitation::create(['account_id' => $account->id, 'invited_by' => $owner->id, 'email' => $user->email, 'role' => 'restricted', 'scopes' => [], 'token_hash' => hash('sha256', $otherToken), 'expires_at' => now()->addDay()]);
        $this->post('/invitations/'.$otherToken)->assertForbidden();
        $this->assertDatabaseCount('memberships', 2);
    }

    public function test_google_unconfigured_keeps_local_access_and_existing_two_factor_never_bypasses_challenge(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/login')->assertOk()->assertSee('Google no est', false)->assertSee('Recuperar acceso')->assertSee('name="password"', false);
        $this->get('/auth/google/redirect')->assertSessionHasErrors('google');
        config(['services.google.client_id' => 'synthetic-client']);
        Schema::table('users', fn ($table) => $table->text('two_factor_secret')->nullable());
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'synthetic-existing-secret'])->save();
        app(GoogleIdentityResolver::class)->link($user, ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'synthetic-person']);
        Route::get('/two-factor-challenge', fn () => 'Existing challenge')->name('two-factor.login');
        Route::getRoutes()->refreshNameLookups();
        $parameters = $this->start();
        $this->provider($parameters);
        $this->finishGoogle($parameters)->assertRedirect('https://emailing.example.test/two-factor-challenge')->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_shared_sql_state_and_code_consumption_is_atomic_across_real_processes(): void
    {
        $state = Str::random(40);
        $binding = Str::random(64);
        DB::table('google_login_attempts')->insert(['state_hash' => hash('sha256', $state), 'binding_hash' => hash('sha256', $binding), 'nonce_hash' => hash('sha256', 'nonce'), 'intent' => 'register', 'return_to' => '/dashboard', 'expires_at' => now()->addMinutes(5)]);
        $this->assertSame(['accepted', 'rejected'], $this->race([
            ['state' => $state, 'binding' => $binding, 'code' => 'same-concurrent-code'],
            ['state' => $state, 'binding' => $binding, 'code' => 'same-concurrent-code'],
        ]));
        $this->assertSame(1, DB::table('google_login_attempts')->whereNotNull('consumed_at')->count());
        $this->assertDatabaseCount('google_login_codes', 1);
        $states = [Str::random(40), Str::random(40)];
        foreach ($states as $next) {
            DB::table('google_login_attempts')->insert(['state_hash' => hash('sha256', $next), 'binding_hash' => hash('sha256', $binding), 'nonce_hash' => hash('sha256', 'nonce'), 'intent' => 'register', 'return_to' => '/dashboard', 'expires_at' => now()->addMinutes(5)]);
        }
        $this->assertSame(['accepted', 'rejected'], $this->race([
            ['state' => $states[0], 'binding' => $binding, 'code' => 'shared-code'],
            ['state' => $states[1], 'binding' => $binding, 'code' => 'shared-code'],
        ]));
        $this->assertSame(3, DB::table('google_login_attempts')->whereNotNull('consumed_at')->count());
        $this->assertDatabaseCount('users', 0);
    }

    public function test_google_rate_limits_remain_enforced_per_operation_and_ip(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.30']);
        for ($i = 0; $i < 15; $i++) {
            $this->get('/auth/google/redirect/login')->assertRedirect();
        }
        $this->get('/auth/google/redirect/login')->assertStatus(429)->assertHeader('Retry-After');
        for ($i = 0; $i < 30; $i++) {
            $this->get('/auth/google/callback?state=invalid&code=invalid')->assertRedirect();
        }
        $this->get('/auth/google/callback?state=invalid&code=invalid')->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_jwks_respects_cache_headers_rotates_with_bounded_refresh_and_fails_closed(): void
    {
        $parameters = ['nonce' => 'synthetic-nonce'];
        $hash = hash('sha256', $parameters['nonce']);
        $verifier = app(GoogleTokenVerifier::class);
        $token = $this->token($parameters);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([GoogleTokenVerifier::KEYS_URL => Http::response($this->jwks, 200, ['Cache-Control' => 'public, max-age=40', 'Age' => '10'])]);
        $this->assertSame('synthetic-person', $verifier->verify($token, $hash)['sub']);
        $verifier->verify($token, $hash);
        Http::assertSentCount(1);
        $this->travel(29)->seconds();
        $verifier->verify($token, $hash);
        Http::assertSentCount(1);
        $this->travel(2)->seconds();
        $verifier->verify($token, $hash);
        Http::assertSentCount(2);

        $rotatedKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($rotatedKey);
        $newJwks = ['keys' => [...$this->jwks['keys'], ['kty' => 'RSA', 'alg' => 'RS256', 'kid' => 'rotated-key',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])]]];
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([GoogleTokenVerifier::KEYS_URL => Http::response($newJwks, 200, ['Cache-Control' => 'max-age=999999'])]);
        $rotatedToken = $this->token($parameters, [], $rotatedKey, 'rotated-key');
        $verifier->verify($rotatedToken, $hash);
        $verifier->verify($token, $hash);
        Http::assertSentCount(1);
        $unknown = $this->token($parameters, [], $rotatedKey, 'unknown-key');
        $this->assertClosed(fn () => $verifier->verify($unknown, $hash));
        Http::assertSentCount(1);
        $this->travel(31)->seconds();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([GoogleTokenVerifier::KEYS_URL => Http::response([], 503)]);
        $this->assertClosed(fn () => $verifier->verify($unknown, $hash));
        $this->assertClosed(fn () => $verifier->verify($unknown, $hash));
        Http::assertSentCount(1);
        // A failed refresh never replaces a still-valid cached trusted key.
        $verifier->verify($rotatedToken, $hash);
        $this->travel(3601)->seconds();
        $this->assertClosed(fn () => $verifier->verify($rotatedToken, $hash));
        Http::assertSentCount(2);

        foreach (['google-identity-jwks-v1', 'google-identity-jwks-fetch-v1', 'google-identity-jwks-refresh-v1'] as $cacheKey) {
            Cache::forget($cacheKey);
        }
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([GoogleTokenVerifier::KEYS_URL => Http::response($this->jwks, 200, ['Cache-Control' => 'no-store, max-age=3600'])]);
        $verifier->verify($token, $hash);
        $this->assertNull(Cache::get('google-identity-jwks-v1'));
        $this->assertClosed(fn () => $verifier->verify($token, $hash));
        Http::assertSentCount(1);
        $this->assertDatabaseCount('users', 0);
    }

    private function assertClosed(callable $action): void
    {
        try {
            $action();
            $this->fail('Untrusted identity accepted.');
        } catch (ValidationException) {
            $this->assertGuest();
        }
    }

    private function race(array $inputs): array
    {
        $directory = storage_path('app/private/google-flow-race-'.Str::uuid());
        mkdir($directory, 0700, true);
        $processes = [];
        try {
            foreach ($inputs as $index => $input) {
                $process = new Process([PHP_BINARY, base_path('tests/support/google-flow-consume.php')], base_path(), ['APP_ENV' => 'testing', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing', 'DB_URL' => '']);
                $process->setInput(json_encode($input + ['ready' => $directory.'/ready-'.$index, 'start' => $directory.'/start'], JSON_THROW_ON_ERROR));
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (! is_file($directory.'/ready-0') || ! is_file($directory.'/ready-1')) {
                if (microtime(true) > $deadline) {
                    $this->fail('Google flow children did not reach barrier.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/start', 'go');
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Flow child failed: '.$process->getErrorOutput());
            }
            $results = array_map(fn ($process) => trim($process->getOutput()), $processes);
            sort($results);

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
