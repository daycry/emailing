<?php

namespace Tests\Unit;

use App\Services\Mcp\ContractArguments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

final class McpContractArgumentsTest extends TestCase
{
    public function test_empty_json_object_is_valid_for_optional_contact_list_arguments(): void
    {
        $this->assertTrue(ContractArguments::valid(new stdClass, self::schema('contacts_list')));
    }

    #[DataProvider('contractExamples')]
    public function test_pinned_data_schemas_preserve_json_types_and_constraints(string $operation, mixed $arguments, bool $valid): void
    {
        $this->assertSame($valid, ContractArguments::valid($arguments, self::schema($operation)));
    }

    public static function contractExamples(): iterable
    {
        yield 'an empty array cannot replace a JSON object' => ['contacts_list', [], false];
        yield 'closed root rejects tenant selection' => ['contacts_list', (object) ['account_id' => 7], false];
        yield 'minimum page size' => ['contacts_list', (object) ['limit' => 1], true];
        yield 'maximum page size' => ['contacts_list', (object) ['limit' => 100], true];
        yield 'page size zero' => ['contacts_list', (object) ['limit' => 0], false];
        yield 'page size beyond contract' => ['contacts_list', (object) ['limit' => 101], false];
        yield 'integer cannot be a numeric string' => ['contacts_list', (object) ['limit' => '25'], false];
        yield 'nullable filter' => ['contacts_list', (object) ['list_id' => null, 'cursor' => null], true];
        yield 'Unicode length counts characters' => ['contacts_list', (object) ['search' => str_repeat('é', 150)], true];
        yield 'search beyond character limit' => ['contacts_list', (object) ['search' => str_repeat('é', 151)], false];
        yield 'stored preview branch' => ['templates_preview', (object) ['source' => (object) ['type' => 'stored', 'template_id' => 7]], true];
        yield 'closed branch rejects inline content on stored source' => ['templates_preview', (object) ['source' => (object) ['type' => 'stored', 'template_id' => 7, 'content' => (object) ['subject' => 'Fixture', 'html' => '<p>Fixture</p>']]], false];
        yield 'inline preview branch' => ['templates_preview', self::inline('<p>Fixture</p>'), true];
        yield 'inline preview needs nonempty HTML or text' => ['templates_preview', self::inline(''), false];
        yield 'subject cannot contain a newline' => ['templates_preview', self::inline('<p>Fixture</p>', "Fixture\nHeader"), false];
        yield 'exact UTF8 byte limit' => ['templates_preview', self::inline(str_repeat('é', 50000)), true];
        yield 'UTF8 byte extension rejects within-character-limit overflow' => ['templates_preview', self::inline(str_repeat('é', 50001)), false];
        yield 'domain-normalizable email remains schema-valid' => ['contacts_create', self::contact(['email' => ' Person@Example.test ']), true];
        yield 'granted consent requires its proof' => ['contacts_create', self::contact(['consent_status' => 'granted']), false];
        yield 'granted consent rejects null proof' => ['contacts_create', self::contact(['consent_status' => 'granted', 'consent_source' => null, 'consented_at' => null]), false];
        yield 'granted consent accepts supplied proof shape' => ['contacts_create', self::contact(['consent_status' => 'granted', 'consent_source' => 'Fixture form', 'consented_at' => '2026-10-01T10:30']), true];
        yield 'timestamp shape rejects a trailing newline' => ['contacts_create', self::contact(['consented_at' => "2026-10-01T10:30\n"]), false];
        yield 'closed nested object rejects internal fields' => ['contacts_create', self::contact(['account_id' => 7]), false];
    }

    private static function inline(string $html, string $subject = 'Fixture'): stdClass
    {
        return (object) ['source' => (object) ['type' => 'inline', 'content' => (object) ['subject' => $subject, 'html' => $html]]];
    }

    private static function contact(array $overrides): stdClass
    {
        return (object) ['contact' => (object) array_replace(['email' => 'contact@example.test', 'name' => 'Fixture', 'consent_status' => 'unknown'], $overrides)];
    }

    private static function schema(string $name): array
    {
        $contract = json_decode(file_get_contents(dirname(__DIR__, 2).'/docs/api/mcp-tools.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach ($contract['operations'] as $operation) {
            if ($operation['name'] === $name) {
                return $operation['inputSchema'];
            }
        }

        throw new \LogicException('Unknown fixture operation');
    }
}
