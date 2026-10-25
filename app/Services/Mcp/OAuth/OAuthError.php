<?php

namespace App\Services\Mcp\OAuth;

use App\Support\ApiNoStoreHeaders;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;

final class OAuthError extends \RuntimeException implements Responsable
{
    public function __construct(public readonly string $error = 'invalid_request', public readonly int $status = 400, public readonly ?int $retryAfter = null, public readonly ?string $authenticate = null)
    {
        parent::__construct($error);
    }

    public static function require(bool $condition, string $error = 'invalid_request', int $status = 400): void
    {
        if (! $condition) {
            throw new self($error, $status);
        }
    }

    public function toResponse($request): JsonResponse
    {
        $response = response()->json(['error' => $this->error], $this->status);
        $response->headers = new ApiNoStoreHeaders($response->headers->all());
        $response->headers->set('Pragma', 'no-cache');
        if ($this->status === 401) {
            $response->headers->set('WWW-Authenticate', ($this->authenticate ?? 'Bearer').' realm="MCP OAuth"');
        }
        if ($this->retryAfter !== null || $this->status === 429) {
            $response->headers->set('Retry-After', (string) ($this->retryAfter ?? 60));
        }

        return $response;
    }
}
