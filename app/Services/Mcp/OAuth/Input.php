<?php

namespace App\Services\Mcp\OAuth;

use App\Services\Mcp\DelegatedContext;
use App\Support\StrictApiJson;
use Illuminate\Http\Request;

final class Input
{
    public static function parameters(string $raw, int $maximum = 8192, bool $preserveEmpty = false): array
    {
        OAuthError::require(strlen($raw) <= $maximum);
        $result = [];
        foreach ($raw === '' ? [] : explode('&', $raw) as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
            OAuthError::require(! preg_match('/%(?![a-fA-F0-9]{2})/', $key.$value));
            $key = urldecode($key);
            $value = urldecode($value);
            OAuthError::require(mb_check_encoding($key.$value, 'UTF-8') && ! array_key_exists($key, $result));
            $result[$key] = $value;
        }

        return $preserveEmpty ? $result : array_filter($result, fn ($value) => $value !== '');
    }

    public static function form(Request $request): array
    {
        OAuthError::require(strtolower(trim(explode(';', $request->header('Content-Type', ''))[0])) === 'application/x-www-form-urlencoded', 'invalid_request', 415);
        $headers = $request->headers->all('authorization');
        if ($headers !== []) {
            // RFC6749 5.2 / RFC7009 2.2.1: reject attempted header authentication
            // with its known scheme, never reflecting credentials or an arbitrary value.
            $scheme = 'Bearer';
            foreach (['Basic', 'Bearer', 'Digest', 'DPoP'] as $known) {
                if (preg_match('/\A'.preg_quote($known, '/').'(?:\s|\z)/iD', $headers[0])) {
                    $scheme = $known;
                    break;
                }
            }
            throw new OAuthError('invalid_client', 401, null, $scheme);
        }
        $data = self::parameters($request->getContent(), 16384);
        foreach (['client_secret', 'client_assertion', 'client_assertion_type'] as $name) {
            OAuthError::require(! array_key_exists($name, $data), 'invalid_client');
        }

        return $data;
    }

    public static function json(Request $request): array
    {
        OAuthError::require($request->headers->all('authorization') === [], 'invalid_client');
        OAuthError::require(strtolower(trim(explode(';', $request->header('Content-Type', ''))[0])) === 'application/json', 'invalid_request', 415);
        OAuthError::require(strlen($request->getContent()) <= 16384);
        try {
            $object = StrictApiJson::decode($request->getContent());
        } catch (\Throwable) {
            throw new OAuthError;
        }
        OAuthError::require($object instanceof \stdClass);
        $depth = function (mixed $value, int $level = 0) use (&$depth): void {
            if (is_array($value) || $value instanceof \stdClass) {
                OAuthError::require($level < 16);
                foreach ((array) $value as $child) {
                    $depth($child, $level + 1);
                }
            }
        };
        $depth($object);

        return (array) $object;
    }

    public static function string(array $data, string $key, int $minimum = 1, int $maximum = 2048, ?string $pattern = null): string
    {
        $value = $data[$key] ?? null;
        OAuthError::require(is_string($value) && strlen($value) >= $minimum && strlen($value) <= $maximum && ($pattern === null || preg_match($pattern, $value) === 1));

        return $value;
    }

    public static function clientId(array $data): string
    {
        return self::string($data, 'client_id', 1, 128, '/\A[A-Za-z0-9_-]+\z/D');
    }

    public static function clientName(array $data): string
    {
        $value = $data['client_name'] ?? null;
        OAuthError::require(is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') >= 1 && mb_strlen($value, 'UTF-8') <= 150);

        return $value;
    }

    public static function scopes(string $value): array
    {
        OAuthError::require(strlen($value) <= 2048, 'invalid_scope');
        $scopes = $value === '' ? [] : explode(' ', $value);
        OAuthError::require(count($scopes) <= 10 && count(array_unique($scopes)) === count($scopes) && array_diff($scopes, DelegatedContext::scopes()) === [], 'invalid_scope');

        return $scopes;
    }
}
