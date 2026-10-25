<?php

namespace App\Services\Mcp\OAuth;

use App\Models\McpClient;

final class RedirectPolicy
{
    public function validate(string $uri): void
    {
        $url = parse_url($uri);
        OAuthError::require(strlen($uri) <= 2048 && filter_var($uri, FILTER_VALIDATE_URL) && is_array($url) && isset($url['host']) && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['fragment']) && ! preg_match('/[\x00-\x20\\\\]/', $uri), 'invalid_redirect_uri');
        $native = in_array($url['host'], ['127.0.0.1', '[::1]', 'localhost'], true);
        OAuthError::require(($url['scheme'] ?? null) === 'https' || (($url['scheme'] ?? null) === 'http' && $native), 'invalid_redirect_uri');
        $query = Input::parameters($url['query'] ?? '', preserveEmpty: true);
        OAuthError::require(array_intersect(array_keys($query), ['code', 'state', 'iss', 'error', 'error_description']) === [], 'invalid_redirect_uri');
    }

    public function accepts(McpClient $client, string $uri): bool
    {
        try {
            $this->validate($uri);
        } catch (OAuthError) {
            return false;
        }
        foreach ($client->redirect_uris ?? [] as $registered) {
            if ($uri === $registered) {
                return true;
            }
            if ($client->native_port_relaxation) {
                $actual = parse_url($uri);
                $expected = parse_url($registered);
                if (($actual['scheme'] ?? null) === 'http' && in_array($actual['host'] ?? null, ['127.0.0.1', '[::1]', 'localhost'], true) && isset($actual['port']) && $actual['port'] > 0 && $actual['port'] <= 65535) {
                    unset($actual['port'], $expected['port']);
                    if ($actual === $expected) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
