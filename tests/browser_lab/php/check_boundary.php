<?php

require __DIR__.'/../../support/GoogleBrowserBoundary.php';

$checks = 0;
$ok = static function (bool $condition) use (&$checks): void {
    $checks++;
    if (! $condition) {
        throw new RuntimeException('Pure browser boundary assertion failed.');
    }
};
$refuses = static function (callable $operation) use ($ok): void {
    try {
        $operation();
    } catch (RuntimeException) {
        $ok(true);

        return;
    }
    $ok(false);
};
$run = 'b5dc463d-f182-4d0a-896f-13337b12ea66';
$directory = static fn (string $root): string => $root.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR
    .'mcp-google-phase3'.DIRECTORY_SEPARATOR.'browser-'.$run;
$native = ['APP_ENV' => 'testing', 'GOOGLE_BROWSER_TEST' => '1', 'GOOGLE_BROWSER_RUN' => $run,
    'GOOGLE_BROWSER_DIRECTORY' => $directory('/synthetic/browser')];
$nativeRuntime = ['root' => '/synthetic/browser', 'sapi' => 'cli-server', 'os' => 'Windows', 'docker' => false, 'uid' => null];
$docker = [...$native, 'GOOGLE_BROWSER_PROFILE' => 'docker', 'GOOGLE_BROWSER_DIRECTORY' => $directory('/var/www/emailing'),
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => 'mysql', 'DB_DATABASE' => 'emailing_testing', 'DB_USERNAME' => 'emailing_testing',
    'DB_URL' => '', 'MAIL_MAILER' => 'log', 'EMAIL_EXTERNAL_ENABLED' => 'false'];
$dockerRuntime = ['root' => '/var/www/emailing', 'sapi' => 'fpm-fcgi', 'os' => 'Linux', 'docker' => true, 'uid' => 33];

$ok(GoogleBrowserBoundary::http($native, $nativeRuntime)['profile'] === 'native');
$ok(GoogleBrowserBoundary::http($docker, $dockerRuntime)['profile'] === 'docker');
$descriptor = GoogleBrowserBoundary::http($docker, $dockerRuntime);
GoogleBrowserBoundary::dockerManifest($descriptor, ['run' => $run, 'directory' => $descriptor['directory']]);
$ok(true);
foreach ([['run' => 'other', 'directory' => $descriptor['directory']], ['run' => $run, 'directory' => '/foreign'], null] as $fixture) {
    $refuses(static fn () => GoogleBrowserBoundary::dockerManifest($descriptor, $fixture));
}
$ok(GoogleBrowserBoundary::cli($docker, [...$dockerRuntime, 'sapi' => 'cli'], 'setup')['run'] === $run);
$withoutDirectory = $native;
unset($withoutDirectory['GOOGLE_BROWSER_DIRECTORY']);
$ok(GoogleBrowserBoundary::cli($withoutDirectory, [...$nativeRuntime, 'sapi' => 'cli'], 'expire-mcp')['directory'] === $directory('/synthetic/browser'));
$legacy = ['APP_ENV' => 'testing', 'GOOGLE_BROWSER_TEST' => '1'];
$ok(GoogleBrowserBoundary::cli($legacy, [...$nativeRuntime, 'sapi' => 'cli'], 'cleanup')['run'] === null);

foreach (['APP_ENV' => 'production', 'GOOGLE_BROWSER_TEST' => '0', 'GOOGLE_BROWSER_PROFILE' => 'other',
    'GOOGLE_BROWSER_RUN' => 'not-a-uuid', 'GOOGLE_BROWSER_DIRECTORY' => '/foreign', 'DB_CONNECTION' => 'sqlite',
    'DB_HOST' => 'localhost', 'DB_DATABASE' => 'emailing', 'DB_USERNAME' => 'root', 'DB_URL' => 'not-empty',
    'MAIL_MAILER' => 'smtp', 'EMAIL_EXTERNAL_ENABLED' => 'true'] as $key => $value) {
    $refuses(static fn () => GoogleBrowserBoundary::http([...$docker, $key => $value], $dockerRuntime));
}
foreach ([strtoupper($run), $run."\n", $run."\r\n"] as $invalidRun) {
    $refuses(static fn () => GoogleBrowserBoundary::http([...$docker, 'GOOGLE_BROWSER_RUN' => $invalidRun], $dockerRuntime));
}
foreach (['sapi' => 'cli-server', 'os' => 'Windows', 'docker' => false, 'uid' => 0, 'root' => '/foreign'] as $key => $value) {
    $refuses(static fn () => GoogleBrowserBoundary::http($docker, [...$dockerRuntime, $key => $value]));
}
$refuses(static fn () => GoogleBrowserBoundary::http($docker, [...$dockerRuntime, 'uid' => '33']));
$refuses(static fn () => GoogleBrowserBoundary::http($docker, [...$dockerRuntime, 'sapi' => 'cli']));
$refuses(static fn () => GoogleBrowserBoundary::http($native, [...$nativeRuntime, 'sapi' => 'fpm-fcgi']));
$refuses(static fn () => GoogleBrowserBoundary::http([...$native, 'GOOGLE_BROWSER_PROFILE' => ''], $nativeRuntime));
$refuses(static fn () => GoogleBrowserBoundary::http([...$native, 'GOOGLE_BROWSER_RUN' => null], $nativeRuntime));
$refuses(static fn () => GoogleBrowserBoundary::http($withoutDirectory, $nativeRuntime));
foreach (['', 'erase'] as $action) {
    $refuses(static fn () => GoogleBrowserBoundary::cli($docker, [...$dockerRuntime, 'sapi' => 'cli'], $action));
}
$refuses(static fn () => GoogleBrowserBoundary::cli($docker, $dockerRuntime, 'read-fixture'));
$refuses(static fn () => GoogleBrowserBoundary::cli($withoutDirectory, [...$nativeRuntime, 'sapi' => 'cli'], 'setup'));
$refuses(static fn () => GoogleBrowserBoundary::cli([...$withoutDirectory, 'GOOGLE_BROWSER_RUN' => 'other'], [...$nativeRuntime, 'sapi' => 'cli'], 'expire-mcp'));
$noDockerDirectory = $docker;
unset($noDockerDirectory['GOOGLE_BROWSER_DIRECTORY']);
$refuses(static fn () => GoogleBrowserBoundary::cli($noDockerDirectory, [...$dockerRuntime, 'sapi' => 'cli'], 'setup'));
$noDockerRun = $docker;
unset($noDockerRun['GOOGLE_BROWSER_RUN']);
$refuses(static fn () => GoogleBrowserBoundary::cli($noDockerRun, [...$dockerRuntime, 'sapi' => 'cli'], 'cleanup'));
$refuses(static fn () => GoogleBrowserBoundary::cli([...$legacy, 'APP_ENV' => 'production'], [...$nativeRuntime, 'sapi' => 'cli'], 'cleanup'));
$refuses(static fn () => GoogleBrowserBoundary::cli([...$legacy, 'GOOGLE_BROWSER_TEST' => '0'], [...$nativeRuntime, 'sapi' => 'cli'], 'cleanup'));

echo json_encode(['checks' => $checks, 'bootstrap_used' => false, 'sql_used' => false, 'docker_used' => false], JSON_THROW_ON_ERROR)."\n";
