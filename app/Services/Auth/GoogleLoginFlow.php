<?php

namespace App\Services\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Support\TrustedUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

class GoogleLoginFlow
{
    public const ERROR = 'No se pudo completar el acceso con Google. Puedes acceder con tu contraseña o recuperar el acceso local.';

    public static function configured(): bool
    {
        $redirect = config('services.google.redirect');
        $origin = rtrim((string) config('app.url'), '/');

        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'))
            && is_string($redirect) && $redirect === $origin.'/auth/google/callback'
            && filter_var($redirect, FILTER_VALIDATE_URL)
            && (str_starts_with($origin, 'https://') || preg_match('~\Ahttp://127\.0\.0\.1(?::[0-9]+)?\z~D', $origin));
    }

    public function start(Request $request, string $intent)
    {
        if (! self::configured() || ! in_array($intent, ['login', 'register', 'link'], true)
            || ($intent === 'link' && ! $request->user()) || ($intent !== 'link' && $request->user())
            || ($intent === 'register' && ! config('emailing.registration_enabled'))) {
            $this->reject();
        }
        $binding = $request->session()->get('google.binding');
        if (! is_string($binding)) {
            $binding = Str::random(64);
            $request->session()->put('google.binding', $binding);
        }
        $nonce = Str::random(64);
        $provider = Socialite::buildProvider(GoogleProvider::class, config('services.google'));
        $response = $provider->setScopes(['openid', 'email', 'profile'])->with(['nonce' => $nonce, 'prompt' => 'select_account'])->redirect();
        $state = $request->session()->pull('state');
        $return = $this->returnTo($request, $intent);
        DB::table('google_login_attempts')->insert([
            'state_hash' => hash('sha256', $state), 'binding_hash' => hash('sha256', $binding),
            'nonce_hash' => hash('sha256', $nonce), 'actor_id' => $request->user()?->id,
            'intent' => $intent, 'return_to' => $return, 'expires_at' => now()->addMinutes(5),
        ]);
        // Bounded collection; never retain codes or identity payloads in these rows.
        foreach (['google_login_attempts', 'google_login_codes'] as $table) {
            $column = $table === 'google_login_codes' ? 'code_hash' : 'id';
            $ids = DB::table($table)->where('expires_at', '<', now()->subDay())->limit(100)->pluck($column);
            DB::table($table)->whereIn($column, $ids)->delete();
        }

        return $response;
    }

    /** Shared SQL consumption is independent of the lifecycle of loaded web sessions. */
    public function consume(string $state, string $binding, string $code, ?int $actorId): object
    {
        if (strlen($state) > 256 || strlen($binding) > 128 || strlen($code) > 4096 || $code === '' || $state === '') {
            $this->reject();
        }
        $attempt = DB::transaction(function () use ($state, $binding, $code, $actorId) {
            $row = DB::table('google_login_attempts')->where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            if (! $row || $row->consumed_at !== null || $row->expires_at <= now()->toDateTimeString()
                || ! hash_equals($row->binding_hash, hash('sha256', $binding))
                || ($row->actor_id !== null ? (int) $row->actor_id : null) !== $actorId) {
                $this->reject();
            }
            DB::table('google_login_attempts')->where('id', $row->id)->update(['consumed_at' => now()]);
            $row->code_reserved = DB::table('google_login_codes')->insertOrIgnore([
                'code_hash' => hash('sha256', $code), 'expires_at' => now()->addDay(),
            ]) === 1;

            return $row;
        }, 5);
        if (! $attempt->code_reserved) {
            $this->reject();
        }

        return $attempt;
    }

    public function callback(Request $request)
    {
        if (! self::configured() || ! is_string($request->query('state')) || ! is_string($request->query('code'))
            || $request->query('error') !== null || ! is_string($request->session()->get('google.binding'))) {
            $this->reject();
        }
        $attempt = $this->consume($request->query('state'), $request->session()->get('google.binding'), $request->query('code'), $request->user()?->id);
        $response = Http::asForm()->acceptJson()->timeout(5)->connectTimeout(3)->withOptions([
            'allow_redirects' => false,
            'progress' => static function ($total, $downloaded) {
                if ($total > 32768 || $downloaded > 32768) {
                    throw new \RuntimeException('Identity response too large.');
                }
            },
        ])->post('https://www.googleapis.com/oauth2/v4/token', [
            'grant_type' => 'authorization_code', 'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'), 'redirect_uri' => config('services.google.redirect'), 'code' => $request->query('code'),
        ]);
        if (! $response->successful() || strlen($response->body()) > 32768 || ! is_string($response->json('id_token'))) {
            $this->reject();
        }
        $identity = app(GoogleTokenVerifier::class)->verify($response->json('id_token'), $attempt->nonce_hash);
        $resolver = app(GoogleIdentityResolver::class);
        if ($attempt->intent === 'link') {
            $resolver->link($request->user(), $identity);

            return redirect(TrustedUrl::route('identities'))->with('status', 'Google se ha vinculado a tu usuario.');
        }
        $user = $resolver->resolve($identity);
        if ($user) {
            return $this->login($request, $user, $attempt->return_to);
        }
        if ($attempt->intent !== 'register' || ! config('emailing.registration_enabled') || ! $identity['email_authoritative']) {
            $this->reject();
        }
        $request->session()->put('google.registration', ['identity' => $identity, 'return_to' => $attempt->return_to, 'expires_at' => time() + 300]);

