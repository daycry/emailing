<?php

namespace App\Services\Mcp;

use Generator;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\ToolInvoker;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

final class CallTool extends \Laravel\Mcp\Server\Methods\CallTool
{
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if ($context->tools()->contains(fn ($tool) => $tool->name() === $request->get('name'))) {
            return parent::handle($request, $context);
        }
        // Hidden tools still return the contracted authorization error when called directly.
        $name = $request->get('name');
        $class = is_string($name) ? (DataTools::MAP[$name] ?? null) : null;
        if ($class !== null) {
            return (new ToolInvoker)->invoke(app($class), $request);
        }

        return parent::handle($request, $context);
    }
}
