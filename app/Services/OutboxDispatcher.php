<?php

namespace App\Services;

use App\Models\DeliveryOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboxDispatcher
{
    public function __construct(private ConfirmedDeliveryPublisher $publisher) {}

    public function claim(?string $eventId = null): ?DeliveryOutbox
    {
        if (DBTransactionGuard::active()) {
            throw new \LogicException('Dispatch requires an independent SQL transaction.');
        }

        return DB::transaction(function () use ($eventId) {
            // The generated date orders by actual eligibility, including lease
            // recovery, and excludes completed history from the index range.
            $event = DeliveryOutbox::when($eventId, fn ($q) => $q->whereKey($eventId))->where('eligible_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->orderBy('eligible_at')->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
            if (! $event) {
                return null;
            }
            $seconds = max(15, min(300, (int) config('delivery.lease_seconds')));
            DeliveryOutbox::whereKey($event->id)->update(['status' => 'publishing', 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '.$seconds.' SECOND)'), 'publish_attempts' => $event->publish_attempts + 1]);

            return $event->fresh();
        }, 3);
    }

    public function complete(DeliveryOutbox $claim, PublicationResult $result): bool
    {
        $retry = max(1, min(3600, (int) config('delivery.retry_seconds')));
        $changes = ['status' => $result->confirmed ? 'published' : 'pending', 'lease_token' => null, 'lease_expires_at' => null, 'last_error' => $result->error, 'updated_at' => DB::raw('UTC_TIMESTAMP(6)')];
        if ($result->confirmed) {
            $changes['published_at'] = DB::raw('UTC_TIMESTAMP(6)');
        } else {
            $changes['available_at'] = DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '.$retry.' SECOND)');
        }

        return DeliveryOutbox::whereKey($claim->id)->where('account_id', $claim->account_id)->where('delivery_id', $claim->delivery_id)->where('generation', $claim->generation)->where('status', 'publishing')->where('lease_token', $claim->lease_token)->where('lease_expires_at', '>', DB::raw('UTC_TIMESTAMP(6)'))->update($changes) === 1;
    }

    public function dispatch(?string $eventId = null): bool
    {
        $claim = $this->claim($eventId);
        if (! $claim) {
            return false;
        }
        $result = $this->publisher->publish($claim);
        $this->complete($claim, $result);

        return true;
    }
}
