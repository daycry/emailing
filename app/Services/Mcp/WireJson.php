<?php

namespace App\Services\Mcp;

use App\Support\McpJsonNumber;
use stdClass;

/** Retains unrelated numeric lexemes and strings; normalizes only declared integer inputs. */
final class WireJson
{
    public static function encode(mixed $value): string
    {
        if ($value instanceof McpJsonNumber) {
            return $value->lexeme();
        }
        if ($value instanceof stdClass) {
            $pairs = [];
            foreach (get_object_vars($value) as $key => $item) {
                $pairs[] = json_encode((string) $key, JSON_THROW_ON_ERROR).':'.self::encode($item);
            }

            return '{'.implode(',', $pairs).'}';
        }
        if (is_array($value)) {
            return '['.implode(',', array_map(self::encode(...), $value)).']';
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function integerInputs(mixed $value, array $schema, array $root): mixed
    {
        if (isset($schema['$ref'])) {
            $resolved = $root;
            foreach (explode('/', substr($schema['$ref'], 2)) as $part) {
                $resolved = $resolved[str_replace(['~1', '~0'], ['/', '~'], $part)] ?? [];
            }

            return self::integerInputs($value, $resolved, $root);
        }
        if (($schema['type'] ?? '') === 'integer') {
            $integer = $value instanceof McpJsonNumber ? $value->safePositiveInteger() : $value;
            if (! is_int($integer) || $integer < ($schema['minimum'] ?? 1) || $integer > ($schema['maximum'] ?? 9007199254740991)) {
                throw new ProtocolError(400, -32602, 'Invalid arguments');
            }

            return $integer;
        }
        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $child = $schema['properties'][$key] ?? (is_array($schema['additionalProperties'] ?? null) ? $schema['additionalProperties'] : []);
                $value->{$key} = self::integerInputs($item, $child, $root);
            }
        } elseif (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::integerInputs($item, $schema['items'] ?? [], $root);
            }
        }
        foreach ($schema['allOf'] ?? [] as $part) {
            $value = self::integerInputs($value, $part, $root);
        }
        if (isset($schema['if'])) {
            $conditional = ContractArguments::valid($value, $schema['if']) ? 'then' : 'else';
            if (isset($schema[$conditional])) {
                $value = self::integerInputs($value, $schema[$conditional], $root);
            }
        }
        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (! isset($schema[$keyword])) {
                continue;
            }
            $matched = false;
            foreach ($schema[$keyword] as $alternative) {
                try {
                    $candidate = self::integerInputs(self::copy($value), $alternative, $root);
                    if (ContractArguments::valid($candidate, $alternative)) {
                        $value = $candidate;
                        $matched = true;
                        break;
                    }
                } catch (ProtocolError) {
                    // A failed alternative must not mutate the value offered to another branch.
                }
            }
            if (! $matched) {
                throw new ProtocolError(400, -32602, 'Invalid arguments');
            }
        }

        return $value;
    }

    private static function copy(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $copy = new stdClass;
            foreach (get_object_vars($value) as $key => $item) {
                $copy->{$key} = self::copy($item);
            }

            return $copy;
        }

        return is_array($value) ? array_map(self::copy(...), $value) : $value;
    }
}
