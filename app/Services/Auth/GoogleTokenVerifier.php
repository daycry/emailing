<?php

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleTokenVerifier
{
    public const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function verify(string $token, string $nonceHash): array
    {
        try {
            if (strlen($token) > 16384 || substr_count($token, '.') !== 2) {
                throw new \RuntimeException('Invalid token.');
            }
            // Only the untrusted JOSE header is inspected to choose a key. Identity claims
            // are read exclusively after JWT::decode has verified the RS256 signature.
            $headerPart = explode('.', $token)[0];
            $header = strlen($headerPart) <= 2048 ? json_decode(JWT::urlsafeB64Decode($headerPart), true, 16, JSON_THROW_ON_ERROR) : null;
            if (! is_array($header) || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)
                || ! preg_match('/\A[!-~]{1,128}\z/D', $header['kid'])) {
                throw new \RuntimeException('Invalid token header.');
            }
            $jwks = $this->keysFor($header['kid']);
            $claims = (array) JWT::decode($token, JWK::parseKeySet($jwks, 'RS256'));
            $client = config('services.google.client_id');
            $audience = $claims['aud'] ?? null;
            $audienceValid = is_string($audience) ? hash_equals($client, $audience)
                : (is_array($audience) && in_array($client, $audience, true) && ($claims['azp'] ?? null) === $client);
            if (! in_array($claims['iss'] ?? null, [GoogleIdentityResolver::ISSUER, 'accounts.google.com'], true)
                || ! $audienceValid || (isset($claims['azp']) && $claims['azp'] !== $client)
                || ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time()
                || ! is_int($claims['iat'] ?? null) || $claims['iat'] > time() || $claims['iat'] < time() - 600
                || ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonceHash, hash('sha256', $claims['nonce']))
                || ! is_string($claims['sub'] ?? null) || ! preg_match('/\A[!-~]{1,255}\z/D', $claims['sub'])
                || ($claims['email_verified'] ?? null) !== true || ! is_string($claims['email'] ?? null)
                || strlen($claims['email']) > 254 || ! filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Invalid identity.');
            }
            $domains = config('emailing.google_workspace_domains', []);
            $hd = $claims['hd'] ?? null;
            $validHostedDomain = is_string($hd) && strlen($hd) <= 253 && str_contains($hd, '.')
                && filter_var($hd, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false
                && filter_var($hd, FILTER_VALIDATE_IP) === false;
            if ($hd !== null && ! $validHostedDomain) {
                throw new \RuntimeException('Invalid hosted domain.');
            }
            if ($domains && (! is_string($claims['hd'] ?? null) || ! in_array($claims['hd'], $domains, true))) {
                throw new \RuntimeException('Identity not admitted.');
            }

            return ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => $claims['sub'],
                'email' => mb_strtolower($claims['email']),
                'email_authoritative' => mb_strtolower(substr(strrchr($claims['email'], '@'), 1)) === 'gmail.com' || $validHostedDomain,
                'name' => is_string($claims['name'] ?? null) ? mb_substr($claims['name'], 0, 100) : ''];
        } catch (\Throwable) {
            // Do not report provider responses, JWTs, key material or HTTP request bodies.
            throw ValidationException::withMessages(['google' => GoogleLoginFlow::ERROR]);
        }
    }

    private function keysFor(string $kid): array
    {
        $cached = Cache::get('google-identity-jwks-v1');
        if ($this->containsKey($cached, $kid)) {
            return $cached;
        }

        return Cache::lock('google-identity-jwks-load-v1', 10)->block(1, function () use ($kid) {
            $current = Cache::get('google-identity-jwks-v1');
            if ($this->containsKey($current, $kid)) {
                return $current;
            }
            // At most one cold fetch and one unknown-kid refresh per 30 seconds,
            // shared by the configured cache. No per-attacker URL or kid cache keys.
            $gate = is_array($current) ? 'google-identity-jwks-refresh-v1' : 'google-identity-jwks-fetch-v1';
            if (! Cache::add($gate, true, 30)) {
                throw new \RuntimeException('Identity keys temporarily unavailable.');
            }
            $response = Http::acceptJson()->timeout(5)->connectTimeout(3)->withOptions([
                'allow_redirects' => false,
                'progress' => static function ($total, $downloaded) {
                    if ($downloaded > 65536 || $total > 65536) {
                        throw new \RuntimeException('Identity response too large.');
                    }
                },
            ])->get(self::KEYS_URL);
            if (! $response->successful() || strlen($response->body()) > 65536) {
                throw new \RuntimeException('Invalid keys response.');
            }
            $keys = $response->json('keys');
            if (! is_array($keys) || count($keys) < 1 || count($keys) > 20) {
                throw new \RuntimeException('Invalid keys.');
            }
            foreach ($keys as $key) {
                if (! is_array($key) || ($key['kty'] ?? null) !== 'RSA' || ($key['alg'] ?? 'RS256') !== 'RS256'
                    || ($key['use'] ?? 'sig') !== 'sig'
                    || ! is_string($key['kid'] ?? null) || strlen($key['kid']) > 128) {
                    throw new \RuntimeException('Invalid key.');
                }
            }

            $jwks = ['keys' => $keys];
            JWK::parseKeySet($jwks, 'RS256');
            $control = strtolower((string) $response->header('Cache-Control'));
            $ttl = 300;
            if (preg_match('/\b(?:no-store|no-cache)\b/', $control)) {
                $ttl = 0;
            } elseif (preg_match('/\bmax-age\s*=\s*"?([0-9]{1,10})"?/', $control, $match)) {
                $age = (string) $response->header('Age');
                $ttl = max(0, min(3600, (int) $match[1] - (ctype_digit($age) ? (int) $age : 0)));
            }
            if ($ttl > 0) {
                Cache::put('google-identity-jwks-v1', $jwks, $ttl);
            } else {
                Cache::forget('google-identity-jwks-v1');
            }

            return $jwks;
        });
    }

    private function containsKey(mixed $jwks, string $kid): bool
    {
        if (! is_array($jwks) || ! is_array($jwks['keys'] ?? null)) {
            return false;
        }

        return collect($jwks['keys'])->contains(fn ($key) => is_array($key) && ($key['kid'] ?? null) === $kid);
    }
}
