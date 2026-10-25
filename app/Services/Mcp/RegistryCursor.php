<?php

namespace App\Services\Mcp;

use Illuminate\Support\Facades\Crypt;

/** Authenticated keyset continuation for the protocol registry (not a business-data cursor). */
final class RegistryCursor
{
    private function binding(): string
    {
        $context = request()->attributes->get('mcp.context');
        abort_unless($context instanceof DelegatedContext, 401);
        $member = $context->authorize();
        $scopes = $context->effectiveScopes();
        sort($scopes);

        $authority = array_diff_key($context->provenance(), ['trace_id' => true]);

        return hash('sha256', json_encode([config('mcp.resource'), $context->accountId(), $context->actorId(), $authority, $member->role, $member->scopes, $scopes], JSON_THROW_ON_ERROR));
    }

    public function issue(string $after): string
    {
        $plain = json_encode(['purpose' => 'correo:mcp:registry:v1', 'binding' => $this->binding(), 'after' => $after, 'issued' => time(), 'expires' => time() + 900], JSON_THROW_ON_ERROR);
        $wire = rtrim(strtr(Crypt::encryptString($plain), '+/', '-_'), '=');
        abort_unless(strlen($wire) <= 2048, 500);

        return $wire;
    }

    public function after(string $wire): string
    {
        try {
            if (strlen($wire) > 2048 || ! preg_match('/\A[A-Za-z0-9_-]+\z/D', $wire)) {
                throw new \RuntimeException;
            }
            $encoded = strtr($wire, '-_', '+/');
            $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
            $decoded = base64_decode($encoded, true);
            if ($decoded === false || base64_encode($decoded) !== $encoded) {
                throw new \RuntimeException;
            }
            $plain = Crypt::decryptString($encoded);
            if (strlen($plain) > 1024) {
                throw new \RuntimeException;
            }
            $data = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
            if (($data['purpose'] ?? null) !== 'correo:mcp:registry:v1' || ! hash_equals($this->binding(), $data['binding'] ?? '') || ! is_int($data['issued'] ?? null) || ! is_int($data['expires'] ?? null) || $data['issued'] > time() + 30 || $data['expires'] - $data['issued'] !== 900 || ! is_string($data['after'] ?? null)) {
                throw new \RuntimeException;
            }
            if ($data['expires'] <= time()) {
                throw new ProtocolError(400, -32602, 'Cursor expired');
            }

            return $data['after'];
        } catch (ProtocolError $error) {
            throw $error;
        } catch (\Throwable) {
            throw new ProtocolError(400, -32602, 'Invalid cursor');
        }
    }
}
