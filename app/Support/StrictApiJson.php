<?php

namespace App\Support;

/** Bounded JSON grammar scanner, retaining object/array types and detecting decoded duplicate keys. */
final class StrictApiJson
{
    private int $position = 0;

    private function __construct(private string $bytes) {}

    public static function decode(string $bytes): mixed
    {
        ApiError::require(strlen($bytes) <= 2097152, 413);
        ApiError::require(mb_check_encoding($bytes, 'UTF-8'), 400);
        $parser = new self($bytes);
        $value = $parser->value(0);
        $parser->space();
        ApiError::require($parser->position === strlen($bytes), 400);

        return $value;
    }

    private function space(): void
    {
        $this->position += strspn($this->bytes, " \t\r\n", $this->position);
    }

    private function value(int $depth): mixed
    {
        $this->space();
        ApiError::require($this->position < strlen($this->bytes), 400);
        $char = $this->bytes[$this->position] ?? '';
        if ($char === '{' || $char === '[') {
            ApiError::require($depth < 32, 400);
            $object = $char === '{';
            $end = $object ? '}' : ']';
            // Build properties in an array first: assigning a valid JSON name
            // starting with NUL directly to stdClass throws a PHP Error.
            $result = [];
            $seen = [];
            $this->position++;
            $this->space();
            if (($this->bytes[$this->position] ?? '') === $end) {
                $this->position++;

                return $object ? (object) $result : $result;
            }
            while (true) {
                if ($object) {
                    $this->space();
                    $key = $this->string();
                    // Prefix avoids PHP numeric-string array-key equivalences.
                    ApiError::require(! isset($seen['k'.$key]), 400);
                    $seen['k'.$key] = true;
                    $this->space();
                    ApiError::require(($this->bytes[$this->position++] ?? '') === ':', 400);
                    $result[$key] = $this->value($depth + 1);
                } else {
                    $result[] = $this->value($depth + 1);
                }
                $this->space();
                $next = $this->bytes[$this->position++] ?? '';
                if ($next === $end) {
                    return $object ? (object) $result : $result;
                }
                ApiError::require($next === ',', 400);
            }
        }
        if ($char === '"') {
            return $this->string();
        }
        foreach (['true' => true, 'false' => false, 'null' => null] as $literal => $value) {
            if (substr_compare($this->bytes, $literal, $this->position, strlen($literal)) === 0) {
                $this->position += strlen($literal);

                return $value;
            }
        }
        ApiError::require((bool) preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/', $this->bytes, $match, 0, $this->position), 400);
        $this->position += strlen($match[0]);
        try {
            // Keep decimal/exponent lexemes exact until the integer schema is checked.
            // Plain integers up to 18 digits fit PHP's 64-bit integer without rounding.
            return preg_match('/^-?[0-9]{1,18}$/D', $match[0]) ? (int) $match[0] : new ApiJsonNumber($match[0]);
        } catch (\JsonException) {
            throw new ApiError(400);
        }
    }

    private function string(): string
    {
        ApiError::require(($this->bytes[$this->position] ?? '') === '"', 400);
        $start = $this->position++;
        $length = strlen($this->bytes);
        while ($this->position < $length) {
            $char = $this->bytes[$this->position++];
            if ($char === '\\') {
                $this->position++;
            } elseif ($char === '"') {
                try {
                    return json_decode(substr($this->bytes, $start, $this->position - $start), false, 2, JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    throw new ApiError(400);
                }
            }
        }
        throw new ApiError(400);
    }
}
