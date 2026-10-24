<?php

namespace Tests\Unit;

use App\Support\SchedulerTriggerInput;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SchedulerTriggerInputTest extends TestCase
{
    public function test_explicit_finite_actions_accept_json_with_an_optional_charset(): void
    {
        $this->assertSame('materialize', SchedulerTriggerInput::fromJson('{"action":"materialize"}', 'application/json; charset=utf-8')->action);
        $this->assertSame('dispatch', SchedulerTriggerInput::fromJson('{"action":"dispatch"}', 'application/json')->action);
    }

    public function test_invalid_types_extra_fields_and_arbitrary_commands_are_rejected(): void
    {
        foreach (['[]', 'null', '{}', '{"action":1}', '{"action":[]}', '{"action":"shell"}', '{"action":"dispatch","account_id":1}', '{"action":"dispatch","limit":100000}'] as $body) {
            try {
                SchedulerTriggerInput::fromJson($body, 'application/json');
                $this->fail('Invalid trigger was accepted.');
            } catch (HttpException $error) {
                $this->assertSame(422, $error->getStatusCode());
                $this->assertSame('invalid_action', $error->getMessage());
            }
        }
    }

    public function test_malformed_json_wrong_media_and_oversize_have_explicit_statuses(): void
    {
        foreach ([['{', 'application/json', 400], ['{"action":"dispatch"}', 'text/plain', 415], [str_repeat(' ', 1025), 'application/json', 413]] as [$body, $media, $status]) {
            try {
                SchedulerTriggerInput::fromJson($body, $media);
                $this->fail('Invalid body was accepted.');
            } catch (HttpException $error) {
                $this->assertSame($status, $error->getStatusCode());
            }
        }
    }
}
