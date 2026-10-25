<?php

namespace App\Services\Mcp;

use App\Models\McpAuditEvent;
use Illuminate\Support\Str;

final class Audit
{
    public function record(int $actor, ?int $account, string $client, ?string $grant, string $operation, string $outcome, ?string $correlation = null): void
    {
        $operations = array_column(json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR)['operations'], 'name');
        abort_unless(in_array($operation, [...$operations, 'oauth.authorize', 'oauth.revoke', 'connection.revoke'], true) && in_array($outcome, ['approved', 'denied', 'succeeded', 'failed', 'revoked'], true), 500);
        McpAuditEvent::create(['actor_id' => $actor, 'account_id' => $account, 'client_id' => $client, 'grant_id' => $grant, 'operation' => $operation, 'outcome' => $outcome, 'correlation_id' => $correlation !== null && Str::isUuid($correlation) ? $correlation : (string) Str::uuid()]);
    }

    public function tool(DelegatedContext $context, string $operation, string $outcome): void
    {
        $source = $context->provenance();
        $this->record($context->actorId(), $context->accountId(), $source['client_id'], $source['grant_id'], $operation, $outcome, $context->correlationId());
    }
}
