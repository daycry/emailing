<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        RateLimiter::for('login', function ($request) {
            $email = $request->input('email');

            return Limit::perMinute(5)->by((is_string($email) ? mb_strtolower($email) : '').'|'.$request->ip());
        });
        RateLimiter::for('google-start', fn ($request) => Limit::perMinute(15)->by('google-start|'.$request->ip()));
        RateLimiter::for('google-callback', fn ($request) => Limit::perMinute(30)->by('google-callback|'.$request->ip()));
        RateLimiter::for('google-registration', fn ($request) => Limit::perMinute(5)->by('google-registration|'.$request->ip()));
        RateLimiter::for('google-unlink', fn ($request) => Limit::perMinute(15)->by('google-unlink|'.$request->ip()));
    }
}
