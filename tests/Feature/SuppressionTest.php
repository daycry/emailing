<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Suppressions;
use App\Livewire\Templates;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\Suppression;
use App\Models\UnsubscribeToken;
use App\Models\User;
use App\Services\AccountMailTransport;
use App\Services\ContactImportService;
use App\Services\ContactService;
use App\Services\SuppressionService;
use App\Services\TemplateService;
use App\Support\TrustedUrl;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('delivery')]
class SuppressionTest extends TestCase
{
    use DatabaseMigrations;

    private User $owner;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('mail-artifacts');
        $this->owner = app(CreateNewUser::class)->create(['name' => 'Persona', 'account_name' => 'Equipo A', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123']);
        $this->account = $this->owner->memberships()->sole()->account;
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
    }

    private function service(): SuppressionService
    {
        return app(SuppressionService::class);
    }

    private function issue(string $email = 'recipient@example.test'): string
    {
        return $this->service()->issue($this->account, $this->owner->id, ['email' => $email]);
    }

    private function sender(Account $account): MailSender
    {
        return MailSender::create(['account_id' => $account->id, 'email' => 'sender@example.test', 'name' => 'Laboratorio', 'lab_authorized' => true]);
    }

    private function other(): Account
    {
        return Account::create(['name' => 'Equipo B', 'owner_id' => User::factory()->create(['email' => 'other@example.test'])->id]);
    }

    private function member(array $scopes): Membership
    {
        return Membership::create(['account_id' => $this->account->id, 'user_id' => User::factory()->create(['email' => 'member@example.test'])->id, 'role' => 'restricted', 'scopes' => $scopes]);
    }

    public function test_get_only_confirms_post_is_idempotent_and_response_never_discloses_identity(): void
    {
        $url = $this->issue(' Recipient@Example.test ');
        auth()->forgetGuards();
        session()->flush();
        $this->get($url)->assertOk()->assertSee('Confirmar baja')->assertDontSee('recipient@example.test')->assertDontSee('Equipo A')->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertDatabaseCount('suppressions', 0);
        $first = $this->post($url)->assertOk()->assertSee('Solicitud de baja registrada')->assertDontSee('recipient@example.test')->getContent();
        $second = $this->post($url)->assertOk()->getContent();
        $this->assertSame($first, $second);
        $this->assertDatabaseHas('suppressions', ['account_id' => $this->account->id, 'normalized_email' => 'recipient@example.test', 'source' => 'unsubscribe']);
        $this->assertSame(1, AuditEvent::where('action', 'suppression.created')->count());
        $this->assertNull(AuditEvent::where('action', 'suppression.created')->sole()->actor_id);
        $this->assertDatabaseCount('suppressions', 1);
    }

    public function test_both_opaque_token_and_signature_are_required_without_identity_in_rejections(): void
    {
        $url = $this->issue();
        $token = basename(parse_url($url, PHP_URL_PATH));
        $changed = ($token[0] === 'a' ? 'b' : 'a').substr($token, 1);
        $unknownButSigned = TrustedUrl::signedRoute('unsubscribe.show', ['token' => str_repeat('c', 64)]);
        $badSignature = preg_replace('/signature=./', 'signature=z', $url);
        foreach ([str_replace($token, $changed, $url), $unknownButSigned, $badSignature, strtok($url, '?'), $url.'&unexpected=1'] as $invalid) {
            $this->get($invalid)->assertNotFound()->assertDontSee('recipient@example.test')->assertDontSee('Equipo A');
            $this->post($invalid)->assertNotFound()->assertDontSee('recipient@example.test')->assertDontSee('Equipo A');
        }
        $this->assertDatabaseCount('suppressions', 0);
        $this->assertSame(0, AuditEvent::where('action', 'suppression.created')->count());
    }

    public function test_capabilities_are_hashed_hidden_unrecoverable_and_generated_from_trusted_origin(): void
    {
        config(['app.url' => 'https://trusted.example.test']);
        $response = $this->withHeader('Host', 'attacker.example.test')->withHeader('X-Forwarded-Host', 'attacker.example.test')->post(route('suppressions.issue', [], false), ['email' => 'recipient@example.test'])->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="enlace-baja.txt"');
        $url = trim($response->getContent());
        $token = basename(parse_url($url, PHP_URL_PATH));
        $record = UnsubscribeToken::sole();
        $this->assertStringStartsWith('https://trusted.example.test/unsubscribe/', $url);
        $this->assertSame(64, strlen($token));
        $this->assertSame(hash('sha256', $token), $record->getRawOriginal('token_hash'));
        $this->assertArrayNotHasKey('token_hash', $record->toArray());
        $this->assertStringNotContainsString('recipient', $url);
        $this->assertStringNotContainsString($token, AuditEvent::all()->toJson());
        $this->assertStringNotContainsString($record->token_hash, Livewire::test(Suppressions::class)->html());
        $this->assertStringNotContainsString($token, Livewire::test(Suppressions::class)->html());
        $this->get($url)->assertOk();
    }

    public function test_public_post_keeps_csrf_protection(): void
    {
        $url = $this->issue();
        // Laravel normally bypasses CSRF in unit tests; enable its real HTTP check here.
        $this->app->instance('env', 'production');
        $this->post($url)->assertStatus(419);
        $this->assertDatabaseCount('suppressions', 0);
        $this->app->instance('env', 'testing');
    }

    public function test_same_email_in_another_account_is_independent_and_no_session_is_needed_for_transport(): void
    {
        $other = $this->other();
        $firstSender = $this->sender($this->account);
        $secondSender = $this->sender($other);
        $url = $this->issue();
        $this->post($url)->assertOk();
        auth()->forgetGuards();
        session()->flush();
        $transport = app(AccountMailTransport::class);
        $first = $transport->send($this->account, $firstSender->id, ['recipient' => 'RECIPIENT@example.test', 'subject' => 'Prueba', 'text' => 'Mensaje']);
        $second = $transport->send($other, $secondSender->id, ['recipient' => 'recipient@example.test', 'subject' => 'Prueba', 'text' => 'Mensaje']);
        $this->assertSame('suppressed', $first->status);
        $this->assertNull($first->transport_started_at);
        $this->assertNull($first->artifact_path);
        $this->assertSame('logged', $second->status);
        $this->assertNotNull($second->transport_started_at);
        $this->assertFalse($this->service()->isSuppressed($other, 'recipient@example.test'));
        Storage::disk('mail-artifacts')->assertExists($second->artifact_path);
        $this->assertCount(1, Storage::disk('mail-artifacts')->allFiles());
    }

    public function test_suppressed_log_and_mailpit_never_open_smtp_or_write_artifacts_even_without_a_contact(): void
    {
        $sender = $this->sender($this->account);
        $this->service()->suppress($this->account, $this->owner->id, ['email' => 'recipient@example.test']);
        $this->assertDatabaseCount('contacts', 0);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => (int) substr(strrchr($address, ':'), 1)]);
        try {
            foreach (['log', 'mailpit'] as $mode) {
                MailSetting::updateOrCreate(['account_id' => $this->account->id], ['mode' => $mode]);
                $record = app(AccountMailTransport::class)->send($this->account, $sender->id, ['recipient' => ' Recipient@Example.test ', 'subject' => 'Suprimido', 'text' => 'No transportar']);
                $this->assertSame('suppressed', $record->status);
                $this->assertSame('recipient_suppressed', $record->diagnostic);
                $this->assertNull($record->transport_started_at);
                $this->assertNull($record->artifact_path);
            }
            stream_set_blocking($socket, false);
            $this->assertFalse(@stream_socket_accept($socket, 0));
            $this->assertSame([], Storage::disk('mail-artifacts')->allFiles());
        } finally {
            fclose($socket);
        }
    }

    public function test_suppression_survives_contact_delete_recreate_and_granted_consent(): void
    {
        $data = ['email' => ' Recipient@Example.test ', 'name' => 'Persona', 'consent_status' => 'granted', 'consent_source' => 'Formulario sintético', 'consented_at' => now()->subDay()->format('Y-m-d\TH:i')];
        $contact = app(ContactService::class)->saveContact(null, $data);
        $this->service()->suppress($this->account, $this->owner->id, ['email' => $data['email']]);
        app(ContactService::class)->deleteContact($contact->id);
        $replacement = app(ContactService::class)->saveContact(null, $data);
        $this->assertNotSame($contact->id, $replacement->id);
        $this->assertSame('granted', $replacement->consent_status);
        $this->assertTrue($this->service()->isSuppressed($this->account, $replacement->email));
        Storage::fake('contact-imports');
        $report = app(ContactImportService::class)->import(UploadedFile::fake()->createWithContent('contactos.csv', "email,name,consent_status,consent_source,consented_at\nrecipient@example.test,Otro nombre,granted,Formulario,2025-01-10T12:30\n"));
        $this->assertSame(1, $report->duplicates);
        $this->assertSame('Persona', $replacement->fresh()->name);
        $this->assertTrue($this->service()->isSuppressed($this->account, $replacement->email));
        $replacement->forceDelete();
        $this->assertDatabaseCount('suppressions', 1);
    }

    public function test_manual_suppression_retries_clean_visible_errors_and_are_idempotent(): void
    {
        $component = Livewire::test(Suppressions::class)->set('email', 'invalid')->call('suppress')->assertHasErrors('email');
        $this->assertDatabaseCount('suppressions', 0);
        $component->set('email', ' Recipient@Example.test ')->call('suppress')->assertHasNoErrors()->assertSee('Supresión guardada')->assertSet('email', '');
        $component->set('email', 'recipient@example.test')->call('suppress')->assertHasNoErrors();
        $this->assertDatabaseCount('suppressions', 1);
        $this->assertSame(1, AuditEvent::where('action', 'suppression.created')->count());
    }

    public function test_removing_the_only_suppression_on_the_last_page_shows_remaining_suppressions(): void
    {
        $suppressions = collect(range(1, 16))->map(fn ($number) => Suppression::create([
            'account_id' => $this->account->id,
            'normalized_email' => "recipient{$number}@example.test",
            'source' => 'manual',
        ]));
        $lastPageSuppression = $suppressions->first();
        $remaining = $suppressions->last();

        $component = Livewire::test(Suppressions::class)
            ->call('gotoPage', 2)
            ->assertSet('paginators.page', 2)
            ->assertSee($lastPageSuppression->normalized_email)
            ->assertDontSee($remaining->normalized_email);

        $component->call('remove', $lastPageSuppression->id)
            ->assertHasNoErrors()
            ->assertSee('Supresión retirada explícitamente')
            ->assertSee($remaining->normalized_email)
            ->assertDontSee($lastPageSuppression->normalized_email)
            ->assertDontSee('Todavía no hay direcciones suprimidas.')
            ->assertSet('paginators.page', 1);

        $this->assertDatabaseMissing('suppressions', ['id' => $lastPageSuppression->id]);
        $this->assertDatabaseCount('suppressions', 15);
        $this->assertSame(1, AuditEvent::where('action', 'suppression.removed')->count());
    }

    public function test_teammates_share_resources_but_foreign_ids_are_not_found_and_read_only_cannot_write(): void
    {
        $url = $this->issue();
        $own = $this->service()->suppress($this->account, $this->owner->id, ['email' => 'recipient@example.test']);
        $other = $this->other();
        $foreign = Suppression::create(['account_id' => $other->id, 'normalized_email' => 'foreign@example.test', 'source' => 'manual']);
        $foreignToken = UnsubscribeToken::create(['account_id' => $other->id, 'normalized_email' => 'foreign@example.test', 'token_hash' => hash('sha256', 'synthetic')]);
        $member = $this->member(['suppressions.read', 'suppressions.write']);
        $this->actingAs($member->user);
        Livewire::test(Suppressions::class)->assertSee('recipient@example.test')->assertDontSee('foreign@example.test')->call('remove', $foreign->id)->assertNotFound();
        Livewire::test(Suppressions::class)->call('revoke', $foreignToken->id)->assertNotFound();
        Livewire::test(Suppressions::class)->call('remove', $own->id)->assertHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $member->user_id, 'action' => 'suppression.removed']);
        $member->update(['role' => 'read-only', 'scopes' => []]);
        $audit = AuditEvent::count();
        Livewire::test(Suppressions::class)->assertDontSee('Guardar supresión')->assertDontSee('Descargar enlace de baja')->call('suppress')->assertForbidden();
        $this->post(route('suppressions.issue'), ['email' => 'other@example.test'])->assertForbidden();
        Livewire::test(Suppressions::class)->call('revoke', UnsubscribeToken::where('account_id', $this->account->id)->sole()->id)->assertForbidden();
        $this->assertSame($audit, AuditEvent::count());
        $this->get($url)->assertOk();
    }

    public function test_revoked_permissions_are_checked_on_the_same_component_before_any_effect(): void
    {
        $member = $this->member(['suppressions.read', 'suppressions.write']);
        $this->actingAs($member->user);
        $component = Livewire::test(Suppressions::class)->set('email', 'recipient@example.test');
        $member->update(['scopes' => ['suppressions.read']]);
        $audit = AuditEvent::count();
        $component->call('suppress')->assertForbidden();
        $this->assertDatabaseCount('suppressions', 0);
        $this->assertSame($audit, AuditEvent::count());
        $member->update(['scopes' => ['suppressions.write']]);
        $this->get(route('suppressions'))->assertForbidden();
        $this->post(route('suppressions.issue'), ['email' => 'recipient@example.test'])->assertForbidden();
        $this->assertDatabaseCount('unsubscribe_tokens', 0);
        $member->delete();
        $this->get(route('suppressions'))->assertForbidden();
    }

    public function test_link_revocation_is_idempotent_does_not_remove_suppression_and_links_do_not_expire(): void
    {
        $url = $this->issue();
        $this->travel(3)->years();
        $this->post($url)->assertOk();
        $token = UnsubscribeToken::sole();
        $this->service()->revoke($this->account, $this->owner->id, $token->id);
        $this->service()->revoke($this->account, $this->owner->id, $token->id);
        $this->get($url)->assertNotFound();
        $this->post($url)->assertNotFound();
        $this->assertDatabaseCount('suppressions', 1);
        $this->assertSame(1, AuditEvent::where('action', 'unsubscribe.revoked')->count());
    }

    public function test_explicit_removal_does_not_grant_consent_and_a_live_link_can_suppress_again(): void
    {
        $contact = Contact::create(['account_id' => $this->account->id, 'email' => 'recipient@example.test', 'name' => 'Persona', 'consent_status' => 'revoked']);
        $url = $this->issue();
        $this->post($url)->assertOk();
        $this->service()->remove($this->account, $this->owner->id, Suppression::sole()->id);
        $this->assertSame('revoked', $contact->fresh()->consent_status);
        $this->assertFalse($this->service()->isSuppressed($this->account, $contact->email));
        $this->post($url)->assertOk();
        $this->assertTrue($this->service()->isSuppressed($this->account, $contact->email));
    }

    public function test_feedback_limitations_are_visible_without_healthy_metrics(): void
    {
        Livewire::test(Suppressions::class)->assertSee('Feedback de entrega no disponible')->assertSee('Sus tasas son desconocidas')->assertSee('El correo externo continúa bloqueado')->assertDontSee('0 %');
    }

    public function test_typed_input_and_sql_uniqueness_are_enforced_without_partial_audits(): void
    {
        $audit = AuditEvent::count();
        foreach ([[], ['email' => []], ['email' => 123], ['email' => "recipient@example.test\r\nBcc: other@example.test"], ['email' => 'invalid']] as $input) {
            foreach (['suppress', 'issue'] as $method) {
                try {
                    $this->service()->{$method}($this->account, $this->owner->id, $input);
                    $this->fail('Se aceptó un destinatario inválido.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('email', $exception->errors());
                }
            }
        }
        $this->assertSame($audit, AuditEvent::count());
        $this->assertDatabaseCount('suppressions', 0);
        $this->assertDatabaseCount('unsubscribe_tokens', 0);
        $this->service()->suppress($this->account, $this->owner->id, ['email' => 'recipient@example.test']);
        $columns = DB::select("SHOW FULL COLUMNS FROM suppressions WHERE Field = 'normalized_email'");
        $this->assertSame('utf8mb4_bin', $columns[0]->Collation);
        try {
            DB::transaction(fn () => Suppression::create(['account_id' => $this->account->id, 'normalized_email' => 'recipient@example.test', 'source' => 'manual']));
            $this->fail('MySQL no aplicó la restricción única por cuenta e identidad.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0]);
        }
        $this->assertDatabaseCount('suppressions', 1);
    }

    public function test_template_test_scopes_are_preserved_and_suppression_guard_is_shared(): void
    {
        $sender = $this->sender($this->account);
        $template = EmailTemplate::create(['account_id' => $this->account->id, 'name' => 'Plantilla', 'subject' => 'Hola', 'text' => 'Hola {{contact.name}}', 'html' => '']);
        $this->service()->suppress($this->account, $this->owner->id, ['email' => 'recipient@example.test']);
        $member = $this->member(['templates.read', 'mail.send']);
        $this->actingAs($member->user);
        $result = app(TemplateService::class)->test($template->id, $sender->id, 'recipient@example.test', ['name' => 'Persona', 'email' => 'recipient@example.test']);
        $this->assertSame('suppressed', $result->status);
        $member->update(['scopes' => ['templates.read']]);
        Livewire::test(Templates::class)->call('test')->assertForbidden();
        $this->assertDatabaseCount('mail_messages', 1);
        $this->assertSame([], Storage::disk('mail-artifacts')->allFiles());
    }
}
