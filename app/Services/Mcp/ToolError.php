<?php

namespace App\Services\Mcp;

use Illuminate\Support\Str;

final class ToolError
{
    public static function result(string $operation, string $code, string $outcome = 'not_applied', ?array $reference = null, ?int $retryAfter = null): array
    {
        $contract = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR);
        $properties = $contract['errorSchema']['properties']['error']['properties'];
        if (! in_array($operation, $properties['operation']['enum'], true)) {
            throw new ProtocolError(500, -32603, 'Internal error');
        }
        $code = in_array($code, $properties['code']['enum'], true) ? $code : 'internal_error';
        $outcome = in_array($outcome, $properties['outcome']['enum'], true) ? $outcome : 'unknown';
        // Never turn a confirmed/uncertain effect into a promise that nothing happened.
        if ($outcome !== 'not_applied' && in_array($code, ['rate_limited', 'validation_failed', 'not_found', 'forbidden', 'idempotency_conflict', 'version_conflict', 'state_conflict', 'payload_too_large', 'cursor_invalid', 'cursor_expired'], true)) {
            $code = 'internal_error';
        }
        if ($code === 'rate_limited' && ($retryAfter === null || $retryAfter < 1 || $retryAfter > 3600)) {
            $code = 'internal_error';
        }
        $referenceProperties = $properties['reference']['properties'];
        $id = $reference['id'] ?? null;
        $kind = $reference['kind'] ?? null;
        $reference = is_string($id) && strlen($id) >= 1 && strlen($id) <= $referenceProperties['id']['maxLength'] && preg_match('/\A[A-Za-z0-9_.:-]+\z/D', $id) && in_array($kind, $referenceProperties['kind']['enum'], true)
            ? ['kind' => $kind, 'id' => $id] : null;
        $error = ['operation' => $operation, 'code' => $code, 'message' => match ($code) {
            'forbidden' => 'Operation not permitted.',
            'not_found' => 'Resource unavailable.',
            'validation_failed' => 'Arguments are invalid.',
            'result_too_large' => 'Result exceeds the response limit.',
            default => 'Operation could not be completed.',
        }, 'outcome' => $outcome, 'can_repeat_operation' => false, 'recovery' => $outcome === 'not_applied' ? ($code === 'result_too_large' ? 'reduce_page_size' : ($code === 'validation_failed' ? 'correct_arguments' : 'none')) : ($reference ? 'read_resource' : 'none')];
        if ($reference !== null) {
            $error['reference'] = $reference;
        }
        if ($outcome !== 'not_applied' && $operation === 'mail_send') {
            $error['can_repeat_operation'] = true;
            $error['recovery'] = 'same_idempotency_key';
        } elseif ($code === 'rate_limited') {
            $error['retry_after_seconds'] = $retryAfter;
            $error['recovery'] = 'wait';
        }
        $trace = request()->attributes->get('mcp.trace_id');
        $trace = is_string($trace) && Str::isUuid($trace) ? $trace : (string) Str::uuid();
        $dto = ['error' => $error, 'meta' => ['trace_id' => $trace, 'observed_at' => gmdate('Y-m-d\TH:i:s\Z')]];
        $text = json_encode($dto, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($text) > $contract['wire_limits']['error_text_bytes']) {
            throw new ProtocolError(500, -32603, 'Internal error');
        }
        request()->attributes->set('mcp.safe_tool_error', $text);

        return ['isError' => true, 'content' => [['type' => 'text', 'text' => $text]]];
    }
}
