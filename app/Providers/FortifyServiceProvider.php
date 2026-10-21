<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request'=>$request]));
        Fortify::createUsersUsing(\App\Actions\Fortify\CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(\App\Actions\Fortify\ResetUserPassword::class);
        \Illuminate\Support\Facades\RateLimiter::for('login', fn ($request) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(mb_strtolower($request->input('email', '')).'|'.$request->ip()));
    }
}
