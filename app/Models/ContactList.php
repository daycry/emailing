<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ContactList extends Model
{
    protected $fillable = ['account_id', 'name', 'description'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_list_memberships')->withPivot('account_id');
    }
}
