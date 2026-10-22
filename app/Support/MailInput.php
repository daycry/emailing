<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

class MailInput
{
    public static function emailRules(bool $required = true): array
    {
        return ['bail', $required ? 'required' : 'nullable', 'string', 'max:254', 'not_regex:/[\r\n]/', 'email:filter_unicode'];
    }

    public static function message(array $input): array
    {
        $data = Validator::make($input, [
            'recipient' => self::emailRules(),
            'subject' => ['bail', 'required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'text' => ['bail', 'required', 'string', 'max:200000'],
            'html' => ['bail', 'nullable', 'string', 'max:200000'],
        ])->validate();
        $data['recipient'] = mb_strtolower(trim($data['recipient']));

        return $data;
    }
}
