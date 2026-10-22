<?php

use App\Models\Account;
use App\Models\User;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 5).'/vendor/autoload.php';
$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
$email = $argv[1] ?? '';
$name = $argv[2] ?? '';
if (! preg_match('/^qa-[a-f0-9-]{36}-off@example\.test$/D', $email) || ! str_starts_with($name, 'QA Disabled diagnostic ')) {
    throw new RuntimeException('Diagnostic UUID required.');
}
if (($argv[3] ?? '') === 'cleanup') {
    DB::transaction(function () use ($email, $name) {
        $user = User::where('email', $email)->first();
        if ($user) {
            Account::where('name', $name)->where('owner_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            $user->delete();
        }
    });
}
echo json_encode([
    'users' => User::where('email', $email)->count(),
    'accounts' => Account::where('name', $name)->count(),
    'memberships' => DB::table('memberships')->join('users', 'users.id', '=', 'memberships.user_id')->where('users.email', $email)->count(),
]);
