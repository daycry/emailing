<?php

namespace App\Http\Controllers;

use App\Services\MailProviders\ProviderWebhookService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MailProviderWebhookController
{
    public function __invoke(Request $request, string $provider, string $connection, ProviderWebhookService $service): mixed
    {
        try {
            if (! $request->isSecure()) {
                throw new HttpException(401);
            }
            $service->consume($provider, $connection, (string) $request->attributes->get('mail_provider.raw_body'), [
                'authorization' => (string) $request->header('Authorization'),
                'timestamp' => (string) $request->header('X-Twilio-Email-Event-Webhook-Timestamp'),
                'signature' => (string) $request->header('X-Twilio-Email-Event-Webhook-Signature'),
            ]);

            return response()->noContent()->header('Cache-Control', 'no-store');
        } catch (HttpException $error) {
            return response()->json(['error' => $error->getStatusCode() === 401 ? 'Unauthorized' : 'Invalid payload'], $error->getStatusCode())->header('Cache-Control', 'no-store');
        } catch (\Throwable) {
            // Do not report exceptions that could carry provider credentials or raw webhook bytes.
            return response()->json(['error' => 'Temporarily unavailable'], 503)->header('Cache-Control', 'no-store');
        }
    }
}
