<?php

// Called only by controlled CLI fixtures; keeps full visible bytes before work.
return static function (string $directory, string $role): void {
    $command = [PHP_BINARY, '-n', __DIR__.'/runtime-source-inventory.php'];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $pid = proc_get_status($process)['pid'];
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    file_put_contents($directory.'/runtime-'.$role.'.json', $stdout);
    file_put_contents($directory.'/runtime-'.$role.'-stderr.txt', $stderr);
    file_put_contents($directory.'/runtime-'.$role.'-receipt.json', json_encode(['command' => $command, 'pid' => $pid, 'exit' => $exit, 'stdout_sha256' => hash('sha256', $stdout), 'stderr_sha256' => hash('sha256', $stderr)], JSON_THROW_ON_ERROR));
    $document = json_decode($stdout, true);
    if ($exit !== 0 || $stderr !== '' || ! is_array($document) || ($document['kind'] ?? null) !== 'runtime-visible-source-inventory') {
        throw new RuntimeException('Controlled runtime source inventory failed.');
    }
};
