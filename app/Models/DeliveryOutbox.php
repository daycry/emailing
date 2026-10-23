<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOutbox extends Model
{
    protected $table = 'delivery_outbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['generation' => 'integer', 'publish_attempts' => 'integer', 'available_at' => 'immutable_datetime', 'lease_expires_at' => 'immutable_datetime', 'published_at' => 'immutable_datetime'];
    }

    public function payload(): array
    {
        return ['v' => 1, 'event_id' => $this->id, 'type' => $this->type, 'account_id' => $this->account_id, 'delivery_id' => $this->delivery_id, 'generation' => $this->generation];
    }
}
