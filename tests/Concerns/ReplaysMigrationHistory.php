<?php

namespace Tests\Concerns;

use App\Support\TestingDatabaseGuard;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\OutboxProtocolBarrier;

require_once __DIR__.'/../support/OutboxProtocolBarrier.php';

trait ReplaysMigrationHistory
{
    /** Exercise a real historical schema, then restore every applied later migration. */
    private function replayMigrationWindow(string $target, ?callable $historicalAssertions = null): void
    {
        TestingDatabaseGuard::assertSafe();
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Migration fixture requires an independent testing connection.');
        }
        $migrator = app('migrator');
        $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
        $applied = app('migration.repository')->getRan();
        if (! in_array($target, $applied, true) || ! isset($files[$target])) {
            throw new \LogicException('Historical target must be an applied migration with source.');
        }
        $window = [];
        foreach ($applied as $name) {
            if (strcmp($name, $target) < 0) {
                continue;
            }
            if (! isset($files[$name]) || is_link($files[$name])) {
                throw new \LogicException('Every applied migration in the window needs regular source.');
            }
            $migration = (static fn ($path) => require $path)($files[$name]);
            if (! $migration instanceof Migration || ! in_array($migration->getConnection(), [null, config('database.default')], true)) {
                throw new \LogicException('Migration fixture refuses another database connection.');
            }
            $window[$name] = ['migration' => $migration, 'sha256' => hash_file('sha256', $files[$name])];
        }
        ksort($window, SORT_STRING);
        $base = realpath(getenv('T16_EVIDENCE_DIRECTORY') ?: storage_path('app/private'));
        $storage = realpath(storage_path());
        $normalize = static fn ($path) => PHP_OS_FAMILY === 'Windows' ? strtolower(str_replace('\\', '/', $path)) : $path;
        if ($base === false || $storage === false || ! str_starts_with($normalize($base).'/', $normalize($storage).'/')) {
            throw new \LogicException('Existing contained fixture evidence directory required.');
        }
        $id = (string) Str::uuid();
        $trace = ['fixture_uuid' => $id, 'guarded_database' => 'emailing_testing', 'target' => $target, 'applied_window' => array_keys($window), 'steps' => []];
        // The trace records only schema names, code hashes and safe error classes.
        $run = function (string $name, string $direction) use ($window, &$trace, $base, $id): void {
            TestingDatabaseGuard::assertSafe();
            if (DB::transactionLevel() !== 0) {
                throw new \LogicException('Migration fixture acquired an unexpected transaction.');
            }
            $step = ['migration' => $name, 'source_sha256' => $window[$name]['sha256'], 'direction' => $direction, 'at_utc' => gmdate('c')];
            try {
                $window[$name]['migration']->{$direction}();
                $step['completed'] = true;
            } catch (\Throwable $error) {
                $step += ['completed' => false, 'exception_class' => get_class($error), 'code' => (string) $error->getCode()];
                throw $error;
            } finally {
                $trace['steps'][] = $step;
                OutboxProtocolBarrier::publish($base.'/migration-window-'.$id.'.json', $trace);
            }
        };
        $completed = [];
        $failure = null;
        try {
            foreach (array_reverse(array_keys($window)) as $name) {
                $run($name, 'down');
                $completed[] = $name;
            }
            if ($historicalAssertions !== null) {
                $historicalAssertions();
            }
        } catch (\Throwable $error) {
            $failure = $error;
        } finally {
            foreach (array_reverse($completed) as $name) {
                try {
                    $run($name, 'up');
                } catch (\Throwable $restoreError) {
                    $trace['restoration_failure_class'] = get_class($restoreError);
                    $failure ??= $restoreError;
                    break; // Never apply dependent migrations after a failed prerequisite.
                }
            }
            $trace['restored'] = ! isset($trace['restoration_failure_class']) && count($completed) === count($window);
            $trace['primary_failure_class'] = $failure ? get_class($failure) : null;
            OutboxProtocolBarrier::publish($base.'/migration-window-'.$id.'.json', $trace);
        }
        if ($failure) {
            throw $failure;
        }
    }
}