        return redirect(TrustedUrl::route('google.confirm'));
    }

    public function registration(Request $request): array
    {
        $pending = $request->session()->get('google.registration');
        if ($request->user() || ! config('emailing.registration_enabled') || ! is_array($pending) || ($pending['expires_at'] ?? 0) <= time()) {
            $this->reject();
        }

        return $pending;
    }

    public function register(Request $request)
    {
        $pending = $this->registration($request);
        try {
            $request->validate(['name' => ['required', 'string', 'max:100'], 'account_name' => ['required', 'string', 'max:100'],
                'confirm_registration' => ['accepted'], 'password' => ['nullable', 'string', 'confirmed', Password::min(12)]]);
        } catch (ValidationException $exception) {
            // Only correctable form errors return here. Identity/admission/database failures stay generic.
            $safeInput = [];
            foreach (['name', 'account_name'] as $field) {
                $value = $request->input($field);
                if (is_string($value)) {
                    $safeInput[$field] = mb_substr($value, 0, 100);
                }
            }
            $safeInput['confirm_registration'] = $request->input('confirm_registration') === '1' ? '1' : '';

            return redirect(TrustedUrl::route('google.confirm'))->withErrors($exception->errors())->withInput($safeInput);
        }
        $request->session()->forget('google.registration');
        $user = DB::transaction(function () use ($request, $pending) {
            $user = app(CreateNewUser::class)->createGoogle($request->only('name', 'account_name', 'password', 'password_confirmation'), $pending['identity']);
            app(GoogleIdentityResolver::class)->link($user, $pending['identity']);

            return $user;
        }, 5);

        return $this->login($request, $user, $pending['return_to']);
    }

    private function login(Request $request, User $user, string $return)
    {
        $target = rtrim(config('app.url'), '/').$return;
        $request->session()->put('url.intended', $target);
        if (filled($user->getAttribute('two_factor_secret'))) {
            // Existing 2FA may never be skipped. If its challenge is unavailable, fail closed.
            if (! Route::has('two-factor.login')) {
                $this->reject();
            }
            $request->session()->put(['login.id' => $user->id, 'login.remember' => false]);
            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect(TrustedUrl::route('two-factor.login'));
        }
        Auth::guard(config('fortify.guard', 'web'))->login($user);
        $request->session()->regenerate();
        $request->session()->forget(['google.binding', 'google.registration', 'google.mcp_return', 'url.intended']);

        return redirect($target);
    }

    /** Future OAuth controller calls this only after validating and persisting its pending request. */
    public function rememberMcpReturn(Request $request, string $pendingId): void
    {
        if (! Str::isUuid($pendingId)) {
            $this->reject();
        }
        $request->session()->put('google.mcp_return', ['pending_id' => $pendingId, 'expires_at' => time() + 600]);
    }

    private function returnTo(Request $request, string $intent): string
    {
        if ($intent === 'link') {
            return '/settings/identities';
        }
        $mcp = $request->session()->pull('google.mcp_return');
        if (is_array($mcp) && ($mcp['expires_at'] ?? 0) > time() && Str::isUuid($mcp['pending_id'] ?? '')) {
            return '/oauth/authorize?pending='.$mcp['pending_id'];
        }
        $candidate = $request->query('return_to', $request->session()->pull('url.intended', '/dashboard'));
        $origin = rtrim((string) config('app.url'), '/');
        if (is_string($candidate) && str_starts_with($candidate, $origin.'/')) {
            $candidate = substr($candidate, strlen($origin));
        }
        if (is_string($candidate) && (in_array($candidate, ['/dashboard', '/accounts'], true)
            || preg_match('~\A/invitations/[A-Za-z0-9]{64}\z~D', $candidate))) {
            return $candidate;
        }

        return '/dashboard';
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['google' => self::ERROR]);
    }
}
