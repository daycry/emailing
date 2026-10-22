<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    protected $fillable = ['account_id', 'invited_by', 'email', 'role', 'scopes', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function pending(): bool
    {
        return ! $this->accepted_at && ! $this->revoked_at && $this->expires_at->isFuture();
    }
}
