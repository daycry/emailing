<?php

namespace App\Http\Controllers;

use App\Services\SuppressionService;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $token, SuppressionService $service)
    {
        $valid = $request->hasValidSignature(false);
        $available = $valid && ($request->isMethod('post') ? $service->unsubscribe($token) : $service->available($token));

        return response()->view('unsubscribe', ['available' => $available, 'completed' => $available && $request->isMethod('post')], $available ? 200 : 404)
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
