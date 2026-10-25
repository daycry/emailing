<?php

// Test-only HTTP-boundary provider adapter. Production routing never loads this file.
use App\Services\Auth\GoogleIdentityResolver;
use App\Services\Auth\GoogleLoginFlow;
use App\Services\Auth\GoogleTokenVerifier;
use App\Support\TestingDatabaseGuard;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

if (PHP_SAPI !== 'cli-server' || getenv('GOOGLE_BROWSER_TEST') !== '1' || getenv('APP_ENV') !== 'testing') {
    http_response_code(404);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/../../public'.$path)) {
    return false;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
TestingDatabaseGuard::assertSafe();
// Enable real CSRF middleware while retaining the SQL-guarded testing configuration.
$app->instance('env', 'local');
$directory = getenv('GOOGLE_BROWSER_DIRECTORY');
foreach (['sessions', 'cache'] as $child) {
    if (! is_dir($directory.'/'.$child)) {
        mkdir($directory.'/'.$child, 0700, true);
    }
}
config(['app.url' => 'http://127.0.0.1:18967', 'session.driver' => 'file', 'session.files' => $directory.'/sessions', 'session.cookie' => 'google-browser-session',
    'cache.default' => 'file', 'cache.stores.file.path' => $directory.'/cache', 'cache.stores.file.lock_path' => $directory.'/cache', 'cache.prefix' => 'google-browser-'.getenv('GOOGLE_BROWSER_RUN'),
    'services.google.client_id' => 'synthetic-browser-client', 'services.google.client_secret' => 'synthetic-browser-secret',
    'services.google.redirect' => config('app.url').'/auth/google/callback', 'mail.default' => 'log']);
$mode = $_COOKIE['google_fixture_mode'] ?? '';
config(['emailing.registration_enabled' => $mode !== 'closed', 'emailing.google_workspace_domains' => $mode === 'workspace' ? ['workspace.example.test'] : []]);
if ($mode === 'disabled') {
    config(['services.google.client_id' => null]);
}
$key = openssl_pkey_get_private(base64_decode(getenv('GOOGLE_BROWSER_KEY'), true));
$details = openssl_pkey_get_details($key);
$jwks = ['keys' => [['kty' => 'RSA', 'alg' => 'RS256', 'kid' => 'synthetic-browser-key', 'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])]]];
Http::preventStrayRequests();
Http::fake(function ($outbound) use ($key, $jwks) {
    if ($outbound->url() === GoogleTokenVerifier::KEYS_URL) {
        return Http::response($jwks);
    }
    if ($outbound->url() !== 'https://www.googleapis.com/oauth2/v4/token') {
        throw new RuntimeException('Unexpected outbound browser request.');
    }
    $claims = json_decode(base64_decode(strtr((string) $outbound['code'], '-_', '+/'), true) ?: '', true);
    if (! is_array($claims) || ($claims['scenario'] ?? '') === 'bad-code') {
        return Http::response(['error' => 'invalid_grant'], 400);
    }
    $payload = array_replace(['iss' => GoogleIdentityResolver::ISSUER, 'aud' => 'synthetic-browser-client', 'iat' => time(), 'exp' => time() + 300,
        'email_verified' => true, 'hd' => 'workspace.example.test', 'name' => 'Browser Google Persona'], $claims);

    return Http::response(['id_token' => JWT::encode($payload, $key, 'RS256', 'synthetic-browser-key'), 'access_token' => 'unused-synthetic-access']);
});
Route::middleware('web')->get('/__google_fixture/mcp-return', function (Request $request, GoogleLoginFlow $flow) {
    $flow->rememberMcpReturn($request, getenv('GOOGLE_BROWSER_PENDING'));

    return redirect(config('app.url').'/login');
});
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
