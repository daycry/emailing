<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError extends \RuntimeException implements \Illuminate\Contracts\Support\Responsable
{
    public function __construct(public readonly int $status, public readonly array $headers = [])
    {
        parent::__construct('Solicitud no admitida.');
    }

    public function response(): JsonResponse
    {
        $code = [400 => 'malformed_request', 401 => 'unauthenticated', 403 => 'forbidden', 404 => 'not_found', 409 => 'idempotency_conflict', 413 => 'payload_too_large', 415 => 'unsupported_media_type', 422 => 'validation_failed', 429 => 'rate_limited', 503 => 'temporarily_unavailable'][$this->status] ?? 'temporarily_unavailable';

        $response = response()->json(['error' => ['code' => $code, 'message' => $this->getMessage()]], $this->status, $this->headers + ($this->status === 401 ? ['WWW-Authenticate' => 'Bearer'] : []));
        $response->headers = new ApiNoStoreHeaders($response->headers->all());

        return $response;
    }

    public function toResponse($request): JsonResponse
    {
        return $this->response();
    }

    public static function require(bool $condition, int $status = 422): void
    {
        if (! $condition) {
            throw new self($status);
        }
    }
}
