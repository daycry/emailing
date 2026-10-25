<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpGrant extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'account_id' => 'integer', 'user_id' => 'integer', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $grant): void {
            foreach (['account_id', 'user_id', 'client_id', 'issuer', 'resource', 'scopes', 'expires_at'] as $field) {
                if ($grant->isDirty($field)) {
                    throw new \LogicException('A delegated grant is immutable; issue another grant.');
                }
            }
        });
    }
}
