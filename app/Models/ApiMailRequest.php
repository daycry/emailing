<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApiMailRequest extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $hidden = ['idempotency_hash', 'fingerprint', 'source_hash', 'sender_snapshot'];

    protected function casts(): array
    {
        return ['receipt' => 'json', 'sender_snapshot' => 'json', 'accepted_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Accepted API sources are immutable.'));
        static::deleting(fn () => throw new \LogicException('Permanent API receipts cannot be individually deleted.'));
    }
}
