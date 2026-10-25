<?php

namespace App\Services\Mcp;

use App\Services\Mcp\OAuth\OAuthError;
use Illuminate\Support\Facades\DB;

final class Admission
{
    /** No raw peer address is persisted, and untrusted forwarded headers are ignored. */
    public function peer(string $ip): string
    {
        $normalized = inet_pton($ip);

        return hash_hmac('sha256', 'mcp:peer:'.gmdate('Y-m-d').':'.($normalized === false ? 'unknown' : bin2hex($normalized)), (string) config('app.key'));
    }

    /** All dimensions commit together, including rejected authentication/validation attempts. */
    public function take(array $dimensions): void
    {
        $retry = DB::transaction(function () use ($dimensions): ?int {
            $now = (int) DB::selectOne('SELECT UNIX_TIMESTAMP(UTC_TIMESTAMP()) AS window_now')->window_now;
            $rows = [];
            foreach ($dimensions as [$purpose, $subject, $maximum, $seconds]) {
                $end = (intdiv($now, $seconds) + 1) * $seconds;
                $key = hash('sha256', $purpose.':'.$subject.':'.$end);
                $rows[$key] = [$maximum, $end];
            }
            ksort($rows);
            foreach ($rows as $key => [$maximum, $end]) {
                DB::table('mcp_admission_windows')->insertOrIgnore(['key' => $key, 'hits' => 0, 'expires_at' => gmdate('Y-m-d H:i:s', $end)]);
            }
            $retry = null;
            foreach ($rows as $key => [$maximum, $end]) {
                $row = DB::table('mcp_admission_windows')->where('key', $key)->lockForUpdate()->first();
                if ($row->hits >= $maximum) {
                    $retry = max($retry ?? 1, $end - $now);
                }
            }
            if ($retry === null) {
                foreach (array_keys($rows) as $key) {
                    DB::table('mcp_admission_windows')->where('key', $key)->increment('hits');
                }
            }

            return $retry;
        }, 3);
        if ($retry !== null) {
            throw new OAuthError('temporarily_unavailable', 429, max(1, $retry));
        }
    }
}
