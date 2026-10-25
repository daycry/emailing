<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpAuthorizationRequest extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['browser_hash', 'state'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'state' => 'encrypted', 'expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime'];
    }
}
