<?php

namespace App\Http\Controllers;

use App\Services\AccountKeyService;
use App\Support\ApiError;
use App\Support\SendingApiInput;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ApiKeyController extends Controller
{
    private function page(AccountKeyService $keys, ?array $created = null)
    {
        $member = app(\App\Services\AccountContext::class)->membership();
        $read = \App\Services\Permissions::allows($member, 'api-keys.read');
        $cursor = request()->query('cursor');
        abort_unless($cursor === null || (is_string($cursor) && strlen($cursor) <= 512), 400);
        $data = $keys->ui($read ? 'api-keys.read' : 'api-keys.write', fn ($account, $ceiling) => ['page' => $read ? $keys->page($account, ['limit' => 100] + ($cursor === null ? [] : ['cursor' => $cursor])) : ['data' => [], 'next_cursor' => null], 'ceiling' => $ceiling]);

        return response()->view('api-keys', $data + ['created' => $created])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function index(AccountKeyService $keys)
    {
        return $this->page($keys);
    }

    public function store(Request $request, AccountKeyService $keys)
    {
        try {
            $created = $keys->ui('api-keys.write', function ($account, $ceiling) use ($request, $keys) {
                $data = $request->only(['name', 'scopes', 'expires_at']);
                if (($data['expires_at'] ?? null) === null || $data['expires_at'] === '') {
                    unset($data['expires_at']);
                }

                return $keys->create($account, SendingApiInput::key((object) $data), $ceiling, actor: auth()->id());
            });
        } catch (ApiError $error) {
            if ($error->status === 403) {
                abort(403);
            }
            throw ValidationException::withMessages(['key' => 'Revisa el nombre, los permisos y la caducidad UTC futura.']);
        } catch (\Illuminate\Database\QueryException) {
            abort(503, 'No se pudo confirmar la creación. Revisa las llaves de la cuenta antes de repetir.');
        }

        // Plaintext exists only in this response, never session or a Livewire snapshot.
        return $this->page($keys, $created);
    }

    public function destroy(string $keyId, AccountKeyService $keys)
    {
        try {
            $keys->ui('api-keys.write', fn ($account) => $keys->revoke($account, $keyId, actor: auth()->id()));
        } catch (ApiError $error) {
            abort($error->status);
        }

        return redirect()->route('api-keys')->with('success', 'Llave revocada. El trabajo ya aceptado se conserva.')->header('Cache-Control', 'no-store');
    }
}
