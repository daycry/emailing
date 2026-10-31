<?php

namespace Tests\Support;

require_once __DIR__.'/mail-provider-tls-fixture.php';

trait MailProviderWireFixtures
{
    private function wireDirectory(): string
    {
        $parent = dirname(__DIR__).'/.runtime';
        if (! is_dir($parent)) {
            mkdir($parent, 0775, true);
        }
        $directory = $parent.'/provider-wire-'.bin2hex(random_bytes(8));
        mkdir($directory, 0775);

        return $directory;
    }

    private function certificates(string $directory): void
    {
        MailProviderTlsFixture::create($directory, fn (bool $valid) => $this->assertTrue($valid));
    }

    private function launch(array $command, string $directory, string $label)
    {
        $process = proc_open($command, [['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            ['file', $directory.'/'.$label.'.stdout', 'w'], ['file', $directory.'/'.$label.'.stderr', 'w']], $pipes,
            dirname(__DIR__, 2), null, ['create_new_console' => false]);
        $this->assertIsResource($process);

        return $process;
    }

    private function waitJson(string $path, $process): array
    {
        $deadline = microtime(true) + 5;
        do {
            if (is_file($path) && is_array($data = json_decode(file_get_contents($path), true))) {
                return $data;
            }
            if (! proc_get_status($process)['running']) {
                break;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        $this->fail('Owned provider fixture did not become ready.');
    }

    private function waitProcess($process): int
    {
        $deadline = microtime(true) + 12;
        do {
            $state = proc_get_status($process);
            if (! $state['running']) {
                $closed = proc_close($process);

                return $state['exitcode'] >= 0 ? $state['exitcode'] : $closed;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        proc_terminate($process);
        proc_close($process);
        $this->fail('Owned provider fixture exceeded its time budget.');
    }

    private function cleanupWireFixture(string $directory, array $processes, bool $successful): void
    {
        foreach ($processes as $process) {
            if (is_resource($process)) {
                if (proc_get_status($process)['running']) {
                    proc_terminate($process);
                }
                proc_close($process);
            }
        }
        $resolved = realpath($directory);
        $this->assertNotFalse($resolved);
        $this->assertStringStartsWith(realpath(dirname(__DIR__).'/.runtime').DIRECTORY_SEPARATOR, $resolved);
        if (! $successful) {
            return; // Retain private diagnostics, after closing every owned process.
        }
        foreach (['openssl.cnf', 'ca.pem', 'certificate.pem', 'key.pem', 'ready.json', 'smtp-facts.json', 'http-facts.json',
            'probe-facts.json', 'server.stdout', 'server.stderr', 'client.stdout', 'client.stderr'] as $name) {
            $file = $directory.'/'.$name;
            $this->assertFalse(is_link($file));
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);
    }
}
