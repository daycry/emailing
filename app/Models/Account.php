<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['name', 'owner_id'];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}
