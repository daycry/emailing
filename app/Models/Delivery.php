<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['generation' => 'integer', 'attempts' => 'integer', 'mail_message_id' => 'integer', 'available_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'started_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }
}
