<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DeliveryQuota
{
    // Caller holds the account mutex. All quota locks follow it, sorted by key.
    // Fixed UTC minute/day windows allow a burst up to the configured minute cap;
    // adjoining windows can admit two such bursts. Ambiguous starts remain charged.
    public function take(int $accountId): ?string
    {
        $pool = (string) config('delivery.relay_pool');
        if (! preg_match('/\A[a-zA-Z0-9_.-]{1,80}\z/D', $pool)) {
            throw new \LogicException('A server-owned relay pool identity is required.');
        }
        $now = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
        $windows = [];
        foreach (['pool:'.$pool => 'pool', 'account:'.$accountId => 'account'] as $scope => $policy) {
            foreach (['minute' => 60, 'day' => 86400] as $window => $seconds) {
                $start = intdiv($now, $seconds) * $seconds;
                $id = $scope.':'.$window.':'.$start;
                $windows[$id] = ['limit' => max(1, min(1000000000, (int) config('delivery.quotas.'.$policy.'.'.$window))), 'ends_at' => gmdate('Y-m-d H:i:s', $start + $seconds)];
            }
        }
        ksort($windows, SORT_STRING);
        $waitUntil = null;
        foreach ($windows as $id => $window) {
            DB::table('delivery_quota_windows')->insertOrIgnore(['id' => $id, 'used' => 0, 'ends_at' => $window['ends_at']]);
            $row = DB::table('delivery_quota_windows')->where('id', $id)->lockForUpdate()->first();
            if ($row->used >= $window['limit']) {
                $waitUntil = max($waitUntil ?? $row->ends_at, $row->ends_at);
            }
        }
        $lockedEpoch = (int) DB::selectOne('SELECT UNIX_TIMESTAMP() AS epoch')->epoch;
        if (intdiv($lockedEpoch, 60) !== intdiv($now, 60)) {
            // Waiting for another account's pool mutex can cross a UTC window.
            // Do not charge stale buckets or acquire another set while holding these.
            return gmdate('Y-m-d H:i:s', $lockedEpoch + 1);
        }
        if ($waitUntil !== null) {
            return $waitUntil;
        }
        foreach (array_keys($windows) as $id) {
            DB::table('delivery_quota_windows')->where('id', $id)->increment('used');
        }

        return null;
    }
}
