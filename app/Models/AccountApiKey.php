<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

final class AccountApiKey extends PersonalAccessToken
{
    protected $table = 'account_api_keys';

    protected $guarded = [];

    protected $fillable = [];

    protected $hidden = ['token'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['abilities' => 'json', 'account_id' => 'integer', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime', 'last_used_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Account keys are revoked, not individually deleted.'));
    }
}
