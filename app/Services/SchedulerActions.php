<?php

namespace App\Services;

use App\Models\Campaign;
use App\Support\SchedulerRunResult;
use App\Support\SchedulerTriggerInput;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class SchedulerActions
{
    public function __construct(
        private DeliveryMaterializer $materializer,
        private OutboxDispatcher $dispatcher,
    ) {}

    public function run(SchedulerTriggerInput $input): SchedulerRunResult
    {
        $started = hrtime(true);
        if ($input->action === 'dispatch') {
            $limit = max(1, min(100, (int) config('cronicle.dispatch_claim_limit', 20)));
            $budget = max(1, min(60000, (int) config('cronicle.dispatch_budget_ms', 10000)));
            $claims = 0;
            $reason = 'claim_limit';
            while ($claims < $limit) {
                if ($this->elapsed($started) >= $budget) {
                    $reason = 'budget';
                    break;
                }
                if (! $this->dispatcher->dispatch()) {
                    $reason = 'empty';
                    break;
                }
                $claims++;
            }

            return new SchedulerRunResult('dispatch', outboxClaims: $claims, stopReason: $reason, elapsedMs: $this->elapsed($started));
        }

        // The indexed checkpoint excludes completed snapshots before selection;
        // an idle tick never rechecks their historical recipients.
        // The domain rechecks due state and creates delivery/outbox pairs under
        // its account/campaign locks. Repeated requests keep the same identity.
        $campaign = Campaign::query()
            ->where('materialization_pending', true)
            ->whereIn('status', ['authorized', 'scheduled'])
            ->whereNotNull('authorized_at')
            ->whereNotNull('reviewed_revision_id')
            ->where('effective_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))
            ->whereExists(function (Builder $revisions): void {
                $revisions->selectRaw('1')->from('campaign_revisions as revision')
                    ->whereColumn('revision.id', 'campaigns.reviewed_revision_id')
                    ->whereColumn('revision.account_id', 'campaigns.account_id')
                    ->whereColumn('revision.campaign_id', 'campaigns.id');
            })
            ->orderBy('effective_at')->orderBy('id')->first();

        $created = $campaign === null ? 0 : $this->materializer->materialize($campaign->account_id, $campaign->id);

        return new SchedulerRunResult('materialize', deliveriesCreated: $created, stopReason: $campaign === null ? 'empty' : 'campaign_limit', elapsedMs: $this->elapsed($started));
    }

    private function elapsed(int $started): int
    {
        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
