<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Delivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryMaterializer
{
    private const BATCH_SIZE = 500;

    public function materialize(int $accountId, int $campaignId): int
    {
        return DB::transaction(function () use ($accountId, $campaignId) {
            Account::whereKey($accountId)->lockForUpdate()->firstOrFail();
            $campaign = Campaign::where('account_id', $accountId)->whereKey($campaignId)->lockForUpdate()->firstOrFail();
            $due = Campaign::whereKey($campaign->id)->whereIn('status', ['authorized', 'scheduled'])->whereNotNull('authorized_at')->whereNotNull('reviewed_revision_id')->where('effective_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists();
            if (! $due) {
                return 0;
            }
            $revision = CampaignRevision::where('account_id', $accountId)->where('campaign_id', $campaignId)->findOrFail($campaign->reviewed_revision_id);
            $existing = Delivery::where('account_id', $accountId)->where('campaign_revision_id', $revision->id)->pluck('campaign_recipient_id')->flip();
            $dbNow = DB::selectOne('SELECT UTC_TIMESTAMP(6) AS db_now')->db_now;
            $created = 0;
            $deliveries = [];
            $events = [];
            foreach (CampaignRecipient::where('account_id', $accountId)->where('campaign_revision_id', $revision->id)->orderBy('id')->cursor() as $recipient) {
                if ($existing->has($recipient->id)) {
                    continue;
                }
                $deliveryId = (string) Str::uuid();
                $deliveries[] = ['id' => $deliveryId, 'account_id' => $accountId, 'campaign_id' => $campaignId, 'campaign_revision_id' => $revision->id, 'campaign_recipient_id' => $recipient->id, 'source_hash' => $revision->source_hash, 'status' => 'pending', 'generation' => 1, 'created_at' => $dbNow, 'updated_at' => $dbNow];
                $events[] = ['id' => (string) Str::uuid(), 'account_id' => $accountId, 'delivery_id' => $deliveryId, 'generation' => 1, 'type' => 'delivery.ready', 'status' => 'pending', 'available_at' => $dbNow, 'created_at' => $dbNow, 'updated_at' => $dbNow];
                $created++;
                if (count($deliveries) === self::BATCH_SIZE) {
                    $this->insertBatch($deliveries, $events);
                    $deliveries = [];
                    $events = [];
                }
            }
            if ($deliveries !== []) {
                $this->insertBatch($deliveries, $events);
            }

            return $created;
        }, 3);
    }

    private function insertBatch(array $deliveries, array $events): void
    {
        DB::table('deliveries')->insert($deliveries);
        DB::table('delivery_outbox')->insert($events);
    }
}
