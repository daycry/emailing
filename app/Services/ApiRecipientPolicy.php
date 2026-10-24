<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

final class ApiRecipientPolicy
{
    /** Called under the account mutex, also used by all consent/suppression writers. */
    public function eligible(int $account, string $email, array $bound = []): ?array
    {
        if (app(SuppressionService::class)->isSuppressed(Account::findOrFail($account), $email)) {
            return null;
        }
        // Separate indexed paths: email OR bound IDs makes MySQL scan the
        // account even with the historical email index. The account mutex
        // keeps both current locking reads in the same consent population.
        $contacts = Contact::withTrashed()->where('account_id', $account)->where('email', $email)->lockForUpdate()->get();
        if ($bound !== []) {
            $contacts = $contacts->merge(Contact::withTrashed()->where('account_id', $account)->whereKey($bound)->lockForUpdate()->get());
        }
        foreach ($bound as $id) {
            if (! $contacts->contains(fn ($contact) => $contact->id === $id && $contact->email === $email)) {
                return null;
            }
        }
        foreach ($contacts as $contact) {
            if ($contact->deleted_at !== null || $contact->email !== $email || $contact->consent_status !== 'granted' || blank($contact->consent_source) || $contact->consented_at === null
                || ! Contact::withTrashed()->whereKey($contact->id)->where('consented_at', '<=', DB::raw('UTC_TIMESTAMP(6)'))->exists()) {
                return null;
            }
        }

        return $contacts->pluck('id')->all();
    }
}
