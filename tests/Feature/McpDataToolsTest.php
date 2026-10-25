<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactImportRow;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\Membership;
use App\Services\Mcp\ResponseBudget;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Tests\Concerns\McpFixtures;
use Tests\TestCase;

class McpDataToolsTest extends TestCase
{
    use DatabaseMigrations, McpFixtures;

    private const DATA_OPERATIONS = [
        'contacts_create', 'contacts_delete', 'contacts_get', 'contacts_list', 'contacts_update',
        'imports_get', 'imports_list',
        'lists_attach_contact', 'lists_create', 'lists_delete', 'lists_detach_contact', 'lists_get', 'lists_list', 'lists_update',
        'templates_create', 'templates_delete', 'templates_get', 'templates_list', 'templates_preview', 'templates_update',
    ];

    public function test_all_twenty_operations_use_owned_services_and_closed_outputs(): void
    {
        $this->mcpConfiguration();
        [$user, $foreignAccount] = $this->mcpAccount();
        [, $account] = $this->mcpAccount($user);
        [, , $bearer] = $this->mcpCredential($user, $account);
        $this->actingAs($user)->withSession(['account_id' => $foreignAccount->id]);
        $seen = [];
        $call = function (string $name, array $arguments = []) use ($bearer, &$seen): array {
            $response = $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
            $response->assertOk();
            $error = json_decode($response->json('result.content.0.text') ?? '{}', true);
            $this->assertSame(false, $response->json('result.isError'), $name.' failed: '.($error['error']['code'] ?? 'invalid_response'));
            $dto = $response->json('result.structuredContent');
            $this->assertSame($dto, json_decode($response->json('result.content.0.text'), true, 512, JSON_THROW_ON_ERROR));
            $this->assertNoInternalFields($dto);
            $this->assertFalse(str_contains(json_encode($dto, JSON_THROW_ON_ERROR), $bearer), 'Credential present in DTO');
            $seen[$name] = true;

            return $dto;
        };
        $contact = $call('contacts_create', ['contact' => ['email' => ' Contact@Example.test ', 'name' => 'Fixture', 'consent_status' => 'unknown']])['data']['contact'];
        $contactId = $contact['id'];
        $this->assertSame('contact@example.test', $contact['email']);
        $this->assertSame($contactId, $call('contacts_get', ['contact_id' => $contactId])['data']['contact']['id']);
        $updated = $call('contacts_update', ['contact_id' => $contactId, 'contact' => ['email' => 'contact@example.test', 'name' => 'Updated fixture', 'consent_status' => 'granted', 'consent_source' => 'Synthetic form', 'consented_at' => now()->subDay()->format('Y-m-d\TH:i')]]);
        $this->assertSame('granted', $updated['data']['contact']['consent_status']);
        $this->assertCount(1, $call('contacts_list', ['search' => 'contact@example.test'])['data']['items']);
        $listId = $call('lists_create', ['list' => ['name' => 'Fixture list', 'description' => '']])['data']['list']['id'];
        $this->assertSame('Renamed list', $call('lists_update', ['list_id' => $listId, 'list' => ['name' => 'Renamed list', 'description' => 'Fixture description']])['data']['list']['name']);
        $this->assertTrue($call('lists_attach_contact', ['list_id' => $listId, 'contact_id' => $contactId])['data']['attached']);
        $this->assertSame(1, $call('lists_get', ['list_id' => $listId])['data']['list']['contacts_count']);
        $this->assertSame($listId, $call('lists_list')['data']['items'][0]['id']);
        $this->assertFalse($call('lists_detach_contact', ['list_id' => $listId, 'contact_id' => $contactId])['data']['attached']);

        $report = ContactImport::create(['account_id' => $account->id, 'actor_id' => $user->id, 'destination_list_id' => null, 'source_path' => 'imports/synthetic.csv', 'total' => 3, 'imported' => 1, 'invalid' => 1, 'duplicates' => 1]);
        foreach (['imported', 'invalid', 'duplicate'] as $index => $status) {
            ContactImportRow::create(['account_id' => $account->id, 'contact_import_id' => $report->id, 'row_number' => $index + 2, 'email' => 'row'.$index.'@example.test', 'status' => $status, 'message' => 'Synthetic report row']);
        }
        $this->assertSame($report->id, $call('imports_list')['data']['items'][0]['id']);
        $rows = $call('imports_get', ['import_id' => $report->id, 'limit' => 2]);
        $this->assertSame([2, 3], array_column($rows['data']['items'], 'row_number'));
        $this->assertTrue($rows['page']['has_more']);
        $continued = $call('imports_get', ['import_id' => $report->id, 'cursor' => $rows['page']['next_cursor']]);
        $this->assertSame([4], array_column($continued['data']['items'], 'row_number'));
        $this->assertFalse($continued['page']['has_more']);

        $fields = ['name' => 'Fixture template', 'subject' => 'Hello {{contact.name}}', 'html' => '<p>Hello {{contact.name}}</p><img src="https://example.invalid/pixel"><script>alert(1)</script>'];
        $templateId = $call('templates_create', ['template' => $fields])['data']['template']['id'];
        $this->assertSame($fields['html'], $call('templates_get', ['template_id' => $templateId])['data']['template']['html']);
        $fields['name'] = 'Renamed template';
        $this->assertSame('Renamed template', $call('templates_update', ['template_id' => $templateId, 'template' => $fields])['data']['template']['name']);
        $summary = $call('templates_list')['data']['items'][0];
        $this->assertArrayNotHasKey('html', $summary);
        $this->assertArrayNotHasKey('text', $summary);
        $preview = $call('templates_preview', ['source' => ['type' => 'stored', 'template_id' => $templateId], 'sample' => ['name' => '<Fixture>', 'email' => 'sample@example.test']])['data']['preview'];
        $this->assertSame('Hello <Fixture>', $preview['subject']);
        $this->assertStringContainsString('&lt;Fixture&gt;', $preview['html']);
        $this->assertStringNotContainsString('<script', $preview['document']);
        $this->assertStringNotContainsString('example.invalid/pixel', $preview['document']);
        $this->assertTrue($call('templates_delete', ['template_id' => $templateId])['data']['deleted']);
        $this->assertNull(EmailTemplate::find($templateId));
        $this->assertTrue($call('lists_delete', ['list_id' => $listId])['data']['deleted']);
        $this->assertNull(ContactList::find($listId));
        $this->assertTrue($call('contacts_delete', ['contact_id' => $contactId])['data']['deleted']);
        $this->assertNull(Contact::find($contactId));
        $actual = array_keys($seen);
        sort($actual);
        $expected = self::DATA_OPERATIONS;
        sort($expected);
        $this->assertSame($expected, $actual);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
        $this->assertDatabaseHas('contacts', ['id' => $contactId, 'account_id' => $account->id]);
    }

