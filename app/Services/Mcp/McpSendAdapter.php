<?php

namespace App\Services\Mcp;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\MailSender;
use App\Models\McpGrant;
use App\Services\MailProviders\MailTransportPolicy;
use App\Services\Mcp\Tools\DataTool;
use App\Services\SendingApiService;
use App\Support\SendingApiInput;
use Carbon\CarbonImmutable;

/** MCP adapter over the shared durable source; never performs transport IO. */
final class McpSendAdapter
{
    public const NAMES = ['connection_info', 'mail_request_get', 'mail_send'];

    /** Normalize the message with REST policy before the normalized MCP schema. */
    public static function normalizeArguments(\stdClass $arguments): \stdClass
    {
        $normalized = clone $arguments;
        if (property_exists($normalized, 'message')) {
            $message = SendingApiInput::send($normalized->message);
            $normalized->message = json_decode(json_encode($message, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        }

        return $normalized;
    }

    public function run(DataTool $tool, DelegatedContext $context, array $arguments): array
    {
        if ($tool->name() === 'connection_info') {
            return $this->connection($tool, $context, $arguments);
        }
        $sending = app(SendingApiService::class);
        if ($tool->name() === 'mail_send') {
            // Same defaults, normalization and canonical fingerprint as REST.
            $message = SendingApiInput::send(json_decode(json_encode($arguments['message'], JSON_THROW_ON_ERROR)));
            [$receipt, $replayed] = $sending->acceptDelegated($context, $arguments['idempotency_key'], $message);
            $this->reference($receipt['request_id']);
            // The common tool audit has the same trace, linking the invoker to
            // this request without changing the first commit's stored principal.
            AuditEvent::create(['account_id' => $context->accountId(), 'actor_id' => $context->actorId(),
                'action' => $replayed ? 'mcp.mail.request.replayed' : 'mcp.mail.request.accepted',
                'metadata' => ['request_id' => $receipt['request_id'], 'replayed' => $replayed] + $context->provenance()]);

            return ['data' => ['request' => $receipt, 'replayed' => $replayed], 'meta' => DataDto::meta($context)];
        }
        $context->authorize(['activity.read'], true);
        $status = $sending->status($context->accountId(), $arguments['request_id']);
        $this->reference($status['request_id']);

        return ['data' => ['request' => $status], 'meta' => DataDto::meta($context)];
    }

    private function connection(DataTool $tool, DelegatedContext $context, array $arguments): array
    {
        $includeSenders = array_key_exists('senders', $arguments);
        $context->authorize($includeSenders ? ['mail.send'] : [], true);
        $source = $context->provenance();
        $grant = McpGrant::whereKey($source['grant_id'])->where('account_id', $context->accountId())
            ->where('client_id', $source['client_id'])->where('user_id', $context->actorId())->firstOrFail();
        $authority = $context->cursorAuthority();
        $account = Account::whereKey($context->accountId())->firstOrFail(['id', 'name']);
        $contract = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR);
        $data = ['server' => ['name' => 'Correo MCP', 'contract_version' => $contract['contract_version'],
            'resource_uri' => config('mcp.resource'), 'issuer_uri' => config('mcp.issuer')],
            'account' => ['id' => (int) $account->id, 'name' => $account->name],
            'principal' => ['user_id' => $context->actorId()],
            'connection' => ['public_id' => $grant->id,
                'access_expires_at' => CarbonImmutable::createFromTimestampUTC($authority['expires_at'])->format('Y-m-d\TH:i:s.u\Z'),
                'granted_scopes' => $grant->scopes, 'effective_scopes' => $context->effectiveScopes()]];
        $meta = DataDto::meta($context);
        if (! $includeSenders) {
            return ['data' => $data, 'meta' => $meta];
        }
        $page = $arguments['senders'];
        $limit = $page['limit'] ?? 25;
        $query = MailTransportPolicy::eligibleSenders($context->accountId());
        $cursor = app(DataCursor::class);
        if (isset($page['cursor'])) {
            $position = $cursor->read($context, $tool->name(), [], $page['cursor'], 'senders')['position'];
            $query->where('id', '<', $position['id']);
        }
        $rows = $query->orderByDesc('id')->limit($limit + 1)->get(['id', 'email', 'name', 'reply_to', 'lab_authorized', 'external_authorized']);
        $issue = null;
        $continuation = function (MailSender $sender) use ($cursor, $context, $tool, &$issue): string {
            $issue ??= $cursor->issuer($context, $tool->name(), [], 'senders');

            return $issue(['kind' => 'id_desc', 'id' => (string) $sender->id]);
        };

        return app(DataPage::class)->cut($tool, $rows, $limit,
            fn (MailSender $sender): array => ['id' => (int) $sender->id, 'email' => $sender->email, 'name' => $sender->name, 'reply_to' => $sender->reply_to, 'lab_authorized' => $sender->lab_authorized, 'external_authorized' => $sender->external_authorized],
            fn (array $items, array $paging): array => ['data' => $data + ['senders' => ['items' => $items, 'page' => $paging]], 'meta' => $meta],
            $continuation);
    }

    private function reference(string $id): void
    {
        request()->attributes->set('mcp.reference', ['kind' => 'mail_request', 'id' => $id]);
    }
}
