<?php

// Runtime source inventory: tests/support/runtime-source-inventory.php. CLI only.
// This reports allowlisted source hashes without booting Laravel or reading env.

try {
    set_error_handler(static function (int $severity): never {
        throw new ErrorException('QA inventory filesystem error.', 0, $severity);
    });

    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI required.');
    }

    $root = realpath(dirname(__DIR__, 2));
    if ($root === false || ! is_file($root.'/composer.json')) {
        throw new RuntimeException('Project root required.');
    }

    $targets = [
        'app',
        'bootstrap',
        'config',
        'database/migrations',
        'routes',
        'resources/views',
        'lang',
        'public/index.php',
        'public/build',
        'artisan',
        'composer.json',
        'composer.lock',
        'package.json',
        'package-lock.json',
        'vite.config.js',
        'vendor',
        'vendor/composer/installed.json',
    ];
    $files = [];
    $hash = static function (string $path) use ($root, &$files): void {
        if (is_link($path)) {
            throw new RuntimeException('Linked source is not attested.');
        }
        $absolute = realpath($path);
        if ($absolute === false || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR) || ! is_file($absolute)) {
            throw new RuntimeException('Contained regular file required.');
        }
        $name = str_replace(DIRECTORY_SEPARATOR, '/', substr($absolute, strlen($root) + 1));
        foreach (explode('/', $name) as $segment) {
            if (str_starts_with(strtolower($segment), '.env')) {
                throw new RuntimeException('Environment files are excluded.');
            }
        }
        $digest = hash_file('sha256', $absolute);
        if ($digest === false) {
            throw new RuntimeException('Source hash unavailable.');
        }
        $files[$name] = $digest;
    };
    foreach ($targets as $target) {
        $path = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $target);
        if (is_link($path) || ! file_exists($path)) {
            throw new RuntimeException('Required inventory target unavailable.');
        }
        if (is_file($path)) {
            $hash($path);

            continue;
        }
        $canonical = static fn (string $name): string => chr(92) === DIRECTORY_SEPARATOR
            ? strtolower(str_replace(chr(92), '/', $name)) : $name;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $file) {
            $resolved = realpath($file->getPathname());
            if ($resolved === false || $canonical($resolved) !== $canonical($file->getPathname())) {
                throw new RuntimeException('Aliased inventory entry is not attested.');
            }
            if ($file->isLink()) {
                throw new RuntimeException('Linked inventory entry is not attested.');
            }
            if ($file->isFile()) {
                $hash($file->getPathname());
            }
        }
    }
    foreach (['app/', 'bootstrap/', 'config/', 'database/migrations/', 'routes/', 'resources/views/', 'lang/', 'public/build/', 'vendor/'] as $tree) {
        if (! array_any(array_keys($files), static fn (string $name): bool => str_starts_with($name, $tree))) {
            throw new RuntimeException('Required source tree is empty.');
        }
    }
    ksort($files);
    echo json_encode([
        'schema_version' => 1,
        'kind' => 'runtime-visible-source-inventory',
        'php_version' => PHP_VERSION,
        'platform' => PHP_OS_FAMILY,
        'reporter_sha256' => hash_file('sha256', __FILE__),
        'files' => $files,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $exception) {
    restore_error_handler();
    file_put_contents('php://stderr', json_encode([
        'error' => 'qa_source_inventory_failed',
        'exception_class' => get_class($exception),
    ], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
