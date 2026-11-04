<?php

/** Test-only barrier. Shared leases preserve concurrent HTTP execution. */
final class GoogleBrowserLease
{
    private mixed $handle = null;

    private string $directory;

    public function __construct(array $descriptor, ?string $root = null)
    {
        $root ??= dirname(__DIR__, 2);
        $run = $descriptor['run'] ?? '';
        $directory = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'mcp-google-phase3'
            .DIRECTORY_SEPARATOR.'browser-'.$run;
        if (($descriptor['profile'] ?? null) !== 'docker' || ! is_string($run)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $run) !== 1
            || ($descriptor['directory'] ?? null) !== $directory || realpath($directory) !== $directory) {
            throw new RuntimeException('browser_lease_directory_refused');
        }
        $this->directory = $directory;
    }

    private function marker(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'http-enabled';
    }

    private function regular(string $path): bool
    {
        return is_file($path) && ! is_link($path) && realpath($path) === $path
            && (stat($path)['nlink'] ?? 0) === 1;
    }

    private function acquire(int $mode, float $timeout, string $name = 'browser-http.lock'): void
    {
        if (! is_finite($timeout) || $timeout <= 0 || $timeout > 30 || $this->handle !== null) {
            throw new RuntimeException('browser_lease_budget_refused');
        }
        if (! in_array($name, ['browser-http.lock', 'google-owned-rows.lock'], true)) {
            throw new RuntimeException('browser_lease_file_refused');
        }
        $path = $this->directory.DIRECTORY_SEPARATOR.$name;
        if ((file_exists($path) || is_link($path)) && ! $this->regular($path)) {
            throw new RuntimeException('browser_lease_file_refused');
        }
        $handle = fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException('browser_lease_unavailable');
        }
        $this->handle = $handle;
        $opened = fstat($handle);
        clearstatcache(true, $path);
        $observed = stat($path);
        if (! $this->regular($path) || ($opened['ino'] ?? null) !== ($observed['ino'] ?? null)
            || ($opened['dev'] ?? null) !== ($observed['dev'] ?? null) || ($opened['nlink'] ?? 0) !== 1) {
            throw new RuntimeException('browser_lease_identity_changed');
        }
        $deadline = hrtime(true) + (int) ($timeout * 1e9);
        do {
            if (flock($handle, $mode | LOCK_NB)) {
                return;
            }
            usleep(1000);
        } while (hrtime(true) < $deadline);
        throw new RuntimeException('browser_lease_deadline');
    }

    public function enable(): void
    {
        $this->acquire(LOCK_EX, 10);
        $path = $this->marker();
        if (file_exists($path) || is_link($path)) {
            throw new RuntimeException('browser_lease_already_enabled');
        }
        $marker = fopen($path, 'x+b');
        if ($marker === false) {
            throw new RuntimeException('browser_lease_marker_unavailable');
        }
        try {
            fflush($marker);
            fsync($marker);
        } finally {
            fclose($marker);
        }
    }

    public function enterHttp(float $timeout = 10): void
    {
        $this->acquire(LOCK_SH, $timeout);
        $path = $this->marker();
        clearstatcache(true, $path);
        if (! $this->regular($path) || filesize($path) !== 0) {
            throw new RuntimeException('browser_http_quarantined');
        }
    }

    public function quiesce(float $timeout = 30): void
    {
        $this->acquire(LOCK_EX, $timeout);
        $path = $this->marker();
        clearstatcache(true, $path);
        if (file_exists($path) || is_link($path)) {
            if (! $this->regular($path) || filesize($path) !== 0 || ! unlink($path)) {
                throw new RuntimeException('browser_lease_marker_refused');
            }
        }
        // Keep LOCK_EX until CLI cleanup exits. Late readers then see the
        // missing marker before autoload, even if FPM scheduled them late.
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    public function mergeOwnedRows(array $changes): void
    {
        $this->acquire(LOCK_EX, 10, 'google-owned-rows.lock');
        $path = $this->directory.DIRECTORY_SEPARATOR.'google-owned-rows.json';
        $owned = ['attempts' => [], 'codes' => [], 'windows' => []];
        if (file_exists($path) || is_link($path)) {
            if (! $this->regular($path) || filesize($path) > 1048576) {
                throw new RuntimeException('browser_owned_ledger_refused');
            }
            $owned = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        }
        foreach ([$owned, $changes] as $value) {
            if (! is_array($value) || count($value) !== 3 || array_diff(['attempts', 'codes', 'windows'], array_keys($value))) {
                throw new RuntimeException('browser_owned_ledger_refused');
            }
            foreach ($value as $rows) {
                if (! is_array($rows) || count($rows) > 10000) {
                    throw new RuntimeException('browser_owned_ledger_refused');
                }
                foreach ($rows as $row) {
                    if ((! is_int($row) || $row < 1) && (! is_string($row) || $row === '' || strlen($row) > 255 || preg_match('/[\x00-\x1f]/', $row))) {
                        throw new RuntimeException('browser_owned_ledger_refused');
                    }
                }
            }
        }
        foreach (['attempts', 'codes', 'windows'] as $key) {
            $owned[$key] = array_values(array_unique([...$owned[$key], ...$changes[$key]], SORT_STRING));
            if (count($owned[$key]) > 10000) {
                throw new RuntimeException('browser_owned_ledger_refused');
            }
        }
        $body = json_encode($owned, JSON_THROW_ON_ERROR);
        if (strlen($body) > 1048576) {
            throw new RuntimeException('browser_owned_ledger_refused');
        }
        $temporary = $this->directory.DIRECTORY_SEPARATOR.'owned-write-'.bin2hex(random_bytes(12));
        $stream = fopen($temporary, 'x+b');
        if ($stream === false) {
            throw new RuntimeException('browser_owned_ledger_unavailable');
        }
        try {
            $offset = 0;
            while ($offset < strlen($body)) {
                $written = fwrite($stream, substr($body, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('browser_owned_ledger_unavailable');
                }
                $offset += $written;
            }
            fflush($stream);
            fsync($stream);
        } finally {
            fclose($stream);
        }
        if (! rename($temporary, $path)) {
            throw new RuntimeException('browser_owned_ledger_unavailable');
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
