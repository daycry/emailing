<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Templates;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\EmailTemplate;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\Membership;
use App\Models\User;
use App\Services\TemplateService;
use App\Support\TemplatePreview;
use App\Support\TemplateRenderer;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('mail-artifacts');
    }

    private function owner(): User
    {
        $owner = app(CreateNewUser::class)->create(['name' => 'Propietario', 'account_name' => 'Equipo A', 'email' => 'owner@example.test', 'password' => 'Correct-password-123', 'password_confirmation' => 'Correct-password-123']);
        $this->actingAs($owner)->withSession(['account_id' => $owner->memberships()->sole()->account_id]);

        return $owner;
    }

    private function otherAccount(): Account
    {
        return Account::create(['name' => 'Equipo B', 'owner_id' => User::factory()->create(['name' => 'Persona B', 'email' => 'other-'.Str::uuid().'@example.test'])->id]);
    }

    private function member(Account $account, array $scopes): Membership
    {
        return Membership::create(['account_id' => $account->id, 'user_id' => User::factory()->create(['name' => 'Colaborador', 'email' => 'member-'.Str::uuid().'@example.test'])->id, 'role' => 'restricted', 'scopes' => $scopes]);
    }

    private function fields(array $override = []): array
    {
        return array_replace(['name' => 'Bienvenida', 'subject' => 'Hola {{ contact.name }}', 'html' => '<p>Hola {{ contact.name }} de {{ account.name }}.</p><a href="https://example.test/news">Noticias</a>', 'text' => 'Hola {{ contact.name }} de {{ account.name }}. Email: {{ contact.email }}.'], $override);
    }

    private function template(Account $account, array $override = []): EmailTemplate
    {
        return EmailTemplate::create(['account_id' => $account->id] + $this->fields($override));
    }

    private function sender(Account $account, string $email = 'sender@example.test'): MailSender
    {
        return MailSender::create(['account_id' => $account->id, 'email' => $email, 'name' => 'Laboratorio', 'lab_authorized' => true]);
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $document;
    }

    public function test_authorized_teammates_share_crud_and_successful_retry_clears_errors(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        $component = Livewire::test(Templates::class)->call('create')->set('name', '')->call('save')->assertHasErrors('name');
        $this->assertDatabaseCount('email_templates', 0);
        $component->set('name', 'Compartida')->call('save')->assertHasNoErrors()->assertSee('Plantilla guardada');
        $template = EmailTemplate::sole();
        $member = $this->member($account, ['templates.read', 'templates.write']);
        $this->actingAs($member->user);
        Livewire::test(Templates::class)->call('open', $template->id)->set('name', 'Editada por el equipo')->call('save')->assertHasNoErrors();
        $this->assertSame('Editada por el equipo', $template->fresh()->name);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $owner->id, 'action' => 'template.saved']);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $member->user_id, 'action' => 'template.saved']);
        $other = $this->template($account, ['name' => 'Conservar']);
        Livewire::test(Templates::class)->call('delete', $template->id)->assertHasNoErrors();
        $this->assertModelMissing($template);
        $this->assertModelExists($other);
    }

    public function test_html_values_escape_quotes_and_tags_while_subject_and_text_stay_readable(): void
    {
        $this->owner();
        $service = app(TemplateService::class);
        $name = 'Ana & Luis "<b>"';
        $result = $service->preview(null, $this->fields(), ['name' => $name, 'email' => 'person@example.test']);
        $this->assertSame('Hola '.$name, $result['subject']);
        $this->assertStringContainsString(htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $result['html']);
        $this->assertStringContainsString('Hola '.$name, $result['text']);
        $this->assertStringNotContainsString('&amp;', $result['subject']);
        $missing = $service->preview(null, $this->fields(), []);
        $this->assertSame('Hola ', $missing['subject']);
        $this->assertStringContainsString('de Equipo A', $missing['text']);
        $literal = app(TemplateRenderer::class)->render('{{ contact.name }}', ['contact.name' => '{{ account.name }}', 'account.name' => 'NO RECURSIVO'], 'text');
        $this->assertSame('{{ account.name }}', $literal);
    }

    public function test_unknown_expressions_malformed_variables_and_attribute_contexts_are_rejected(): void
    {
        $this->owner();
        foreach (['<p>{{ smtp.password }}</p>', '<p>{{ contact.name', '<p>name }}</p>', '<p>{{ 7 * 7 }}</p>', '<a href="{{ contact.email }}">Link</a>', '<p style="color:{{ contact.name }}">Text</p>', '<script>{{ contact.name }}</script>', '<!-- {{ contact.name }} -->'] as $html) {
            try {
                app(TemplateService::class)->save(null, $this->fields(['html' => $html]));
                $this->fail('Se aceptó una expresión no admitida.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('html', $e->errors());
            }
        }
        $this->assertDatabaseCount('email_templates', 0);
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_expansion_limits_and_subject_headers_are_checked_after_substitution(): void
    {
        $this->owner();
        $cases = [
            [$this->fields(), ['name' => "Ana\r\nBcc: hidden@example.test"], 'subject'],
            [$this->fields(['subject' => str_repeat('a', 100).'{{ contact.name }}']), ['name' => str_repeat('b', 150)], 'subject'],
            [$this->fields(['html' => str_repeat('{{ contact.name }}', 201)]), [], 'html'],
            [$this->fields(['html' => str_repeat('á', 50001)]), [], 'html'],
            [$this->fields(['subject' => "Line\nInjected"]), [], 'subject'],
        ];
        foreach ($cases as [$fields, $sample, $field]) {
            try {
                app(TemplateService::class)->preview(null, $fields, $sample);
                $this->fail('Se aceptó una cabecera o tamaño inválido.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey($field, $e->errors());
            }
        }
        foreach ([[], 123, "\xFF"] as $badValue) {
            try {
                app(TemplateRenderer::class)->render('{{ contact.name }}', ['contact.name' => $badValue], 'text');
                $this->fail('Se aceptó un tipo o encoding de valor inválido.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('values', $e->errors());
            }
        }
        $this->assertDatabaseCount('mail_messages', 0);
    }

    public function test_preview_allowlist_removes_active_content_navigation_remote_urls_and_css_functions(): void
    {
        $this->owner();
        $hostile = '<meta http-equiv="refresh" content="0;url=https://outside.example.test"><base href="https://outside.example.test"><script id="evil">window.top.location="https://outside.example.test"</script><svg onload="alert(1)"><foreignObject><p>svg</p></foreignObject></svg><math><mtext>math</mtext></math><form action="https://outside.example.test"><input name="x"><button>Send</button></form><iframe src="https://outside.example.test"></iframe><object data="https://outside.example.test"></object><style>@import "https://outside.example.test";</style><a href="https://outside.example.test" target="_blank" onclick="alert(1)">Link text</a><img src="data:image/svg+xml;base64,PHN2Zz4=" onerror="alert(1)"><p style="color:#123456;background-image:url(https://outside.example.test);position:fixed;font-size:18px" onmouseover="alert(1)">Safe text</p>';
        $result = app(TemplateService::class)->preview(null, $this->fields(['html' => $hostile]), []);
        $document = $this->document($result['document']);
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//body//script|//body//svg|//body//math|//body//form|//body//iframe|//body//object|//body//style|//body//img|//body//a|//base|//meta[@http-equiv="refresh"]')->length);
        $this->assertSame(0, $xpath->query('//*[@href or @src or @target or @onclick or @onerror or @onmouseover]')->length);
        $this->assertStringNotContainsString('outside.example.test', $result['document']);
        $this->assertStringContainsString('Link text', $result['document']);
        $this->assertStringContainsString('color:#123456;font-size:18px', $result['document']);
        $this->assertStringNotContainsString('position:', $result['document']);
        $csp = $xpath->query('//head/meta[@http-equiv="Content-Security-Policy"]')->item(0);
        $this->assertSame(TemplatePreview::CSP, $csp->getAttribute('content'));
        foreach (["default-src 'none'", "script-src 'none'", "connect-src 'none'", "object-src 'none'", "frame-src 'none'", "base-uri 'none'", "form-action 'none'", "img-src 'none'"] as $directive) {
            $this->assertStringContainsString($directive, $csp->getAttribute('content'));
        }
        $this->assertStringContainsString('href="https://outside.example.test"', $result['html'], 'El HTML del email conserva sus enlaces; la preview se aísla por separado.');
    }

    public function test_editor_escapes_hostile_html_and_iframe_has_no_sandbox_permissions(): void
    {
        $this->owner();
        $hostile = '</textarea><script id="outer-attack">alert(1)</script><p>Visible</p>';
        $template = $this->template(Account::sole(), ['html' => $hostile]);
        $component = Livewire::test(Templates::class)->call('open', $template->id)->call('preview')->assertHasNoErrors();
        $document = $this->document($component->html());
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//script[@id="outer-attack"]')->length);
        $editor = $xpath->query('//textarea[@id="template-html"]')->item(0);
        $this->assertSame($hostile, $editor->textContent);
        $frame = $xpath->query('//iframe[@title="Preview aislada del email"]')->item(0);
        $this->assertTrue($frame->hasAttribute('sandbox'));
        $this->assertSame('', $frame->getAttribute('sandbox'));
        $this->assertSame('no-referrer', $frame->getAttribute('referrerpolicy'));
        $srcdoc = $frame->getAttribute('srcdoc');
        $this->assertStringContainsString('Content-Security-Policy', $srcdoc);
        $this->assertStringNotContainsString('outer-attack', $srcdoc);
        $this->assertLessThan(strpos($srcdoc, '<body>'), strpos($srcdoc, 'Content-Security-Policy'));
    }

    public function test_dom_parser_does_not_fetch_external_dtd_and_restores_libxml_error_mode(): void
    {
        $this->owner();
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        $previous = libxml_use_internal_errors(false);
        try {
            $result = app(TemplatePreview::class)->document('<!DOCTYPE html SYSTEM "http://'.$address.'/external.dtd"><html><body><p>Safe</p></body></html>');
            $this->assertFalse(libxml_use_internal_errors());
            stream_set_blocking($server, false);
            $this->assertFalse(@stream_socket_accept($server, 0));
            $this->assertStringNotContainsString('external.dtd', $result);
            $this->assertStringContainsString('<p>Safe</p>', $result);
        } finally {
            libxml_use_internal_errors($previous);
            fclose($server);
        }
    }

    public function test_preview_is_invalidated_on_draft_change_and_invalid_to_valid_retry_clears_errors(): void
    {
        $this->owner();
        $component = Livewire::test(Templates::class)->call('create')->set('name', 'Borrador')->call('preview')->assertHasNoErrors()->assertSet('previewOpen', true);
        $component->set('html', '<p>{{ unsupported }}</p>')->assertSet('previewOpen', false);
        $component->call('preview')->assertHasErrors('html')->assertSet('previewOpen', false);
        $component->set('html', '<p>{{ contact.name }}</p>')->call('preview')->assertHasNoErrors()->assertSet('previewOpen', true);
        $component->call('save')->assertHasNoErrors()->assertSet('previewOpen', false);
        $this->assertSame('<p>{{ contact.name }}</p>', EmailTemplate::sole()->html);
    }

    public function test_foreign_template_ids_are_denied_in_open_save_delete_preview_and_test(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = $this->otherAccount();
        $foreign = $this->template($other, ['name' => 'Secreta ajena']);
        $sender = $this->sender($account);
        Livewire::test(Templates::class)->assertDontSee('Secreta ajena')->call('open', $foreign->id)->assertNotFound();
        Livewire::test(Templates::class)->call('delete', $foreign->id)->assertNotFound();
        Livewire::test(Templates::class)->set('editing', $foreign->id)->assertNotFound();
        foreach (['save', 'preview', 'test'] as $action) {
            try {
                $service = app(TemplateService::class);
                match ($action) {
                    'save' => $service->save($foreign->id, $this->fields(['name' => 'Atacada'])),
                    'preview' => $service->preview($foreign->id, $this->fields(), []),
                    'test' => $service->test($foreign->id, $sender->id, 'recipient@example.test', []),
                };
                $this->fail('Se aceptó una plantilla de otra cuenta.');
            } catch (ModelNotFoundException) {
                $this->assertModelExists($foreign);
            }
        }
        $this->assertSame('Secreta ajena', $foreign->fresh()->name);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertSame(0, AuditEvent::where('action', 'template.saved')->count());
    }

    public function test_readers_cannot_mutate_and_revoked_read_blocks_before_writes_or_sends(): void
    {
        $this->owner();
        $account = Account::sole();
        $template = $this->template($account);
        $sender = $this->sender($account);
        $member = $this->member($account, ['templates.read']);
        $this->actingAs($member->user);
        Livewire::test(Templates::class)->call('open', $template->id)->call('preview')->assertHasNoErrors()->assertDontSee('Guardar plantilla')->assertDontSee('Crear prueba');
        foreach ([['create', []], ['save', []], ['delete', [$template->id]], ['test', []]] as [$method, $args]) {
            Livewire::test(Templates::class)->call($method, ...$args)->assertForbidden();
        }
        $member->update(['scopes' => ['templates.read', 'templates.write', 'mail.send']]);
        $component = Livewire::test(Templates::class)->call('open', $template->id)->set('name', 'No persistir')->set('senderId', $sender->id)->set('recipient', 'recipient@example.test');
        $member->update(['scopes' => ['templates.write', 'mail.send']]);
        $component->call('save')->assertForbidden();
        $this->assertSame('Bienvenida', $template->fresh()->name);
        $this->assertDatabaseCount('mail_messages', 0);
        $this->get('/templates')->assertForbidden();
        $member->delete();
        $this->get('/templates')->assertForbidden();
    }

    public function test_developer_can_test_saved_template_without_settings_or_contacts_permissions(): void
    {
        $this->owner();
        $account = Account::sole();
        $template = $this->template($account);
        $sender = $this->sender($account);
        $member = $this->member($account, ['templates.read', 'mail.send']);
        $this->actingAs($member->user);
        $component = Livewire::test(Templates::class)->call('open', $template->id)->set('senderId', $sender->id)->set('recipient', 'invalid')->call('test')->assertHasErrors('recipient');
        $this->assertDatabaseCount('mail_messages', 0);
        $component->set('recipient', 'recipient@example.test')->set('sampleName', 'Ana & Luis')->set('html', '<p>UNSAVED FORGED BODY</p>')->call('test')->assertHasNoErrors()->assertSee('Prueba guardada en log privado');
        $message = MailMessage::sole();
        $this->assertSame('logged', $message->status);
        $this->assertSame($member->user_id, $message->actor_id);
        $this->assertSame('Hola Ana & Luis', $message->subject);
        $eml = Storage::disk('mail-artifacts')->get($message->artifact_path);
        $this->assertStringContainsString('https://example.test/news', $eml);
        $this->assertStringNotContainsString('UNSAVED FORGED BODY', $eml);
        $this->assertSame($this->fields()['html'], $template->fresh()->html);
        $this->assertStringNotContainsString('smtp_password', json_encode($component->snapshot));
        $foreignSender = $this->sender($this->otherAccount(), 'foreign-sender@example.test');
        $component->set('senderId', $foreignSender->id)->call('test')->assertNotFound();
        $this->assertDatabaseCount('mail_messages', 1);
    }

    public function test_pagination_search_and_missing_read_scope_keep_account_data_private(): void
    {
        $this->owner();
        $account = Account::sole();
        for ($i = 1; $i <= 12; $i++) {
            $this->template($account, ['name' => 'Template '.$i]);
        }
        $this->template($this->otherAccount(), ['name' => 'Foreign secret']);
        Livewire::test(Templates::class)->assertSee('Template 12')->assertDontSee('Template 1<', false)->assertDontSee('Foreign secret')->assertViewHas('templates', fn ($templates) => $templates->count() === 10 && $templates->total() === 12)
            ->call('setPage', 2)->assertSee('Template 1')->set('search', 'Template 12')->assertSee('Template 12')->assertViewHas('templates', fn ($templates) => $templates->total() === 1);
        $member = $this->member($account, ['templates.write', 'mail.send']);
        $this->actingAs($member->user)->get('/templates')->assertForbidden();
        Livewire::test(Templates::class)->assertForbidden();
    }
}
