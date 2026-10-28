<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailMessage extends Model
{
    protected $guarded = [];

    protected $hidden = ['artifact_path'];

    protected static function booted(): void
    {
        static::saving(function (self $message): void {
            // FK SET NULL may tombstone the actor during transport. A status-only
            // save must not compare a retained model actor with the updated grant.
            $provenanceChanged = ! $message->exists || $message->isDirty(['origin', 'account_id', 'mcp_grant_id', 'mcp_client_id', 'actor_id']);
            if ($message->origin === 'mcp' && $provenanceChanged) {
                $grant = McpGrant::whereKey($message->mcp_grant_id)->where('account_id', $message->account_id)->where('client_id', $message->mcp_client_id)->first();
                $actor = $message->actor_id === null ? null : (int) $message->actor_id;
                if (! $grant || $actor !== $grant->user_id) {
                    throw new \LogicException('MCP work must retain its accepted grant actor.');
                }
            }
            if ($message->origin === 'api' && $message->actor_id !== null) {
                throw new \LogicException('API work cannot impersonate an interactive actor.');
            }
        });
    }
}
