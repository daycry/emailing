<?php

namespace Tests\Concerns;

use App\Models\Account;
use App\Models\ApiMailRecipient;
use App\Models\ApiMailRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Services\ApiRecipientPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

trait ExercisesSendingApiReviewFixes
{
    use ReplaysMigrationHistory;

    public function test_nul_object_properties_receive_contractual_http_errors_without_partial_work(): void
    {
        $send = $this->payload();
        $send["\0unknown"] = 1;
        $recipient = $this->payload();
        $recipient['recipients'][0]["\0unknown"] = 1;
        $content = $this->payload();
        $content['content']["\0unknown"] = 1;
        $mangled = $this->payload();
        $mangled["\0*\0unknown"] = 1;
        $private = $this->payload();
        $private["\0Other\0unknown"] = 1;
        $cases = [
            ['/api/v1/mail/send', json_encode($send, JSON_THROW_ON_ERROR), 422],
            ['/api/v1/mail/send', json_encode($recipient, JSON_THROW_ON_ERROR), 422],
            ['/api/v1/mail/send', json_encode($content, JSON_THROW_ON_ERROR), 422],
            ['/api/v1/mail/send', json_encode($mangled, JSON_THROW_ON_ERROR), 422],
            ['/api/v1/mail/send', json_encode($private, JSON_THROW_ON_ERROR), 422],
            ['/api/v1/keys', '{"name":"Fixture","scopes":["mail.send"],"\u0000unknown":1}', 422],
            ['/api/v1/mail/send', '{"\u0000unknown":1,"\u0000unknown":2}', 400],
            ['/api/v1/keys', '{"\u0000unknown":1,}', 400],
        ];
        $observed = [];
        foreach ($cases as [$url, $body, $expected]) {
            $response = $this->api('POST', $url, $body);
            $observed[] = ['url' => $url, 'expected' => $expected, 'status' => $response->status(), 'code' => $response->json('error.code')];
        }
        $this->evidence('nul-http', ['responses' => $observed, 'requests' => ApiMailRequest::count(), 'recipients' => ApiMailRecipient::count(), 'deliveries' => Delivery::count(), 'outbox' => DeliveryOutbox::count()]);
        foreach ($observed as $result) {
            $this->assertSame($result['expected'], $result['status'], $result['url']);
        }
        $this->assertSame(0, ApiMailRequest::count());
        $this->assertSame(0, ApiMailRecipient::count());
        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, DeliveryOutbox::count());
    }

    public function test_status_transfers_bounded_metadata_for_one_hundred_large_snapshots(): void
    {
        $payload = $this->payload();
        $payload['recipients'] = array_map(fn ($n) => ['email' => 'status-'.$n.'@example.test'], range(1, 100));
        $payload['content']['html'] = str_repeat('h', 200000);
        $payload['content']['text'] = str_repeat('t', 200000);
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertLessThan(2097152, strlen($raw));
        $accepted = $this->api(body: $raw)->assertStatus(202);
        $storedBodyBytes = (int) DB::table('api_mail_recipients')->where('api_request_id', $accepted->json('request_id'))->selectRaw('SUM(OCTET_LENGTH(html) + OCTET_LENGTH(text)) AS bytes')->value('bytes');
        $hydratedBytes = 0;
        $hydrated = 0;
        $event = 'eloquent.retrieved: '.ApiMailRecipient::class;
        Event::listen($event, function ($recipient) use (&$hydratedBytes, &$hydrated): void {
            $hydrated++;
            foreach ($recipient->getAttributes() as $value) {
                $hydratedBytes += is_string($value) ? strlen($value) : 0;
            }
        });
        DB::connection()->enableQueryLog();
        try {
            $response = $this->api('GET', $accepted->headers->get('Location'));
            $queries = array_values(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from `api_mail_recipients`')));
        } finally {
            DB::connection()->disableQueryLog();
            DB::connection()->flushQueryLog();
            Event::forget($event);
        }
        $this->evidence('status-transfer-budget', ['input_bytes' => strlen($raw), 'stored_body_bytes' => $storedBodyBytes, 'hydrated_recipients' => $hydrated, 'hydrated_string_bytes' => $hydratedBytes, 'response_bytes' => strlen($response->getContent()), 'recipient_sql' => array_column($queries, 'query'), 'budget_bytes' => 65536]);
        $response->assertOk()->assertJsonPath('counts.pending', 100)->assertJsonCount(100, 'deliveries');
        $this->assertSame($accepted->json('delivery_ids'), array_column($response->json('deliveries'), 'id'));
        $this->assertSame(array_column($payload['recipients'], 'email'), array_column($response->json('deliveries'), 'recipient'));
        $this->assertSame(40000000, $storedBodyBytes);
        $this->assertSame(100, $hydrated);
        $this->assertLessThanOrEqual(65536, $hydratedBytes, 'Status must transfer only bounded recipient metadata.');
    }

    public function test_historical_email_eligibility_has_a_bounded_real_mysql_read_budget(): void
    {
        for ($batch = 0; $batch < 20; $batch++) {
            $rows = [];
            for ($offset = 0; $offset < 500; $offset++) {
                $n = $batch * 500 + $offset;
                $rows[] = ['account_id' => $this->account->id, 'email' => 'historical-'.$n.'@example.test', 'name' => 'Synthetic budget fixture', 'deleted_at' => $n % 2 === 0 ? '2020-01-01 00:00:00' : null];
            }
            DB::table('contacts')->insert($rows);
        }
        // Refresh optimizer statistics after the controlled 10k-row population.
        DB::statement('ANALYZE TABLE contacts');
        $counters = static function (): array {
            $values = [];
            foreach (DB::select("SHOW SESSION STATUS WHERE Variable_name LIKE 'Handler_read%'") as $row) {
                $values[$row->Variable_name] = (int) $row->Value;
            }

            return $values;
        };
        $connection = DB::connection();
        $connection->enableQueryLog();
        try {
            $before = $counters();
            $eligible = DB::transaction(function (): array {
                DB::table('accounts')->where('id', $this->account->id)->lockForUpdate()->first();
                $results = [];
                for ($n = 0; $n < 100; $n++) {
                    $results[] = app(ApiRecipientPolicy::class)->eligible($this->account->id, 'new-direct-'.$n.'@example.test');
                }

                return $results;
            });
            $after = $counters();
            $lookups = array_values(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from `contacts`') && str_contains($query['query'], 'for update')));
        } finally {
            $connection->disableQueryLog();
            $connection->flushQueryLog();
        }
        $delta = [];
        foreach ($after as $key => $value) {
            $delta[$key] = $value - $before[$key];
        }
        $lookup = $lookups[0];
        $plan = DB::select('EXPLAIN FORMAT=JSON '.$lookup['query'], $lookup['bindings']);
        $actual = DB::select('EXPLAIN ANALYZE '.$lookup['query'], $lookup['bindings']);
        $this->evidence('historical-email-read-budget', ['contacts' => 10000, 'direct_recipients' => 100, 'contact_lock_queries' => count($lookups), 'lookup_sql' => $lookup['query'], 'handler_read_delta' => $delta, 'handler_read_total' => array_sum($delta), 'budget_reads' => 1000, 'explain_json' => $plan, 'explain_analyze' => $actual]);
        $this->assertSame(array_fill(0, 100, []), $eligible);
        $this->assertCount(100, $lookups);
        $this->assertLessThanOrEqual(1000, array_sum($delta), '100 new recipients must not scan the 10k-contact account.');
    }

    public function test_bound_recipient_lookups_preserve_history_identity_and_account_with_bounded_reads(): void
    {
        for ($batch = 0; $batch < 20; $batch++) {
            $rows = [];
            for ($offset = 0; $offset < 500; $offset++) {
                $rows[] = ['account_id' => $this->account->id, 'email' => 'bound-budget-'.($batch * 500 + $offset).'@example.test'];
            }
            DB::table('contacts')->insert($rows);
        }
        $base = ['account_id' => $this->account->id, 'email' => 'bound@example.test', 'consent_status' => 'granted', 'consent_source' => 'Synthetic fixture', 'consented_at' => now('UTC')->subDay()];
        $bound = Contact::create($base);
        [$foreign] = $this->foreign();
        $foreignContact = Contact::create(array_replace($base, ['account_id' => $foreign->id]));
        $observations = [];
        $observe = function (string $scenario, string $email, array $ids, ?array $expected) use (&$observations): void {
            $counters = static fn () => array_sum(array_map(fn ($row) => (int) $row->Value, DB::select("SHOW SESSION STATUS WHERE Variable_name LIKE 'Handler_read%'")));
            DB::connection()->enableQueryLog();
            try {
                $before = $counters();
                $result = DB::transaction(function () use ($email, $ids) {
                    Account::whereKey($this->account->id)->lockForUpdate()->firstOrFail();

                    return app(ApiRecipientPolicy::class)->eligible($this->account->id, $email, $ids);
                });
                $reads = $counters() - $before;
                $queries = array_values(array_filter(DB::getQueryLog(), fn ($query) => str_contains($query['query'], 'from `contacts`') && str_contains($query['query'], 'for update')));
            } finally {
                DB::connection()->disableQueryLog();
                DB::connection()->flushQueryLog();
            }
            $plans = [];
            foreach ($queries as $query) {
                $plans[] = ['sql' => $query['query'], 'explain_json' => DB::select('EXPLAIN FORMAT=JSON '.$query['query'], $query['bindings']), 'explain_analyze' => DB::select('EXPLAIN ANALYZE '.$query['query'], $query['bindings'])];
            }
            $observations[] = ['scenario' => $scenario, 'result' => $result, 'expected' => $expected, 'handler_reads' => $reads, 'plans' => $plans];
        };
        DB::statement('ANALYZE TABLE contacts');
        $observe('bound-current', $bound->email, [$bound->id], [$bound->id]);
        $observe('foreign-bound', $bound->email, [$foreignContact->id], null);
        $bound->update(['consented_at' => now('UTC')->addDay()]);
        $observe('future-consent', $bound->email, [$bound->id], null);
        $bound->update(['consented_at' => now('UTC')->subDay()]);
        $bound->delete();
        $recreated = Contact::create($base);
        $observe('deleted-and-recreated', $bound->email, [$recreated->id], null);
        $bound->forceDelete();
        $observe('missing-original-bound', $recreated->email, [$bound->id], null);
        $recreated->update(['email' => 'changed@example.test']);
        $observe('changed-identity', $base['email'], [$recreated->id], null);
        $this->evidence('bound-email-read-budget', ['contact_population' => 10002, 'budget_per_scenario' => 100, 'observations' => $observations]);
        foreach ($observations as $observation) {
            $this->assertSame($observation['expected'], $observation['result'], $observation['scenario']);
            $this->assertLessThanOrEqual(100, $observation['handler_reads'], $observation['scenario']);
        }
    }

    public function test_historical_email_index_replay_preserves_deleted_and_recreated_contacts(): void
    {
        $base = ['account_id' => $this->account->id, 'email' => 'index-replay@example.test', 'consent_status' => 'unknown'];
        $deleted = Contact::create($base);
        $deleted->delete();
        Contact::create($base);
        $snapshot = fn () => DB::table('contacts')->where('account_id', $this->account->id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $before = $snapshot();
        $this->assertTrue(Schema::hasIndex('contacts', 'contacts_account_email_index'));
        $this->replayMigrationWindow('2026_10_24_000014_index_historical_contact_email', function () use ($snapshot, $before): void {
            $this->assertFalse(Schema::hasIndex('contacts', 'contacts_account_email_index'));
            $this->assertSame($before, $snapshot());
        });
        $this->assertTrue(Schema::hasIndex('contacts', 'contacts_account_email_index'));
        $this->assertSame($before, $snapshot());
        $this->evidence('historical-email-index-replay', ['migration' => '2026_10_24_000014_index_historical_contact_email', 'rows_preserved' => count($before), 'index_restored' => true]);
    }
}
