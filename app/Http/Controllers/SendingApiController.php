<?php

namespace App\Http\Controllers;

use App\Models\AccountApiKey;
use App\Services\AccountKeyService;
use App\Services\SendingApiService;
use App\Support\ApiError;
use App\Support\SendingApiInput;
use Illuminate\Http\Request;

final class SendingApiController extends Controller
{
    public function __invoke(Request $request, AccountKeyService $keys, SendingApiService $sending)
    {
        try {
            return $this->execute($request, $keys, $sending);
        } catch (ApiError $error) {
            throw $error;
        } catch (\Illuminate\Validation\ValidationException) {
            throw new ApiError(422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw new ApiError(404);
        } catch (\Throwable) {
            // Translate before the routing pipeline can report sensitive SQL bindings.
            throw new ApiError(503);
        }
    }

    private function execute(Request $request, AccountKeyService $keys, SendingApiService $sending)
    {
        /** @var AccountApiKey $key */
        $key = $request->attributes->get('account_credential');
        $operation = $request->route()->getName();
        $scope = match ($operation) {
            'api.mail.send' => 'mail.send', 'api.mail.status' => 'activity.read', 'api.keys.list' => 'api-keys.read', default => 'api-keys.write',
        };
        ApiError::require(in_array($scope, $key->abilities, true), 403);
        foreach (['X-Account-ID', 'X-User-ID', 'X-Owner-ID'] as $header) {
            ApiError::require(! $request->headers->has($header));
        }
        $query = SendingApiInput::query($request, $operation === 'api.keys.list');
        if ($request->isMethod('POST')) {
            $body = SendingApiInput::body($request);
        } else {
            ApiError::require($request->getContent() === '');
        }
        if ($operation === 'api.mail.send') {
            $data = SendingApiInput::send($body);
            ApiError::require($data['content']['type'] !== 'template' || in_array('templates.read', $key->abilities, true), 403);
            $values = $request->headers->all('idempotency-key');
            ApiError::require(count($values) <= 1 && (! isset($values[0]) || ! str_contains($values[0], ',')), 400);
            [$receipt, $replayed] = $sending->accept($key, $values[0] ?? '', $data);

            return response()->json($receipt, 202, ['Location' => '/api/v1/mail/requests/'.$receipt['request_id'], 'Idempotency-Replayed' => $replayed ? 'true' : 'false']);
        }
        if ($operation === 'api.mail.status') {
            ApiError::require(\Illuminate\Support\Str::isUuid($request->route('requestId')));
            return response()->json($sending->status($key->account_id, $request->route('requestId')));
        }
        if ($operation === 'api.keys.list') {
            return response()->json($keys->page($key->account_id, $query));
        }
        if ($operation === 'api.keys.create') {
            return response()->json($keys->create($key->account_id, SendingApiInput::key($body), $key->abilities, $key->expires_at?->format('Y-m-d H:i:s.u'), $key->id), 201);
        }
        ApiError::require(\Illuminate\Support\Str::isUuid($request->route('keyId')));
        $keys->revoke($key->account_id, $request->route('keyId'), credential: $key->id);

        return response()->noContent();
    }
}
