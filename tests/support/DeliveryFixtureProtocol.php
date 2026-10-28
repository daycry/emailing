<?php

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** Preparation and cancellation for the controlled delivery test processes only. */
final class DeliveryFixtureProtocol
{
    public const STARTUP_SECONDS = 180;

    public const PREPARATION_SECONDS = 300;

    public const ACTION_SECONDS = 35;

    public const CONTROL_SECONDS = self::STARTUP_SECONDS + self::PREPARATION_SECONDS + 2 * self::ACTION_SECONDS + 20;

    public static function identity(array $input): array
    {
        $directory = realpath($input['directory']);
        $storage = realpath(storage_path());
        $canonical = static fn (string $path): string => strtolower(str_replace('\\', '/', $path));
        if ($directory === false || $storage === false || is_link($input['directory'])
            || ! str_starts_with($canonical($directory), $canonical($storage).'/phase3-t13/implementation/')
            || ! preg_match('/^runtime-[0-9a-f-]{36}$/D', basename($directory))
            || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $input['worker'] ?? '')
            || ! preg_match('/^[0-9a-f-]{36}$/D', $input['fixture_id'] ?? '')
            || ! is_string($input['delivery_id'] ?? null)) {
            throw new RuntimeException('Controlled delivery fixture identity is invalid.');
        }

        return [
            'fixture_id' => $input['fixture_id'], 'worker' => $input['worker'],
            'delivery_id' => $input['delivery_id'],
            'helper_sha256' => hash_file('sha256', __DIR__.'/delivery-action.php'),
        ];
    }

    public static function read(string $directory, string $signal, array $identity): ?array
    {
        if (! in_array($signal, ['started', 'go', 'abort'], true)) {
            throw new RuntimeException('Controlled delivery signal is invalid.');
        }
        $path = $directory.'/controlled-'.$signal.'-'.$identity['worker'];
        clearstatcache(true, $path);
        if (! is_file($path) || is_link($path)) {
            return null;
        }
        $bytes = file_get_contents($path, false, null, 0, 4097);
        if ($bytes === false || strlen($bytes) > 4096 || ! json_validate($bytes)) {
            return null;
        }
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            return null;
        }
        foreach ($identity as $key => $value) {
            if (($data[$key] ?? null) !== $value) {
                return null;
            }
        }

        return $data;
    }

    public static function check(string $directory, array $identity, int $deadline): void
    {
        if (self::read($directory, 'abort', $identity) !== null) {
            throw new RuntimeException('Controlled delivery fixture was cancelled.');
        }
        if (hrtime(true) >= $deadline) {
            throw new RuntimeException('Controlled delivery phase expired.');
        }
    }

    public static function delay(string $directory, array $identity, int $milliseconds, int $deadline): void
    {
        if ($milliseconds < 0 || $milliseconds > 120000) {
            throw new RuntimeException('Controlled delivery delay is out of bounds.');
        }
        $finish = hrtime(true) + $milliseconds * 1000000;
        do {
            self::check($directory, $identity, $deadline);
            if (hrtime(true) >= $finish) {
                return;
            }
            usleep(10000);
        } while (true);
    }

    public static function prepare(array $input): array
    {
        $identity = self::identity($input);
        $directory = $input['directory'];
        $start = hrtime(true);
        $deadline = $start + self::PREPARATION_SECONDS * 1000000000;
        OutboxProtocolBarrier::publish($directory.'/controlled-started-'.$input['worker'], $identity + ['monotonic_ns' => $start]);
        self::delay($directory, $identity, $input['preparation_delay_ms'] ?? 0, $deadline);
        $process = new Process([PHP_BINARY, '-n', __DIR__.'/runtime-source-inventory.php'], base_path());
        $process->setTimeout(max(0.001, ($deadline - hrtime(true)) / 1000000000));
        $failure = null;
        $started = false;
        $pid = null;
        try {
            self::check($directory, $identity, $deadline);
            $process->start();
            $started = true;
            $pid = $process->getPid();
            OutboxProtocolBarrier::publish($directory.'/controlled-inventory-'.$input['worker'], $identity + ['monotonic_ns' => hrtime(true)]);
            while ($process->isRunning()) {
                self::check($directory, $identity, $deadline);
                $process->checkTimeout();
                usleep(10000);
            }
            $process->wait();
        } catch (Throwable $error) {
            $failure = $error;
        } finally {
            if ($started && $process->isRunning()) {
                $process->stop(0);
            }
            $stdout = $started ? $process->getOutput() : '';
            $stderr = $started ? $process->getErrorOutput() : '';
            $document = json_validate($stdout) ? json_decode($stdout, true, 512, JSON_THROW_ON_ERROR) : null;
            $valid = $failure === null && $process->getExitCode() === 0 && $stderr === ''
                && is_array($document) && ($document['kind'] ?? null) === 'runtime-visible-source-inventory';
            file_put_contents($directory.'/runtime-'.$input['worker'].($valid ? '.json' : '-incomplete.stdout.txt'), $stdout);
            file_put_contents($directory.'/runtime-'.$input['worker'].'-stderr.txt', $stderr);
            OutboxProtocolBarrier::publish($directory.'/runtime-'.$input['worker'].'-receipt.json', $identity + [
                'exit' => $process->getExitCode(), 'pid' => $pid, 'terminal' => ! $process->isRunning(),
                'status' => $valid ? 'completed' : 'failed',
                'stdout_sha256' => hash('sha256', $stdout), 'stderr_sha256' => hash('sha256', $stderr),
                'started_monotonic_ns' => $start, 'ended_monotonic_ns' => hrtime(true),
            ]);
        }
        if ($failure !== null) {
            throw $failure;
        }
        if (! $valid) {
            throw new RuntimeException('Controlled delivery source inventory failed.');
        }
        self::check($directory, $identity, $deadline);

        return $identity;
    }

    public static function awaitStart(array $input, array $identity): void
    {
        $directory = $input['directory'];
        $deadline = hrtime(true) + self::ACTION_SECONDS * 1000000000;
        do {
            self::check($directory, $identity, $deadline);
            $go = self::read($directory, 'go', $identity);
            if ($go !== null) {
                $milliseconds = $go['valid_for_ms'] ?? null;
                if (! is_int($milliseconds) || $milliseconds < 0 || $milliseconds > self::ACTION_SECONDS * 1000) {
                    throw new RuntimeException('Controlled delivery action budget is invalid.');
                }
                $actionDeadline = hrtime(true) + $milliseconds * 1000000;
                self::delay($directory, $identity, $input['action_delay_ms'] ?? 0, $actionDeadline);
                self::check($directory, $identity, $actionDeadline);

                return;
            }
            usleep(10000);
        } while (true);
    }
}
