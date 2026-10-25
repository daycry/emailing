<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Transport\JsonRpcRequest;
use Laravel\Mcp\Transport\JsonRpcResponse;

final class ListTools implements Method
{
    public function handle(JsonRpcRequest $request, ServerContext $context): JsonRpcResponse
    {
        $cursor = app(RegistryCursor::class);
        $after = $request->get('cursor');
        $after = $after === null ? null : $cursor->after($after);
        $limit = $request->get('per_page', 25);
        $tools = $context->tools()->sortBy(fn ($tool) => $tool->name())->filter(fn ($tool) => $after === null || strcmp($tool->name(), $after) > 0)->values();
        $page = $tools->take($limit)->map(fn ($tool) => $tool->toArray())->all();
        $result = ['tools' => $page];
        if ($tools->count() > $limit) {
            $result['nextCursor'] = $cursor->issue($page[array_key_last($page)]['name']);
        }

        return JsonRpcResponse::result($request->id, $result);
    }
}
