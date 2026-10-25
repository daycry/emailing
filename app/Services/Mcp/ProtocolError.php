<?php

namespace App\Services\Mcp;

use RuntimeException;

final class ProtocolError extends RuntimeException
{
    public function __construct(public readonly int $status = 400, public readonly int $rpcCode = -32600, public readonly string $safeMessage = 'Invalid request')
    {
        parent::__construct($safeMessage);
    }

    public static function require(bool $condition, int $status = 400): void
    {
        if (! $condition) {
            throw new self($status, $status === 413 ? -32600 : -32700, $status === 413 ? 'Payload too large' : 'Invalid JSON');
        }
    }
}
