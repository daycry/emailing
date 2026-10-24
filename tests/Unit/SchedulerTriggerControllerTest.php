<?php

namespace Tests\Unit;

use App\Http\Controllers\SchedulerTriggerController;
use App\Services\SchedulerActions;
use App\Support\SchedulerRunResult;
use App\Support\SchedulerTriggerInput;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SchedulerTriggerControllerTest extends TestCase
{
    private function request(string $body, string $media = 'application/json'): Request
    {
        return Request::create('/internal/cronicle/trigger', 'POST', server: ['CONTENT_TYPE' => $media], content: $body);
    }

    public function test_materialization_reports_durable_work_without_claiming_email_completion(): void
    {
        $actions = $this->createMock(SchedulerActions::class);
        $actions->expects($this->once())->method('run')
            ->with($this->callback(fn (SchedulerTriggerInput $input) => $input->action === 'materialize'))
            ->willReturn(new SchedulerRunResult('materialize', deliveriesCreated: 2, stopReason: 'campaign_limit', elapsedMs: 5));

        $response = (new SchedulerTriggerController)($this->request('{"action":"materialize"}'), $actions);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'status' => 'processed', 'action' => 'materialize',
            'deliveries_created' => 2, 'outbox_claims' => 0, 'emails_completed' => null,
            'stop_reason' => 'campaign_limit', 'elapsed_ms' => 5,
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
    }

    public function test_dispatch_claim_is_distinct_from_publication_or_smtp_completion(): void
    {
        $actions = $this->createMock(SchedulerActions::class);
        $actions->expects($this->once())->method('run')->willReturn(new SchedulerRunResult('dispatch', outboxClaims: 1));
        $response = (new SchedulerTriggerController)($this->request('{"action":"dispatch"}'), $actions);
        $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(1, $body['outbox_claims']);
        $this->assertSame(0, $body['deliveries_created']);
        $this->assertNull($body['emails_completed']);
        $this->assertArrayNotHasKey('emails_sent', $body);
    }

    public function test_invalid_requests_never_call_the_domain_action(): void
    {
        foreach ([['{', 'application/json', 400], ['{}', 'application/json', 422], ['{"action":"shell"}', 'application/json', 422], ['{"action":"dispatch","account_id":1}', 'application/json', 422], ['{"action":"dispatch"}', 'text/plain', 415], [str_repeat(' ', 1025), 'application/json', 413]] as [$body, $media, $status]) {
            $actions = $this->createMock(SchedulerActions::class);
            $actions->expects($this->never())->method('run');
            $response = (new SchedulerTriggerController)($this->request($body, $media), $actions);
            $this->assertSame($status, $response->getStatusCode());
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        }
    }

    public function test_backend_failure_is_sanitized_even_without_the_framework_exception_handler(): void
    {
        $actions = $this->createMock(SchedulerActions::class);
        $actions->expects($this->once())->method('run')->willThrowException(new RuntimeException('private-backend-fixture'));
        $response = (new SchedulerTriggerController)($this->request('{"action":"dispatch"}'), $actions);

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(['error' => 'trigger_unavailable', 'work_outcome' => 'unknown', 'emails_completed' => null], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('private-backend-fixture', $response->getContent());
        $this->assertSame('10', $response->headers->get('Retry-After'));
    }
}
