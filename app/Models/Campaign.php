<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $guarded = [];

    public const LABELS = ['draft' => 'Borrador', 'reviewed' => 'Revisada', 'authorized' => 'Autorizada', 'scheduled' => 'Programada', 'paused' => 'Pausada'];

    protected function casts(): array
    {
        return ['list_ids' => 'array', 'contact_ids' => 'array', 'version' => 'integer', 'effective_at' => 'immutable_datetime', 'authorized_at' => 'immutable_datetime'];
    }
}
