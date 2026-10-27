<?php

namespace Tests\Unit;

use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\WireJson;
use App\Support\ApiError;
use App\Support\SendingApiInput;
use App\Support\StrictApiJson;
use App\Support\StrictMcpJson;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pure component-boundary checks; HTTP integration is covered separately. */
final class McpMailCanonicalInputTest extends TestCase
{
    private mixed $priorFacadeApplication;

    protected function setUp(): void
    {
        parent::setUp();
        // Only the standalone validation component: no Laravel Application/bootstrap/config/DB.
        $this->priorFacadeApplication = Facade::getFacadeApplication();
        $container = new Container;
        $container->instance('validator', new Factory(new Translator(new ArrayLoader, 'en'), $container));
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->priorFacadeApplication);
        parent::tearDown();
    }

    #[DataProvider('exactSenderIds')]
    public function test_rest_and_mcp_share_normalization_defaults_and_fingerprint(string $number): void
    {
        $raw = '{"sender_id":'.$number.',"recipients":[{"email":" \\tRecipient@EXAMPLE.test\\t "}],'
            .'"content":{"type":"inline","subject":"  Literal 7e1  ","text":"Body\\r\\n{{ untouched }}"}}';
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $wire = StrictMcpJson::decode('{"idempotency_key":"synthetic-canonical-key-0001","message":'.$raw.'}');
        $wire = WireJson::integerInputs($wire, $schema, $schema);
        // The published schema describes normalized input; this raw email must fail it.
        $this->assertFalse(ContractArguments::valid($wire, $schema));
        $mcp = SendingApiInput::send($wire->message);
        $rest = SendingApiInput::send(StrictApiJson::decode($raw));
        $this->assertSame($rest, $mcp);
        $this->assertSame(SendingApiInput::fingerprint($rest), SendingApiInput::fingerprint($mcp));
        $this->assertSame('recipient@example.test', $mcp['recipients'][0]['email']);
        $this->assertSame('', $mcp['recipients'][0]['name']);
        $this->assertSame('', $mcp['content']['html']);
        $this->assertSame('  Literal 7e1  ', $mcp['content']['subject']);
        $this->assertSame("Body\r\n{{ untouched }}", $mcp['content']['text']);
        $wire->message = json_decode(json_encode($mcp, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue(ContractArguments::valid($wire, $schema));
    }

    public static function exactSenderIds(): iterable
    {
        yield 'integer' => ['7'];
        yield 'decimal exponent' => ['70e-1'];
        yield 'largest safe integer' => ['9007199254740991.0'];
    }

    public function test_duplicates_are_rejected_after_common_case_and_whitespace_normalization(): void
    {
        $raw = '{"sender_id":7,"recipients":[{"email":"Recipient@example.test"},{"email":" recipient@EXAMPLE.test "}],'
            .'"content":{"type":"inline","subject":"Synthetic","text":"Body"}}';
        $this->expectException(ApiError::class);
        $this->canonicalMcp($raw);
    }

    public function test_explicit_null_recipient_name_is_not_replaced_by_a_default(): void
    {
        $raw = '{"sender_id":7,"recipients":[{"email":"recipient@example.test","name":null}],'
            .'"content":{"type":"inline","subject":"Synthetic","text":"Body"}}';
        $this->expectException(ApiError::class);
        $this->canonicalMcp($raw);
    }

    public function test_one_hundred_recipients_have_the_same_order_and_fingerprint_on_both_entries(): void
    {
        $recipients = array_map(fn (int $i): array => ['email' => ' Recipient'.$i.'@EXAMPLE.test ',
            'name' => 'Synthetic '.$i], range(1, 100));
        $raw = json_encode(['sender_id' => 7, 'recipients' => $recipients,
            'content' => ['type' => 'inline', 'subject' => 'Synthetic', 'text' => 'Body']], JSON_THROW_ON_ERROR);
        $mcp = $this->canonicalMcp($raw);
        $rest = SendingApiInput::send(StrictApiJson::decode($raw));
        $this->assertSame($rest, $mcp);
        $this->assertCount(100, $mcp['recipients']);
        $this->assertSame('recipient1@example.test', $mcp['recipients'][0]['email']);
        $this->assertSame('recipient100@example.test', $mcp['recipients'][99]['email']);
        $this->assertSame(SendingApiInput::fingerprint($rest), SendingApiInput::fingerprint($mcp));
        $wire = (object) ['idempotency_key' => 'synthetic-canonical-key-0002',
            'message' => json_decode(json_encode($mcp, JSON_THROW_ON_ERROR))];
        $this->assertTrue(ContractArguments::valid($wire, ContractArguments::operation('mail_send')['inputSchema']));
    }

    public function test_one_hundred_and_one_recipients_are_rejected_by_the_shared_policy(): void
    {
        $raw = json_encode(['sender_id' => 7,
            'recipients' => array_map(fn (int $i): array => ['email' => 'recipient'.$i.'@example.test'], range(1, 101)),
            'content' => ['type' => 'inline', 'subject' => 'Synthetic', 'text' => 'Body']], JSON_THROW_ON_ERROR);
        $this->expectException(ApiError::class);
        $this->canonicalMcp($raw);
    }

    private function canonicalMcp(string $raw): array
    {
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $wire = WireJson::integerInputs(StrictMcpJson::decode('{"idempotency_key":"synthetic-canonical-key-0003","message":'.$raw.'}'), $schema, $schema);

        return SendingApiInput::send($wire->message);
    }
}