    private function assertNoInternalFields(array $value): void
    {
        foreach ($value as $key => $item) {
            $this->assertNotContains($key, ['account_id', 'actor_id', 'source_path', 'destination_list_id', 'token', 'grant_id', 'client_id']);
            if (is_array($item)) {
                $this->assertNoInternalFields($item);
            }
        }
    }

    public function test_all_twenty_data_tools_are_discoverable_with_the_pinned_schemas(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $response = $this->rpc($bearer, 'tools/list', ['per_page' => 100])->assertOk();
        $tools = $response->json('result.tools');
        $dataTools = array_values(array_filter($tools, fn (array $tool): bool => in_array(explode('_', $tool['name'], 2)[0], ['contacts', 'imports', 'lists', 'templates'], true)));
        $names = array_column($dataTools, 'name');
        sort($names);
        $expected = self::DATA_OPERATIONS;
        sort($expected);
        $this->assertSame($expected, $names);
        $byName = array_column($dataTools, null, 'name');
        $contract = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($contract['operations'] as $operation) {
            if (in_array($operation['name'], self::DATA_OPERATIONS, true)) {
                $this->assertSame($operation['inputSchema'], $byName[$operation['name']]['inputSchema']);
                $this->assertSame($operation['outputSchema'], $byName[$operation['name']]['outputSchema']);
            }
        }
    }

