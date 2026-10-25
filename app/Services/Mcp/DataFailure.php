<?php

namespace App\Services\Mcp;

use RuntimeException;

final class DataFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct('MCP data operation rejected.');
    }
}
