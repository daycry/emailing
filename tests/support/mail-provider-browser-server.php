<?php

use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

ini_set('zend.exception_ignore_args', '1');
if (PHP_SAPI !== 'cli-server' || getenv('PROVIDER_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing'
    || ! hash_equals(getenv('PROVIDER_BROWSER_PROXY_TOKEN') ?: 'missing', $_SERVER['HTTP_X_PROVIDER_FIXTURE_PROXY'] ?? '')) {
    http_response_code(404);
    exit;
}
// This private PHP listener is reached only through our owned HTTPS peer.
$_SERVER['HTTPS'] = 'on';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
TestingDatabaseGuard::assertSafe();
$directory = getenv('PROVIDER_BROWSER_DIRECTORY');
foreach (['sessions', 'cache', 'artifacts', 'logs'] as $child) {
    if (! is_dir($directory.'/'.$child)) {
        mkdir($directory.'/'.$child, 0700, true);
    }
}
config(['app.url' => 'https://127.0.0.1:18867', 'app.debug' => false, 'emailing.external_enabled' => ($_COOKIE['provider_fixture_outcome'] ?? '') !== 'closed',
    'emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => 11025,
    'session.driver' => 'file', 'session.files' => $directory.'/sessions', 'session.cookie' => 'provider-browser', 'session.secure' => true,
    'cache.default' => 'file', 'cache.stores.file.path' => $directory.'/cache', 'cache.stores.file.lock_path' => $directory.'/cache',
    'filesystems.disks.mail-artifacts.root' => $directory.'/artifacts', 'logging.channels.single.path' => $directory.'/logs/laravel.log',
    'delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000, 'mail.default' => 'log']);
// Keep real CSRF/session behavior for browser requests.
$app->instance('env', 'local');
require __DIR__.'/mail-provider-browser-adapters.php';
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
