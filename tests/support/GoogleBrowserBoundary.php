<?php

/** Test-only boundary; no autoload, configuration, database or filesystem reads. */
final class GoogleBrowserBoundary
{
    public static function dockerManifest(array $descriptor, mixed $fixture): void
    {
        if (($descriptor['profile'] ?? null) !== 'docker' || ! is_array($fixture)
            || ($fixture['run'] ?? null) !== ($descriptor['run'] ?? null)
            || ($fixture['directory'] ?? null) !== ($descriptor['directory'] ?? null)) {
            throw new RuntimeException('browser_private_manifest_owner_refused');
        }
    }

    public static function http(array $environment, array $runtime): array
    {
        return self::describe($environment, $runtime, false);
    }

    public static function cli(array $environment, array $runtime, string $action): array
    {
        if (! in_array($action, ['setup', 'cleanup', 'expire-mcp', 'read-fixture'], true)
            || ($runtime['sapi'] ?? '') !== 'cli') {
            throw new RuntimeException('browser_cli_descriptor_refused');
        }
        // Existing native teardown obtains its owner from its already guarded
        // manifest. Preserve that interface; Docker always requires an explicit UUID.
        if ($action === 'cleanup' && ($environment['GOOGLE_BROWSER_PROFILE'] ?? 'native') === 'native'
            && ! isset($environment['GOOGLE_BROWSER_RUN'])
            && ($environment['GOOGLE_BROWSER_TEST'] ?? '') === '1' && ($environment['APP_ENV'] ?? '') === 'testing') {
            return ['profile' => 'native', 'run' => null, 'directory' => null];
        }
        if (in_array($action, ['expire-mcp', 'cleanup'], true)
            && ($environment['GOOGLE_BROWSER_PROFILE'] ?? 'native') === 'native'
            && ! isset($environment['GOOGLE_BROWSER_DIRECTORY']) && is_string($runtime['root'] ?? null)) {
            $environment['GOOGLE_BROWSER_DIRECTORY'] = $runtime['root'].DIRECTORY_SEPARATOR.'storage'
                .DIRECTORY_SEPARATOR.'mcp-google-phase3'.DIRECTORY_SEPARATOR.'browser-'
                .($environment['GOOGLE_BROWSER_RUN'] ?? '');
        }

        return self::describe($environment, $runtime, true);
    }

    private static function describe(array $environment, array $runtime, bool $cli): array
    {
        $profile = $environment['GOOGLE_BROWSER_PROFILE'] ?? 'native';
        $run = $environment['GOOGLE_BROWSER_RUN'] ?? '';
        if (! in_array($profile, ['native', 'docker'], true)
            || ($environment['GOOGLE_BROWSER_TEST'] ?? '') !== '1'
            || ($environment['APP_ENV'] ?? '') !== 'testing'
            || ! is_string($run)
            || preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $run) !== 1) {
            throw new RuntimeException('browser_http_descriptor_refused');
        }
        $root = $runtime['root'] ?? '';
        if (! is_string($root) || $root === '' || str_contains($root, "\0")) {
            throw new RuntimeException('browser_http_root_refused');
        }
        if ($profile === 'native') {
            if (($runtime['sapi'] ?? '') !== ($cli ? 'cli' : 'cli-server')) {
                throw new RuntimeException('browser_http_runtime_refused');
            }
        } elseif (($runtime['sapi'] ?? '') !== ($cli ? 'cli' : 'fpm-fcgi')
            || ($runtime['os'] ?? '') !== 'Linux' || ($runtime['docker'] ?? false) !== true
            || ($runtime['uid'] ?? null) !== 33 || $root !== '/var/www/emailing'
            || ($environment['DB_CONNECTION'] ?? '') !== 'mysql'
            || ($environment['DB_HOST'] ?? '') !== 'mysql'
            || ($environment['DB_DATABASE'] ?? '') !== 'emailing_testing'
            || ($environment['DB_USERNAME'] ?? '') !== 'emailing_testing'
            || ($environment['DB_URL'] ?? null) !== ''
            || ($environment['MAIL_MAILER'] ?? '') !== 'log'
            || ($environment['EMAIL_EXTERNAL_ENABLED'] ?? '') !== 'false') {
            throw new RuntimeException('browser_http_runtime_refused');
        }
        $directory = $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'mcp-google-phase3'
            .DIRECTORY_SEPARATOR.'browser-'.$run;
        // The Linux fixture path remains identical to its existing SQL helper.
        if (($environment['GOOGLE_BROWSER_DIRECTORY'] ?? '') !== $directory) {
            throw new RuntimeException('browser_http_directory_refused');
        }

        return ['profile' => $profile, 'run' => $run, 'directory' => $directory];
    }
}
