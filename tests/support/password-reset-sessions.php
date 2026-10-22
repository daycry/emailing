<?php

// Independent real HTTP browsers; run sequentially with suites using emailing_testing.
use App\Models\Account;
use App\Models\User;
use App\Support\TestingDatabaseGuard;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$base = $argv[1] ?? '';
if (! in_array($base, ['http://127.0.0.1:8082', 'http://127.0.0.1:8083'], true)) {
    throw new RuntimeException('Only the isolated native and Docker QA endpoints are allowed.');
}

function browser(string $base): Client
{
    return new Client(['base_uri' => $base, 'cookies' => new CookieJar, 'allow_redirects' => false, 'http_errors' => false, 'timeout' => 30]);
}

function csrf(Client $client, string $path): string
{
    $page = $client->get($path);
    if ($page->getStatusCode() !== 200 || preg_match('/name="_token"\s+value="([^"]+)"/', (string) $page->getBody(), $match) !== 1) {
        throw new RuntimeException('QA form or CSRF token unavailable.');
    }

    return html_entity_decode($match[1]);
}

function expectStatus(int $actual, int $expected, string $label): void
{
    if ($actual !== $expected) {
        throw new RuntimeException($label.': expected '.$expected.', received '.$actual);
    }
}

$email = 'session-'.Str::uuid().'@example.test';
$password = 'Correct-password-123';
$newPassword = 'New-correct-password-456';
try {
    $registration = browser($base);
    $response = $registration->post('/register', ['form_params' => ['_token' => csrf($registration, '/register'), 'name' => 'Prueba', 'account_name' => 'Equipo', 'email' => $email, 'password' => $password, 'password_confirmation' => $password]]);
    expectStatus($response->getStatusCode(), 302, 'Registration');
    $browsers = ['registration before first navigation' => $registration];
    foreach (['login before first navigation', 'login after navigation'] as $label) {
        $client = browser($base);
        $response = $client->post('/login', ['form_params' => ['_token' => csrf($client, '/login'), 'email' => $email, 'password' => $password]]);
        expectStatus($response->getStatusCode(), 302, 'Login');
        if ($label === 'login after navigation') {
            expectStatus($client->get('/dashboard')->getStatusCode(), 200, 'Initial dashboard');
        }
        $browsers[$label] = $client;
    }
    $user = User::where('email', $email)->sole();
    $token = Password::broker()->createToken($user);
    $victim = browser($base);
    $response = $victim->post('/reset-password', ['form_params' => ['_token' => csrf($victim, '/reset-password/'.$token.'?email='.urlencode($email)), 'token' => $token, 'email' => $email, 'password' => $newPassword, 'password_confirmation' => $newPassword]]);
    expectStatus($response->getStatusCode(), 302, 'Password reset');
    $failed = false;
    foreach ($browsers as $label => $client) {
        $response = $client->get('/dashboard');
        $rejected = $response->getStatusCode() === 302 && str_ends_with($response->getHeaderLine('Location'), '/login');
        echo ($rejected ? 'PASS' : 'FAIL').': old '.$label.($rejected ? ' rejected' : ' retained access').PHP_EOL;
        $failed = $failed || ! $rejected;
    }
    $response = $victim->post('/login', ['form_params' => ['_token' => csrf($victim, '/login'), 'email' => $email, 'password' => $newPassword]]);
    expectStatus($response->getStatusCode(), 302, 'Fresh login');
    expectStatus($victim->get('/dashboard')->getStatusCode(), 200, 'New password dashboard');
    echo 'PASS: fresh login with the new password'.PHP_EOL;
    if ($failed) {
        throw new RuntimeException('Pre-reset sessions retained access.');
    }
} finally {
    TestingDatabaseGuard::assertSafe();
    DB::transaction(function () use ($email) {
        $user = User::where('email', $email)->first();
        if ($user) {
            Account::where('owner_id', $user->id)->delete();
            $user->delete();
        }
        DB::table('password_reset_tokens')->where('email', $email)->delete();
    });
}
