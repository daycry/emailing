<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $guarded = [];

    protected $hidden = ['artifact_path'];

    protected static function booted(): void
    {
        static::saving(function (self $message): void {
            if ($message->origin === 'api' && $message->actor_id !== null) {
                throw new \LogicException('API work cannot impersonate an interactive actor.');
            }
        });
    }
}
