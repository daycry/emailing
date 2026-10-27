<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Services\Mcp\DelegatedContext;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Server-verified source identity for the one transactional acceptance pipeline. */
final readonly class SendingPrincipal
{
    private function __construct(
        public int $accountId,
        public string $origin,
        public ?int $credentialId,
        public ?string $grantId,
        public ?string $clientId,
        public ?int $actorId,
    ) {}

    public static function api(AccountApiKey $candidate, array $message): self
    {
        self::transaction();
        ApiError::require(Account::whereKey($candidate->account_id)->lockForUpdate()->first() !== null, 401);
        $key = app(AccountKeyService::class)->current($candidate, self::scopes($message));

        return new self((int) $key->account_id, 'api', (int) $key->id, null, null, null);
    }

    public static function delegated(DelegatedContext $context, array $message): self
    {
        self::transaction();
        $context->authorize(self::scopes($message), true);
        $provenance = $context->provenance();

        return new self($context->accountId(), 'mcp', null, $provenance['grant_id'], $provenance['client_id'], $context->actorId());
    }

    public function attributes(): array
    {
        return ['account_id' => $this->accountId, 'origin' => $this->origin,
            'credential_id' => $this->credentialId, 'mcp_grant_id' => $this->grantId,
            'mcp_client_id' => $this->clientId];
    }

    private static function scopes(array $message): array
    {
        return ($message['content']['type'] ?? null) === 'template' ? ['mail.send', 'templates.read'] : ['mail.send'];
    }

    private static function transaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Source admission requires the current account transaction.');
        }
    }
}
