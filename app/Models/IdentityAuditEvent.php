<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdentityAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'provider', 'issuer', 'action', 'outcome', 'correlation_id'];
}
