<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\GoogleIdentityResolver;
use App\Services\Auth\GoogleLoginFlow;
use App\Support\TrustedUrl;
use Illuminate\Http\Request;

class GoogleLoginController
{
    public function start(Request $request, GoogleLoginFlow $flow, string $intent = 'login')
    {
        return $this->safely($request, fn () => $flow->start($request, $intent));
    }

    public function link(Request $request, GoogleLoginFlow $flow)
    {
        return $this->safely($request, fn () => $flow->start($request, 'link'));
    }

    public function callback(Request $request, GoogleLoginFlow $flow)
    {
        return $this->safely($request, fn () => $flow->callback($request));
    }

    public function confirm(Request $request, GoogleLoginFlow $flow)
    {
        return $this->safely($request, fn () => response()->view('auth.google-confirm', ['pending' => $flow->registration($request)]));
    }

    public function register(Request $request, GoogleLoginFlow $flow)
    {
        return $this->safely($request, fn () => $flow->register($request));
    }

    public function identities(Request $request)
    {
        return response()->view('auth.identities', ['identities' => $request->user()->externalIdentities()->get()])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function unlink(Request $request, int $identity, GoogleIdentityResolver $resolver)
    {
        return $this->safely($request, function () use ($request, $identity, $resolver) {
            $resolver->unlink($request->user(), $identity);

            return redirect(TrustedUrl::route('identities'))->with('status', 'Google se ha desvinculado. Tu acceso local sigue disponible.');
        });
    }

    private function safely(Request $request, callable $operation)
    {
        try {
            $response = $operation();
        } catch (\Throwable) {
            // Provider errors can embed credentials. Never report or reflect their message/request.
            $response = redirect(TrustedUrl::route($request->user() ? 'identities' : 'login'))
                ->withErrors(['google' => GoogleLoginFlow::ERROR]);
        }

        return $response->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
