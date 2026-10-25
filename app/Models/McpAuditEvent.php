<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class McpAuditEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'account_id', 'client_id', 'grant_id', 'operation', 'outcome', 'correlation_id'];
}
