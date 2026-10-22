<?php

// Isolated, UUID-owned browser fixtures. Never migrates, truncates or refreshes.
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Services\Permissions;
use App\Support\TestingDatabaseGuard;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require dirname(__DIR__, 5).'/vendor/autoload.php';
$app = require dirname(__DIR__, 5).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
TestingDatabaseGuard::assertSafe();
[$script,$action,$run] = $argv;
if (! preg_match('/^[a-f0-9-]{36}$/', $run)) {
    throw new RuntimeException('UUID required');
}
$file = __DIR__.'/raw/artifacts/'.$run.'.json';
if ($action === 'setup') {
    $s = ['run' => $run, 'password' => bin2hex(random_bytes(16)), 'users' => [], 'accounts' => [], 'members' => []];
    foreach (['owner', 'actor', 'other', 'invitee', 'expired', 'revoked', 'resent', 'readonly', 'developer', 'marketer', 'accountant'] as $key) {
        $u = User::create(['name' => 'QA '.ucfirst($key), 'email' => 'qa-'.$run.'-'.$key.'@example.test', 'password' => Hash::make($s['password'])]);
        $s['users'][$key] = ['id' => $u->id, 'email' => $u->email, 'name' => $u->name];
    }
    foreach (['A' => 'owner', 'B' => 'other', 'C' => 'other'] as $key => $owner) {
        $a = Account::create(['name' => 'QA '.$key.' '.substr($run, 0, 8), 'owner_id' => $s['users'][$owner]['id']]);
        $s['accounts'][$key] = ['id' => $a->id, 'name' => $a->name];
        Membership::create(['account_id' => $a->id, 'user_id' => $a->owner_id, 'role' => 'admin', 'scopes' => []]);
    }
    foreach (['actor' => ['A', 'admin', []], 'readonly' => ['A', 'read-only', []], 'developer' => ['A', 'restricted', Permissions::PRESETS['developer']], 'marketer' => ['A', 'restricted', Permissions::PRESETS['marketer']], 'accountant' => ['A', 'restricted', Permissions::PRESETS['accountant']]] as $key => [$a,$role,$scopes]) {
        $m = Membership::create(['account_id' => $s['accounts'][$a]['id'], 'user_id' => $s['users'][$key]['id'], 'role' => $role, 'scopes' => $scopes]);
        $s['members'][$key] = $m->id;
    }
    Membership::create(['account_id' => $s['accounts']['B']['id'], 'user_id' => $s['users']['actor']['id'], 'role' => 'read-only', 'scopes' => []]);
    $s['members']['owner'] = Membership::where('account_id', $s['accounts']['A']['id'])->where('user_id', $s['users']['owner']['id'])->value('id');
    file_put_contents($file, json_encode($s, JSON_PRETTY_PRINT));
    echo 'fixture created';
    exit;
}
$s = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$ids = array_column($s['accounts'], 'id');
if ($action === 'cleanup') {
    DB::transaction(function () use ($ids, $run) {
        // Includes the one registration made through the UI, identified by this run only.
        $users = User::where('email', 'like', 'qa-'.$run.'-%@example.test')->get();
        Account::whereIn('owner_id', $users->pluck('id'))->delete();
        Account::whereIn('id', $ids)->delete();
        DB::table('password_reset_tokens')->whereIn('email', $users->pluck('email'))->delete();
        DB::table('sessions')->whereIn('user_id', $users->pluck('id'))->delete();
        foreach ($users as $u) {
            $u->delete();
        }
    });
    unlink($file);
    echo 'fixture cleaned';
    exit;
}
if ($action === 'expire') {
    Invitation::whereIn('account_id', $ids)->where('email', $s['users']['expired']['email'])->update(['expires_at' => now()->subMinute()]);
}
if ($action === 'revoke-actor') {
    Membership::where('id', $s['members']['actor'])->whereIn('account_id', $ids)->delete();
}
if ($action === 'reduce-actor') {
    Membership::where('id', $s['members']['actor'])->whereIn('account_id', $ids)->update(['role' => 'read-only', 'scopes' => []]);
}
if ($action === 'status' || in_array($action, ['expire', 'revoke-actor', 'reduce-actor'])) {
    $users = User::where('email', 'like', 'qa-'.$run.'-%@example.test')->get();
    echo json_encode(['users' => $users->map(fn ($u) => ['id' => $u->id, 'email' => $u->email])->all(),
        'accounts' => Account::whereIn('owner_id', $users->pluck('id'))->get(['id', 'name', 'owner_id']),
        'members' => Membership::whereIn('account_id', array_merge($ids, Account::whereIn('owner_id', $users->pluck('id'))->pluck('id')->all()))->get(),
        'invitations' => Invitation::whereIn('account_id', $ids)->get()->map(fn ($i) => array_merge($i->toArray(), ['token_is_hash' => strlen($i->getRawOriginal('token_hash')) === 64])),
        'events' => AuditEvent::whereIn('account_id',$ids)->get(['account_id', 'actor_id', 'action'])]);
    exit;
}
throw new RuntimeException('Unknown fixture action');
