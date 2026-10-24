<?php

namespace Tests\Feature;

use App\Livewire\Campaigns;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignRevision;
use App\Models\ContactList;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Models\Suppression;
use App\Models\User;
use App\Services\TeamService;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\TestCase;

#[Group('delivery')]
class CampaignTest extends TestCase
{
    use InteractsWithCampaigns;
    use RefreshDatabase;

    public function test_draft_review_future_and_authorized_have_distinct_eligibility_without_transport(): void
    {
        $this->travelTo(now('UTC')->startOfMinute());
        $draft = $this->draft();
        $this->assertSame('draft', $draft->status);
        $this->assertNull($draft->authorized_at);
        $this->assertSame(0, $this->service->due($this->account)->count());
        $reviewed = $this->service->review($this->account, $this->owner->id, $draft->id, $draft->version);
        $this->assertSame('reviewed', $reviewed->status);
        $this->assertSame(0, $this->service->due($this->account)->count());
        $future = $this->authorize($reviewed, now('UTC')->addHours(2)->format('Y-m-d\TH:i'));
        $this->assertSame('scheduled', $future->status);
        $this->assertSame(0, $this->service->due($this->account)->count());
        $immediate = $this->authorize($this->reviewed());
        $this->assertSame('authorized', $immediate->status);
        $this->assertSame([$immediate->id], $this->service->due($this->account)->pluck('id')->all());
        $this->travel(2)->hours();
        $this->assertSame(2, $this->service->due($this->account)->count());
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_dates_reject_malformed_overflow_past_and_non_utc_input_without_partial_authorization(): void
    {
        $campaign = $this->reviewed();
        foreach (['', 'tomorrow', '2028-02-30T12:00', '2028-13-01T12:00', '2028-03-26T24:30', '2028-03-26T12:00Z', '2028-03-26T12:00+02:00', '2028-3-1T2:00', '2020-01-01T12:00', now('UTC')->format('Y-m-d\TH:i')] as $date) {
            try {
                $this->authorize($campaign, $date);
                $this->fail('Se aceptó una fecha no válida: '.$date);
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('effectiveUtc', $error->errors());
            }
            $this->assertSame('reviewed', $campaign->fresh()->status);
            $this->assertSame($campaign->version, $campaign->fresh()->version);
            $this->assertNull($campaign->fresh()->authorized_at);
        }
        $this->assertSame(0, AuditEvent::where('action', 'campaign.authorized')->count());
    }

    public function test_explicit_utc_is_preserved_across_a_local_daylight_saving_boundary(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-03-25T00:00:00Z'));
        $campaign = $this->authorize($this->reviewed(), '2028-03-26T02:30');
        $this->assertSame('2028-03-26 02:30:00', $campaign->effective_at->utc()->format('Y-m-d H:i:s'));
        Livewire::test(Campaigns::class)->call('open', $campaign->id)->assertSee('2028-03-26 02:30:00')->assertSee('UTC')->assertSee('Programada');
    }

    public function test_review_deduplicates_lists_and_freezes_eligible_consent_provenance(): void
    {
        $listA = ContactList::create(['account_id' => $this->account->id, 'name' => 'A']);
        $listB = ContactList::create(['account_id' => $this->account->id, 'name' => 'B']);
        $revoked = $this->newContact(['consent_status' => 'revoked']);
        $unknown = $this->newContact(['consent_status' => 'unknown', 'consent_source' => null, 'consented_at' => null]);
        $suppressed = $this->newContact();
        Suppression::create(['account_id' => $this->account->id, 'normalized_email' => $suppressed->email, 'source' => 'manual']);
        foreach ([$listA, $listB] as $list) {
            $list->contacts()->attach([$this->contact->id, $revoked->id, $unknown->id, $suppressed->id], ['account_id' => $this->account->id]);
        }
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$listA->id, $listB->id]]));
        $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $revision = CampaignRevision::sole();
        $this->assertSame(1, $revision->recipient_count);
        $this->assertSame(3, $revision->excluded_count);
        $snapshot = CampaignRecipient::sole();
        $this->assertSame($this->contact->id, $snapshot->contact_id);
        $this->assertSame('Formulario web', $snapshot->consent_source);
        $this->assertSame($this->contact->consented_at->format('Y-m-d H:i:s'), $snapshot->consented_at->format('Y-m-d H:i:s'));
        $this->authorize($campaign);
        $this->contact->update(['name' => 'Nombre cambiado', 'consent_status' => 'revoked']);
        $this->template->update(['html' => '<p>Nuevo contenido</p>']);
        $this->assertSame('Ana', $snapshot->fresh()->name);
        $this->assertSame('<p>Hola {{ contact.name }} de {{ account.name }}</p>', $revision->fresh()->html);
        $this->assertDatabaseCount('campaign_recipients', 1);
    }

    public function test_edit_invalidates_review_and_stale_review_authorization_or_save_has_no_effect(): void
    {
        $reviewed = $this->reviewed();
        $changed = $this->service->save($this->account, $this->owner->id, $reviewed->id, $reviewed->version, $this->input(['name' => 'Editada']));
        $this->assertSame('draft', $changed->status);
        $this->assertNull($changed->reviewed_revision_id);
        $this->assertSame($reviewed->version + 1, $changed->version);
        $this->assertRejected(409, fn () => $this->authorize($reviewed));
        $this->assertRejected(409, fn () => $this->service->review($this->account, $this->owner->id, $reviewed->id, $reviewed->version));
        $this->assertRejected(409, fn () => $this->service->save($this->account, $this->owner->id, $reviewed->id, $reviewed->version, $this->input()));
        $this->assertSame('Editada', $changed->fresh()->name);
        $this->assertDatabaseCount('campaign_revisions', 1);
    }

    public function test_changed_template_sender_account_or_audience_requires_new_review(): void
    {
        $campaign = $this->reviewed();
        $mutations = [
            fn () => $this->template->update(['text' => 'Contenido nuevo']),
            fn () => $this->sender->update(['name' => 'Otro nombre']),
            fn () => $this->account->update(['name' => 'Otro nombre de cuenta']),
            fn () => $this->contact->update(['consent_source' => 'Otro formulario']),
            fn () => $this->contact->update(['name' => 'Nombre actualizado']),
        ];
        foreach ($mutations as $mutate) {
            $mutate();
            $this->assertRejected(409, fn () => $this->authorize($campaign));
            $this->assertNull($campaign->fresh()->authorized_at);
            $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        }
        $this->assertSame('authorized', $this->authorize($campaign)->status);
    }

    public function test_added_list_member_after_review_cannot_be_authorized_unseen(): void
    {
        $list = ContactList::create(['account_id' => $this->account->id, 'name' => 'Lista']);
        $list->contacts()->attach($this->contact->id, ['account_id' => $this->account->id]);
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$list->id], 'contact_ids' => []]));
        $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $list->contacts()->attach($this->newContact()->id, ['account_id' => $this->account->id]);
        $this->assertRejected(409, fn () => $this->authorize($campaign));
        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertSame('reviewed', $campaign->fresh()->status);
    }

    public function test_review_rejects_no_eligible_recipients_and_invalid_rendering_without_snapshot_or_audit(): void
    {
        $campaign = $this->draft();
        foreach ([['consent_status' => 'revoked'], ['consent_status' => 'granted', 'consent_source' => null], ['consent_source' => 'Web', 'consented_at' => now()->addDay()]] as $fields) {
            $this->contact->update($fields);
            try {
                $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
                $this->fail('Se aceptó audiencia sin consentimiento vigente trazable.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('audience', $error->errors());
            }
        }
        $this->contact->update(['consent_status' => 'granted', 'consent_source' => 'Web', 'consented_at' => now()->subDay()]);
        $this->template->update(['html' => '<a href="{{ contact.email }}">No permitido</a>']);
        try {
            $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
            $this->fail('Se aceptó variable en atributo HTML.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('html', $error->errors());
        }
        $this->assertDatabaseCount('campaign_revisions', 0);
        $this->assertDatabaseCount('campaign_recipients', 0);
        $this->assertSame(0, AuditEvent::where('action', 'campaign.reviewed')->count());
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_authorization_requires_exact_review_and_deliberate_confirmation(): void
    {
        $campaign = $this->reviewed();
        foreach ([[false, $campaign->reviewed_revision_id], [true, $campaign->reviewed_revision_id + 1]] as [$confirm, $revision]) {
            $this->assertRejected(409, fn () => $this->service->authorizeCampaign($this->account, $this->owner->id, $campaign->id, $campaign->version, $revision, $confirm));
        }
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);
        $authorized = $this->authorize($campaign);
        $this->assertRejected(409, fn () => $this->service->save($this->account, $this->owner->id, $authorized->id, $authorized->version, $this->input()));
        $this->assertRejected(409, fn () => $this->authorize($campaign));
        $this->assertSame(1, AuditEvent::where('action', 'campaign.authorized')->count());
    }

    public function test_pause_resume_preserves_authorized_revision_audience_and_utc_date(): void
    {
        $authorized = $this->authorize($this->reviewed());
        $paused = $this->service->pause($this->account, $this->owner->id, $authorized->id, $authorized->version);
        $this->assertSame(0, $this->service->due($this->account)->count());
        $this->assertRejected(409, fn () => $this->service->pause($this->account, $this->owner->id, $authorized->id, $authorized->version));
        $this->contact->delete();
        $this->template->delete();
        $resumed = $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version);
        $this->assertSame('authorized', $resumed->status);
        $this->assertSame($authorized->reviewed_revision_id, $resumed->reviewed_revision_id);
        $this->assertTrue($authorized->effective_at->equalTo($resumed->effective_at));
        $this->assertDatabaseCount('campaign_recipients', 1);
        $this->assertDatabaseCount('mail_messages', 0);
        $paused = $this->service->pause($this->account, $this->owner->id, $resumed->id, $resumed->version);
        $this->sender->update(['lab_authorized' => false]);
        $this->assertRejected(409, fn () => $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version));
        $this->assertSame('paused', $paused->fresh()->status);
    }

    public function test_resume_uses_strict_sender_snapshot_values_after_mysql_json_key_reordering(): void
    {
        $this->sender->update(['name' => '01']);
        $campaign = $this->authorize($this->reviewed());
        $paused = $this->service->pause($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $resumed = $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version);
        $paused = $this->service->pause($this->account, $this->owner->id, $resumed->id, $resumed->version);
        $this->sender->update(['name' => '1']);
        $this->assertRejected(409, fn () => $this->service->resume($this->account, $this->owner->id, $paused->id, $paused->version));
        $this->assertSame('paused', $paused->fresh()->status);
    }

    public function test_foreign_campaign_and_source_ids_are_rejected_without_writes(): void
    {
        $other = Account::create(['name' => 'Cuenta ajena', 'owner_id' => User::factory()->create()->id]);
        $foreignTemplate = EmailTemplate::create(['account_id' => $other->id, 'name' => 'Secreto plantilla', 'subject' => 'Secreto asunto', 'html' => '', 'text' => 'Texto']);
        $foreignSender = MailSender::create(['account_id' => $other->id, 'email' => 'secret@example.test', 'name' => 'Secreto remitente', 'lab_authorized' => true]);
        $foreignList = ContactList::create(['account_id' => $other->id, 'name' => 'Secreto lista']);
        $foreignContact = $this->newContact(['account_id' => $other->id, 'name' => 'Secreto contacto']);
        foreach ([['template_id' => $foreignTemplate->id], ['sender_id' => $foreignSender->id], ['list_ids' => [$foreignList->id]], ['contact_ids' => [$foreignContact->id]]] as $fields) {
            try {
                $this->service->save($this->account, $this->owner->id, null, null, $this->input($fields));
                $this->fail('Se aceptó un recurso ajeno.');
            } catch (ModelNotFoundException) {
                $this->assertDatabaseCount('campaigns', 0);
            } catch (HttpException $error) {
                $this->assertSame(404, $error->getStatusCode());
            }
        }
        $foreign = Campaign::create(['account_id' => $other->id, 'name' => 'Secreto campaña', 'template_id' => $foreignTemplate->id, 'list_ids' => [], 'contact_ids' => []]);
        Livewire::test(Campaigns::class)->call('create')->assertDontSee('Secreto plantilla')->assertDontSee('Secreto remitente')->assertDontSee('Secreto lista')->assertDontSee('Secreto contacto')->assertDontSee('Secreto campaña')->call('open', $foreign->id)->assertNotFound();
        foreach (['review', 'pause', 'resume'] as $method) {
            try {
                $this->service->{$method}($this->account, $this->owner->id, $foreign->id, 1);
                $this->fail('Se aceptó campaña ajena.');
            } catch (ModelNotFoundException) {
                $this->assertSame('draft', $foreign->fresh()->status);
            }
        }
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_shared_campaign_survives_creator_revocation_and_sender_use_needs_no_settings_scope(): void
    {
        $scopes = ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read', 'mail.send'];
        $creator = $this->member($scopes);
        $campaign = $this->service->save($this->account, $creator->user_id, null, null, $this->input());
        $reviewed = $this->service->review($this->account, $creator->user_id, $campaign->id, $campaign->version);
        app(TeamService::class)->removeMember($creator->id);
        $this->assertRejected(403, fn () => $this->service->campaign($this->account, $creator->user_id, $campaign->id));
        $teammate = $this->member($scopes);
        $authorized = $this->service->authorizeCampaign($this->account, $teammate->user_id, $reviewed->id, $reviewed->version, $reviewed->reviewed_revision_id, true);
        $this->assertSame($creator->user_id, $authorized->created_by);
        $this->assertSame($teammate->user_id, $authorized->authorized_by);
        $this->assertSame($this->account->id, CampaignRevision::sole()->account_id);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $teammate->user_id, 'action' => 'campaign.authorized']);
        $this->actingAs($teammate->user);
        Livewire::test(Campaigns::class)->call('open', $authorized->id)->assertSee('sender@example.test')->assertSee('Formulario web');
    }

    public function test_scope_matrix_hides_selectors_content_and_audience_and_blocks_direct_actions(): void
    {
        $reviewed = $this->reviewed();
        $reader = $this->member(['campaigns.read']);
        $this->actingAs($reader->user);
        Livewire::test(Campaigns::class)->call('open', $reviewed->id)->assertSee('Noticias')->assertDontSee('Plantilla compartida')->assertDontSee('sender@example.test')->assertDontSee('Formulario web')->assertDontSee('Audiencia y consentimiento')->assertDontSee('Preview aislada')->assertDontSee('Confirmar autorización')->call('create')->assertForbidden();
        $this->assertRejected(403, fn () => $this->service->save($this->account, $reader->user_id, null, null, $this->input()));
        $this->assertRejected(403, fn () => $this->service->review($this->account, $reader->user_id, $reviewed->id, $reviewed->version));
        $this->assertRejected(403, fn () => $this->service->authorizeCampaign($this->account, $reader->user_id, $reviewed->id, $reviewed->version, $reviewed->reviewed_revision_id, true));
        foreach (['contacts.read', 'templates.read', 'campaigns.read', 'campaigns.write', 'mail.send'] as $missing) {
            $scopes = array_values(array_diff(['contacts.read', 'templates.read', 'campaigns.read', 'campaigns.write', 'mail.send'], [$missing]));
            $reader->update(['scopes' => $scopes]);
            $this->assertRejected(403, fn () => $this->service->authorizeCampaign($this->account, $reader->user_id, $reviewed->id, $reviewed->version, $reviewed->reviewed_revision_id, true));
        }
        $reader->update(['scopes' => ['campaigns.write', 'mail.send']]);
        $this->get('/campaigns')->assertForbidden();
        $this->assertSame('reviewed', $reviewed->fresh()->status);
        $this->assertSame(0, AuditEvent::where('action', 'campaign.authorized')->count());
    }

    public function test_revoked_mail_send_blocks_authorization_and_resume_but_allows_pausing(): void
    {
        $scopes = ['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read', 'mail.send'];
        $member = $this->member($scopes);
        $campaign = $this->reviewed();
        $member->update(['scopes' => array_values(array_diff($scopes, ['mail.send']))]);
        $this->assertRejected(403, fn () => $this->service->authorizeCampaign($this->account, $member->user_id, $campaign->id, $campaign->version, $campaign->reviewed_revision_id, true));
        $authorized = $this->authorize($campaign);
        $paused = $this->service->pause($this->account, $member->user_id, $authorized->id, $authorized->version);
        $this->assertRejected(403, fn () => $this->service->resume($this->account, $member->user_id, $paused->id, $paused->version));
        $this->assertSame('paused', $paused->fresh()->status);
        $this->assertSame(0, $this->service->due($this->account)->count());
    }

    public function test_writer_without_mail_send_can_save_draft_but_review_requires_a_configured_sender(): void
    {
        $member = $this->member(['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']);
        $this->actingAs($member->user);
        Livewire::test(Campaigns::class)->call('create')->set('name', 'Borrador editorial')->set('templateId', $this->template->id)->set('contactIds', [$this->contact->id])->call('save')->assertHasNoErrors()->assertSee('Borrador guardado')->assertDontSee('sender@example.test')->call('review')->assertHasErrors('senderId');
        $this->assertNull(Campaign::sole()->sender_id);
        $this->assertSame('draft', Campaign::sole()->status);
    }

    public function test_preparing_review_needs_no_mail_send_and_keeps_sender_private_until_authorization(): void
    {
        $campaign = $this->draft();
        $member = $this->member(['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']);
        $this->actingAs($member->user);
        Livewire::test(Campaigns::class)->call('open', $campaign->id)->set('name', 'Revisada por edición')->call('save')->assertHasNoErrors()->call('review')->assertHasNoErrors()->assertSee('Audiencia y consentimiento revisados')->assertSee('Formulario web')->assertDontSee('sender@example.test')->assertDontSee('Confirmar autorización')->set('confirmed', true)->call('authorizeCampaign')->assertForbidden();
        $reviewed = $campaign->fresh();
        $this->assertSame('reviewed', $reviewed->status);
        $this->assertNull($reviewed->authorized_at);
        $this->assertSame($member->user_id, CampaignRevision::sole()->reviewed_by);
        $this->assertSame('authorized', $this->authorize($reviewed)->status);
    }

    public function test_livewire_empty_error_success_review_and_authorize_states_are_visible_and_retry_clears_error(): void
    {
        $this->get('/campaigns')->assertOk()->assertSee('No hay campañas');
        $component = Livewire::test(Campaigns::class)->call('create')->call('save')->assertHasErrors()->assertSee('role="alert"', false)->assertSee('Actualizando campaña');
        $component->set('name', 'Boletín')->set('templateId', $this->template->id)->set('senderId', $this->sender->id)->set('contactIds', [$this->contact->id])->call('save')->assertHasNoErrors()->assertSee('Borrador guardado')->call('review')->assertHasNoErrors()->assertSee('Audiencia y consentimiento revisados')->assertSee('Formulario web')->assertSee('Confirmar autorización');
        $component->call('authorizeCampaign')->assertHasErrors('confirmed')->assertSee('role="alert"', false);
        $component->set('timing', 'future')->set('effectiveUtc', '2028-02-30T12:00')->set('confirmed', true)->call('authorizeCampaign')->assertHasErrors('effectiveUtc');
        $component->set('effectiveUtc', now('UTC')->addDays(2)->format('Y-m-d\TH:i'))->set('confirmed', true)->call('authorizeCampaign')->assertHasNoErrors()->assertSee('Autorización registrada')->assertSee('Programada')->assertDontSee('Composición del borrador');
        $this->assertSame('scheduled', Campaign::sole()->status);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_explicit_confirmation_survives_the_same_livewire_batch_as_future_timing_in_either_key_order(): void
    {
        $when = now('UTC')->addDays(2)->format('Y-m-d\TH:i');
        foreach ([
            ['confirmed' => true, 'timing' => 'future', 'effectiveUtc' => $when],
            ['effectiveUtc' => $when, 'timing' => 'future', 'confirmed' => true],
        ] as $updates) {
            $campaign = $this->reviewed();
            // set(array) performs separate requests; update exercises one real batch.
            Livewire::test(Campaigns::class)->call('open', $campaign->id)
                ->update(calls: [['method' => 'authorizeCampaign', 'params' => [], 'path' => '']], updates: $updates)
                ->assertHasNoErrors()->assertSee('Autorización registrada')->assertSee('Programada');
            $this->assertSame('scheduled', $campaign->fresh()->status);
            $this->assertSame($when, $campaign->fresh()->effective_at->utc()->format('Y-m-d\TH:i'));
        }
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_batched_timing_still_requires_confirmation_and_composition_changes_still_require_review(): void
    {
        app()->setLocale('es');
        $campaign = $this->reviewed();
        $when = now('UTC')->addDays(2)->format('Y-m-d\TH:i');
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id);
        $component->update(calls: [['method' => 'authorizeCampaign', 'params' => [], 'path' => '']],
            updates: ['confirmed' => false, 'timing' => 'future', 'effectiveUtc' => $when])
            ->assertHasErrors('confirmed')->assertSee('Debes marcar la confirmación para continuar.')
            ->assertDontSee('validation.accepted');
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);

        // Changing timing in a later request invalidates an earlier confirmation.
        $component->set('confirmed', true)->set('effectiveUtc', now('UTC')->addDays(3)->format('Y-m-d\TH:i'))
            ->assertSet('confirmed', false)->call('authorizeCampaign')->assertHasErrors('confirmed');
        $component->set('confirmed', true)->set('timing', 'now')->assertSet('confirmed', false);

        // A same-batch confirmation cannot authorize unreviewed composition.
        $component->update(calls: [['method' => 'authorizeCampaign', 'params' => [], 'path' => '']],
            updates: ['confirmed' => true, 'name' => 'Cambio pendiente de revisión'])
            ->assertStatus(409);
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_unsaved_client_changes_cannot_authorize_the_reviewed_snapshot_and_preview_is_sandboxed(): void
    {
        $this->template->update(['html' => '<p>Hola {{ contact.name }}</p><script id="evil">alert(1)</script>']);
        $campaign = $this->reviewed();
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id)->assertSee('sandbox=""', false)->assertSee('referrerpolicy="no-referrer"', false)->assertDontSee('<script id="evil">', false);
        $component->set('name', 'Cambios no guardados')->assertSee('Hay cambios sin guardar')->assertDontSee('Confirmar autorización')->set('confirmed', true)->call('authorizeCampaign')->assertStatus(409);
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);
    }

    public function test_recipient_details_are_paginated_and_not_serialized_as_livewire_public_state(): void
    {
        $list = ContactList::create(['account_id' => $this->account->id, 'name' => 'Audiencia']);
        for ($i = 0; $i < 12; $i++) {
            $list->contacts()->attach($this->newContact(['name' => 'Destinatario '.$i])->id, ['account_id' => $this->account->id]);
        }
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$list->id], 'contact_ids' => []]));
        $campaign = $this->service->review($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id)->assertViewHas('recipients', fn ($recipients) => $recipients->count() === 10 && $recipients->total() === 12 && ! $recipients->contains('name', 'Destinatario 11'))->assertSee('Siguiente');
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html(), LIBXML_NONET);
        $audience = (new DOMXPath($document))->query('//*[@data-testid="campaign-reviewed-audience"]')->item(0);
        $this->assertNotNull($audience);
        $this->assertStringNotContainsString('Destinatario 11', $audience->textContent);
        $component->call('setPage', 2, 'recipientsPage')->assertSee('Destinatario 11');
        $this->assertArrayNotHasKey('recipients', $component->snapshot['data']);
        $this->assertArrayNotHasKey('revision', $component->snapshot['data']);
        $this->assertArrayNotHasKey('html', $component->snapshot['data']);
    }

    public function test_stale_component_rejects_another_teammates_version_and_shows_reopen_action(): void
    {
        $campaign = $this->reviewed();
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id);
        $this->service->save($this->account, $this->owner->id, $campaign->id, $campaign->version, $this->input(['name' => 'Otra versión']));
        $component->call('$refresh')->assertSee('Otra persona cambió esta campaña')->assertSee('Volver a abrir la versión actual')->call('review')->assertStatus(409);
        $this->assertSame('Otra versión', $campaign->fresh()->name);
        $this->assertSame('draft', $campaign->fresh()->status);
    }

    public function test_explicit_audience_clear_recovers_same_draft_after_selected_list_and_contact_are_deleted(): void
    {
        $this->contact->update(['name' => 'Contacto eliminado de selección']);
        $removedList = ContactList::create(['account_id' => $this->account->id, 'name' => 'Lista eliminada de selección']);
        $campaign = $this->service->save($this->account, $this->owner->id, null, null, $this->input(['list_ids' => [$removedList->id]]));
        $alternative = $this->newContact(['name' => 'Contacto vigente alternativo']);
        $validList = ContactList::create(['account_id' => $this->account->id, 'name' => 'Lista vigente alternativa']);
        $validList->contacts()->attach($alternative->id, ['account_id' => $this->account->id]);
        $removedList->delete();
        $this->contact->delete();

        Livewire::test(Campaigns::class)->call('open', $campaign->id)->call('save')->assertNotFound();
        $this->assertSame([$removedList->id], $campaign->fresh()->list_ids);
        $this->assertSame([$this->contact->id], $campaign->fresh()->contact_ids);
        $this->assertSame($campaign->version, $campaign->fresh()->version);
        $audits = AuditEvent::count();
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id)
            ->assertSet('listIds', [$removedList->id])->assertSet('contactIds', [$this->contact->id])
            ->assertDontSee('Lista eliminada de selección')->assertDontSee('Contacto eliminado de selección')
            ->assertSee('Vaciar selección de destinatarios')->assertSee('wire:click="clearAudience"', false)
            ->call('clearAudience')->assertSet('listIds', [])->assertSet('contactIds', [])
            ->assertSet('compositionChanged', true)->assertSet('confirmed', false)->assertSee('Hay cambios sin guardar');
        $this->assertSame([$removedList->id], $campaign->fresh()->list_ids);
        $this->assertSame([$this->contact->id], $campaign->fresh()->contact_ids);
        $this->assertSame($campaign->version, $campaign->fresh()->version);
        $this->assertSame($audits, AuditEvent::count());
        $this->assertDatabaseCount('campaign_revisions', 0);
        $this->assertDatabaseCount('campaign_recipients', 0);

        $component->set('listIds', [$validList->id])->set('contactIds', [$alternative->id])->call('save')
            ->assertHasNoErrors()->assertSet('editing', $campaign->id)->assertSee('Borrador guardado')
            ->call('review')->assertHasNoErrors()->assertSee('Audiencia y consentimiento revisados');
        $this->assertDatabaseCount('campaigns', 1);
        $this->assertSame([$validList->id], $campaign->fresh()->list_ids);
        $this->assertSame([$alternative->id], $campaign->fresh()->contact_ids);
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);
        $this->assertSame($alternative->id, CampaignRecipient::sole()->contact_id);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_explicit_audience_clear_of_review_requires_save_and_review_before_authorization(): void
    {
        $campaign = $this->reviewed();
        $audits = AuditEvent::count();
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id)->set('confirmed', true)
            ->assertSee('Vaciar selección de destinatarios')->call('clearAudience')
            ->assertSet('listIds', [])->assertSet('contactIds', [])->assertSet('confirmed', false)
            ->assertSet('compositionChanged', true)->assertSee('Hay cambios sin guardar')->assertDontSee('Confirmar autorización');
        $this->assertSame($audits, AuditEvent::count());
        $this->assertSame($campaign->version, $campaign->fresh()->version);
        $this->assertSame($campaign->reviewed_revision_id, $campaign->fresh()->reviewed_revision_id);
        $this->assertSame([$this->contact->id], $campaign->fresh()->contact_ids);
        $this->assertDatabaseCount('campaign_recipients', 1);
        $component->set('confirmed', true)->call('authorizeCampaign')->assertStatus(409);
        $this->assertSame('reviewed', $campaign->fresh()->status);
        $this->assertNull($campaign->fresh()->authorized_at);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_explicit_audience_clear_rechecks_current_permissions_and_does_not_write_campaign(): void
    {
        $campaign = $this->draft();
        $member = $this->member(['campaigns.read', 'campaigns.write', 'contacts.read', 'templates.read']);
        $this->actingAs($member->user);
        $component = Livewire::test(Campaigns::class)->call('open', $campaign->id)->assertSee('Vaciar selección de destinatarios');
        $member->update(['scopes' => ['campaigns.read', 'campaigns.write', 'templates.read']]);
        $component->call('clearAudience')->assertForbidden();
        $this->assertSame([$this->contact->id], $campaign->fresh()->contact_ids);
        $this->assertSame($campaign->version, $campaign->fresh()->version);
        $this->assertSame('draft', $campaign->fresh()->status);
    }
}
