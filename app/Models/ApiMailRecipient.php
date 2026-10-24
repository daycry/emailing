<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ApiMailRecipient extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['contact_ids' => 'json'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Accepted API recipients are immutable.'));
        static::deleting(fn () => throw new \LogicException('Accepted API recipients cannot be individually deleted.'));
    }
}