    public function test_every_operation_rejects_unknown_fields_and_denies_direct_calls_without_scopes(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $full] = $this->mcpCredential($user, $account);
        [$restricted] = $this->mcpAccount();
        Membership::create(['account_id' => $account->id, 'user_id' => $restricted->id, 'role' => 'restricted', 'scopes' => []]);
        [, , $none] = $this->mcpCredential($restricted, $account, []);
        $this->rpc($none, 'tools/list')->assertOk()->assertJsonCount(0, 'result.tools');
        foreach (self::DATA_OPERATIONS as $name) {
            $this->error($this->toolCall($full, $name, ['unexpected' => 'private-fixture-marker']), $name, 'validation_failed');
            $this->error($this->toolCall($none, $name, []), $name, 'forbidden');
        }
        foreach (['contacts', 'contact_lists', 'contact_imports', 'email_templates', 'mail_messages', 'delivery_outbox'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_foreign_ids_never_read_or_mutate_the_other_account(): void
    {
        $this->mcpConfiguration();
        [$user, $foreign] = $this->mcpAccount();
        [, $owned] = $this->mcpAccount($user);
        [, , $bearer] = $this->mcpCredential($user, $owned);
        $this->actingAs($user)->withSession(['account_id' => $foreign->id]);
        $contact = Contact::create(['account_id' => $foreign->id, 'email' => 'foreign@example.test', 'name' => 'Foreign', 'consent_status' => 'unknown']);
        $ownContact = Contact::create(['account_id' => $owned->id, 'email' => 'owned@example.test', 'name' => 'Owned', 'consent_status' => 'unknown']);
        $list = ContactList::create(['account_id' => $foreign->id, 'name' => 'Foreign', 'description' => '']);
        $ownList = ContactList::create(['account_id' => $owned->id, 'name' => 'Owned', 'description' => '']);
        $template = EmailTemplate::create(['account_id' => $foreign->id, 'name' => 'Foreign', 'subject' => 'Fixture', 'html' => '<p>Fixture</p>', 'text' => 'Fixture']);
        $report = ContactImport::create(['account_id' => $foreign->id, 'actor_id' => $user->id, 'source_path' => 'imports/foreign.csv', 'total' => 0, 'imported' => 0, 'invalid' => 0, 'duplicates' => 0]);
        $cases = [
            ['contacts_get', ['contact_id' => $contact->id]],
            ['contacts_update', ['contact_id' => $contact->id, 'contact' => ['email' => $contact->email, 'name' => 'Changed', 'consent_status' => 'unknown']]],
            ['contacts_delete', ['contact_id' => $contact->id]],
            ['contacts_list', ['list_id' => $list->id]],
            ['lists_get', ['list_id' => $list->id]],
            ['lists_update', ['list_id' => $list->id, 'list' => ['name' => 'Changed', 'description' => '']]],
            ['lists_delete', ['list_id' => $list->id]],
            ['lists_attach_contact', ['list_id' => $list->id, 'contact_id' => $ownContact->id]],
            ['lists_attach_contact', ['list_id' => $ownList->id, 'contact_id' => $contact->id]],
            ['lists_detach_contact', ['list_id' => $list->id, 'contact_id' => $ownContact->id]],
            ['lists_detach_contact', ['list_id' => $ownList->id, 'contact_id' => $contact->id]],
            ['imports_get', ['import_id' => $report->id]],
            ['templates_get', ['template_id' => $template->id]],
            ['templates_update', ['template_id' => $template->id, 'template' => ['name' => 'Changed', 'subject' => 'Changed', 'text' => 'Changed']]],
            ['templates_delete', ['template_id' => $template->id]],
            ['templates_preview', ['source' => ['type' => 'stored', 'template_id' => $template->id]]],
        ];
        foreach ($cases as [$name, $arguments]) {
            $this->error($this->toolCall($bearer, $name, $arguments), $name, 'not_found');
        }
        $this->assertSame('Foreign', $contact->fresh()->name);
        $this->assertSame('Foreign', $list->fresh()->name);
        $this->assertSame('Foreign', $template->fresh()->name);
        $this->assertNull($contact->fresh()->deleted_at);
        $this->assertDatabaseCount('contact_list_memberships', 0);
    }

    public function test_common_domain_validation_preserves_withdrawal_email_identity_and_safe_preview(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $created = $this->success($this->toolCall($bearer, 'contacts_create', ['contact' => ['email' => 'revoked@example.test', 'name' => 'Fixture', 'consent_status' => 'revoked']]));
        $id = $created['data']['contact']['id'];
        $base = ['email' => 'revoked@example.test', 'name' => 'Fixture', 'consent_status' => 'revoked'];
        $this->error($this->toolCall($bearer, 'contacts_update', ['contact_id' => $id, 'contact' => array_replace($base, ['email' => 'another@example.test'])]), 'contacts_update', 'validation_failed');
        $this->error($this->toolCall($bearer, 'contacts_update', ['contact_id' => $id, 'contact' => array_replace($base, ['consent_status' => 'granted', 'consent_source' => 'Fixture form', 'consented_at' => now()->subDay()->format('Y-m-d\TH:i')])]), 'contacts_update', 'validation_failed');
        foreach ([['consent_source' => '   ', 'consented_at' => now()->subDay()->format('Y-m-d\TH:i')], ['consent_source' => 'Fixture form', 'consented_at' => now()->addDay()->format('Y-m-d\TH:i')], ['consent_source' => 'Fixture form', 'consented_at' => '2026-99-99T00:00']] as $proof) {
            $arguments = ['contact' => ['email' => 'new@example.test', 'name' => 'Fixture', 'consent_status' => 'granted'] + $proof];
            $this->error($this->toolCall($bearer, 'contacts_create', $arguments), 'contacts_create', 'validation_failed');
        }
        foreach ([['subject' => 'Fixture', 'text' => '{{contact.password}}'], ['subject' => 'Fixture', 'html' => '<a href="{{contact.name}}">Fixture</a>']] as $content) {
            $this->error($this->toolCall($bearer, 'templates_preview', ['source' => ['type' => 'inline', 'content' => $content]]), 'templates_preview', 'validation_failed');
        }
        $this->error($this->toolCall($bearer, 'templates_preview', ['source' => ['type' => 'inline', 'content' => ['subject' => 'Fixture', 'text' => 'Fixture']], 'sample' => ['email' => 'not-an-email']]), 'templates_preview', 'validation_failed');
        $this->assertDatabaseHas('contacts', ['id' => $id, 'email' => 'revoked@example.test', 'consent_status' => 'revoked']);
        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('delivery_outbox', 0);
    }

    public function test_write_only_contacts_remain_usable_and_templates_require_read_and_write(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount(null, 'restricted', ['contacts.write', 'templates.write']);
        [, , $bearer] = $this->mcpCredential($user, $account, ['contacts.write', 'templates.write']);
        $this->rpc($bearer, 'tools/list')->assertOk()->assertJsonCount(8, 'result.tools');
        $fields = ['email' => 'write-only@example.test', 'name' => 'Fixture', 'consent_status' => 'unknown'];
        $id = $this->success($this->toolCall($bearer, 'contacts_create', ['contact' => $fields]))['data']['contact']['id'];
        $fields['name'] = 'Changed';
        $this->success($this->toolCall($bearer, 'contacts_update', ['contact_id' => $id, 'contact' => $fields]));
        $listId = $this->success($this->toolCall($bearer, 'lists_create', ['list' => ['name' => 'Fixture', 'description' => '']]))['data']['list']['id'];
        $this->success($this->toolCall($bearer, 'lists_update', ['list_id' => $listId, 'list' => ['name' => 'Changed', 'description' => '']]));
        $this->success($this->toolCall($bearer, 'lists_attach_contact', ['list_id' => $listId, 'contact_id' => $id]));
        $this->success($this->toolCall($bearer, 'lists_detach_contact', ['list_id' => $listId, 'contact_id' => $id]));
        $this->success($this->toolCall($bearer, 'lists_delete', ['list_id' => $listId]));
        $this->success($this->toolCall($bearer, 'contacts_delete', ['contact_id' => $id]));
        foreach (['templates_create', 'templates_update', 'templates_delete'] as $name) {
            $this->error($this->toolCall($bearer, $name, []), $name, 'forbidden');
        }
        $this->assertDatabaseCount('email_templates', 0);
    }

    public function test_keyset_pages_change_size_without_duplicates_and_bind_filters_operation_grant_and_role(): void
    {
        $this->mcpConfiguration();
        [$user, $account, $member] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $ids = [];
        for ($index = 0; $index < 6; $index++) {
            $ids[] = Contact::create(['account_id' => $account->id, 'email' => 'page'.$index.'@example.test', 'name' => 'Alpha', 'consent_status' => 'unknown'])->id;
        }
        rsort($ids);
        $first = $this->success($this->toolCall($bearer, 'contacts_list', ['limit' => 2, 'search' => 'Alpha']));
        $cursor = $first['page']['next_cursor'];
        $second = $this->success($this->toolCall($bearer, 'contacts_list', ['limit' => 1, 'search' => 'Alpha', 'cursor' => $cursor]));
        $third = $this->success($this->toolCall($bearer, 'contacts_list', ['limit' => 3, 'search' => 'Alpha', 'cursor' => $second['page']['next_cursor']]));
        $actual = array_merge(array_column($first['data']['items'], 'id'), array_column($second['data']['items'], 'id'), array_column($third['data']['items'], 'id'));
        $this->assertSame($ids, $actual);
        $this->assertFalse($third['page']['has_more']);
        $this->assertNull($third['page']['next_cursor']);
        $this->assertNotSame($first['meta']['trace_id'], $second['meta']['trace_id']);
        $this->error($this->toolCall($bearer, 'contacts_list', ['search' => ' Alpha', 'cursor' => $cursor]), 'contacts_list', 'cursor_invalid');
        $this->error($this->toolCall($bearer, 'lists_list', ['cursor' => $cursor]), 'lists_list', 'cursor_invalid');
        [, , $otherGrant] = $this->mcpCredential($user, $account);
        $this->error($this->toolCall($otherGrant, 'contacts_list', ['search' => 'Alpha', 'cursor' => $cursor]), 'contacts_list', 'cursor_invalid');
        [, $otherAccount] = $this->mcpAccount($user);
        [, , $otherAccountBearer] = $this->mcpCredential($user, $otherAccount);
        $this->error($this->toolCall($otherAccountBearer, 'contacts_list', ['search' => 'Alpha', 'cursor' => $cursor]), 'contacts_list', 'cursor_invalid');
        $member->update(['role' => 'read-only']);
        $this->error($this->toolCall($bearer, 'contacts_list', ['search' => 'Alpha', 'cursor' => $cursor]), 'contacts_list', 'cursor_invalid');
    }

    public function test_cursor_expiry_canonical_ciphertext_and_trusted_key_rotation(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        for ($index = 0; $index < 3; $index++) {
            Contact::create(['account_id' => $account->id, 'email' => 'cursor'.$index.'@example.test', 'name' => 'Fixture', 'consent_status' => 'unknown']);
        }
        $cursor = $this->success($this->toolCall($bearer, 'contacts_list', ['limit' => 1]))['page']['next_cursor'];
        foreach ([$cursor.'=', 'invalid-cursor-wire', substr($cursor, 0, -1).($cursor[-1] === 'A' ? 'B' : 'A')] as $invalid) {
            $this->error($this->toolCall($bearer, 'contacts_list', ['cursor' => $invalid]), 'contacts_list', 'cursor_invalid');
        }
        $old = Crypt::getFacadeRoot();
        $rotated = new Encrypter(random_bytes(32), 'AES-256-CBC');
        $rotated->previousKeys([$old->getKey()]);
        try {
            Crypt::swap($rotated);
            $this->success($this->toolCall($bearer, 'contacts_list', ['cursor' => $cursor]));
            $rotated->previousKeys([]);
            $this->error($this->toolCall($bearer, 'contacts_list', ['cursor' => $cursor]), 'contacts_list', 'cursor_invalid');
        } finally {
            Crypt::swap($old);
        }
        try {
            $this->travel(901)->seconds();
            $this->error($this->toolCall($bearer, 'contacts_list', ['cursor' => $cursor]), 'contacts_list', 'cursor_expired');
        } finally {
            $this->travelBack();
        }
    }

    public function test_byte_cut_pages_keep_all_unreturned_rows_and_reject_an_unfittable_first_item(): void
    {
        $this->mcpConfiguration();
        [$user, $account] = $this->mcpAccount();
        [, , $bearer] = $this->mcpCredential($user, $account);
        $ids = [];
        for ($index = 0; $index < 12; $index++) {
            $ids[] = Contact::create(['account_id' => $account->id, 'email' => 'bytes'.$index.'@example.test', 'name' => str_repeat('é', 150), 'consent_status' => 'unknown'])->id;
        }
        rsort($ids);
        $this->app->instance(ResponseBudget::class, new ResponseBudget(9000));
        $actual = [];
        $cursor = null;
        $pages = 0;
        do {
            $response = $this->toolCall($bearer, 'contacts_list', ['limit' => 10, 'cursor' => $cursor]);
            $dto = $this->success($response);
            $this->assertLessThanOrEqual(9000, strlen($response->getContent()));
            $this->assertNotEmpty($dto['data']['items']);
            if ($pages === 0) {
                $this->assertLessThan(10, count($dto['data']['items']));
            }
            $actual = array_merge($actual, array_column($dto['data']['items'], 'id'));
            $cursor = $dto['page']['next_cursor'];
            $this->assertLessThanOrEqual(12, ++$pages);
        } while ($dto['page']['has_more']);
        $this->assertSame($ids, $actual);
        $this->app->instance(ResponseBudget::class, new ResponseBudget(100));
        $this->error($this->toolCall($bearer, 'contacts_list'), 'contacts_list', 'result_too_large');
        $this->assertSame(12, Contact::count());
    }

    public function test_import_cursors_bind_parent_validate_authenticated_payload_and_stop_at_token_expiry(): void
    {
        $this->travelTo(now()->startOfSecond());
        try {
            $this->mcpConfiguration();
            [$user, $account] = $this->mcpAccount();
            [$token, , $bearer] = $this->mcpCredential($user, $account);
            $token->update(['expires_at' => now()->addSeconds(30)]);
            $reports = [];
            for ($index = 0; $index < 2; $index++) {
                $report = ContactImport::create(['account_id' => $account->id, 'actor_id' => $user->id, 'source_path' => 'imports/synthetic.csv', 'total' => 2, 'imported' => 2, 'invalid' => 0, 'duplicates' => 0]);
                foreach ([2, 3] as $number) {
                    ContactImportRow::create(['account_id' => $account->id, 'contact_import_id' => $report->id, 'row_number' => $number, 'email' => 'row'.$number.'@example.test', 'status' => 'imported', 'message' => 'Fixture']);
                }
                $reports[] = $report;
            }
            $page = $this->success($this->toolCall($bearer, 'imports_get', ['import_id' => $reports[0]->id, 'limit' => 1]));
            $cursor = $page['page']['next_cursor'];
            $encoded = strtr($cursor, '-_', '+/');
            $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
            $plain = Crypt::decryptString($encoded);
            $payload = json_decode($plain, true, 32, JSON_THROW_ON_ERROR);
            $this->assertSame($token->expires_at->getTimestamp(), $payload['expires_at']);
            $this->assertSame(30, $payload['expires_at'] - $payload['issued_at']);
            $this->assertSame('row_number_asc', $payload['position']['kind']);
            $this->error($this->toolCall($bearer, 'imports_get', ['import_id' => $reports[1]->id, 'cursor' => $cursor]), 'imports_get', 'cursor_invalid');
            $malformed = [];
            $malformed[] = array_replace($payload, ['account_id' => (string) ($account->id + 1)]);
            $malformed[] = array_replace($payload, ['resource_hash' => str_repeat('0', 64)]);
            $malformed[] = array_replace($payload, ['expires_at' => $payload['issued_at'] + 901]);
            $malformed[] = array_replace($payload, ['issued_at' => $payload['issued_at'] + 31, 'expires_at' => $payload['issued_at'] + 40]);
            $malformed[] = array_replace($payload, ['position' => ['id' => '1', 'kind' => 'id_desc']]);
            $malformed[] = array_replace($payload, ['position' => ['kind' => 'row_number_asc', 'row_number' => '2']]);
            $malformed[] = $payload + ['unexpected' => true];
            $missing = $payload;
            unset($missing['position']);
            $malformed[] = $missing;
            foreach ($malformed as $data) {
                $wire = $this->encryptedCursor(json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->error($this->toolCall($bearer, 'imports_get', ['import_id' => $reports[0]->id, 'cursor' => $wire]), 'imports_get', 'cursor_invalid');
            }
            foreach ([substr($plain, 0, -1).',"version":1}', str_repeat('x', 1025)] as $invalidPlain) {
                $this->error($this->toolCall($bearer, 'imports_get', ['import_id' => $reports[0]->id, 'cursor' => $this->encryptedCursor($invalidPlain)]), 'imports_get', 'cursor_invalid');
            }
            $this->error($this->toolCall($bearer, 'imports_get', ['import_id' => $reports[0]->id, 'source_path' => 'imports/synthetic.csv']), 'imports_get', 'validation_failed');
            $this->travel(31)->seconds();
            $this->toolCall($bearer, 'imports_get', ['import_id' => $reports[0]->id, 'cursor' => $cursor])->assertUnauthorized()->assertJsonMissingPath('result.structuredContent');
        } finally {
            $this->travelBack();
        }
    }

    private function encryptedCursor(string $plain): string
    {
        return rtrim(strtr(Crypt::encryptString($plain), '+/', '-_'), '=');
    }

    private function toolCall(string $bearer, string $name, array $arguments = [])
    {
        return $this->rpc($bearer, 'tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    private function success($response): array
    {
        $response->assertOk();
        $error = json_decode($response->json('result.content.0.text') ?? '{}', true);
        $this->assertSame(false, $response->json('result.isError'), 'Tool failed: '.($error['error']['code'] ?? 'invalid_response'));

        return $response->json('result.structuredContent');
    }

    private function error($response, string $operation, string $code): array
    {
        $response->assertOk()->assertJsonPath('result.isError', true)->assertJsonMissingPath('result.structuredContent');
        $text = $response->json('result.content.0.text');
        $error = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame($operation, $error['error']['operation']);
        $this->assertSame($code, $error['error']['code']);
        $this->assertSame('not_applied', $error['error']['outcome']);
        $this->assertFalse($error['error']['can_repeat_operation']);
        $this->assertFalse(str_contains($text, 'private-fixture-marker'));

        return $error;
    }

    private function rpc(string $bearer, string $method, array $params = [])
    {
        $params['_meta'] = ['io.modelcontextprotocol/protocolVersion' => '2026-07-28', 'io.modelcontextprotocol/clientCapabilities' => (object) []];
        $headers = [
            'Authorization' => 'Bearer '.$bearer,
            'Accept' => 'application/json, text/event-stream',
            'MCP-Protocol-Version' => '2026-07-28',
            'Mcp-Method' => $method,
        ];
        if ($method === 'tools/call') {
            $headers['Mcp-Name'] = $params['name'];
        }

        return $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params], $headers);
    }
}
