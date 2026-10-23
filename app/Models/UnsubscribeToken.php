<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UnsubscribeToken extends Model
{
    protected $guarded = [];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }
}
