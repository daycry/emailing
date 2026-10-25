<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Server\Transport\HttpTransport;

/** Bounds the final SDK envelope, including SDK metadata and mirrored content. */
final class BoundedHttpTransport extends HttpTransport
{
    public function stream(\Closure $stream): void
    {
        request()->attributes->set('mcp.transport_failure', new ProtocolError(500, -32603, 'Internal error'));
        throw new ProtocolError(500, -32603, 'Internal error');
    }

    public function send(string $message): void
    {
        $reply = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        // The SDK's Errable handler can expose exception messages with APP_DEBUG.
        // Only our fixed DTO factory may produce a tool-level error payload.
        if (($reply['result']['isError'] ?? false) === true) {
            $safe = request()->attributes->get('mcp.safe_tool_error');
            $text = $reply['result']['content'][0]['text'] ?? null;
            if (! is_string($safe) || ! is_string($text) || ! hash_equals($safe, $text)) {
                $operation = request()->attributes->get('mcp.operation');
                if (is_string($operation)) {
                    $reply['result'] = array_replace($reply['result'], ToolError::result($operation, 'internal_error', request()->attributes->get('mcp.outcome', 'not_applied'), request()->attributes->get('mcp.reference')));
                    unset($reply['result']['structuredContent']);
                } else {
                    $reply = ['jsonrpc' => '2.0', 'id' => $reply['id'], 'error' => ['code' => -32603, 'message' => 'Internal error']];
                }
                $message = json_encode($reply, JSON_THROW_ON_ERROR);
            } else {
                unset($reply['result']['structuredContent']);
                $reply['result']['content'] = [['type' => 'text', 'text' => $safe]];
                $message = json_encode($reply, JSON_THROW_ON_ERROR);
            }
        }
        if (strlen($message) <= 8388608) {
            parent::send($message);

            return;
        }
        if (request()->attributes->get('mcp.method') === 'tools/list') {
            $tools = &$reply['result']['tools'];
            while (count($tools) > 1) {
                array_pop($tools);
                $reply['result']['nextCursor'] = app(RegistryCursor::class)->issue($tools[array_key_last($tools)]['name']);
                $message = json_encode($reply, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (strlen($message) <= 8388608) {
                    parent::send($message);

                    return;
                }
            }
        }
        $operation = request()->attributes->get('mcp.operation');
        if (is_string($operation)) {
            $outcome = request()->attributes->get('mcp.outcome', 'not_applied');
            $reply['result'] = array_replace($reply['result'], ToolError::result($operation, 'result_too_large', $outcome, request()->attributes->get('mcp.reference')));
            unset($reply['result']['structuredContent']);
            parent::send(json_encode($reply, JSON_THROW_ON_ERROR));

            return;
        }
        // Server::handle catches Throwable and may translate it to generic 500.
        // Carry the boundary result out of that SDK catch, then reject in the controller.
        request()->attributes->set('mcp.transport_failure', new ProtocolError(413, -32603, 'Result too large'));
        parent::send(json_encode(['jsonrpc' => '2.0', 'id' => $reply['id'], 'error' => ['code' => -32603, 'message' => 'Result too large']], JSON_THROW_ON_ERROR));
    }
}
