<?php

// Real Linux filesystem boundaries; no framework, env, database or network.
$root = sys_get_temp_dir().'/emailing-inventory-'.bin2hex(random_bytes(12));
if (PHP_OS_FAMILY !== 'Linux' || posix_geteuid() === 0) {
    fwrite(STDERR, "Inventory fixtures require an unprivileged Linux identity.\n");
    exit(2);
}
mkdir($root, 0700);
$inputs = [
    'composer.json' => '{}', 'composer.lock' => '{}', 'package-lock.json' => '{}',
    'artisan' => '<?php // fixture CLI', 'package.json' => '{}', 'vite.config.js' => '// fixture build',
    'app/Synthetic.php' => '<?php // fixture', 'bootstrap/app.php' => '<?php // fixture',
    'bootstrap/providers.php' => '<?php return [];', 'config/synthetic.php' => '<?php return [];',
    'database/migrations/001.php' => '<?php // fixture', 'routes/synthetic.php' => '<?php // fixture',
    'lang/es/validation.php' => '<?php return [];',
    'resources/views/synthetic.blade.php' => '<p>Synthetic</p>', 'public/index.php' => '<?php // fixture',
    'public/build/manifest.json' => '{}', 'public/build/fixture.js' => '/* fixture */',
    'vendor/composer/installed.json' => '[]', 'vendor/synthetic/runtime.php' => '<?php // dependency',
];
$remove = static function (string $path) use (&$remove, $root): void {
    if (! str_starts_with($path, $root.'/')) {
        throw new RuntimeException('Unowned fixture cleanup refused.');
    }
    if (is_link($path) || is_file($path)) {
        if (! is_link($path)) {
            chmod($path, 0600);
        }
        unlink($path);
    } elseif (is_dir($path)) {
        chmod($path, 0700);
        foreach (new DirectoryIterator($path) as $entry) {
            if (! $entry->isDot()) {
                $remove($entry->getPathname());
            }
        }
        rmdir($path);
    }
};
$results = [];
try {
    foreach (['complete', 'linked-file', 'linked-directory', 'linked-required-target', 'unreadable-file', 'unreadable-directory', 'unreadable-dependency', 'environment-name', 'empty-tree', 'empty-language-tree'] as $case) {
        $fixture = $root.'/'.$case;
        mkdir($fixture, 0700);
        foreach ($inputs as $name => $bytes) {
            if (! is_dir(dirname($fixture.'/'.$name))) {
                mkdir(dirname($fixture.'/'.$name), 0700, true);
            }
            file_put_contents($fixture.'/'.$name, $bytes);
        }
        mkdir($fixture.'/tests/support', 0700, true);
        copy(__DIR__.'/runtime-source-inventory.php', $fixture.'/tests/support/runtime-source-inventory.php');
        match ($case) {
            'linked-file' => symlink($fixture.'/composer.lock', $fixture.'/app/alias.php'),
            'linked-directory' => symlink($fixture.'/config', $fixture.'/resources/views/alias'),
            'linked-required-target' => (static function () use ($fixture): void {
                unlink($fixture.'/composer.lock');
                symlink($fixture.'/package-lock.json', $fixture.'/composer.lock');
            })(),
            'unreadable-file' => chmod($fixture.'/app/Synthetic.php', 0000),
            'unreadable-directory' => chmod($fixture.'/resources/views', 0000),
            'unreadable-dependency' => chmod($fixture.'/vendor/synthetic/runtime.php', 0000),
            'environment-name' => file_put_contents($fixture.'/config/.EnV.private', 'SYNTHETIC_PRIVATE'),
            'empty-tree' => unlink($fixture.'/database/migrations/001.php'),
            'empty-language-tree' => unlink($fixture.'/lang/es/validation.php'),
            default => null,
        };
        $process = proc_open([PHP_BINARY, '-n', $fixture.'/tests/support/runtime-source-inventory.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $pid = proc_get_status($process)['pid'];
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($case === 'complete') {
            $actual = json_decode($stdout, true, flags: JSON_THROW_ON_ERROR);
            $expected = [];
            foreach ($inputs as $name => $bytes) {
                $expected[$name] = hash('sha256', $bytes);
            }
            ksort($expected);
            $passed = $exit === 0 && $stderr === '' && $actual['files'] === $expected;
        } else {
            $error = json_decode($stderr, true);
            $passed = $exit === 1 && $stdout === '' && is_array($error) && array_keys($error) === ['error', 'exception_class']
                && $error['error'] === 'qa_source_inventory_failed' && ! str_contains($stderr, $root) && ! str_contains($stderr, 'SYNTHETIC_PRIVATE');
        }
        $results[] = ['case' => $case, 'passed' => $passed, 'exit' => $exit, 'pid' => $pid, 'stdout' => $stdout, 'stderr' => $stderr, 'stdout_sha256' => hash('sha256', $stdout), 'stderr_sha256' => hash('sha256', $stderr)];
        $remove($fixture);
    }
} finally {
    foreach (new DirectoryIterator($root) as $entry) {
        if (! $entry->isDot()) {
            $remove($entry->getPathname());
        }
    }
    rmdir($root);
}
echo json_encode(['platform' => PHP_OS_FAMILY, 'non_root' => posix_geteuid() !== 0, 'reporter_sha256' => hash_file('sha256', __DIR__.'/runtime-source-inventory.php'), 'cases' => $results, 'owned_fixture_remaining' => is_dir($root)], JSON_THROW_ON_ERROR).PHP_EOL;
exit(array_all($results, static fn (array $result): bool => $result['passed']) ? 0 : 1);
