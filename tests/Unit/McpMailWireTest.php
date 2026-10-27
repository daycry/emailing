<?php

namespace Tests\Unit;

use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\WireJson;
use App\Support\StrictMcpJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Exact wire IDs and pinned root references for transactional MCP input. */
final class McpMailWireTest extends TestCase
{
    #[DataProvider('exactIds')]
    public function test_template_mail_normalizes_exact_ids_with_root_definitions(string $lexeme, int $expected): void
    {
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $json = '{"idempotency_key":"synthetic-wire-key-0001","message":{"sender_id":'.$lexeme.',"recipients":[{"email":"recipient@example.test"}],"content":{"type":"template","template_id":'.$lexeme.'}}}';
        $normalized = WireJson::integerInputs(StrictMcpJson::decode($json), $schema, $schema);
        $this->assertSame($expected, $normalized->message->sender_id);
        $this->assertSame($expected, $normalized->message->content->template_id);
        $this->assertSame('recipient@example.test', $normalized->message->recipients[0]->email);
        $this->assertTrue(ContractArguments::valid($normalized, $schema));
    }

    public static function exactIds(): iterable
    {
        yield 'integer' => ['7', 7];
        yield 'exact decimal' => ['7.0', 7];
        yield 'positive exponent' => ['7e1', 70];
        yield 'exact negative exponent' => ['70e-1', 7];
        yield 'largest exact ID' => ['9007199254740991.0', 9007199254740991];
    }

    public function test_inline_mail_resolves_its_branch_without_rewriting_literal_content(): void
    {
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $input = StrictMcpJson::decode('{"idempotency_key":"synthetic-wire-key-0002","message":{"sender_id":7.0,"recipients":[{"email":"recipient@example.test","name":"Synthetic 7e1"}],"content":{"type":"inline","subject":"  Literal 9007199254740992  ","text":"7e1\\n{{ untouched }}"}}}');
        $normalized = WireJson::integerInputs($input, $schema, $schema);
        $this->assertSame(7, $normalized->message->sender_id);
        $this->assertSame('Synthetic 7e1', $normalized->message->recipients[0]->name);
        $this->assertSame('  Literal 9007199254740992  ', $normalized->message->content->subject);
        $this->assertSame("7e1\n{{ untouched }}", $normalized->message->content->text);
        $this->assertFalse(property_exists($normalized->message->content, 'template_id'));
        $this->assertTrue(ContractArguments::valid($normalized, $schema));
    }

    #[DataProvider('invalidTemplateIds')]
    public function test_template_branch_cannot_coerce_invalid_ids(string $lexeme): void
    {
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $input = StrictMcpJson::decode('{"idempotency_key":"synthetic-wire-key-0003","message":{"sender_id":7,"recipients":[{"email":"recipient@example.test"}],"content":{"type":"template","template_id":'.$lexeme.'}}}');
        $this->expectException(ProtocolError::class);
        WireJson::integerInputs($input, $schema, $schema);
    }

    public static function invalidTemplateIds(): iterable
    {
        foreach (['"7"', '7.1', '0', '-1', '9007199254740992', '1e999999999999', 'null', 'true', '{}', '[]'] as $lexeme) {
            yield 'invalid '.$lexeme => [$lexeme];
        }
    }

    public function test_an_inline_branch_cannot_accept_template_fields_through_its_root_reference(): void
    {
        $schema = ContractArguments::operation('mail_send')['inputSchema'];
        $input = StrictMcpJson::decode('{"idempotency_key":"synthetic-wire-key-0004","message":{"sender_id":7,"recipients":[{"email":"recipient@example.test"}],"content":{"type":"inline","subject":"Synthetic","text":"Body","template_id":7}}}');
        $this->expectException(ProtocolError::class);
        WireJson::integerInputs($input, $schema, $schema);
    }
}
