<?php

declare(strict_types=1);

namespace Tests\BrokerLab;

use InvalidArgumentException;
use RuntimeException;

/** Minimal application B wire format and idempotent synthetic effect. */
final class ProtocolEvent
{
    private const UUID = '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D';

    public static function encode(string $run, int $sequence, int $publishedNs, int $bytes = 2048): string
    {
        $fields = ['schema' => 1, 'run_id' => $run, 'sequence' => $sequence, 'published_ns' => $publishedNs, 'padding' => ''];
        $empty = self::json($fields);
        if ($bytes < strlen($empty) || $bytes > 4096) {
            throw new InvalidArgumentException('Invalid protocol payload budget.');
        }
        $fields['padding'] = str_repeat('x', $bytes - strlen($empty));
        $body = self::json($fields);
        self::decode($body, $run, 10000);

        return $body;
    }

    public static function decode(string $body, string $run, int $maximum): array
    {
        if (! preg_match(self::UUID, $run) || $maximum < 1 || $maximum > 10000 || strlen($body) > 4096) {
            throw new InvalidArgumentException('Invalid protocol boundary.');
        }
        try {
            $fields = json_decode($body, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new InvalidArgumentException('Invalid protocol JSON.');
        }
        if (! is_array($fields)
            || array_keys($fields) !== ['schema', 'run_id', 'sequence', 'published_ns', 'padding']
            || $fields['schema'] !== 1 || $fields['run_id'] !== $run
            || ! is_int($fields['sequence']) || $fields['sequence'] < 1 || $fields['sequence'] > $maximum
            || ! is_int($fields['published_ns']) || $fields['published_ns'] < 1
            || ! is_string($fields['padding']) || self::json($fields) !== $body) {
            throw new InvalidArgumentException('Invalid protocol envelope.');
        }

        return $fields;
    }

    public static function recordOnce(string $directory, string $body, string $run, int $maximum): bool
    {
        $event = self::decode($body, $run, $maximum);
        $real = realpath($directory);
        $storage = realpath(dirname(__DIR__, 2).'/storage');
        if ($real === false || $storage === false || ! str_starts_with($real, $storage.DIRECTORY_SEPARATOR.'broker-lab-')) {
            throw new InvalidArgumentException('Protocol effects require an owned laboratory directory.');
        }
        $path = $real.'/'.sprintf('%06d', $event['sequence']).'.json';
        if (is_link($path) || is_link($path.'.lock')) {
            throw new RuntimeException('Protocol effect path refused.');
        }
        $lock = fopen($path.'.lock', 'c+b');
        if ($lock === false) {
            throw new RuntimeException('Protocol effect lock unavailable.');
        }
        $temporary = null;
        $record = ['schema' => 1, 'run_id' => $run, 'sequence' => $event['sequence'], 'payload_sha256' => hash('sha256', $body)];
        try {
            $deadline = hrtime(true) + 2_000_000_000;
            while (! flock($lock, LOCK_EX | LOCK_NB)) {
                if (hrtime(true) >= $deadline) {
                    throw new RuntimeException('Protocol effect lock deadline exceeded.');
                }
                usleep(1000);
            }
            if (is_file($path)) {
                $existing = file_get_contents($path, false, null, 0, 1025);
                if ($existing === false || strlen($existing) > 1024 || $existing !== self::json($record)."\n") {
                    throw new RuntimeException('Protocol sequence content conflict.');
                }

                return false;
            }
            $temporary = $real.'/.effect-'.bin2hex(random_bytes(16));
            $handle = fopen($temporary, 'xb');
            if ($handle === false) {
                throw new RuntimeException('Protocol effect file unavailable.');
            }
            try {
                $bytes = self::json($record)."\n";
                if (fwrite($handle, $bytes) !== strlen($bytes) || ! fflush($handle) || ! fsync($handle)) {
                    throw new RuntimeException('Protocol effect could not be persisted.');
                }
            } finally {
                fclose($handle);
            }
            if (! rename($temporary, $path)) {
                throw new RuntimeException('Protocol effect could not be committed.');
            }
            $temporary = null;

            return true;
        } finally {
            if ($temporary !== null && is_file($temporary)) {
                unlink($temporary);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function json(array $fields): string
    {
        return json_encode($fields, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
