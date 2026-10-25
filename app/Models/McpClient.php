<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpClient extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['redirect_uris' => 'array', 'native_port_relaxation' => 'boolean', 'dynamic' => 'boolean', 'revoked_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
