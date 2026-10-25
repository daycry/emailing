<?php

namespace App\Services\Mcp;

use LogicException;
use Opis\JsonSchema\CompliantValidator;
use Opis\JsonSchema\Schema;
use stdClass;

/** Typed JSON validation against pinned schemas, with no external resolution or defaults. */
final class ContractArguments
{
    private static ?CompliantValidator $validator = null;

    /** @var array<string, stdClass>|null */
    private static ?array $schemas = null;

    /** @var array<string, Schema> */
    private static array $compiled = [];

    private static array $operations = [];

    public static function operation(string $name): array
    {
        self::schemas();

        return self::$operations[$name] ?? throw new LogicException('Unknown MCP operation');
    }

    public static function valid(mixed $value, array $schema): bool
    {
        $key = hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR));
        $validator = self::validator();
        if (! isset(self::$compiled[$key])) {
            $original = self::schemas()[$key] ?? throw new LogicException('Schema is not in the pinned MCP catalogue');
            self::$compiled[$key] = $validator->loader()->loadObjectSchema(self::byteLimits($original));
        }

        return $validator->validate($value, self::$compiled[$key])->isValid();
    }

    private static function validator(): CompliantValidator
    {
        if (self::$validator === null) {
            self::$validator = new CompliantValidator(max_errors: 1, stop_at_first_error: true);
            self::$validator->setResolver(null);
            self::$validator->parser()->setDefaultDraftVersion('2020-12');
        }

        return self::$validator;
    }

    /** Keep original schema object types, including empty object containers. */
    private static function schemas(): array
    {
        if (self::$schemas === null) {
            $bytes = file_get_contents(dirname(__DIR__, 3).'/docs/api/mcp-tools.json');
            if (! is_string($bytes) || hash('sha256', $bytes) !== 'aafcf53ed42ee722cf18d183b986e22505ca1da355aed38916567059d6d207bf') {
                throw new LogicException('MCP catalogue integrity check failed');
            }
            $contract = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
            $schemas = [];
            $register = function (stdClass $schema) use (&$schemas): stdClass {
                $encoded = json_encode($schema, JSON_THROW_ON_ERROR);
                $array = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
                $key = hash('sha256', json_encode($array, JSON_THROW_ON_ERROR));
                if (isset($schemas[$key]) && json_encode($schemas[$key], JSON_THROW_ON_ERROR) !== $encoded) {
                    throw new LogicException('Ambiguous MCP schema representation');
                }
                $schemas[$key] = $schema;

                return $schema;
            };
            foreach ($contract->operations as $operation) {
                self::$operations[$operation->name] = json_decode(json_encode($operation, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                self::walk($operation->inputSchema, $register);
                self::walk($operation->outputSchema, $register);
            }
            self::walk($contract->cursor->decrypted_payload_schema, $register);
            self::$schemas = $schemas;
        }

        return self::$schemas;
    }

    /** Integrate byte assertions into the standard evaluator, including conditional branches. */
    private static function byteLimits(stdClass $original): stdClass
    {
        return self::walk($original, function (stdClass $schema): stdClass {
            if (property_exists($schema, 'x-maxUtf8Bytes')) {
                $limit = $schema->{'x-maxUtf8Bytes'};
                if (! is_int($limit) || $limit < 0) {
                    throw new LogicException('Invalid MCP byte constraint');
                }
                $format = 'emailing-utf8-max-'.$limit;
                self::validator()->parser()->getFormatResolver()->registerCallable('string', $format, fn (string $value): bool => strlen($value) <= $limit);
                $schema->allOf ??= [];
                $schema->allOf[] = (object) ['format' => $format];
            }

            return $schema;
        });
    }

    /** Visit schema positions only; enum/const values are data, not nested schemas. */
    private static function walk(stdClass $original, callable $visit): stdClass
    {
        $schema = clone $original;
        foreach (['properties', 'patternProperties', '$defs', 'definitions', 'dependentSchemas'] as $keyword) {
            if (($schema->{$keyword} ?? null) instanceof stdClass) {
                $map = clone $schema->{$keyword};
                foreach (get_object_vars($map) as $name => $child) {
                    if ($child instanceof stdClass) {
                        $map->{$name} = self::walk($child, $visit);
                    }
                }
                $schema->{$keyword} = $map;
            }
        }
        foreach (['items', 'additionalProperties', 'unevaluatedProperties', 'unevaluatedItems', 'contains', 'propertyNames', 'if', 'then', 'else', 'not', 'contentSchema'] as $keyword) {
            if (($schema->{$keyword} ?? null) instanceof stdClass) {
                $schema->{$keyword} = self::walk($schema->{$keyword}, $visit);
            }
        }
        foreach (['allOf', 'anyOf', 'oneOf', 'prefixItems'] as $keyword) {
            if (is_array($schema->{$keyword} ?? null)) {
                $schema->{$keyword} = array_map(fn ($child) => $child instanceof stdClass ? self::walk($child, $visit) : $child, $schema->{$keyword});
            }
        }

        return $visit($schema);
    }
}
