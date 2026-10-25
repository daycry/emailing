<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcResponse;

/** Use the installed SDK serializer, including both mirrored and structured content. */
class ResponseBudget extends ToolInvoker
{
    public function __construct(private readonly int $maxBytes = 8388608)
    {
        parent::__construct();
        if ($maxBytes < 1 || $maxBytes > 8388608) {
            throw new \InvalidArgumentException('Invalid response budget');
        }
    }

    public function bytes(Tool $tool, array $dto): int
    {
        $id = request()->attributes->get('mcp.rpc_id', str_repeat('x', 128));
        $result = ($this->serializable($tool))(Response::structured($dto));

        return strlen(JsonRpcResponse::result($id, $result)->toJson());
    }

    public function fits(Tool $tool, array $dto): bool
    {
        return $this->bytes($tool, $dto) <= $this->maxBytes;
    }
}
