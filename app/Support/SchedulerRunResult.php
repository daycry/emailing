<?php

namespace App\Support;

final readonly class SchedulerRunResult
{
    public function __construct(
        public string $action,
        public int $deliveriesCreated = 0,
        public int $outboxClaims = 0,
        public string $stopReason = 'empty',
        public int $elapsedMs = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'status' => 'processed',
            'action' => $this->action,
            'deliveries_created' => $this->deliveriesCreated,
            'outbox_claims' => $this->outboxClaims,
            'emails_completed' => null,
            'stop_reason' => $this->stopReason,
            'elapsed_ms' => $this->elapsedMs,
        ];
    }
}
