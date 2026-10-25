<?php

namespace App\Services\Mcp;

/** Closed object/string/integer schemas used by the two T07 prerequisites only. */
final class ContractArguments
{
    public static function valid(mixed $value, array $schema): bool
    {
        return match ($schema['type'] ?? null) {
            'object' => is_array($value) && (array_diff($schema['required'] ?? [], array_keys($value)) === []) && (array_diff(array_keys($value), array_keys($schema['properties'])) === []) && self::properties($value, $schema['properties']),
            'string' => is_string($value) && mb_strlen($value, 'UTF-8') >= ($schema['minLength'] ?? 0) && mb_strlen($value, 'UTF-8') <= ($schema['maxLength'] ?? PHP_INT_MAX),
            'integer' => is_int($value) && $value >= ($schema['minimum'] ?? PHP_INT_MIN) && $value <= ($schema['maximum'] ?? PHP_INT_MAX),
            default => false,
        };
    }

    private static function properties(array $value, array $properties): bool
    {
        foreach ($value as $key => $item) {
            if (! self::valid($item, $properties[$key])) {
                return false;
            }
        }

        return true;
    }
}
