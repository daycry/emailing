<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Contacts;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\ContactList;
use App\Models\Membership;
use App\Models\User;
use App\Services\ContactService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContactListTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = app(CreateNewUser::class)->create(['name' => 'Propietario', 'account_name' => 'Equipo A', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123']);
        $this->actingAs($owner)->withSession(['account_id' => $owner->memberships()->sole()->account_id]);

        return $owner;
    }

    private function member(Account $account, string $role, array $scopes = []): Membership
    {
        return Membership::create(['account_id' => $account->id, 'user_id' => User::factory()->create(['name' => 'Colaborador', 'email' => 'member-'.Str::uuid().'@example.test'])->id, 'role' => $role, 'scopes' => $scopes]);
    }

    private function contact(Account $account, string $email = 'person@example.test'): Contact
    {
        return Contact::create(['account_id' => $account->id, 'email' => $email, 'name' => 'Persona']);
    }

    private function list(Account $account, string $name = 'Boletín'): ContactList
    {
        return ContactList::create(['account_id' => $account->id, 'name' => $name]);
    }

    private function foreignAccount(): Account
    {
        return Account::create(['name' => 'Equipo ajeno', 'owner_id' => User::factory()->create(['name' => 'Persona ajena', 'email' => 'foreign-'.Str::uuid().'@example.test'])->id]);
    }

    public function test_review_fix1_mounted_ui_rechecks_read_before_each_mutation(): void
    {
        $this->owner();
        $account = Account::sole();
        $member = $this->member($account, 'restricted', ['contacts.read', 'contacts.write']);
        $this->actingAs($member->user);
        $effects = [];
        foreach (['saveContact', 'deleteContact', 'saveList', 'deleteList', 'associate', 'detach'] as $action) {
            $member->update(['scopes' => ['contacts.read', 'contacts.write']]);
            $contact = $this->contact($account, $action.'@example.test');
            $list = $this->list($account, 'Lista '.$action);
            if ($action === 'detach') {
                app(ContactService::class)->associate($list->id, $contact->id, true);
            }
            $component = Livewire::test(Contacts::class)
                ->set('editingContact', $contact->id)->set('email', $contact->email)->set('name', 'Cambió')
                ->set('editingList', $list->id)->set('listName', 'Cambió')->set('associationList', $list->id);
            $auditCount = AuditEvent::count();
            $member->update(['scopes' => ['contacts.write']]);
            $arguments = match ($action) {
                'deleteContact', 'associate' => [$contact->id],
                'deleteList' => [$list->id],
                'detach' => [$list->id, $contact->id],
                default => [],
            };
            $component->call($action, ...$arguments)->assertForbidden();
            $effects[$action] = [
                'name' => $contact->fresh()->name,
                'deleted' => $contact->fresh()->deleted_at !== null,
                'list_name' => $list->fresh()?->name,
                'membership' => DB::table('contact_list_memberships')->where('contact_list_id', $list->id)->exists(),
                'audits' => AuditEvent::count() - $auditCount,
            ];
            $expected[$action] = ['name' => 'Persona', 'deleted' => false, 'list_name' => 'Lista '.$action, 'membership' => $action === 'detach', 'audits' => 0];
        }
        $this->assertSame($expected, $effects, 'Una acción mutó datos antes del 403 de render.');
    }

    public function test_review_fix1_list_counts_use_indexed_ranges_with_ten_thousand_memberships(): void
    {
        $this->owner();
        $account = Account::sole();
        $rows = [];
        for ($i = 0; $i < 1000; $i++) {
            $rows[] = ['account_id' => $account->id, 'email' => 'range-'.$i.'@example.test', 'name' => 'Persona'];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('contacts')->insert($chunk);
        }
        $ids = Contact::pluck('id');
        for ($i = 0; $i < 10; $i++) {
            $list = $this->list($account, 'Carga '.$i);
            $rows = $ids->map(fn ($id) => ['account_id' => $account->id, 'contact_list_id' => $list->id, 'contact_id' => $id])->all();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('contact_list_memberships')->insert($chunk);
            }
        }
        $query = app(ContactService::class)->lists();
        $plan = json_decode(DB::selectOne('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings())->EXPLAIN, true, flags: JSON_THROW_ON_ERROR);
        $tables = [];
        $walk = function (array $node) use (&$walk, &$tables): void {
            if (isset($node['table_name'])) {
                $tables[] = $node;
            }
            foreach ($node as $value) {
                if (is_array($value)) {
                    $walk($value);
                }
            }
        };
        $walk($plan);
        $pivot = collect($tables)->firstWhere('table_name', 'contact_list_memberships');
        $start = hrtime(true);
        $counts = $query->get()->pluck('contacts_count')->all();
        $elapsed = (hrtime(true) - $start) / 1e9;
        $indexed = ($pivot['access_type'] ?? null) === 'ref' && ($pivot['used_key_parts'][0] ?? null) === 'contact_list_id';
        $path = base_path('tests/.runtime/t09-fix1-list-plan-'.($indexed ? 'after' : 'before').'.json');
        file_put_contents($path, json_encode(['memberships' => DB::table('contact_list_memberships')->count(), 'lists' => count($counts), 'counts' => $counts, 'query_seconds' => $elapsed, 'pivot' => $pivot, 'plan' => $plan], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->assertSame(array_fill(0, 10, 1000), $counts);
        $this->assertTrue($indexed, 'La subconsulta debe usar rangos ref por contact_list_id; plan real guardado.');
    }

    public function test_contact_and_list_crud_are_shared_with_authorized_teammates(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        Livewire::test(Contacts::class)->call('newContact')->set('email', '  PERSON@EXAMPLE.TEST  ')->set('name', 'Original')->call('saveContact')->assertHasNoErrors();
        Livewire::test(Contacts::class)->call('newList')->set('listName', 'Noticias')->set('listDescription', 'Novedades')->call('saveList')->assertHasNoErrors();
        $contact = Contact::sole();
        $list = ContactList::sole();
        $this->assertSame('person@example.test', $contact->email);
        $this->assertSame('unknown', $contact->consent_status);
        $this->assertNull($contact->consented_at);
        $teammate = $this->member($account, 'restricted', ['contacts.read', 'contacts.write']);
        $this->actingAs($teammate->user);
        $component = Livewire::test(Contacts::class)->assertSee('Original')->assertSee('Noticias')
            ->call('editContact', $contact->id)->set('name', 'Compartido')->call('saveContact')->assertHasNoErrors()
            ->call('editList', $list->id)->set('listName', 'Lista compartida')->call('saveList')->assertHasNoErrors()
            ->call('chooseAssociationList', $list->id)->call('associate', $contact->id)->assertHasNoErrors();
        $this->assertSame('Compartido', $contact->fresh()->name);
        $this->assertSame('Lista compartida', $list->fresh()->name);
        $this->assertDatabaseHas('contact_list_memberships', ['account_id' => $account->id, 'contact_list_id' => $list->id, 'contact_id' => $contact->id]);
        $component->call('selectList', $list->id)->assertSee('Compartido')->call('detach', $list->id, $contact->id)->assertHasNoErrors();
        $this->assertDatabaseCount('contact_list_memberships', 0);
        $this->assertModelExists($contact);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $owner->id, 'action' => 'contact.created']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $teammate->user_id, 'action' => 'contact.updated']);
        $component->call('deleteList', $list->id)->call('deleteContact', $contact->id)->assertHasNoErrors();
        $this->assertModelMissing($list);
        $this->assertSoftDeleted($contact);
    }

    public function test_contact_form_retry_clears_errors_and_requires_explicit_consent_evidence(): void
    {
        $this->owner();
        $component = Livewire::test(Contacts::class)->set('email', 'invalid')->call('saveContact')->assertHasErrors('email');
        $component->set('email', 'consent@example.test')->set('consentStatus', 'granted')->call('saveContact')->assertHasErrors(['consent_source', 'consented_at']);
        $this->assertDatabaseCount('contacts', 0);
        $component->set('consentSource', 'Formulario de alta')->set('consentedAt', '2025-01-10T12:30')->call('saveContact')->assertHasNoErrors()->assertSee('Contacto guardado.');
        $contact = Contact::sole();
        $this->assertSame('granted', $contact->consent_status);
        $this->assertSame('2025-01-10 12:30:00', $contact->consented_at->format('Y-m-d H:i:s'));
        $component->call('editContact', $contact->id)->set('name', 'Nombre editado')->call('saveContact')->assertHasNoErrors();
        $this->assertSame('2025-01-10 12:30:00', $contact->fresh()->consented_at->format('Y-m-d H:i:s'));
        $this->assertSame('Formulario de alta', $contact->fresh()->consent_source);
        $event = AuditEvent::where('action', 'contact.updated')->sole();
        $this->assertSame('granted', $event->metadata['previous_consent']['consent_status']);
        $component->call('editContact', $contact->id)->set('consentStatus', 'revoked')->call('saveContact')->assertHasNoErrors();
        $component->call('editContact', $contact->id)->set('consentStatus', 'granted')->set('consentSource', 'Nueva fuente')->set('consentedAt', '2025-01-10T12:30')->call('saveContact')->assertHasErrors('consent_status');
        $this->assertSame('revoked', $contact->fresh()->consent_status);
        $component->set('consentStatus', 'revoked')->set('name', 'Nuevo nombre')->call('saveContact')->assertHasNoErrors();
        $this->assertSame('Nuevo nombre', $contact->fresh()->name);
    }

    public function test_list_and_association_retry_clear_previous_errors_on_same_instance(): void
    {
        $this->owner();
        $component = Livewire::test(Contacts::class)->set('listName', '')->call('saveList')->assertHasErrors('name');
        $component->set('listName', 'Corregida')->call('saveList')->assertHasNoErrors()->assertSee('Lista guardada.');
        $list = ContactList::sole();
        $contact = $this->contact(Account::sole());
        $component->call('associate', $contact->id)->assertHasErrors('associationList');
        $this->assertDatabaseCount('contact_list_memberships', 0);
        $component->call('chooseAssociationList', $list->id)->call('associate', $contact->id)->assertHasNoErrors();
        $this->assertSame(1, $list->contacts()->count());
        $component->call('associate', $contact->id)->assertHasNoErrors();
        $this->assertSame(1, $list->contacts()->count());
    }

    public function test_future_consent_is_rejected_and_unknown_consent_stays_unknown_when_name_changes(): void
    {
        app()->setLocale('es');
        $this->owner();
        $component = Livewire::test(Contacts::class)->set('email', 'future@example.test')->set('consentStatus', 'granted')->set('consentSource', 'Formulario')->set('consentedAt', now()->addDay()->format('Y-m-d\TH:i'))->call('saveContact')->assertHasErrors('consented_at')
            ->assertSee('La fecha de consentimiento no puede ser futura.')->assertDontSee('validation.');
        $this->assertDatabaseCount('contacts', 0);
        $component->set('consentedAt', 'not-a-date')->call('saveContact')->assertHasErrors('consented_at')
            ->assertSee('Introduce una fecha y hora válidas para el consentimiento.')->assertDontSee('validation.');
        $this->assertDatabaseCount('contacts', 0);
        $component->set('consentStatus', 'unknown')->set('consentedAt', '')->call('saveContact')->assertHasNoErrors()
            ->assertDontSee('La fecha de consentimiento no puede ser futura.')
            ->assertDontSee('Introduce una fecha y hora válidas para el consentimiento.');
        $contact = Contact::sole();
        $component->call('editContact', $contact->id)->set('name', 'Nombre corregido')->call('saveContact')->assertHasNoErrors();
        $this->assertSame('unknown', $contact->fresh()->consent_status);
        $this->assertNull($contact->fresh()->consented_at);
        $this->assertNull($contact->fresh()->consent_source);
    }

    public function test_service_rejects_structured_or_numeric_email_and_consent_source_before_normalizing(): void
    {
        $this->owner();
        $service = app(ContactService::class);
        $valid = ['email' => 'valid@example.test', 'name' => 'Persona', 'consent_status' => 'granted', 'consent_source' => 'Formulario', 'consented_at' => '2025-01-10T12:30'];
        foreach ([['email', ['nested']], ['email', 123], ['consent_source', ['nested']], ['consent_source', 123], ['email', str_repeat('a', 100000).'@example.test']] as [$field, $value]) {
            try {
                $service->saveContact(null, array_replace($valid, [$field => $value]));
                $this->fail('Se aceptó una entrada con tipo o tamaño inválido.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
            $this->assertDatabaseCount('contacts', 0);
            $this->assertSame(0, AuditEvent::where('action', 'like', 'contact.%')->count());
        }
    }

    public function test_deleting_a_list_preserves_contacts_and_other_list_associations(): void
    {
        $this->owner();
        $account = Account::sole();
        $contact = $this->contact($account);
        $otherContact = $this->contact($account, 'other@example.test');
        $list = $this->list($account);
        $otherList = $this->list($account, 'Otra');
        $service = app(ContactService::class);
        $service->associate($list->id, $contact->id, true);
        $service->associate($otherList->id, $contact->id, true);
        $service->associate($otherList->id, $otherContact->id, true);
        Livewire::test(Contacts::class)->call('selectList', $list->id)->call('deleteList', $list->id)->assertHasNoErrors()->assertSet('listId', null);
        $this->assertSame(2, Contact::count());
        $this->assertSame(2, $otherList->contacts()->count());
        $service->deleteContact($contact->id);
        $this->assertSame(1, $otherList->contacts()->count());
        $this->assertModelExists($otherContact);
    }

    public function test_deleted_email_can_be_recreated_without_restoring_consent_or_history(): void
    {
        $this->owner();
        $service = app(ContactService::class);
        $old = $service->saveContact(null, ['email' => 'return@example.test', 'name' => 'Anterior', 'consent_status' => 'granted', 'consent_source' => 'Alta original', 'consented_at' => '2025-01-10T12:30']);
        $service->deleteContact($old->id);
        Livewire::test(Contacts::class)->set('email', 'RETURN@example.test')->call('saveContact')->assertHasNoErrors();
        $new = Contact::sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('unknown', $new->consent_status);
        $this->assertNull($new->consented_at);
        $this->assertNull($new->consent_source);
        $this->assertSame('granted', $old->fresh()->consent_status);
        $this->assertSoftDeleted($old);
        $this->assertSame(2, Contact::withTrashed()->count());
        $this->assertSame('Alta original', AuditEvent::where('action', 'contact.created')->oldest('id')->first()->metadata['consent']['consent_source']);
        Livewire::test(Contacts::class)->call('editContact', $old->id)->assertNotFound();
    }

    public function test_email_policy_keeps_accents_distinct_case_normalized_and_account_isolated(): void
    {
        $this->owner();
        Livewire::test(Contacts::class)->set('email', 'jose@example.test')->call('saveContact')->assertHasNoErrors();
        Livewire::test(Contacts::class)->set('email', 'josé@example.test')->call('saveContact')->assertHasNoErrors();
        $component = Livewire::test(Contacts::class)->set('email', 'JOSE@example.test')->call('saveContact')->assertHasErrors('email');
        $this->assertSame(2, Contact::count());
        $component->set('email', 'different@example.test')->call('saveContact')->assertHasNoErrors();
        $contact = Contact::where('email', 'jose@example.test')->sole();
        $component->call('editContact', $contact->id)->set('email', 'changed@example.test')->call('saveContact')->assertHasErrors('email');
        $this->assertSame('jose@example.test', $contact->fresh()->email);
        $foreign = $this->contact($this->foreignAccount(), 'jose@example.test');
        $this->assertModelExists($foreign);
    }

    public function test_mysql_rejects_duplicate_active_email_even_when_bypassing_service(): void
    {
        $this->owner();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $account = Account::sole();
        $this->contact($account);
        try {
            $this->contact($account);
            $this->fail('MySQL aceptó dos emails activos iguales.');
        } catch (QueryException $e) {
            $this->assertSame('23000', $e->errorInfo[0]);
            $this->assertSame(1062, $e->errorInfo[1]);
        }
        $this->assertSame(1, Contact::count());
    }

    public function test_mysql_composite_foreign_keys_reject_both_cross_account_associations(): void
    {
        $this->owner();
        $account = Account::sole();
        $foreign = $this->foreignAccount();
        $contact = $this->contact($account);
        $list = $this->list($account);
        $foreignContact = $this->contact($foreign, 'foreign@example.test');
        $foreignList = $this->list($foreign);
        foreach ([[$account->id, $list->id, $foreignContact->id], [$account->id, $foreignList->id, $contact->id], [$foreign->id, $list->id, $contact->id]] as [$accountId, $listId, $contactId]) {
            try {
                DB::table('contact_list_memberships')->insert(['account_id' => $accountId, 'contact_list_id' => $listId, 'contact_id' => $contactId]);
                $this->fail('MySQL aceptó una afiliación entre cuentas.');
            } catch (QueryException $e) {
                $this->assertSame('23000', $e->errorInfo[0]);
                $this->assertSame(1452, $e->errorInfo[1]);
            }
        }
        $this->assertDatabaseCount('contact_list_memberships', 0);
    }

    public function test_foreign_ids_cannot_be_read_edited_deleted_filtered_or_associated(): void
    {
        $this->owner();
        $account = Account::sole();
        $foreign = $this->foreignAccount();
        $contact = $this->contact($account);
        $list = $this->list($account);
        $foreignContact = $this->contact($foreign, 'secret@example.test');
        $foreignList = $this->list($foreign, 'Lista secreta');
        Livewire::test(Contacts::class)->assertDontSee('secret@example.test')->assertDontSee('Lista secreta');
        foreach ([['editContact', [$foreignContact->id]], ['deleteContact', [$foreignContact->id]], ['editList', [$foreignList->id]], ['deleteList', [$foreignList->id]], ['selectList', [$foreignList->id]], ['chooseAssociationList', [$foreignList->id]], ['detach', [$list->id, $foreignContact->id]], ['detach', [$foreignList->id, $contact->id]]] as [$action, $args]) {
            Livewire::test(Contacts::class)->call($action, ...$args)->assertNotFound();
        }
        Livewire::test(Contacts::class)->set('editingContact', $foreignContact->id)->set('email', 'secret@example.test')->call('saveContact')->assertNotFound();
        Livewire::test(Contacts::class)->set('editingList', $foreignList->id)->set('listName', 'Atacada')->call('saveList')->assertNotFound();
        Livewire::test(Contacts::class)->set('associationList', $foreignList->id)->call('associate', $contact->id)->assertNotFound();
        Livewire::test(Contacts::class)->set('associationList', $list->id)->call('associate', $foreignContact->id)->assertNotFound();
        Livewire::test(Contacts::class)->set('listId', $foreignList->id)->assertNotFound();
        $this->assertSame('Lista secreta', $foreignList->fresh()->name);
        $this->assertNull($foreignContact->fresh()->deleted_at);
        $this->assertDatabaseCount('contact_list_memberships', 0);
    }

    public function test_read_only_and_restricted_readers_cannot_mutate_any_resource(): void
    {
        $this->owner();
        $account = Account::sole();
        $contact = $this->contact($account);
        $list = $this->list($account);
        foreach ([['read-only', []], ['restricted', ['contacts.read']]] as [$role, $scopes]) {
            $member = $this->member($account, $role, $scopes);
            $this->actingAs($member->user);
            Livewire::test(Contacts::class)->assertSee('person@example.test')->assertDontSee('Nuevo contacto')->assertDontSee('Nueva lista');
            foreach ([['newContact', []], ['newList', []], ['editContact', [$contact->id]], ['editList', [$list->id]], ['saveContact', []], ['saveList', []], ['deleteContact', [$contact->id]], ['deleteList', [$list->id]], ['associate', [$contact->id]], ['detach', [$list->id, $contact->id]]] as [$action, $args]) {
                Livewire::test(Contacts::class)->call($action, ...$args)->assertForbidden();
            }
        }
        $this->assertSame(1, Contact::count());
        $this->assertModelExists($list);
        $this->assertDatabaseCount('contact_list_memberships', 0);
    }

    public function test_missing_read_scope_and_revoked_membership_block_listings_and_aggregates(): void
    {
        $this->owner();
        $account = Account::sole();
        $contact = $this->contact($account);
        $list = $this->list($account);
        foreach ([[], ['contacts.write'], ['templates.read']] as $scopes) {
            $member = $this->member($account, 'restricted', $scopes);
            $this->actingAs($member->user)->get('/contacts')->assertForbidden();
            Livewire::test(Contacts::class)->assertForbidden();
            foreach (['contacts', 'lists'] as $method) {
                try {
                    app(ContactService::class)->{$method}()->count();
                    $this->fail('Se permitió una consulta sin contacts.read.');
                } catch (HttpException $e) {
                    $this->assertSame(403, $e->getStatusCode());
                }
            }
        }
        $member = $this->member($account, 'restricted', ['contacts.read', 'contacts.write']);
        $this->actingAs($member->user);
        $component = Livewire::test(Contacts::class);
        $member->update(['scopes' => ['contacts.read']]);
        $component->call('deleteContact', $contact->id)->assertForbidden();
        $reader = Livewire::test(Contacts::class);
        $member->update(['scopes' => []]);
        $reader->set('search', 'person')->assertForbidden();
        $available = $this->foreignAccount();
        Membership::create(['account_id' => $available->id, 'user_id' => $member->user_id, 'role' => 'restricted', 'scopes' => ['contacts.read', 'contacts.write']]);
        $this->contact($available, 'available@example.test');
        $member->delete();
        $this->get('/contacts')->assertForbidden();
        $this->get('/accounts')->assertOk()->assertSee('Equipo ajeno')->assertDontSee('Contactos y listas');
        $this->post('/accounts/switch', ['account_id' => $available->id])->assertRedirect('/dashboard');
        $this->get('/contacts')->assertOk()->assertSee('available@example.test')->assertDontSee('person@example.test');
        Livewire::test(Contacts::class)->call('editContact', $contact->id)->assertNotFound();
        $this->assertModelExists($contact);
        $this->assertModelExists($list);
    }

    public function test_pagination_search_and_aggregates_stay_inside_active_account(): void
    {
        $this->owner();
        $account = Account::sole();
        $list = $this->list($account);
        for ($i = 1; $i <= 17; $i++) {
            $this->contact($account, 'person'.$i.'@example.test');
        }
        for ($i = 1; $i <= 10; $i++) {
            $this->list($account, 'Lista '.$i);
        }
        $foreign = $this->foreignAccount();
        $this->contact($foreign, 'secret@example.test');
        $this->list($foreign, 'Lista secreta');
        $contact = Contact::where('account_id', $account->id)->first();
        app(ContactService::class)->associate($list->id, $contact->id, true);
        $this->assertSame(17, app(ContactService::class)->contacts()->count());
        $this->assertSame(11, app(ContactService::class)->lists()->count());
        $this->assertSame(1, app(ContactService::class)->list($list->id)->contacts_count);
        Livewire::test(Contacts::class)->assertSee('person17@example.test')->assertDontSee('person1@example.test')->assertDontSee('Lista secreta')
            ->call('setPage', 2)->assertSee('person1@example.test')->assertDontSee('person17@example.test')
            ->set('search', 'person17@')->assertSee('person17@example.test')->assertDontSee('person16@example.test')
            ->call('setPage', 2, 'listsPage')->assertSee('Boletín');
        Livewire::test(Contacts::class)->call('selectList', $list->id)->assertSee($contact->email)->assertDontSee('secret@example.test');
    }
}
