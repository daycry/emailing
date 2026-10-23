<?php

namespace Tests\Support;

final class OutboxProtocolBarrier
{
    public static function publish(string $path, array $data, ?callable $beforeCommit = null): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX);
            if ($beforeCommit) {
                $beforeCommit($temporary);
            }
            if (! rename($temporary, $path)) {
                throw new \RuntimeException('Owned barrier could not be committed.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function await(string $path, callable $valid, float $seconds, ?callable $alive = null): array
    {
        $deadline = microtime(true) + $seconds;
        $length = 0;
        $jsonValid = $identityValid = false;
        do {
            clearstatcache(true, $path);
            if (is_file($path)) {
                $bytes = file_get_contents($path);
                $length = strlen($bytes);
                $jsonValid = json_validate($bytes);
                if ($jsonValid) {
                    $data = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
                    $identityValid = is_array($data) && $valid($data);
                    if ($identityValid) {
                        return $data;
                    }
                }
            }
            if ($alive && ! $alive()) {
                break;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(10000);
        } while (true);

        throw new \RuntimeException('Owned barrier unavailable or invalid: last_read_bytes='.$length.'; json_valid='.($jsonValid ? 'true' : 'false').'; identity_valid='.($identityValid ? 'true' : 'false').'.');
    }
}
