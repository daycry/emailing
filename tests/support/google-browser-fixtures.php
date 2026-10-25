<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Services\Auth\GoogleIdentityResolver;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
if (getenv('GOOGLE_BROWSER_TEST') !== '1') {
    throw new RuntimeException('Explicit browser fixture owner required.');
}
$runtime = storage_path('mcp-google-phase2/browser-runtime.json');
$fixturePath = storage_path('mcp-google-phase2/browser-fixtures.json');
if (($argv[1] ?? '') === 'cleanup') {
    if (! is_file($fixturePath)) {
        exit(0);
    }
    $fixture = json_decode(file_get_contents($fixturePath), true, flags: JSON_THROW_ON_ERROR);
    DB::transaction(function () use ($fixture) {
        $users = User::whereIn('email', array_values($fixture['emails']))->pluck('id');
        Account::whereIn('owner_id', $users)->delete();
        User::whereIn('id', $users)->delete();
        DB::table('google_login_attempts')->where('id', '>', $fixture['attempt_baseline'])->delete();
        DB::table('google_login_codes')->whereNotIn('code_hash', $fixture['code_baseline'])->delete();
    });
    echo "Owned Google browser SQL fixtures removed.\n";
    exit(0);
}
Artisan::call('migrate', ['--force' => true]);
$run = getenv('GOOGLE_BROWSER_RUN');
if (! is_string($run) || ! preg_match('/\A[a-f0-9-]{36}\z/D', $run)) {
    throw new RuntimeException('Invalid fixture run.');
}
config(['emailing.registration_enabled' => true]);
$emails = [];
foreach (['owner', 'other', 'collision', 'recipient', 'signup'] as $name) {
    $emails[$name] = 'google-browser-'.$name.'-'.$run.'@example.test';
}
$baseline = (int) DB::table('google_login_attempts')->max('id');
$codes = DB::table('google_login_codes')->pluck('code_hash')->all();
// Partial startup cannot leave committed fixtures without their owner manifest.
DB::beginTransaction();
$users = [];
foreach (['owner', 'other', 'collision', 'recipient'] as $name) {
    $users[$name] = app(CreateNewUser::class)->create(['name' => 'Browser '.ucfirst($name), 'account_name' => 'Browser '.$name.' own', 'email' => $emails[$name], 'password' => 'Synthetic-password-1234', 'password_confirmation' => 'Synthetic-password-1234']);
}
$second = Account::create(['name' => 'Browser second own', 'owner_id' => $users['other']->id]);
Membership::create(['account_id' => $second->id, 'user_id' => $users['owner']->id, 'role' => 'read-only', 'scopes' => []]);
$foreign = Account::create(['name' => 'Browser foreign account', 'owner_id' => $users['other']->id]);
foreach (['owner', 'recipient'] as $name) {
    app(GoogleIdentityResolver::class)->link($users[$name], ['iss' => GoogleIdentityResolver::ISSUER, 'sub' => 'browser-'.$name.'-'.$run]);
}
$invitation = str_repeat('Ab9Z', 16);
Invitation::create(['account_id' => $foreign->id, 'invited_by' => $users['other']->id, 'email' => $emails['recipient'], 'role' => 'read-only', 'scopes' => [], 'token_hash' => hash('sha256', $invitation), 'expires_at' => now()->addHour()]);
$fixture = ['run' => $run, 'emails' => $emails, 'invitation' => $invitation, 'foreign_account_id' => $foreign->id,
    'attempt_baseline' => $baseline, 'code_baseline' => $codes];
file_put_contents($fixturePath, json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
DB::commit();
echo "Owned Google browser SQL fixtures ready.\n";
