<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $guarded = [];

    protected $hidden = ['smtp_username', 'smtp_password'];

    protected function casts(): array
    {
        return ['smtp_username' => 'encrypted', 'smtp_password' => 'encrypted'];
    }
}
