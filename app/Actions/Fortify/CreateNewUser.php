<?php
namespace App\Actions\Fortify;
use App\Models\{Account,AuditEvent,Membership,User};
use Illuminate\Support\Facades\{DB,Hash,Validator};
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        abort_unless(config('emailing.registration_enabled'), 403);
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        Validator::make($input, [
            'name'=>['required','string','max:100'], 'account_name'=>['required','string','max:100'],
            'email'=>['required','email','max:254','unique:users'], 'password'=>['required','confirmed',Password::min(12)],
        ])->validate();
        return DB::transaction(function () use ($input) {
            $user = User::create(['name'=>$input['name'],'email'=>$input['email'],'password'=>Hash::make($input['password'])]);
            $account = Account::create(['name'=>$input['account_name'],'owner_id'=>$user->id]);
            Membership::create(['account_id'=>$account->id,'user_id'=>$user->id,'role'=>'admin','scopes'=>[]]);
            AuditEvent::create(['account_id'=>$account->id,'actor_id'=>$user->id,'action'=>'account.created']);
            return $user;
        });
    }
}
