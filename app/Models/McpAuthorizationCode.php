<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpAuthorizationCode extends Model
{
    protected $guarded = [];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'verifier_attempts' => 'integer'];
    }
}
