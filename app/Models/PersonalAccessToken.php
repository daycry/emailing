<?php

namespace App\Models;

/** MCP credentials deliberately use a different table/model from account API keys. */
class PersonalAccessToken extends \Laravel\Sanctum\PersonalAccessToken
{
    protected $guarded = [];

    protected $fillable = [];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['abilities' => 'json', 'account_id' => 'integer', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime'];
    }
}
