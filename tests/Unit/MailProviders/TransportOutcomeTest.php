<?php

namespace Tests\Unit\MailProviders;

use App\Support\TransportOutcome;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TransportOutcomeTest extends TestCase
{
    public function test_retry_policy_is_not_mixed_into_persisted_attributes(): void
    {
        $outcome = new TransportOutcome('api_rejected', 'api_rate_limited', retryable: true, retryAfterSeconds: 120);
        $this->assertSame(['status' => 'api_rejected', 'diagnostic' => 'api_rate_limited'], $outcome->attributes());
        $this->assertFalse($outcome->accepted());
        $this->assertTrue($outcome->retryable);
        $this->assertSame(120, $outcome->retryAfterSeconds);
    }

    #[DataProvider('acceptedResults')]
    public function test_acceptance_preserves_its_channel(string $status): void
    {
        $outcome = new TransportOutcome($status, providerMessageId: 'synthetic-provider-id');
        $this->assertTrue($outcome->accepted());
        $this->assertSame($status, $outcome->attributes()['status']);
        $this->assertSame('synthetic-provider-id', $outcome->attributes()['provider_message_id']);
        $this->assertFalse($outcome->retryable);
    }

    public static function acceptedResults(): array
    {
        return [['logged'], ['smtp_accepted'], ['api_accepted']];
    }

    #[DataProvider('invalidResults')]
    public function test_invalid_or_ambiguous_outcomes_cannot_be_marked_retryable(array $arguments): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TransportOutcome(...$arguments);
    }

    public static function invalidResults(): array
    {
        return [
            [['status' => 'unknown']],
            [['status' => 'api_accepted', 'retryable' => true]],
            [['status' => 'api_unconfirmed', 'retryable' => true]],
            [['status' => 'smtp_unconfirmed', 'retryable' => true]],
            [['status' => 'api_rejected', 'retryAfterSeconds' => 30]],
            [['status' => 'api_rejected', 'retryable' => true, 'retryAfterSeconds' => 86401]],
            [['status' => 'smtp_accepted', 'providerMessageId' => "synthetic\r\ninjected"]],
        ];
    }
}
