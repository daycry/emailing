<?php

namespace Tests\Unit;

use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\WireJson;
use App\Support\StrictMcpJson;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class McpWireInputsTest extends TestCase
{
    #[DataProvider('storedIds')]
    public function test_exact_integer_normalization_inside_stored_preview_branch(string $lexeme, int $expected): void
    {
        $schema = ContractArguments::operation('templates_preview')['inputSchema'];
        $input = StrictMcpJson::decode('{"source":{"type":"stored","template_id":'.$lexeme.'}}');
        $normalized = WireJson::integerInputs($input, $schema, $schema);
        $this->assertSame($expected, $normalized->source->template_id);
        $this->assertTrue(ContractArguments::valid($normalized, $schema));
    }

    public static function storedIds(): iterable
    {
        yield 'ordinary integer' => ['7', 7];
        yield 'decimal integer' => ['7.0', 7];
        yield 'exponent integer' => ['7e1', 70];
        yield 'negative exponent with exact trailing zero' => ['70e-1', 7];
        yield 'maximum exact safe integer' => ['9007199254740991.0', 9007199254740991];
    }

    #[DataProvider('invalidStoredIds')]
    public function test_invalid_stored_preview_ids_are_never_coerced(string $lexeme): void
    {
        $schema = ContractArguments::operation('templates_preview')['inputSchema'];
        $this->expectException(ProtocolError::class);
        WireJson::integerInputs(StrictMcpJson::decode('{"source":{"type":"stored","template_id":'.$lexeme.'}}'), $schema, $schema);
    }

    public static function invalidStoredIds(): iterable
    {
        foreach (['"7"', '7.1', '0', '-1', '9007199254740992', '1e999999999999', 'null', 'true', '{}', '[]'] as $value) {
            yield 'invalid '.$value => [$value];
        }
    }

    public function test_inline_branch_preserves_strings_and_empty_sample_object(): void
    {
        $schema = ContractArguments::operation('templates_preview')['inputSchema'];
        $input = StrictMcpJson::decode('{"source":{"type":"inline","content":{"subject":"Fixture 7e1","html":"<p>9007199254740992</p>"}},"sample":{}}');
        $normalized = WireJson::integerInputs($input, $schema, $schema);
        $this->assertSame('Fixture 7e1', $normalized->source->content->subject);
        $this->assertSame('<p>9007199254740992</p>', $normalized->source->content->html);
        $this->assertInstanceOf(\stdClass::class, $normalized->sample);
        $this->assertTrue(ContractArguments::valid($normalized, $schema));
    }

    public function test_nullable_filter_does_not_select_the_integer_alternative(): void
    {
        $schema = ContractArguments::operation('contacts_list')['inputSchema'];
        $input = StrictMcpJson::decode('{"list_id":null,"limit":2.5e1}');
        $normalized = WireJson::integerInputs($input, $schema, $schema);
        $this->assertNull($normalized->list_id);
        $this->assertSame(25, $normalized->limit);
        $this->assertTrue(ContractArguments::valid($normalized, $schema));
    }
}
