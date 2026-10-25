<?php

namespace App\Support;

/** A decimal/exponent JSON lexeme retained until integer-schema validation. */
final class McpJsonNumber
{
    public function __construct(private readonly string $lexeme) {}

    public function lexeme(): string
    {
        return $this->lexeme;
    }

    public function safePositiveInteger(): ?int
    {
        if (! preg_match('/\A(-?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?)([0-9]+))?\z/D', $this->lexeme, $parts) || $parts[1] === '-') {
            return null;
        }
        $fraction = $parts[3] ?? '';
        $digits = ltrim($parts[2].$fraction, '0');
        if ($digits === '') {
            return null;
        }
        $exponentDigits = ltrim($parts[5] ?? '0', '0');
        $bound = (string) (strlen($this->lexeme) + 16);
        if (strlen($exponentDigits) > strlen($bound) || (strlen($exponentDigits) === strlen($bound) && strcmp($exponentDigits, $bound) > 0)) {
            // A nonzero coefficient cannot fit the safe range/integrality
            // outside this bound; never expand or cast an arbitrary exponent.
            return null;
        }
        $exponent = (int) ($exponentDigits === '' ? '0' : $exponentDigits);
        if (($parts[4] ?? '') === '-') {
            $exponent = -$exponent;
        }
        $scale = $exponent - strlen($fraction);
        if ($scale < 0) {
            $cut = -$scale;
            $trailing = strlen($digits) - strlen(rtrim($digits, '0'));
            if ($cut > $trailing) {
                return null;
            }
            $digits = substr($digits, 0, strlen($digits) - $cut);
        } else {
            if (strlen($digits) + $scale > 16) {
                return null;
            }
            $digits .= str_repeat('0', $scale); // At most fifteen appended digits.
        }
        if (strlen($digits) > 16 || (strlen($digits) === 16 && strcmp($digits, '9007199254740991') > 0)) {
            return null;
        }

        return (int) $digits;
    }
}
