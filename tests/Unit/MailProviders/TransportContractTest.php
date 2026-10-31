<?php

namespace Tests\Unit\MailProviders;

use PHPUnit\Framework\TestCase;

class TransportContractTest extends TestCase
{
    public function test_api_acceptance_has_its_own_public_state_and_counter(): void
    {
        $root = dirname(__DIR__, 3);
        $bundle = json_decode(file_get_contents($root.'/docs/api/mcp-tools.json'), true, 512, JSON_THROW_ON_ERROR);
        $operations = array_column($bundle['operations'], null, 'name');
        $schema = $operations['mail_request_get']['outputSchema'];
        $request = $schema['$defs']['MailRequestStatus'];
        $delivery = $schema['$defs']['DeliveryStatus'];
        $this->assertContains('api_accepted', $delivery['properties']['status']['enum']);
        $this->assertArrayHasKey('api_accepted', $request['properties']['counts']['properties']);
        $this->assertContains('smtp_accepted', $delivery['properties']['status']['enum']);
        $this->assertNotContains('api_accepted', $request['properties']['counts']['required']);
        $this->assertArrayHasKey('provider_feedback', $delivery['properties']);
    }
}
