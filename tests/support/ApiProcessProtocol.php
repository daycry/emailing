<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

final class ApiFixtureCancelled extends RuntimeException {}

final class ApiFixtureActionExpired extends RuntimeException {}

/** Test-only lifecycle for owned API fixtures and complete source inventories. */
final class ApiProcessProtocol
{
    public const STARTUP_SECONDS = 180;

    public const ACTION_SECONDS = 180;

    public const MAX_PREPARED_FIXTURES = 4;

    public const CONTROL_SECONDS = self::MAX_PREPARED_FIXTURES * self::STARTUP_SECONDS + self::ACTION_SECONDS + 60;

    public const CLEANUP_GRACE_SECONDS = 5;

    public static function validate(string $directory, string $worker): void
    {
        $resolved = realpath($directory);
        $storage = realpath(storage_path());
        $canonical = static fn (string $path): string => PHP_OS_FAMILY === 'Windows'
            ? strtolower(str_replace('\\', '/', $path)) : $path;
        if ($resolved === false || $storage === false || is_link($directory)
            || ! str_starts_with($canonical($resolved), $canonical($storage).'/')
            || ! preg_match('/^api-[0-9a-f-]{36}$/D', basename($resolved))
            || ! preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/D', $worker)) {
            throw new RuntimeException('Owned API fixture identity is invalid.');
        }
    }

    public static function readSignal(string $directory, string $worker, string $signal): ?array
    {
        self::validate($directory, $worker);
        if (! in_array($signal, ['ready', 'go', 'release', 'abort', 'work-release', 'attempt-account', 'failure'], true)) {
            throw new RuntimeException('Owned API signal is invalid.');
        }
        $path = $directory.'/'.$signal.'-'.$worker;
        clearstatcache(true, $path);
        if (! is_file($path) || is_link($path)) {
            return null;
        }
        $bytes = file_get_contents($path, false, null, 0, 4097);
        if ($bytes === false || strlen($bytes) > 4096 || ! json_validate($bytes)) {
            return null;
        }
        $data = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);

        return is_array($data) && ($data['worker'] ?? null) === $worker ? $data : null;
    }

    public static function assertNotCancelled(string $directory, string $worker): void
    {
        if (self::readSignal($directory, $worker, 'abort') !== null) {
            throw new ApiFixtureCancelled('Owned API fixture was cancelled.');
        }
    }

    public static function checkDeadline(int $deadline, bool $action = false): void
    {
        if ($deadline <= hrtime(true)) {
            if ($action) {
                throw new ApiFixtureActionExpired('Owned API action deadline expired.');
            }
            throw new RuntimeException('Owned API fixture phase deadline expired.');
        }
    }

    public static function awaitSignal(string $directory, string $worker, string $signal, int $deadline): array
    {
        self::validate($directory, $worker);
        do {
            self::assertNotCancelled($directory, $worker);
            self::checkDeadline($deadline);
            $data = self::readSignal($directory, $worker, $signal);
            if ($data !== null) {
                self::assertNotCancelled($directory, $worker);

                return $data;
            }
            usleep(10000);
        } while (true);
    }

    public static function delay(string $directory, string $worker, int $milliseconds, int $deadline, bool $action = false): void
    {
        if ($milliseconds < 0 || $milliseconds > 90000) {
            throw new RuntimeException('Owned API delay is out of bounds.');
        }
        $finish = hrtime(true) + $milliseconds * 1000000;
        do {
            self::assertNotCancelled($directory, $worker);
            self::checkDeadline($deadline, $action);
            if (hrtime(true) >= $finish) {
                return;
            }
            usleep(10000);
        } while (true);
    }

    /** Same complete inventory reporter; cancellation can stop its owned process. */
    public static function capture(string $directory, string $worker, int $deadline, string $reporter): void
    {
        self::validate($directory, $worker);
        self::assertNotCancelled($directory, $worker);
        self::checkDeadline($deadline);
        $expected = realpath(base_path('tests/support/runtime-source-inventory.php'));
        if ($expected === false || realpath($reporter) !== $expected || is_link($reporter)) {
            throw new RuntimeException('Owned API source reporter is invalid.');
        }
        $command = [PHP_BINARY, '-n', $reporter];
        $process = new Process($command, base_path());
        $process->setTimeout(max(0.001, ($deadline - hrtime(true)) / 1000000000));
        $started = false;
        $pid = null;
        $failure = null;
        $start = hrtime(true);
        try {
            $process->start();
            $started = true;
            $pid = $process->getPid();
            while ($process->isRunning()) {
                self::assertNotCancelled($directory, $worker);
                self::checkDeadline($deadline);
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
            $exit = $started ? $process->getExitCode() : null;
            $document = json_validate($stdout) ? json_decode($stdout, true, 512, JSON_THROW_ON_ERROR) : null;
            $valid = $failure === null && $exit === 0 && $stderr === ''
                && is_array($document) && ($document['kind'] ?? null) === 'runtime-visible-source-inventory';
            file_put_contents($directory.'/runtime-'.$worker.($valid ? '.json' : '-incomplete.stdout.txt'), $stdout);
            file_put_contents($directory.'/runtime-'.$worker.'-stderr.txt', $stderr);
            OutboxProtocolBarrier::publish($directory.'/runtime-'.$worker.'-receipt.json', [
                'command' => $command, 'pid' => $pid, 'exit' => $exit,
                'stdout_sha256' => hash('sha256', $stdout), 'stderr_sha256' => hash('sha256', $stderr),
                'status' => $valid ? 'completed' : ($failure instanceof ApiFixtureCancelled ? 'aborted' : 'failed'),
                'terminal' => ! $started || ! $process->isRunning(),
                'cancelled' => $failure instanceof ApiFixtureCancelled,
                'started_monotonic_ns' => $start, 'ended_monotonic_ns' => hrtime(true),
            ]);
        }
        if ($failure !== null) {
            throw $failure;
        }
        if (! $valid) {
            throw new RuntimeException('Controlled API source inventory failed.');
        }
        self::assertNotCancelled($directory, $worker);
        self::checkDeadline($deadline);
    }
}
