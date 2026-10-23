<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignRevision extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['sender_snapshot' => 'array', 'created_at' => 'immutable_datetime'];
    }
}
