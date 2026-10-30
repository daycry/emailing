<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Revoke restored MCP authority while retaining accepted mail history. */
final class McpInvalidateRestoredAccess extends Command
{
    protected $signature = 'mcp:invalidate-restored-access {--apply : Invalidate all restored MCP access after draining services}';

    protected $description = 'Preview or invalidate restored MCP clients, grants, tokens, codes and sessions; apply requires maintenance mode.';

    public function handle(): int
    {
        try {
            // Recovery must work while MCP admission and its discovery endpoints remain closed.
            if (! $this->option('apply')) {
                $this->line(json_encode(['mode' => 'dry-run', 'access_invalidated' => false,
                    'affected' => $this->pending()], JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }
            if (! app()->isDownForMaintenance()) {
                $this->error('maintenance_mode_required');

                return self::FAILURE;
            }
            $counts = DB::transaction(function (): array {
                $at = now();
                $counts = [];
                // Retain rows: accepted mail and audit history keep their foreign keys.
                // This namespace has its own token table, distinct from account_api_keys.
                foreach (['clients' => 'mcp_clients', 'grants' => 'mcp_grants',
                    'tokens' => 'personal_access_tokens'] as $kind => $table) {
                    $counts[$kind] = DB::table($table)->whereNull('revoked_at')
                        ->update(['revoked_at' => $at, 'updated_at' => $at]);
                }
                // Codes may be referenced by tokens; consume them rather than deleting them.
                foreach (['codes' => 'mcp_authorization_codes', 'pending_requests' => 'mcp_authorization_requests'] as $kind => $table) {
                    $counts[$kind] = DB::table($table)->whereNull('consumed_at')
                        ->update(['consumed_at' => $at, 'updated_at' => $at]);
                }
                $counts['sessions'] = DB::table('mcp_sessions')->delete();

                return $counts;
            }, 3);
            $this->line(json_encode(['mode' => 'apply', 'access_invalidated' => true,
                'client_registration_required' => true, 'affected' => $counts], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('restore_access_invalidation_failed');

            return self::FAILURE;
        }
    }

    private function pending(): array
    {
        return ['clients' => DB::table('mcp_clients')->whereNull('revoked_at')->count(),
            'grants' => DB::table('mcp_grants')->whereNull('revoked_at')->count(),
            'tokens' => DB::table('personal_access_tokens')->whereNull('revoked_at')->count(),
            'codes' => DB::table('mcp_authorization_codes')->whereNull('consumed_at')->count(),
            'pending_requests' => DB::table('mcp_authorization_requests')->whereNull('consumed_at')->count(),
            'sessions' => DB::table('mcp_sessions')->count()];
    }
}
