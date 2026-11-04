<?php

require __DIR__.'/../../support/GoogleBrowserLease.php';

$checks = 0;
$ok = static function (bool $condition) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException('Browser lease assertion failed.');
    }
};
$refuses = static function (callable $operation, string $reason) use ($ok): void {
    try {
        $operation();
    } catch (RuntimeException $error) {
        $ok($error->getMessage() === $reason);

        return;
    }
    $ok(false);
};
$parent = realpath(sys_get_temp_dir());
$root = $parent.DIRECTORY_SEPARATOR.'emailing-browser-lease-'.bin2hex(random_bytes(8));
$run = 'b5dc463d-f182-4d0a-896f-13337b12ea66';
$directory = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'mcp-google-phase3'.DIRECTORY_SEPARATOR.'browser-'.$run;
mkdir($directory, 0700, true);
$descriptor = ['profile' => 'docker', 'run' => $run, 'directory' => $directory];
$leases = [];
try {
    $enabled = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $enabled->enable();
    $enabled->close();
    $marker = $directory.DIRECTORY_SEPARATOR.'http-enabled';
    $ok(is_file($marker) && filesize($marker) === 0);
    $again = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $refuses(static fn () => $again->enable(), 'browser_lease_already_enabled');
    $again->close();
    $first = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $first->enterHttp(0.1);
    $ok(true);
    $second = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $second->enterHttp(0.1);
    $ok(true); // Two shared readers coexist; requests are not serialized.
    $blocked = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $refuses(static fn () => $blocked->quiesce(0.01), 'browser_lease_deadline');
    $blocked->close();
    clearstatcache(true, $marker);
    $ok(is_file($marker));
    $first->close();
    $second->close();
    $cleanup = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $cleanup->quiesce(0.1);
    clearstatcache(true, $marker);
    $ok(! file_exists($marker));
    $waiting = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $refuses(static fn () => $waiting->enterHttp(0.01), 'browser_lease_deadline');
    $waiting->close();
    $cleanup->close();
    $late = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $refuses(static fn () => $late->enterHttp(0.1), 'browser_http_quarantined');
    $late->close();
    $refuses(static fn () => new GoogleBrowserLease([...$descriptor, 'run' => 'c5dc463d-f182-4d0a-896f-13337b12ea66'], $root), 'browser_lease_directory_refused');
    $refuses(static fn () => new GoogleBrowserLease([...$descriptor, 'profile' => 'native'], $root), 'browser_lease_directory_refused');
    $firstLedger = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $firstLedger->mergeOwnedRows(['attempts' => [1], 'codes' => ['synthetic-code-a'], 'windows' => ['synthetic-window-a']]);
    $firstLedger->close();
    $secondLedger = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $secondLedger->mergeOwnedRows(['attempts' => [2, 1], 'codes' => ['synthetic-code-b'], 'windows' => []]);
    $secondLedger->close();
    // This file contains only the synthetic identifiers created above; it is
    // normal test bootstrap, not an inspection of any runtime/private body.
    $rows = json_decode(file_get_contents($directory.DIRECTORY_SEPARATOR.'google-owned-rows.json'), true, flags: JSON_THROW_ON_ERROR);
    $ok($rows['attempts'] === [1, 2]);
    $ok($rows['codes'] === ['synthetic-code-a', 'synthetic-code-b']);
    $ok($rows['windows'] === ['synthetic-window-a']);
    $invalidLedger = $leases[] = new GoogleBrowserLease($descriptor, $root);
    $refuses(static fn () => $invalidLedger->mergeOwnedRows(['attempts' => [], 'codes' => [''], 'windows' => []]), 'browser_owned_ledger_refused');
    $invalidLedger->close();
} finally {
    foreach ($leases as $lease) {
        $lease->close();
    }
    // Explicit files and empty directories only, inside this newly created root.
    if (realpath($root) !== $root || dirname($root) !== $parent || ! str_starts_with(basename($root), 'emailing-browser-lease-')) {
        throw new RuntimeException('Lease test cleanup root refused.');
    }
    foreach (['http-enabled', 'browser-http.lock', 'google-owned-rows.lock', 'google-owned-rows.json'] as $name) {
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        if (file_exists($path)) {
            if (is_link($path) || ! is_file($path) || (stat($path)['nlink'] ?? 0) !== 1) {
                throw new RuntimeException('Lease test cleanup file refused.');
            }
            unlink($path);
        }
    }
    rmdir($directory);
    rmdir(dirname($directory));
    rmdir(dirname($directory, 2));
    rmdir($root);
}
echo json_encode(['checks' => $checks, 'shared_readers_observed' => 2, 'late_request_denied' => true,
    'bootstrap_used' => false, 'sql_used' => false, 'docker_used' => false], JSON_THROW_ON_ERROR)."\n";
