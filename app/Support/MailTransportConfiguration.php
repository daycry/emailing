<?php

namespace App\Support;

use App\Enums\MailTransportMode;
use App\Services\MailProviders\ProviderCredentials;

final readonly class MailTransportConfiguration
{
    public function __construct(
        public ?MailTransportMode $mode,
        public ?ProviderCredentials $credentials = null,
        public ?string $blockedReason = null,
    ) {
        if (($mode === null && $blockedReason === null)
            || ($credentials !== null && $credentials->mode !== $mode)
            || ($mode?->isExternal() && $credentials === null && $blockedReason === null)) {
            throw new \InvalidArgumentException('Invalid resolved mail configuration.');
        }
    }
}
