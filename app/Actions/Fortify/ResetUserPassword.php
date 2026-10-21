<?php
namespace App\Actions\Fortify;
use Illuminate\Support\Facades\{Hash,Validator};
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        Validator::make($input,['password'=>['required','confirmed',Password::min(12)]])->validate();
        $user->forceFill(['password'=>Hash::make($input['password'])])->save();
    }
}
