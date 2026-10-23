<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Support\DeliveryLeaseLost;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryLifecycle
{
    public const TERMINAL = ['smtp_accepted', 'logged', 'failed', 'ambiguous', 'suppressed', 'blocked'];

    public static function lease(): Expression
    {
        $seconds = max(15, min(300, (int) config('delivery.worker_lease_seconds')));

        return DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '.$seconds.' SECOND)');
    }

    public function claim(array $payload): ?Delivery
    {
        if (DBTransactionGuard::active()) {
            throw new \LogicException('A delivery reservation requires an independent transaction.');
        }

        return DB::transaction(function () use ($payload) {
            // Account is the mutex also used by consent, suppression and campaign changes.
            if (! Account::whereKey($payload['account_id'])->lockForUpdate()->first()) {
                return null;
            }
            $delivery = Delivery::where('account_id', $payload['account_id'])->whereKey($payload['delivery_id'])->lockForUpdate()->first();
            if (! $delivery || $delivery->generation !== $payload['generation'] || ! DeliveryOutbox::whereKey($payload['event_id'])->where('account_id', $delivery->account_id)->where('delivery_id', $delivery->id)->where('generation', $delivery->generation)->where('type', 'delivery.ready')->exists()) {
                return null;
            }
            $this->recoverLocked($delivery);
            if ($delivery->generation !== $payload['generation'] || $delivery->status !== 'pending'
                || ($delivery->available_at !== null && ! Delivery::whereKey($delivery->id)->where('available_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists())) {
                return null;
            }
            $this->write($delivery, ['status' => 'reserved', 'lease_token' => (string) Str::uuid(), 'lease_expires_at' => self::lease()]);

            return $delivery->fresh();
        }, 3);
    }

    public function fenced(Delivery $claim, string $state): ?Delivery
    {
        return Delivery::whereKey($claim->id)->where('account_id', $claim->account_id)->where('generation', $claim->generation)->where('status', $state)->where('lease_token', $claim->lease_token)->where('lease_expires_at', '>', DB::raw('UTC_TIMESTAMP(6)'))->lockForUpdate()->first();
    }

    public function renew(Delivery $claim): bool
    {
        return Delivery::whereKey($claim->id)->where('account_id', $claim->account_id)->where('generation', $claim->generation)->whereIn('status', ['reserved', 'sending'])->where('lease_token', $claim->lease_token)->where('lease_expires_at', '>', DB::raw('UTC_TIMESTAMP(6)'))->update(['lease_expires_at' => self::lease()]) === 1;
    }

    // Runs inside AccountMailTransport's sole start transaction, after the account lock.
    // Returns the live delivery or persists the precise non-start outcome/resumption.
    public function beforeStart(Delivery $claim, ?MailSender $sender, bool $suppressed, string $mode): ?Delivery
    {
        $delivery = $this->fenced($claim, 'reserved');
        if (! $delivery) {
            return null;
        }
        $campaign = Campaign::where('account_id', $delivery->account_id)->whereKey($delivery->campaign_id)->lockForUpdate()->first();
        $revision = CampaignRevision::where('account_id', $delivery->account_id)->where('campaign_id', $delivery->campaign_id)->whereKey($delivery->campaign_revision_id)->first();
        $recipient = CampaignRecipient::where('account_id', $delivery->account_id)->where('campaign_revision_id', $delivery->campaign_revision_id)->whereKey($delivery->campaign_recipient_id)->first();
        $contact = $recipient ? Contact::where('account_id', $delivery->account_id)->whereKey($recipient->contact_id)->lockForUpdate()->first() : null;
        if (! $campaign || ! $revision || ! $recipient || $campaign->reviewed_revision_id !== $revision->id || ! hash_equals($delivery->source_hash, $revision->source_hash) || $campaign->authorized_at === null) {
            $this->terminal($delivery, 'blocked', 'source_not_authorized');

            return null;
        }
        if ($campaign->status === 'paused') {
            $this->defer($delivery, 'campaign_paused', $this->after(max(1, min(3600, (int) config('delivery.pause_poll_seconds')))));

            return null;
        }
        if (! in_array($campaign->status, ['authorized', 'scheduled'], true)) {
            $this->terminal($delivery, 'blocked', 'campaign_not_authorized');

            return null;
        }
        if (! Campaign::whereKey($campaign->id)->where('effective_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists()) {
            $this->defer($delivery, 'campaign_future', $campaign->effective_at->utc()->format('Y-m-d H:i:s.u'));

            return null;
        }
        if (! $contact || $contact->email !== $recipient->email) {
            $this->terminal($delivery, 'suppressed', 'original_contact_changed');

            return null;
        }
        if ($suppressed || $contact->consent_status !== 'granted' || blank($contact->consent_source) || $contact->consented_at === null
            || ! Contact::whereKey($contact->id)->where('consented_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists()) {
            $this->terminal($delivery, 'suppressed', $suppressed ? 'recipient_suppressed' : 'consent_not_current');

            return null;
        }
        $snapshot = $revision->sender_snapshot;
        $current = $sender?->only(['id', 'email', 'name', 'reply_to']) ?? [];
        ksort($snapshot);
        ksort($current);
        if (! $sender || ! $sender->lab_authorized || $sender->id !== $campaign->sender_id || $current !== $snapshot || ! in_array($mode, ['log', 'mailpit'], true)) {
            $this->terminal($delivery, 'blocked', 'sender_or_transport_changed');

            return null;
        }

        return $delivery;
    }

    public function started(Delivery $delivery, MailMessage $message): void
    {
        $changed = Delivery::whereKey($delivery->id)->where('account_id', $delivery->account_id)->where('generation', $delivery->generation)->where('lease_token', $delivery->lease_token)->where('status', 'reserved')->where('lease_expires_at', '>', DB::raw('UTC_TIMESTAMP(6)'))->update(['status' => 'sending', 'attempts' => $delivery->attempts + 1, 'mail_message_id' => $message->id, 'started_at' => DB::raw('UTC_TIMESTAMP(6)'), 'diagnostic' => null, 'lease_expires_at' => self::lease()]);
        if ($changed !== 1) {
            // Roll back quota, capability and message writes from the common start.
            throw new DeliveryLeaseLost;
        }
        $delivery->refresh();
    }

    public function finish(Delivery $claim, MailMessage $message, array $outcome): bool
    {
        return DB::transaction(function () use ($claim, $message, $outcome) {
            Account::whereKey($claim->account_id)->lockForUpdate()->firstOrFail();
            $delivery = $this->fenced($claim, 'sending');
            if (! $delivery || $delivery->mail_message_id !== $message->id) {
                return false;
            }
            $message->update($outcome);
            if (in_array($outcome['status'], ['smtp_accepted', 'logged'], true)) {
                $this->terminal($delivery, $outcome['status']);
            } elseif (in_array($outcome['status'], ['smtp_rejected', 'local_failed'], true)) {
                if ($delivery->attempts >= max(1, min(20, (int) config('delivery.max_attempts')))) {
                    $this->terminal($delivery, 'failed', 'attempts_exhausted');
                    $this->event($delivery, 'delivery.failed', DB::raw('UTC_TIMESTAMP(6)'));
                } else {
                    $base = max(1, min(3600, (int) config('delivery.backoff_seconds')));
                    $cap = max($base, min(86400, (int) config('delivery.backoff_max_seconds')));
                    $this->defer($delivery, $outcome['diagnostic'], $this->after(min($cap, $base * (2 ** min(19, $delivery->attempts - 1)))));
                }
            } else {
                $this->terminal($delivery, 'ambiguous', 'smtp_unconfirmed');
            }

            return true;
        }, 3);
    }

    public function defer(Delivery $delivery, string $reason, mixed $until): void
    {
        $this->write($delivery, ['status' => 'pending', 'generation' => $delivery->generation + 1, 'available_at' => $until, 'lease_token' => null, 'lease_expires_at' => null, 'diagnostic' => $reason]);
        $this->event($delivery, 'delivery.ready', $until);
    }

    private function event(Delivery $delivery, string $type, mixed $available): void
    {
        DB::table('delivery_outbox')->insert(['id' => (string) Str::uuid(), 'account_id' => $delivery->account_id, 'delivery_id' => $delivery->id, 'generation' => $delivery->generation, 'type' => $type, 'status' => 'pending', 'available_at' => $available, 'created_at' => DB::raw('UTC_TIMESTAMP(6)'), 'updated_at' => DB::raw('UTC_TIMESTAMP(6)')]);
    }

    public function terminal(Delivery $delivery, string $status, ?string $diagnostic = null): void
    {
        $this->write($delivery, ['status' => $status, 'diagnostic' => $diagnostic, 'lease_token' => null, 'lease_expires_at' => null, 'completed_at' => DB::raw('UTC_TIMESTAMP(6)')]);
    }

    private function write(Delivery $delivery, array $changes): void
    {
        Delivery::whereKey($delivery->id)->where('account_id', $delivery->account_id)->update($changes + ['updated_at' => DB::raw('UTC_TIMESTAMP(6)')]);
        $delivery->refresh();
    }

    public function after(int $seconds): mixed
    {
        return DB::raw('DATE_ADD(UTC_TIMESTAMP(6), INTERVAL '.$seconds.' SECOND)');
    }

    private function recoverLocked(Delivery $delivery): void
    {
        if (! in_array($delivery->status, ['reserved', 'sending'], true) || ! Delivery::whereKey($delivery->id)->where('lease_expires_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists()) {
            return;
        }
        if ($delivery->status === 'sending') {
            $this->terminal($delivery, 'ambiguous', 'sending_lease_expired');
            if ($delivery->mail_message_id !== null) {
                MailMessage::where('account_id', $delivery->account_id)->whereKey($delivery->mail_message_id)->where('status', 'sending')->update(['status' => 'smtp_unconfirmed', 'diagnostic' => 'sending_lease_expired']);
            }
        } else {
            $this->defer($delivery, 'reservation_expired', DB::raw('UTC_TIMESTAMP(6)'));
        }
    }

    public function recover(int $limit = 100): int
    {
        $ids = Delivery::whereIn('status', ['reserved', 'sending'])->where('lease_expires_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->orderBy('id')->limit(max(1, min(1000, $limit)))->get(['id', 'account_id']);
        foreach ($ids as $reference) {
            DB::transaction(function () use ($reference) {
                Account::whereKey($reference->account_id)->lockForUpdate()->firstOrFail();
                $delivery = Delivery::whereKey($reference->id)->where('account_id', $reference->account_id)->lockForUpdate()->first();
                if ($delivery) {
                    $this->recoverLocked($delivery);
                }
            }, 3);
        }

        return $ids->count();
    }

    public function readyToSettle(Delivery $claim): bool
    {
        return DB::transaction(function () use ($claim) {
            Account::whereKey($claim->account_id)->lockForUpdate()->firstOrFail();
            $delivery = Delivery::whereKey($claim->id)->where('account_id', $claim->account_id)->lockForUpdate()->firstOrFail();
            $this->recoverLocked($delivery);

            return in_array($delivery->status, self::TERMINAL, true)
                || ($delivery->generation !== $claim->generation && DeliveryOutbox::where('delivery_id', $delivery->id)->where('account_id', $delivery->account_id)->where('generation', $delivery->generation)->where('type', 'delivery.ready')->exists());
        }, 3);
    }
}
