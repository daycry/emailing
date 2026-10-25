<?php

namespace App\Actions\Fortify;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    /** Called only with an identity verified in the server-side Google flow. */
    public function createGoogle(array $input, array $identity): User
    {
        abort_unless(($identity['email_authoritative'] ?? false) === true, 403);
        Validator::make($input, ['password' => ['nullable', 'string', 'confirmed', Password::min(12)]])->validate();
        $localPassword = is_string($input['password'] ?? null) && $input['password'] !== '';
        if (! $localPassword) {
            $input['password'] = Str::random(64);
            $input['password_confirmation'] = $input['password'];
        }
        $input['email'] = $identity['email'];

        return DB::transaction(function () use ($input, $localPassword) {
            $user = $this->create($input);
            $user->forceFill(['local_password_enabled' => $localPassword, 'email_verified_at' => now()])->save();

            return $user;
        });
    }

    public function create(array $input): User
    {
        abort_unless(config('emailing.registration_enabled'), 403);
        Validator::make($input, ['email' => ['required', 'string']])->validate();
        $input['email'] = mb_strtolower(trim($input['email']));
        Validator::make($input, [
            'name' => ['required', 'string', 'max:100'], 'account_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users'], 'password' => ['required', 'confirmed', Password::min(12)],
        ])->validate();

        return DB::transaction(function () use ($input) {
            $user = User::create(['name' => $input['name'], 'email' => $input['email'], 'password' => Hash::make($input['password'])]);
            $account = Account::create(['name' => $input['account_name'], 'owner_id' => $user->id]);
            Membership::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'admin', 'scopes' => []]);
            AuditEvent::create(['account_id' => $account->id, 'actor_id' => $user->id, 'action' => 'account.created']);

            return $user;
        });
    }
}
