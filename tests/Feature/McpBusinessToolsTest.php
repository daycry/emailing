<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\DeliveryOutbox;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\Membership;
use App\Models\Suppression;
use App\Services\CampaignService;
use App\Services\DeliveryMaterializer;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\ResponseBudget;
use App\Services\OutboxDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpBusinessToolsTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private const OPERATIONS = [
        'activity_attempts', 'activity_list', 'activity_metrics',
        'campaigns_authorize', 'campaigns_create', 'campaigns_get', 'campaigns_list',
        'campaigns_pause', 'campaigns_resume', 'campaigns_review', 'campaigns_update',
        'suppressions_add', 'suppressions_list', 'suppressions_remove',
    ];

    public function test_all_fourteen_business_operations_publish_the_pinned_contract(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $tools = $this->rpc($bearer, 'tools/list', ['per_page' => 100])->assertOk()->json('result.tools');
        $business = array_values(array_filter($tools, fn (array $tool): bool => in_array($tool['name'], self::OPERATIONS, true)));
        $names = array_column($business, 'name');
        sort($names);
        $this->assertSame(self::OPERATIONS, $names);
        foreach ($business as $tool) {
            $contract = ContractArguments::operation($tool['name']);
            foreach (['inputSchema', 'outputSchema', 'annotations'] as $property) {
                $this->assertSame($contract[$property], $tool[$property], $tool['name'].' '.$property);
            }
        }
    }

    public function test_campaign_workflow_and_suppressions_use_the_owned_domain_without_dispatch(): void
    {
        $this->mcpConfiguration();
        [$user, $foreign] = $this->mcpAccount();
        [, $account] = $this->mcpAccount($user);
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->actingAs($user)->withSession(['account_id' => $foreign->id]);
        [$template, $sender, $contact] = $this->sources($account->id);
        $fields = ['name' => 'Synthetic campaign', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]];
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => $fields])['data']['campaign'];
        $this->assertSame('draft', $campaign['status']);
        $this->assertSame($account->id, Campaign::findOrFail($campaign['id'])->account_id);
        $summary = $this->success($bearer, 'campaigns_get', ['campaign_id' => $campaign['id']]);
        $this->assertSame($campaign, $summary['data']['campaign']);
        $this->assertNull($summary['data']['revision']);
        $this->assertArrayNotHasKey('composition', $summary['data']);
        $this->assertSame($campaign['id'], $this->success($bearer, 'campaigns_list')['data']['items'][0]['id']);
        $fields['name'] = 'Updated synthetic campaign';
        $updated = $this->success($bearer, 'campaigns_update', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version'], 'campaign' => $fields])['data']['campaign'];
        $this->assertSame($campaign['version'] + 1, $updated['version']);
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $updated['version']]);
        $this->assertSame('reviewed', $review['data']['campaign']['status']);
        $this->assertArrayNotHasKey('sender', $review['data']['revision']);
        $this->assertSame(1, $review['data']['revision']['audience']['recipient_count']);
        $this->assertSame($contact->id, $review['data']['revision']['audience']['items'][0]['contact_id']);
        $this->assertStringContainsString('&lt;Synthetic&gt;', $review['data']['revision']['preview']['html']);
        $revision = $review['data']['revision'];
        $detail = $this->success($bearer, 'campaigns_get', ['campaign_id' => $campaign['id'], 'include' => ['composition' => true, 'content' => true, 'sender' => true, 'preview' => true, 'recipients' => (object) []]]);
        $this->assertSame($sender->id, $detail['data']['revision']['sender']['id']);
        $this->assertSame($template->id, $detail['data']['composition']['template_id']);
        $this->assertSame($revision['preview'], $detail['data']['revision']['preview']);
        $authorized = $this->success($bearer, 'campaigns_authorize', ['campaign_id' => $campaign['id'], 'expected_version' => $review['data']['campaign']['version'], 'revision_id' => $revision['id'], 'confirmed' => true])['data']['campaign'];
        $this->assertSame('authorized', $authorized['status']);
        $paused = $this->success($bearer, 'campaigns_pause', ['campaign_id' => $campaign['id'], 'expected_version' => $authorized['version']])['data']['campaign'];
        $this->assertSame('paused', $paused['status']);
        $resumed = $this->success($bearer, 'campaigns_resume', ['campaign_id' => $campaign['id'], 'expected_version' => $paused['version']])['data']['campaign'];
        $this->assertSame('authorized', $resumed['status']);
        $this->assertSame($revision['id'], $resumed['reviewed_revision_id']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 1);
        $suppression = $this->success($bearer, 'suppressions_add', ['email' => ' RECIPIENT@Example.test '])['data']['suppression'];
        $this->assertSame('recipient@example.test', $suppression['normalized_email']);
        $this->assertSame($suppression['id'], $this->success($bearer, 'suppressions_list')['data']['items'][0]['id']);
        $this->assertTrue($this->success($bearer, 'suppressions_remove', ['suppression_id' => $suppression['id']])['data']['deleted']);
        $this->assertNull(Suppression::find($suppression['id']));
        foreach (['deliveries', 'delivery_outbox', 'mail_messages', 'jobs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_unknown_fields_and_direct_calls_without_scopes_have_no_effects(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $full] = $this->mcpCredential($user, $account);
        [$restricted] = $this->mcpAccount();
        Membership::create(['account_id' => $account->id, 'user_id' => $restricted->id, 'role' => 'restricted', 'scopes' => []]);
        [, , $none] = $this->mcpCredential($restricted, $account, []);
        foreach (self::OPERATIONS as $name) {
            $this->failure($full, $name, ['unknown_field' => 'synthetic-private-marker'], 'validation_failed');
            $this->failure($none, $name, [], 'forbidden');
        }
        foreach (['campaigns', 'campaign_revisions', 'campaign_recipients', 'suppressions', 'deliveries', 'delivery_outbox', 'mail_messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_conditional_projections_require_current_scopes_before_revealing_content(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$template, $sender, $contact] = $this->sources($account->id);
        $service = app(CampaignService::class);
        $draft = $service->save($account, $user->id, null, null, ['name' => 'Scope fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]);
        $reviewed = $service->review($account, $user->id, $draft->id, $draft->version);
        [$reader] = $this->mcpAccount();
        Membership::create(['account_id' => $account->id, 'user_id' => $reader->id, 'role' => 'restricted', 'scopes' => ['campaigns.read']]);
        [, , $bearer] = $this->mcpCredential($reader, $account, ['campaigns.read']);
        $summary = $this->success($bearer, 'campaigns_get', ['campaign_id' => $reviewed->id]);
        $this->assertSame(['id', 'version', 'created_at'], array_keys($summary['data']['revision']));
        foreach (['composition', 'content', 'sender', 'preview', 'recipients'] as $projection) {
            $this->failure($bearer, 'campaigns_get', ['campaign_id' => $reviewed->id, 'include' => [$projection => $projection === 'recipients' ? (object) [] : true]], 'forbidden');
        }
        $this->assertSame($summary['data'], $this->success($bearer, 'campaigns_get', ['campaign_id' => $reviewed->id, 'include' => ['composition' => false, 'content' => false, 'sender' => false, 'preview' => false]])['data']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 1);
    }

    public function test_recipient_pages_continue_the_same_revision_and_reject_a_replaced_snapshot(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $first] = $this->sources($account->id);
        $ids = [$first->id];
        foreach (range(2, 5) as $i) {
            $ids[] = Contact::create(['account_id' => $account->id, 'email' => 'recipient'.$i.'@example.test', 'name' => 'Synthetic '.$i, 'consent_status' => 'granted', 'consent_source' => 'Synthetic form', 'consented_at' => now('UTC')->subDay()])->id;
        }
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'Cursor fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => $ids]])['data']['campaign'];
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
        $revisionId = $review['data']['revision']['id'];
        $page = $this->success($bearer, 'campaigns_get', ['campaign_id' => $campaign['id'], 'include' => ['recipients' => ['limit' => 2]]]);
        $seen = array_column($page['data']['revision']['audience']['items'], 'contact_id');
        $cursor = $page['data']['revision']['audience']['page']['next_cursor'];
        $this->assertTrue($page['data']['revision']['audience']['page']['has_more']);
        $next = $this->success($bearer, 'campaigns_get', ['campaign_id' => $campaign['id'], 'include' => ['content' => true, 'recipients' => ['limit' => 3, 'cursor' => $cursor]]]);
        $this->assertSame($ids, [...$seen, ...array_column($next['data']['revision']['audience']['items'], 'contact_id')]);
        $this->assertFalse($next['data']['revision']['audience']['page']['has_more']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 5);
        $again = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $review['data']['campaign']['version']]);
        $this->assertNotSame($revisionId, $again['data']['revision']['id']);
        $this->failure($bearer, 'campaigns_get', ['campaign_id' => $campaign['id'], 'include' => ['recipients' => ['cursor' => $cursor]]], 'version_conflict');
        $this->assertDatabaseCount('campaign_revisions', 2);
        $this->assertDatabaseCount('campaign_recipients', 10);
        $this->assertSame($revisionId, CampaignRecipient::orderBy('id')->firstOrFail()->campaign_revision_id);
        $this->assertSame(2, CampaignRevision::where('account_id', $account->id)->count());
    }

    public function test_activity_tuple_pages_attempts_and_metrics_are_safe_and_account_scoped(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [, $sender, $contact] = $this->sources($account->id);
        $ids = [];
        foreach (['logged', 'smtp_accepted', 'smtp_unconfirmed'] as $status) {
            $ids[] = MailMessage::create(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $user->id,
                'mode' => $status === 'logged' ? 'log' : 'mailpit', 'status' => $status,
                'sender_email' => $sender->email, 'recipient' => $contact->email, 'subject' => 'Synthetic activity',
                'origin' => 'test', 'diagnostic' => $status === 'smtp_unconfirmed' ? 'smtp_unconfirmed' : null,
                'created_at' => now('UTC')->startOfMinute(), 'artifact_path' => 'private/synthetic.eml'])->id;
        }
        $first = $this->success($bearer, 'activity_list', ['limit' => 1]);
        $this->assertSame('message:'.$ids[2], $first['data']['items'][0]['record_key']);
        $this->assertSame('ambiguous', $first['data']['items'][0]['status']);
        $this->assertSame('smtp', $first['data']['items'][0]['transport_mode']);
        $this->assertSame('smtp_unconfirmed', $first['data']['items'][0]['diagnostic_code']);
        $this->assertTrue($first['data']['items'][0]['has_private_artifact']);
        $this->assertStringNotContainsString('private/synthetic.eml', json_encode($first, JSON_THROW_ON_ERROR));
        $next = $this->success($bearer, 'activity_list', ['limit' => 2, 'cursor' => $first['page']['next_cursor']]);
        $this->assertSame(['message:'.$ids[1], 'message:'.$ids[0]], array_column($next['data']['items'], 'record_key'));
        $this->assertFalse($next['page']['has_more']);
        $attempts = $this->success($bearer, 'activity_attempts', ['record_key' => 'message:'.$ids[2]]);
        $this->assertSame('message:'.$ids[2], $attempts['data']['record_key']);
        $this->assertSame([$ids[2]], array_column($attempts['data']['items'], 'id'));
        $this->assertSame('smtp_unconfirmed', $attempts['data']['items'][0]['transport_status']);
        $this->assertNull($attempts['data']['items'][0]['credential']);
        $this->assertNull($attempts['data']['items'][0]['request_id']);
        $metrics = $this->success($bearer, 'activity_metrics');
        $this->assertSame('not_available', $metrics['data']['delivery_feedback']);
        $this->assertSame(3, $metrics['data']['populations']['test']['total']);
        $this->assertSame(33.33, $metrics['data']['populations']['test']['acceptance_percentage']);
        $this->assertSame(1, $metrics['data']['populations']['test']['states']['ambiguous']);
        foreach (['api', 'campaign', 'unknown', 'mcp'] as $population) {
            $this->assertSame(0, $metrics['data']['populations'][$population]['total']);
            $this->assertNull($metrics['data']['populations'][$population]['acceptance_percentage']);
            $this->assertSame(array_fill_keys(['pending', 'reserved', 'sending', 'smtp_accepted', 'logged', 'failed', 'suppressed', 'ambiguous', 'blocked'], 0), $metrics['data']['populations'][$population]['states']);
        }
        [$other, $foreign] = $this->mcpAccount();
        [, , $otherBearer] = $this->mcpCredential($other, $foreign);
        $this->failure($otherBearer, 'activity_attempts', ['record_key' => 'message:'.$ids[2]], 'not_found');
        $this->assertSame([], $this->success($otherBearer, 'activity_list')['data']['items']);
    }

    public function test_default_activity_cursor_retains_its_date_window_across_midnight(): void
    {
        try {
            $this->travelTo(CarbonImmutable::parse('2028-09-17T23:59:30Z'));
            $this->mcpConfiguration();
            [$user, $account] = $this->mcpAccount();
            [, , $bearer] = $this->mcpCredential($user, $account);
            [, $sender, $contact] = $this->sources($account->id);
            $ids = [];
            foreach (range(1, 4) as $number) {
                $ids[] = MailMessage::create(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $user->id,
                    'mode' => 'log', 'status' => 'logged', 'sender_email' => $sender->email, 'recipient' => $contact->email,
                    'subject' => 'Midnight fixture '.$number, 'origin' => 'test', 'created_at' => now('UTC')->subMinutes($number)])->id;
            }
            $first = $this->success($bearer, 'activity_list', ['limit' => 2]);
            $this->assertSame('2028-09-17', $first['data']['filters']['to_utc']);
            $this->travel(45)->seconds();
            $last = $this->success($bearer, 'activity_list', ['limit' => 3, 'cursor' => $first['page']['next_cursor']]);
            $this->assertSame($first['data']['filters'], $last['data']['filters']);
            $this->assertSame(array_map(fn ($id) => 'message:'.$id, $ids), [...array_column($first['data']['items'], 'record_key'), ...array_column($last['data']['items'], 'record_key')]);
            $this->assertFalse($last['page']['has_more']);
            $this->failure($bearer, 'activity_list', ['filters' => ['to' => '2028-09-18'], 'cursor' => $first['page']['next_cursor']], 'cursor_invalid');
            $this->assertSame('2028-09-18', $this->success($bearer, 'activity_list')['data']['filters']['to_utc']);
        } finally {
            $this->travelBack();
        }
    }

    public function test_broker_refusal_retains_the_same_outbox_without_smtp_fallback_or_rematerialization(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'Broker refusal fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]])['data']['campaign'];
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
        $authorized = $this->success($bearer, 'campaigns_authorize', ['campaign_id' => $campaign['id'], 'expected_version' => $review['data']['campaign']['version'], 'revision_id' => $review['data']['revision']['id'], 'confirmed' => true])['data']['campaign'];
        $materializer = app(DeliveryMaterializer::class);
        $this->assertSame(1, $materializer->materialize($account->id, $campaign['id']));
        $this->assertSame(0, $materializer->materialize($account->id, $campaign['id']));
        $event = DeliveryOutbox::sole();
        // Reserve an owned local address without listening: no operational broker is stopped.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage, STREAM_SERVER_BIND);
        $this->assertIsResource($socket);
        try {
            $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
            config(['queue.connections.rabbitmq.hosts.0.host' => '127.0.0.1', 'queue.connections.rabbitmq.hosts.0.port' => $port]);
            $this->assertTrue(app(OutboxDispatcher::class)->dispatch($event->id));
            $this->assertSame('pending', $event->fresh()->status);
            $this->assertSame('publish_uncertain', $event->fresh()->last_error);
            $this->assertNull($event->fresh()->published_at);
            $this->assertSame($event->id, DeliveryOutbox::sole()->id);
            $paused = $this->success($bearer, 'campaigns_pause', ['campaign_id' => $campaign['id'], 'expected_version' => $authorized['version']])['data']['campaign'];
            $this->success($bearer, 'campaigns_resume', ['campaign_id' => $campaign['id'], 'expected_version' => $paused['version']]);
            $this->assertSame(0, $materializer->materialize($account->id, $campaign['id']));
            $this->assertDatabaseCount('campaign_revisions', 1);
            $this->assertDatabaseCount('campaign_recipients', 1);
            $this->assertDatabaseCount('deliveries', 1);
            $this->assertDatabaseCount('delivery_outbox', 1);
            $this->assertDatabaseCount('mail_messages', 0);
        } finally {
            fclose($socket);
        }
    }

    public function test_stale_versions_and_invalid_states_keep_the_original_campaign_and_revision(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $fields = ['name' => 'Conflict fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]];
        $draft = $this->success($bearer, 'campaigns_create', ['campaign' => $fields])['data']['campaign'];
        $this->failure($bearer, 'campaigns_pause', ['campaign_id' => $draft['id'], 'expected_version' => $draft['version']], 'state_conflict');
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $draft['id'], 'expected_version' => $draft['version']]);
        $this->failure($bearer, 'campaigns_update', ['campaign_id' => $draft['id'], 'expected_version' => $draft['version'], 'campaign' => $fields], 'version_conflict');
        $this->failure($bearer, 'campaigns_authorize', ['campaign_id' => $draft['id'], 'expected_version' => $review['data']['campaign']['version'], 'revision_id' => $review['data']['revision']['id'], 'confirmed' => false], 'validation_failed');
        $template->update(['subject' => 'Changed after review']);
        $this->failure($bearer, 'campaigns_authorize', ['campaign_id' => $draft['id'], 'expected_version' => $review['data']['campaign']['version'], 'revision_id' => $review['data']['revision']['id'], 'confirmed' => true], 'version_conflict');
        $this->assertSame('reviewed', Campaign::findOrFail($draft['id'])->status);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_removing_a_suppression_does_not_restore_withdrawn_recipient_consent(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $revoked = $this->success($bearer, 'contacts_update', ['contact_id' => $contact->id, 'contact' => ['email' => $contact->email, 'name' => $contact->name, 'consent_status' => 'revoked']])['data']['contact'];
        $this->assertSame('revoked', $revoked['consent_status']);
        $before = $contact->fresh()->only(['consent_status', 'consent_source', 'consented_at']);
        $suppression = $this->success($bearer, 'suppressions_add', ['email' => $contact->email])['data']['suppression'];
        $this->success($bearer, 'suppressions_remove', ['suppression_id' => $suppression['id']]);
        $this->assertEquals($before, $contact->fresh()->only(['consent_status', 'consent_source', 'consented_at']));
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'Consent fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]])['data']['campaign'];
        $this->failure($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']], 'validation_failed');
        $this->assertDatabaseCount('campaign_revisions', 0);
        $this->assertDatabaseCount('campaign_recipients', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_foreign_campaigns_sources_and_suppressions_never_cross_account_boundaries(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [$other, $foreign] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        [$foreignTemplate, $foreignSender, $foreignContact] = $this->sources($foreign->id);
        $foreignList = ContactList::create(['account_id' => $foreign->id, 'name' => 'Foreign synthetic list']);
        $fields = ['name' => 'Owned synthetic campaign', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]];
        foreach ([['template_id' => $foreignTemplate->id], ['sender_id' => $foreignSender->id], ['list_ids' => [$foreignList->id]], ['contact_ids' => [$foreignContact->id]]] as $replacement) {
            $this->failure($bearer, 'campaigns_create', ['campaign' => array_replace($fields, $replacement)], 'not_found');
            $this->assertDatabaseCount('campaigns', 0);
        }
        $campaign = app(CampaignService::class)->save($foreign, $other->id, null, null, ['name' => 'Foreign synthetic campaign', 'template_id' => $foreignTemplate->id, 'sender_id' => $foreignSender->id, 'list_ids' => [], 'contact_ids' => [$foreignContact->id]]);
        $before = $campaign->getAttributes();
        foreach ([
            'campaigns_get' => ['campaign_id' => $campaign->id],
            'campaigns_update' => ['campaign_id' => $campaign->id, 'expected_version' => $campaign->version, 'campaign' => $fields],
            'campaigns_review' => ['campaign_id' => $campaign->id, 'expected_version' => $campaign->version],
            'campaigns_authorize' => ['campaign_id' => $campaign->id, 'expected_version' => $campaign->version, 'revision_id' => 1, 'confirmed' => true],
            'campaigns_pause' => ['campaign_id' => $campaign->id, 'expected_version' => $campaign->version],
            'campaigns_resume' => ['campaign_id' => $campaign->id, 'expected_version' => $campaign->version],
        ] as $operation => $arguments) {
            $this->failure($bearer, $operation, $arguments, 'not_found');
            $this->assertSame($before, $campaign->fresh()->getAttributes());
        }
        $suppression = Suppression::create(['account_id' => $foreign->id, 'normalized_email' => $foreignContact->email, 'source' => 'manual']);
        $this->failure($bearer, 'suppressions_remove', ['suppression_id' => $suppression->id], 'not_found');
        $this->assertSame([], $this->success($bearer, 'campaigns_list')['data']['items']);
        $this->assertSame([], $this->success($bearer, 'suppressions_list')['data']['items']);
        $this->assertNotNull($suppression->fresh());
        foreach (['campaign_revisions', 'campaign_recipients', 'deliveries', 'delivery_outbox', 'mail_messages'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_sender_input_and_review_projection_enforce_the_conditional_send_permission(): void
    {
        $this->mcpConfiguration();
        [$owner, $account] = $this->mcpAccount();
        [$writer] = $this->mcpAccount();
        $scopes = ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read'];
        Membership::create(['account_id' => $account->id, 'user_id' => $writer->id, 'role' => 'restricted', 'scopes' => $scopes]);
        [, , $bearer] = $this->mcpCredential($writer, $account, $scopes);
        [$template, $sender, $contact] = $this->sources($account->id);
        $fields = ['name' => 'Conditional sender fixture', 'template_id' => $template->id, 'list_ids' => [], 'contact_ids' => [$contact->id]];
        $this->failure($bearer, 'campaigns_create', ['campaign' => $fields + ['sender_id' => $sender->id]], 'forbidden');
        $this->assertDatabaseCount('campaigns', 0);
        $created = $this->success($bearer, 'campaigns_create', ['campaign' => $fields + ['sender_id' => null]])['data']['campaign'];
        $this->assertNull(Campaign::findOrFail($created['id'])->sender_id);
        $this->failure($bearer, 'campaigns_update', ['campaign_id' => $created['id'], 'expected_version' => $created['version'], 'campaign' => $fields + ['sender_id' => $sender->id]], 'forbidden');
        $this->assertSame($created['version'], Campaign::findOrFail($created['id'])->version);
        $owned = app(CampaignService::class)->save($account, $owner->id, $created['id'], $created['version'], $fields + ['sender_id' => $sender->id]);
        $this->failure($bearer, 'campaigns_review', ['campaign_id' => $owned->id, 'expected_version' => $owned->version, 'include_sender' => true], 'forbidden');
        $this->assertDatabaseCount('campaign_revisions', 0);
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $owned->id, 'expected_version' => $owned->version, 'include_sender' => false]);
        $this->assertArrayNotHasKey('sender', $review['data']['revision']);
        $this->assertSame('reviewed', $review['data']['campaign']['status']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_utc_authorization_rejects_invalid_minutes_and_preserves_a_future_schedule_on_resume(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'UTC fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]])['data']['campaign'];
        $review = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
        $arguments = ['campaign_id' => $campaign['id'], 'expected_version' => $review['data']['campaign']['version'], 'revision_id' => $review['data']['revision']['id'], 'confirmed' => true];
        $future = now('UTC')->addHours(2)->startOfMinute()->format('Y-m-d\TH:i');
        foreach (['2028-02-30T12:00', '1969-12-31T23:59', '0000-01-01T00:00', $future.'Z', $future.':00', $future.'+00:00', now('UTC')->subMinute()->format('Y-m-d\TH:i')] as $utc) {
            $this->failure($bearer, 'campaigns_authorize', $arguments + ['effective_utc' => $utc], 'validation_failed');
            $this->assertSame('reviewed', Campaign::findOrFail($campaign['id'])->status);
            $this->assertSame($arguments['expected_version'], Campaign::findOrFail($campaign['id'])->version);
        }
        $scheduled = $this->success($bearer, 'campaigns_authorize', $arguments + ['effective_utc' => $future])['data']['campaign'];
        $this->assertSame('scheduled', $scheduled['status']);
        $this->assertSame($future.':00.000000Z', $scheduled['effective_at']);
        $this->failure($bearer, 'campaigns_authorize', $arguments + ['effective_utc' => $future], 'version_conflict');
        $paused = $this->success($bearer, 'campaigns_pause', ['campaign_id' => $campaign['id'], 'expected_version' => $scheduled['version']])['data']['campaign'];
        $resumed = $this->success($bearer, 'campaigns_resume', ['campaign_id' => $campaign['id'], 'expected_version' => $paused['version']])['data']['campaign'];
        $this->assertSame('scheduled', $resumed['status']);
        $this->assertSame($scheduled['effective_at'], $resumed['effective_at']);
        $this->assertSame($review['data']['revision']['id'], $resumed['reviewed_revision_id']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
    }

    public function test_activity_ties_use_opaque_lexical_keys_and_literal_search_filters(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [, $sender, $contact] = $this->sources($account->id);
        foreach ([9, 10, 100, 101] as $id) {
            MailMessage::create(['id' => $id, 'account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $user->id,
                'mode' => 'log', 'status' => 'logged', 'sender_email' => $sender->email, 'recipient' => $contact->email,
                'subject' => $id === 101 ? 'Other 50ABC noise' : 'Exact 50%_! marker', 'origin' => 'test', 'created_at' => now('UTC')->startOfMinute()]);
        }
        $filters = ['search' => '50%_!'];
        $first = $this->success($bearer, 'activity_list', ['filters' => $filters, 'limit' => 1]);
        $this->assertSame(['message:9'], array_column($first['data']['items'], 'record_key'));
        $last = $this->success($bearer, 'activity_list', ['filters' => $filters, 'limit' => 100, 'cursor' => $first['page']['next_cursor']]);
        $this->assertSame(['message:100', 'message:10'], array_column($last['data']['items'], 'record_key'));
        $this->assertFalse($last['page']['has_more']);
        $this->assertSame('50%_!', $last['data']['filters']['search']);
        $this->failure($bearer, 'activity_list', ['filters' => ['search' => ' 50%_!'], 'cursor' => $first['page']['next_cursor']], 'cursor_invalid');
        $this->assertDatabaseCount('mail_messages', 4);
    }

    public function test_recipient_cursors_reject_another_owned_parent_or_grant_without_mixing_snapshots(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $second = Contact::create(['account_id' => $account->id, 'email' => 'second@example.test', 'name' => 'Synthetic second', 'consent_status' => 'granted', 'consent_source' => 'Synthetic form', 'consented_at' => now('UTC')->subDay()]);
        $ids = [];
        foreach (['First', 'Second'] as $name) {
            $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => $name.' cursor fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id, $second->id]]])['data']['campaign'];
            $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
            $ids[] = $campaign['id'];
        }
        $first = $this->success($bearer, 'campaigns_get', ['campaign_id' => $ids[0], 'include' => ['recipients' => ['limit' => 1]]]);
        $cursor = $first['data']['revision']['audience']['page']['next_cursor'];
        $this->assertIsString($cursor);
        $this->failure($bearer, 'campaigns_get', ['campaign_id' => $ids[1], 'include' => ['recipients' => ['cursor' => $cursor]]], 'cursor_invalid');
        [, , $otherGrant] = $this->mcpCredential($user, $account);
        $this->failure($otherGrant, 'campaigns_get', ['campaign_id' => $ids[0], 'include' => ['recipients' => ['cursor' => $cursor]]], 'cursor_invalid');
        $this->failure($bearer, 'campaigns_list', ['cursor' => $cursor], 'cursor_invalid');
        $last = $this->success($bearer, 'campaigns_get', ['campaign_id' => $ids[0], 'include' => ['sender' => true, 'recipients' => ['limit' => 100, 'cursor' => $cursor]]]);
        $this->assertSame([$second->id], array_column($last['data']['revision']['audience']['items'], 'contact_id'));
        $this->assertFalse($last['data']['revision']['audience']['page']['has_more']);
        $this->assertDatabaseCount('campaign_revisions', 2);
        $this->assertDatabaseCount('campaign_recipients', 4);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_recipient_wire_budget_preserves_every_row_and_static_projection_on_continuation(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $template->update(['subject' => 'Synthetic byte budget']);
        $ids = [$contact->id];
        foreach (range(2, 31) as $number) {
            $ids[] = Contact::create(['account_id' => $account->id, 'email' => 'byte'.$number.'@example.test', 'name' => str_repeat('é', 150), 'consent_status' => 'granted', 'consent_source' => 'Synthetic form', 'consented_at' => now('UTC')->subDay()])->id;
        }
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'Nested byte budget fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => $ids]])['data']['campaign'];
        $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
        $original = app(ResponseBudget::class);
        app()->instance(ResponseBudget::class, new ResponseBudget(9000));
        try {
            $seen = [];
            $cursor = null;
            $pages = 0;
            do {
                $recipientArguments = ['limit' => 25] + ($cursor === null ? [] : ['cursor' => $cursor]);
                $response = $this->rpc($bearer, 'tools/call', ['name' => 'campaigns_get', 'arguments' => (object) ['campaign_id' => $campaign['id'], 'include' => ['content' => true, 'recipients' => $recipientArguments]]])->assertOk()->assertJsonPath('result.isError', false);
                $this->assertLessThanOrEqual(9000, strlen($response->getContent()));
                $dto = $response->json('result.structuredContent');
                $this->assertSame($dto, json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR));
                $this->assertSame($template->html, $dto['data']['revision']['content']['html']);
                $audience = $dto['data']['revision']['audience'];
                $this->assertNotEmpty($audience['items']);
                $this->assertLessThan(25, count($audience['items']));
                $seen = [...$seen, ...array_column($audience['items'], 'contact_id')];
                $cursor = $audience['page']['next_cursor'];
                $this->assertSame($audience['page']['has_more'], $cursor !== null);
                $pages++;
                $this->assertLessThanOrEqual(count($ids), $pages);
            } while ($cursor !== null);
            $this->assertSame($ids, $seen);
            $this->assertSame($seen, array_values(array_unique($seen)));
            $this->assertGreaterThan(1, $pages);
            app()->instance(ResponseBudget::class, new ResponseBudget(300));
            $this->failure($bearer, 'campaigns_get', ['campaign_id' => $campaign['id'], 'include' => ['recipients' => (object) []]], 'result_too_large');
        } finally {
            app()->instance(ResponseBudget::class, $original);
        }
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 31);
        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_a_review_that_cannot_fit_rolls_back_before_commit_and_can_be_inspected_safely(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        [$template, $sender, $contact] = $this->sources($account->id);
        $campaign = $this->success($bearer, 'campaigns_create', ['campaign' => ['name' => 'Oversized review fixture', 'template_id' => $template->id, 'sender_id' => $sender->id, 'list_ids' => [], 'contact_ids' => [$contact->id]]])['data']['campaign'];
        $original = app(ResponseBudget::class);
        app()->instance(ResponseBudget::class, new ResponseBudget(300));
        try {
            $error = $this->failure($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']], 'result_too_large');
            $this->assertArrayNotHasKey('reference', $error['error']);
            $this->assertDatabaseCount('campaign_revisions', 0);
            $this->assertDatabaseCount('campaign_recipients', 0);
        } finally {
            app()->instance(ResponseBudget::class, $original);
        }
        $inspected = $this->success($bearer, 'campaigns_get', ['campaign_id' => $campaign['id']]);
        $this->assertSame($campaign, $inspected['data']['campaign']);
        $this->assertNull($inspected['data']['revision']);
        $reviewed = $this->success($bearer, 'campaigns_review', ['campaign_id' => $campaign['id'], 'expected_version' => $campaign['version']]);
        $this->assertSame('reviewed', $reviewed['data']['campaign']['status']);
        $this->assertDatabaseCount('campaign_revisions', 1);
        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertDatabaseCount('deliveries', 0);
    }

    private function sources(int $accountId): array
    {
        $template = EmailTemplate::create(['account_id' => $accountId, 'name' => 'Synthetic template', 'subject' => 'Hello {{ contact.name }}', 'html' => '<p>Hello {{ contact.name }} at {{ account.name }}</p>', 'text' => 'Hello {{ contact.email }}']);
        $sender = MailSender::create(['account_id' => $accountId, 'email' => 'sender@example.test', 'name' => 'Synthetic sender', 'lab_authorized' => true]);
        $contact = Contact::create(['account_id' => $accountId, 'email' => 'recipient@example.test', 'name' => '<Synthetic>', 'consent_status' => 'granted', 'consent_source' => 'Synthetic form', 'consented_at' => now('UTC')->subDay()]);

        return [$template, $sender, $contact];
    }

    private function success(string $bearer, string $name, array $arguments = []): array
    {
        $response = $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments])->assertOk();
        $error = json_decode($response->json('result.content.0.text') ?? '{}', true);
        $this->assertFalse($response->json('result.isError'), $name.' '.($error['error']['code'] ?? 'invalid_result'));
        $dto = $response->json('result.structuredContent');
        $this->assertSame($dto, json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR));

        return $dto;
    }

    private function failure(string $bearer, string $name, array $arguments, string $code): array
    {
        $response = $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments])->assertOk()->assertJsonPath('result.isError', true);
        $error = json_decode($response->json('result.content.0.text'), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($name, $error['error']['operation']);
        $this->assertSame($code, $error['error']['code']);
        $this->assertSame('not_applied', $error['error']['outcome']);
        $this->assertFalse($error['error']['can_repeat_operation']);
        $this->assertStringNotContainsString('synthetic-private-marker', $response->json('result.content.0.text'));

        return $error;
    }

    private function rpc(string $bearer, string $method, array $params = [])
    {
        $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
        $headers = ['Authorization' => 'Bearer '.$bearer, 'Accept' => 'application/json, text/event-stream', 'MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => $method];
        if ($method === 'tools/call') {
            $headers['Mcp-Name'] = $params['name'];
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }
}
