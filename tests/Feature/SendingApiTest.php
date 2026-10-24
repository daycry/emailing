<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountApiKey;
use App\Models\ApiMailRecipient;
use App\Models\ApiMailRequest;
use App\Models\Contact;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountKeyService;
use App\Services\AccountMailTransport;
use App\Services\ActivityService;
use App\Services\DeliveryLifecycle;
use App\Support\SendingApiInput;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\ExercisesSendingApi;
use Tests\Concerns\ExercisesSendingApiProcesses;
use Tests\Concerns\ExercisesSendingApiReviewFixes;
use Tests\TestCase;

#[Group('delivery')]
class SendingApiTest extends TestCase
{
    use DatabaseMigrations;
    use ExercisesSendingApi;
    use ExercisesSendingApiProcesses;
    use ExercisesSendingApiReviewFixes;

    private Account $account;

    private User $owner;

    private MailSender $sender;

    private array $key;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->account = Account::create(['name' => 'API fixture '.Str::uuid(), 'owner_id' => $this->owner->id]);
        Membership::create(['account_id' => $this->account->id, 'user_id' => $this->owner->id, 'role' => 'admin', 'scopes' => []]);
        $this->sender = MailSender::create(['account_id' => $this->account->id, 'email' => 'sender@example.test', 'name' => 'API fixture', 'lab_authorized' => true]);
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
        $this->key = $this->mint();
        Storage::fake('mail-artifacts');
        config(['delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000]);
    }

    protected function tearDown(): void
    {
        $this->settleApiResources();
        // Owned account cascade removes accepted work before the guarded downgrade.
        if (isset($this->account)) {
            Account::whereIn('id', $this->otherAccounts)->delete();
            Account::whereKey($this->account->id)->delete();
        }
        parent::tearDown();
    }

    private function mint(array $scopes = SendingApiInput::SCOPES, ?string $expiry = null): array
    {
        return app(AccountKeyService::class)->ui('api-keys.write', fn ($account, $ceiling) => app(AccountKeyService::class)->create($account, ['name' => 'Fixture key', 'scopes' => $scopes, 'expires_at' => $expiry], $ceiling, actor: $this->owner->id));
    }

    private function payload(): array
    {
        return ['sender_id' => $this->sender->id, 'recipients' => [['email' => 'direct@example.test']], 'content' => ['type' => 'inline', 'subject' => '  Inline fixture  ', 'text' => "\r\n body {{ literal }} \n"]];
    }

    private function api(string $method = 'POST', string $url = '/api/v1/mail/send', mixed $body = null, ?array $key = null, array $headers = [])
    {
        $raw = is_string($body) ? $body : json_encode($body ?? $this->payload(), JSON_THROW_ON_ERROR);
        if ($method !== 'POST' && $body === null) {
            $raw = '';
        }

        return $this->call($method, $url, [], [], [], $headers + ['HTTP_AUTHORIZATION' => 'Bearer '.($key ?? $this->key)['secret'], 'CONTENT_TYPE' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => 'fixture-idempotency-0001'], $raw);
    }

    private function resetBudget(): void
    {
        DB::table('api_admission_windows')->where('account_id', $this->account->id)->delete();
    }

    public function test_acceptance_replay_normalization_mutations_and_one_shared_transport(): void
    {
        $payload = $this->payload();
        $payload['recipients'][0]['email'] = " \tDIRECT@EXAMPLE.TEST\t ";
        $first = $this->api(body: $payload)->assertStatus(202)->assertHeader('Idempotency-Replayed', 'false')->assertHeader('Cache-Control', 'no-store');
        $secondKey = $this->mint(['mail.send', 'activity.read']);
        $normalized = $this->payload();
        $normalized['sender_id'] = (float) $this->sender->id;
        $normalized['recipients'][0]['name'] = '';
        $normalized['content']['html'] = '';
        $this->sender->update(['name' => 'Mutable sender']);
        $this->api(body: $normalized, key: $secondKey)->assertStatus(202)->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($first->json());
        $different = $normalized;
        $different['content']['text'] .= 'changed';
        $this->api(body: $different)->assertStatus(409)->assertJsonPath('error.code', 'idempotency_conflict');
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(1, Delivery::count());
        $this->assertSame(1, DeliveryOutbox::count());
        $this->assertSame(0, Contact::count());
        $this->assertSame(0, MailMessage::count());
        $this->assertSame($normalized['content']['text'], ApiMailRecipient::sole()->text);
        $this->sender->update(['name' => 'API fixture']);
        $event = DeliveryOutbox::sole();
        $claim = app(DeliveryLifecycle::class)->claim(['account_id' => $this->account->id, 'delivery_id' => $event->delivery_id, 'event_id' => $event->id, 'generation' => 1]);
        $message = app(AccountMailTransport::class)->sendDelivery($claim);
        $this->assertSame('logged', $message->fresh()->status);
        $this->assertSame('api', $message->origin);
        $this->assertNull($message->actor_id);
        $this->assertSame(AccountApiKey::where('public_id', $this->key['key']['id'])->value('id'), $message->api_credential_id);
        $this->api('GET', $first->headers->get('Location'))->assertOk()->assertJsonPath('counts.logged', 1)->assertJsonPath('deliveries.0.attempts', 1);
        $this->assertNull(app(DeliveryLifecycle::class)->claim(['account_id' => $this->account->id, 'delivery_id' => $event->delivery_id, 'event_id' => $event->id, 'generation' => 1]));
        $this->assertSame(1, MailMessage::count());
        $metrics = app(ActivityService::class)->metrics([], $this->account->id);
        $this->assertSame(1, $metrics['api']['total']);
        $this->assertSame(0, $metrics['campaign']['total']);
        $this->assertSame(0, $metrics['unknown']['total']);
        $detail = app(ActivityService::class)->detail('delivery:'.$event->delivery_id, $this->account->id);
        $this->assertSame($this->key['key']['id'], $detail->api_key_id);
        $this->assertSame(1, app(ActivityService::class)->attempts('delivery:'.$event->delivery_id, $this->account->id)->total());
        $csv = $this->get('/activity/export?population=api')->assertOk()->streamedContent();
        $this->assertStringContainsString($this->key['key']['id'], $csv);
        $this->assertStringNotContainsString($this->key['secret'], $csv);
    }

    public function test_bearer_is_explicit_current_and_never_amplified_by_cookie_or_creator_membership(): void
    {
        foreach ([['POST', '/api/v1/mail/send'], ['GET', '/api/v1/mail/requests/'.Str::uuid()], ['GET', '/api/v1/keys'], ['POST', '/api/v1/keys'], ['DELETE', '/api/v1/keys/'.Str::uuid()]] as [$method, $url]) {
            $this->call($method, $url, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{}')->assertStatus(401)->assertHeader('WWW-Authenticate', 'Bearer');
        }
        $limited = $this->mint(['api-keys.read']);
        $this->api(key: $limited)->assertStatus(403);
        $this->api(headers: ['HTTP_AUTHORIZATION' => 'Bearer invalid'])->assertStatus(401);
        $this->api(headers: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->key['secret'].', Bearer '.$limited['secret']])->assertStatus(401);
        $this->api('GET', '/api/v1/keys?account_id='.$this->account->id)->assertStatus(422);
        $this->api(headers: ['HTTP_X_ACCOUNT_ID' => (string) $this->account->id])->assertStatus(422);
        $this->api('GET', '/api/v1/keys')->assertOk()->assertJsonMissing(['secret' => $this->key['secret']]);
        Membership::where('account_id', $this->account->id)->delete();
        $this->api()->assertStatus(202);
        $this->get('/api-keys')->assertForbidden();
        AccountApiKey::where('public_id', $this->key['key']['id'])->update(['expires_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);
        $this->api()->assertStatus(401);
    }

    public function test_scopes_expiry_grant_ceiling_revocation_and_cursor_are_account_owned(): void
    {
        $parent = $this->mint(['mail.send', 'api-keys.read', 'api-keys.write'], gmdate('Y-m-d H:i:s.u', time() + 3600));
        $this->api('POST', '/api/v1/keys', ['name' => 'Too broad', 'scopes' => ['activity.read']], $parent)->assertStatus(403);
        $this->api('POST', '/api/v1/keys', ['name' => 'Unbounded', 'scopes' => ['mail.send'], 'expires_at' => null], $parent)->assertStatus(403);
        $created = $this->api('POST', '/api/v1/keys', ['name' => 'Child', 'scopes' => ['mail.send']], $parent);
        $this->assertSame(201, $created->status());
        $child = $created->json();
        $this->assertSame($parent['key']['expires_at'], $child['key']['expires_at']);
        $this->assertNotSame($child['key']['id'], explode('|', $child['secret'])[0]);
        $this->api('DELETE', '/api/v1/keys/'.$parent['key']['id'], key: $parent)->assertStatus(204);
        $this->api('GET', '/api/v1/keys', key: $parent)->assertStatus(401);
        $this->api(key: $child)->assertStatus(202);
        $this->api('DELETE', '/api/v1/keys/'.$parent['key']['id'])->assertStatus(204);
        $page = $this->api('GET', '/api/v1/keys?limit=1')->assertOk()->json();
        $this->assertCount(1, $page['data']);
        $this->assertNotNull($page['next_cursor']);
        $this->assertLessThanOrEqual(512, strlen($page['next_cursor']));
        $this->api('GET', '/api/v1/keys?cursor='.urlencode($page['next_cursor']))->assertOk();
        $this->api('GET', '/api/v1/keys?cursor='.urlencode($page['next_cursor'].'x'))->assertStatus(400);
        $this->api('DELETE', '/api/v1/keys/'.Str::uuid())->assertStatus(404);
    }

    public function test_exact_numeric_json_ids_and_literal_numeric_content_replay(): void
    {
        $payload = $this->payload();
        $payload['content'] = ['type' => 'inline', 'subject' => '1e3', 'text' => '{"sender_id":1.1}', 'html' => '1e3'];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $receipt = null;
        foreach ([$this->sender->id.'.0', $this->sender->id.'e0', $this->sender->id.str_repeat('0', 2000).'e-2000'] as $number) {
            $body = preg_replace('/"sender_id":[0-9]+/', '"sender_id":'.$number, $raw, 1);
            $response = $this->api(body: $body)->assertStatus(202);
            $receipt ??= $response->json();
            $this->assertSame(SendingApiInput::fingerprint($receipt), SendingApiInput::fingerprint($response->json()));
        }
        $stored = ApiMailRecipient::sole();
        $this->assertSame('1e3', $stored->subject);
        $this->assertSame('{"sender_id":1.1}', $stored->text);
        $this->assertSame('1e3', $stored->html);
        $this->assertSame(1, Delivery::count());
    }

    public function test_strict_json_types_duplicates_limits_media_queries_and_canonical_strings(): void
    {
        $valid = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        foreach (['9007199254740990.1', '9007199254740991.1', '1.0000000000000001', '1e999999999', '1e-999999999', '{"lexeme":"1e0"}'] as $number) {
            $raw = preg_replace('/"sender_id":[0-9]+/', '"sender_id":'.$number, $valid, 1);
            $this->api(body: $raw)->assertStatus(422);
        }
        $cases = [
            ['{"sender_id":1,"sender_id":2}', 400], ['{"sender_id":1,"\\u0073ender_id":2}', 400], ['[]', 422], ['null', 422], ['{"a":'.str_repeat('[', 32).'1'.str_repeat(']', 32).'}', 400], ["{\"bad\":\"\xff\"}", 400], ['{"a":01}', 400], ['{} trailing', 400],
        ];
        foreach ($cases as [$body, $status]) {
            $this->api(body: $body)->assertStatus($status);
        }
        foreach ([['sender_id' => '1'], ['sender_id' => true], ['sender_id' => 1.5], ['sender_id' => 9007199254740992], ['account_id' => $this->account->id], ['recipients' => (object) []], ['recipients' => [['email' => 'direct@example.test'], ['email' => ' DIRECT@EXAMPLE.TEST ']]], ['recipients' => [['email' => "recipient\r\n@example.test"]]], ['recipients' => [['email' => 'direct@example.test', 'name' => null]]], ['content' => ['type' => 'inline', 'subject' => 'Subject', 'text' => null]], ['content' => ['type' => 'inline', 'subject' => ' ', 'text' => 'ok']], ['content' => ['type' => 'inline', 'subject' => 'ok', 'html' => '  ', 'text' => "\n"]]] as $override) {
            $this->api(body: array_replace($this->payload(), $override))->assertStatus(422);
        }
        $this->resetBudget();
        $this->api(body: str_repeat(' ', 2097153))->assertStatus(413);
        $this->api(body: $valid, headers: ['CONTENT_TYPE' => 'text/plain'])->assertStatus(415);
        $this->api(body: $valid, headers: ['HTTP_CONTENT_ENCODING' => 'gzip'])->assertStatus(415);
        $this->api('GET', '/api/v1/keys?limit=1&%6cimit=2')->assertStatus(400);
        $this->api('GET', '/api/v1/keys?cursor=%XX')->assertStatus(400);
        $this->api('GET', '/api/v1/keys?limit=1.0')->assertStatus(422);
        $this->api('GET', '/api/v1/keys', '{}')->assertStatus(422);
        $this->api(headers: ['HTTP_IDEMPOTENCY_KEY' => 'short'])->assertStatus(422);
        $this->api(headers: ['HTTP_IDEMPOTENCY_KEY' => 'fixture-idempotency-0001,fixture-idempotency-0002'])->assertStatus(400);
        $this->assertSame(0, ApiMailRequest::count());
        $this->assertSame(0, DeliveryOutbox::count());
    }

    public function test_template_replay_precedes_mutable_lookup_but_requires_current_template_scope(): void
    {
        $template = EmailTemplate::create(['account_id' => $this->account->id, 'name' => 'Fixture', 'subject' => 'Hello {{ contact.name }}', 'html' => '', 'text' => '{{ contact.email }}']);
        $payload = $this->payload();
        $payload['content'] = ['type' => 'template', 'template_id' => $template->id];
        $first = $this->api(body: $payload)->assertStatus(202)->json();
        $template->delete();
        $this->sender->delete();
        $this->api(body: $payload)->assertStatus(202)->assertExactJson($first);
        $limited = $this->mint(['mail.send']);
        $this->api(body: $payload, key: $limited)->assertStatus(403);
        $payload['content']['template_id']++;
        $this->api(body: $payload)->assertStatus(409);
        $this->assertSame(1, Delivery::count());
    }

    public function test_http_budget_is_shared_durable_counts_errors_and_never_counts_429(): void
    {
        while ((int) DB::selectOne('SELECT SECOND(UTC_TIMESTAMP()) AS second')->second > 40) {
            usleep(100000);
        }
        for ($i = 0; $i < 30; $i++) {
            $this->api('GET', '/api/v1/keys?unknown=1')->assertStatus(422);
        }
        $this->api()->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(60, (int) DB::table('api_admission_windows')->sum('used'));
        $second = $this->mint();
        for ($i = 0; $i < 30; $i++) {
            $this->api('GET', '/api/v1/keys', key: $second)->assertOk();
        }
        $third = $this->mint();
        $this->api(key: $third)->assertStatus(429);
        $this->assertSame(60, (int) DB::table('api_admission_windows')->where('credential_id', 0)->value('used'));
        $this->assertSame(0, (int) DB::table('api_admission_windows')->where('credential_id', AccountApiKey::where('public_id', $third['key']['id'])->value('id'))->value('used'));
    }

    public function test_sql_failure_rolls_back_the_batch_but_keeps_authenticated_admission(): void
    {
        $event = 'eloquent.creating: '.DeliveryOutbox::class;
        Event::listen($event, function ($outbox) {
            // Real MySQL FK failure after request, recipient and delivery writes.
            DB::table('delivery_outbox')->insert(array_replace($outbox->getAttributes(), ['delivery_id' => (string) Str::uuid()]));
        });
        try {
            $this->api()->assertStatus(503)->assertJsonPath('error.code', 'temporarily_unavailable');
        } finally {
            Event::forget($event);
        }
        $this->assertSame(0, ApiMailRequest::count());
        $this->assertSame(0, ApiMailRecipient::count());
        $this->assertSame(0, Delivery::count());
        $this->assertSame(0, DeliveryOutbox::count());
        $this->assertSame(1, (int) DB::table('api_admission_windows')->where('credential_id', 0)->sum('used'));
        $this->api()->assertStatus(202);
        $this->assertSame(1, ApiMailRequest::count());
        $this->assertSame(2, (int) DB::table('api_admission_windows')->where('credential_id', 0)->sum('used'));
        $this->evidence('savepoint-rollback', ['real_mysql_foreign_key_failure' => true, 'failure_status' => 503, 'partial_source_rows' => 0, 'same_idempotency_retry' => 202, 'admitted_requests' => 2]);
    }

    public function test_boundary_sizes_unicode_content_order_and_app_key_rotation_preserve_receipts(): void
    {
        $payload = $this->payload();
        $payload['recipients'] = array_map(fn ($n) => ['email' => 'bounded-'.$n.'@example.test', 'name' => 'Fixture '.$n], range(1, 100));
        $first = $this->api(body: $payload)->assertStatus(202)->json();
        $this->assertCount(100, $first['delivery_ids']);
        $this->assertSame(100, Delivery::count());
        $payload['recipients'][] = ['email' => 'too-many@example.test'];
        $this->api(body: $payload, headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-recipient-0002'])->assertStatus(422);
        array_pop($payload['recipients']);
        $payload['recipients'] = array_reverse($payload['recipients']);
        $this->api(body: $payload)->assertStatus(409);
        $payload = $this->payload();
        $payload['content']['text'] = str_repeat('é', 200000);
        $this->api(body: $payload, headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-unicode-00003'])->assertStatus(202);
        $payload['content']['text'] .= 'é';
        $this->api(body: $payload, headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-unicode-00004'])->assertStatus(422);
        $raw = json_encode($this->payload(), JSON_THROW_ON_ERROR);
        $raw .= str_repeat(' ', 2097152 - strlen($raw));
        $accepted = $this->api(body: $raw, headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-raw-000000005'])->assertStatus(202)->json();
        $this->api(body: $raw.' ', headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-raw-000000006'])->assertStatus(413);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->api(body: $this->payload(), headers: ['HTTP_IDEMPOTENCY_KEY' => 'bounded-raw-000000005'])->assertStatus(202)->assertExactJson($accepted);
        $request = ApiMailRequest::whereKey($accepted['request_id'])->firstOrFail();
        $this->assertSame($accepted['accepted_at'], AccountKeyService::utc($request->accepted_at));
        try {
            $request->update(['fingerprint' => str_repeat('0', 64)]);
            $this->fail('Accepted source was mutable.');
        } catch (\LogicException $error) {
            $this->assertStringContainsString('immutable', $error->getMessage());
        }
    }
}
