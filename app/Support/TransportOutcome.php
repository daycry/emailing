<?php

namespace App\Support;

/** A transport receipt and scheduling policy; only attributes() is persisted. */
final readonly class TransportOutcome
{
    public function __construct(
        public string $status,
        public ?string $diagnostic = null,
        public ?string $artifactPath = null,
        public ?string $providerMessageId = null,
        public bool $retryable = false,
        public ?int $retryAfterSeconds = null,
    ) {
        if (! in_array($status, ['logged', 'local_failed', 'smtp_accepted', 'smtp_rejected', 'smtp_unconfirmed', 'api_accepted', 'api_rejected', 'api_unconfirmed'], true)
            || ($retryable && ! in_array($status, ['local_failed', 'smtp_rejected', 'api_rejected'], true))
            || ($retryAfterSeconds !== null && (! $retryable || $retryAfterSeconds < 1 || $retryAfterSeconds > 86400))
            || ($providerMessageId !== null && (strlen($providerMessageId) > 255 || preg_match('/[\x00-\x1f\x7f]/', $providerMessageId)))) {
            throw new \InvalidArgumentException('Invalid transport outcome.');
        }
    }

    public function accepted(): bool
    {
        return in_array($this->status, ['logged', 'smtp_accepted', 'api_accepted'], true);
    }

    public function attributes(): array
    {
        $attributes = ['status' => $this->status, 'diagnostic' => $this->diagnostic];
        if ($this->artifactPath !== null) {
            $attributes['artifact_path'] = $this->artifactPath;
        }
        if ($this->providerMessageId !== null) {
            $attributes['provider_message_id'] = $this->providerMessageId;
        }

        return $attributes;
    }
}
