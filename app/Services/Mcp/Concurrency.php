<?php

namespace App\Services\Mcp;

use Closure;
use Illuminate\Support\Facades\DB;

/** Connection-owned MySQL mutex slots: disconnect releases a crashed worker's slot. */
final class Concurrency
{
    private static array $held = [];

    public function run(int $accountId, Closure $operation): mixed
    {
        $limit = max(1, min(32, (int) config('mcp.concurrency_per_account', 8)));
        for ($slot = 0; $slot < $limit; $slot++) {
            $name = 'mcp:'.substr(hash('sha256', DB::getDatabaseName().':'.$accountId.':'.$slot), 0, 56);
            if (isset(self::$held[$name])) {
                continue;
            }
            if ((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name])->acquired !== 1) {
                continue;
            }
            self::$held[$name] = true;
            try {
                return $operation();
            } finally {
                unset(self::$held[$name]);
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]);
            }
        }
        throw new ProtocolError(429, -32000, 'Temporarily unavailable');
    }
}
