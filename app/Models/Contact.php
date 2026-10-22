<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'email', 'name', 'consent_status', 'consent_source', 'consented_at'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime'];
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(ContactList::class, 'contact_list_memberships')->withPivot('account_id');
    }
}
