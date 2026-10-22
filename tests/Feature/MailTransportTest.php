<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\MailSettings;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountMailTransport;
use App\Services\MailSettingsService;
use App\Services\SmtpConnector;
use App\Support\SmtpUnconfirmed;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailTransportTest extends TestCase
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

    private function sender(Account $account, string $email = 'sender@example.test'): MailSender
    {
        return MailSender::create(['account_id' => $account->id, 'email' => $email, 'name' => 'Laboratorio', 'reply_to' => 'reply@example.test', 'lab_authorized' => true]);
    }

    private function settings(array $override = []): array
    {
        return array_replace(['mode' => 'log', 'smtp_host' => 'smtp.example.test', 'smtp_port' => 587, 'smtp_security' => 'starttls', 'smtp_username' => '', 'smtp_password' => ''], $override);
    }

    private function message(array $override = []): array
    {
        return array_replace(['recipient' => 'recipient@example.test', 'subject' => 'Prueba sintética', 'text' => 'Texto del laboratorio.', 'html' => '<p>Texto del laboratorio.</p>'], $override);
    }

    public function test_default_log_creates_private_eml_without_smtp_socket_and_download_is_scoped(): void
    {
        $this->owner();
        $account = Account::sole();
        $sender = $this->sender($account);
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => (int) substr(strrchr($address, ':'), 1)]);
        try {
            $message = app(MailSettingsService::class)->test($sender->id, $this->message());
            $this->assertSame('logged', $message->status);
            stream_set_blocking($server, false);
            $this->assertFalse(@stream_socket_accept($server, 0), 'Log no debe abrir ningún socket SMTP.');
        } finally {
            fclose($server);
        }
        $this->assertDatabaseCount('mail_settings', 0);
        Storage::disk('mail-artifacts')->assertExists($message->artifact_path);
        $eml = Storage::disk('mail-artifacts')->get($message->artifact_path);
        $this->assertStringContainsString('From: Laboratorio <sender@example.test>', $eml);
        $this->assertStringContainsString('To: recipient@example.test', $eml);
        $this->assertStringContainsString('Reply-To: reply@example.test', $eml);
        $this->assertStringContainsString('Content-Type: multipart/alternative', $eml);
        $download = $this->get(route('mail-artifacts.download', $message->id))->assertOk()->assertHeader('Content-Type', 'message/rfc822');
        $this->assertSame($eml, $download->streamedContent());
        $this->assertStringContainsString('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        $this->assertFalse(config('filesystems.disks.mail-artifacts.serve'));
        $foreign = $this->otherAccount();
        $foreignSender = $this->sender($foreign, 'other-sender@example.test');
        $foreignMessage = app(AccountMailTransport::class)->send($foreign, $foreignSender->id, $this->message());
        $this->get(route('mail-artifacts.download', $foreignMessage->id))->assertNotFound();
        $this->get('/mail-artifacts/not-a-number/download')->assertNotFound();
        $this->get('/storage/mail-artifacts/'.$message->artifact_path)->assertForbidden();
        $member = $this->member($account, ['settings.read']);
        $this->flushSession();
        $this->actingAs($member->user)->withSession(['account_id' => $account->id])->get(route('mail-artifacts.download', $message->id))->assertForbidden();
    }

    public function test_settings_defaults_and_read_only_render_do_not_persist_configuration(): void
    {
        $this->owner();
        $account = Account::sole();
        $member = $this->member($account, ['settings.read']);
        $this->actingAs($member->user);
        Livewire::test(MailSettings::class)->assertSee('Log privado')->assertDontSee('Guardar configuración')->assertDontSee('Nuevo remitente');
        $this->assertDatabaseCount('mail_settings', 0);
        Livewire::test(MailSettings::class)->call('saveSettings', $this->settings())->assertForbidden();
        $this->assertDatabaseCount('mail_settings', 0);
    }

    public function test_secrets_are_encrypted_hidden_and_never_reflected_even_after_invalid_save(): void
    {
        $this->owner();
        $username = 'synthetic-user-'.Str::uuid();
        $password = 'synthetic-password-'.Str::uuid();
        $component = Livewire::test(MailSettings::class)->call('saveSettings', $this->settings(['smtp_port' => 'invalid', 'smtp_username' => $username, 'smtp_password' => $password]))->assertHasErrors('smtp_port');
        $this->assertStringNotContainsString($username, $component->html());
        $this->assertStringNotContainsString($password, $component->html());
        $this->assertStringNotContainsString($username, json_encode($component->snapshot));
        $this->assertStringNotContainsString($password, json_encode($component->snapshot));
        $document = new \DOMDocument;
        $document->loadHTML($component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        $form = (new \DOMXPath($document))->query('//form[.//input[@name="smtp_password"]]')->item(0);
        $this->assertSame('post', strtolower($form->getAttribute('method')));
        $this->assertSame(route('mail-settings'), $form->getAttribute('action'));
        $this->assertDatabaseCount('mail_settings', 0);
        $component->call('saveSettings', $this->settings(['smtp_username' => $username, 'smtp_password' => $password]))->assertHasNoErrors();
        $settings = MailSetting::sole();
        $raw = DB::table('mail_settings')->sole();
        $this->assertNotSame($username, $raw->smtp_username);
        $this->assertNotSame($password, $raw->smtp_password);
        $this->assertSame($username, $settings->smtp_username);
        $this->assertSame($password, $settings->smtp_password);
        $this->assertArrayNotHasKey('smtp_username', $settings->toArray());
        $this->assertArrayNotHasKey('smtp_password', $settings->toArray());
        $this->assertStringNotContainsString($username, $component->html());
        $this->assertStringNotContainsString($password, $component->html());
        $this->assertStringNotContainsString($password, json_encode($component->snapshot));
        $audit = AuditEvent::where('action', 'mail.settings_changed')->sole();
        $this->assertSame(['mode' => 'log', 'external_enabled' => false], $audit->metadata);
        $component->call('saveSettings', $this->settings())->assertHasNoErrors();
        $this->assertSame($password, $settings->fresh()->smtp_password);
        $component->call('saveSettings', $this->settings(['clear_credentials' => true]))->assertHasNoErrors();
        $this->assertNull($settings->fresh()->smtp_username);
        $this->assertNull($settings->fresh()->smtp_password);
    }

    public function test_sender_forms_validate_header_types_and_retry_on_the_same_component(): void
    {
        $this->owner();
        $component = Livewire::test(MailSettings::class)->call('newSender')->set('senderEmail', "sender@example.test\r\nBcc: hidden@example.test")->call('saveSender')->assertHasErrors('email');
        $this->assertDatabaseCount('mail_senders', 0);
        $component->set('senderEmail', 'sender@example.test')->set('senderName', 'Nombre')->set('labAuthorized', true)->call('saveSender')->assertHasNoErrors();
        $sender = MailSender::sole();
        $this->assertTrue($sender->lab_authorized);
        $component->call('editSender', $sender->id)->set('senderName', "Nombre\nInjected")->call('saveSender')->assertHasErrors('name');
        $component->set('senderName', 'Corregido')->call('saveSender')->assertHasNoErrors();
        $this->assertSame('Corregido', $sender->fresh()->name);
        $component->call('deleteSender', $sender->id)->assertHasNoErrors();
        $this->assertSoftDeleted($sender);
        foreach ([['email' => []], ['email' => 123], ['name' => []], ['reply_to' => 123]] as $bad) {
            try {
                app(MailSettingsService::class)->saveSender(null, array_replace(['email' => 'typed@example.test', 'name' => 'Persona', 'reply_to' => null, 'lab_authorized' => true], $bad));
                $this->fail('Se aceptó un tipo de cabecera inválido.');
            } catch (ValidationException) {
                $this->assertSame(0, MailSender::count());
            }
        }
    }

    public function test_unauthorized_foreign_and_revoked_sender_actions_have_no_effects(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = $this->otherAccount();
        $ownSender = $this->sender($account);
        $foreign = $this->sender($other, 'foreign@example.test');
        Livewire::test(MailSettings::class)->call('editSender', $foreign->id)->assertNotFound();
        Livewire::test(MailSettings::class)->set('editingSender', $foreign->id)->set('senderEmail', 'attack@example.test')->call('saveSender')->assertNotFound();
        Livewire::test(MailSettings::class)->call('deleteSender', $foreign->id)->assertNotFound();
        Livewire::test(MailSettings::class)->set('testSender', $foreign->id)->set('testRecipient', 'test@example.test')->call('test')->assertNotFound();
        $this->assertSame('foreign@example.test', $foreign->fresh()->email);
        $this->assertDatabaseCount('mail_messages', 0);
        $member = $this->member($account, ['settings.read', 'settings.write', 'mail.send']);
        $this->actingAs($member->user);
        $component = Livewire::test(MailSettings::class);
        $component->call('editSender', $ownSender->id)->set('senderName', 'Compartido')->call('saveSender')->assertHasNoErrors();
        $this->assertSame('Compartido', $ownSender->fresh()->name);
        $component->call('saveSettings', $this->settings())->assertHasNoErrors();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $member->user_id, 'action' => 'mail.settings_changed']);
        $member->update(['scopes' => ['settings.write', 'mail.send']]);
        $component->call('saveSettings', $this->settings(['mode' => 'mailpit']))->assertForbidden();
        $this->assertDatabaseCount('mail_settings', 1);
        $this->assertSame('log', MailSetting::sole()->mode);
        $member->update(['scopes' => ['settings.read']]);
        Livewire::test(MailSettings::class)->call('newSender')->assertForbidden();
        Livewire::test(MailSettings::class)->call('test')->assertForbidden();
        $member->delete();
        $this->get('/settings/mail')->assertForbidden();
    }

    public function test_account_transport_is_sessionless_and_never_uses_another_accounts_mode_or_sender(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = $this->otherAccount();
        $first = $this->sender($account);
        $second = $this->sender($other, 'second@example.test');
        $otherSettings = MailSetting::create(['account_id' => $other->id, 'mode' => 'log', 'smtp_host' => 'unreachable.example.test', 'smtp_port' => 465, 'smtp_security' => 'smtps']);
        auth()->forgetGuards();
        session()->flush();
        $transport = app(AccountMailTransport::class);
        $logged = $transport->send($account, $first->id, $this->message());
        $otherLogged = $transport->send($other, $second->id, $this->message());
        $this->assertSame('logged', $otherLogged->status);
        $this->assertStringStartsWith($other->id.'/', $otherLogged->artifact_path);
        $this->assertStringContainsString('second@example.test', Storage::disk('mail-artifacts')->get($otherLogged->artifact_path));
        $otherSettings->update(['mode' => 'external']);
        $blocked = $transport->send($other, $second->id, $this->message());
        $this->assertSame('logged', $logged->status);
        $this->assertSame($account->id, $logged->account_id);
        $this->assertSame('sender@example.test', $logged->sender_email);
        $this->assertStringStartsWith($account->id.'/', $logged->artifact_path);
        $this->assertSame('blocked', $blocked->status);
        $this->assertSame('external_not_enabled', $blocked->diagnostic);
        $this->assertSame('second@example.test', $blocked->sender_email);
        $this->assertNull($blocked->artifact_path);
        try {
            $transport->send($account, $second->id, $this->message());
            $this->fail('Se aceptó el remitente de otra cuenta.');
        } catch (ModelNotFoundException) {
            $this->assertSame(3, MailMessage::count());
        }
    }

    public function test_mailpit_real_acceptance_is_captured_and_is_never_labeled_delivery(): void
    {
        $this->owner();
        $account = Account::sole();
        $sender = $this->sender($account);
        // Stored external endpoint must not influence the trusted laboratory connection.
        app(MailSettingsService::class)->saveSettings($this->settings(['mode' => 'mailpit', 'smtp_host' => 'unreachable.example.test']));
        $recipient = 'transport-'.Str::uuid().'@example.test';
        $subject = 'Laboratorio '.Str::uuid();
        $message = app(MailSettingsService::class)->test($sender->id, $this->message(['recipient' => $recipient, 'subject' => $subject]));
        $this->assertSame('smtp_accepted', $message->status);
        $this->assertNull($message->artifact_path);
        $this->assertNull($message->diagnostic);
        $api = rtrim(config('emailing.mailpit.api_url'), '/');
        $messages = Http::timeout(5)->get($api.'/api/v1/search', ['query' => 'to:'.$recipient])->throw()->json('messages');
        $this->assertCount(1, $messages);
        $captured = Http::timeout(5)->get($api.'/api/v1/message/'.$messages[0]['ID'])->throw()->json();
        $this->assertSame($subject, $captured['Subject']);
        $this->assertSame($sender->email, $captured['From']['Address']);
        Livewire::test(MailSettings::class)->assertSee('SMTP aceptado; entrega no acreditada');
        $this->get(route('mail-artifacts.download', $message->id))->assertNotFound();
    }

    public function test_invalid_message_headers_and_external_mode_are_rejected_without_artifacts(): void
    {
        $this->owner();
        $account = Account::sole();
        $sender = $this->sender($account);
        foreach ([['recipient' => []], ['recipient' => 123], ['recipient' => "to@example.test\nCc: other@example.test"], ['subject' => "Title\r\nBcc: other@example.test"], ['subject' => str_repeat('a', 201)]] as $bad) {
            try {
                app(AccountMailTransport::class)->send($account, $sender->id, $this->message($bad));
                $this->fail('Se aceptó una cabecera inválida.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('mail_messages', 0);
            }
        }
        Livewire::test(MailSettings::class)->call('saveSettings', $this->settings(['mode' => 'external']))->assertHasErrors('mode');
        $sender->update(['lab_authorized' => false]);
        Livewire::test(MailSettings::class)->set('testSender', $sender->id)->set('testRecipient', 'test@example.test')->call('test')->assertForbidden();
        $this->assertSame([], Storage::disk('mail-artifacts')->allFiles());
    }

    public function test_smtp_failures_are_sanitized_and_keep_uncertainty_without_echoing_saved_secrets(): void
    {
        $this->owner();
        $sender = $this->sender(Account::sole());
        $secret = 'synthetic-'.Str::uuid();
        app(MailSettingsService::class)->saveSettings($this->settings(['mode' => 'mailpit', 'smtp_username' => $secret, 'smtp_password' => $secret]));
        $this->mock(SmtpConnector::class)->shouldReceive('send')->once()->andThrow(new SmtpUnconfirmed);
        $component = Livewire::test(MailSettings::class)->set('testSender', $sender->id)->set('testRecipient', 'test@example.test')->call('test')->assertHasNoErrors()->assertSee('Aceptación SMTP no confirmada.');
        $message = MailMessage::sole();
        $this->assertSame('smtp_unconfirmed', $message->status);
        $this->assertSame('smtp_unconfirmed', $message->diagnostic);
        $this->assertStringNotContainsString($secret, $component->html());
        $this->assertStringNotContainsString($secret, json_encode($component->snapshot));
        $this->assertStringNotContainsString($secret, json_encode($message->toArray()));
    }

    public function test_real_untrusted_tls_certificate_is_rejected_for_implicit_tls_and_starttls(): void
    {
        $this->owner();
        $runtime = base_path('tests/.runtime/tls-'.Str::uuid());
        mkdir($runtime, 0775, true);
        $openssl = PHP_OS_FAMILY === 'Windows' ? 'C:/laragon/bin/git/mingw64/bin/openssl.exe' : 'openssl';
        $certificate = $runtime.'/certificate.pem';
        $key = $runtime.'/key.pem';
        $generate = proc_open([$openssl, 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', $key, '-out', $certificate, '-days', '1', '-subj', '/CN=untrusted.example.test', '-addext', 'subjectAltName=DNS:untrusted.example.test'], [['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], ['file', $runtime.'/openssl.out', 'a'], ['file', $runtime.'/openssl.err', 'a']], $pipes);
        $this->assertSame(0, proc_close($generate));
        foreach (['smtps', 'starttls'] as $mode) {
            $ready = $runtime.'/'.$mode.'-ready.json';
            $result = $runtime.'/'.$mode.'-result.json';
            $server = proc_open([PHP_BINARY, base_path('tests/Fixtures/SmtpTlsServer.php'), $certificate, $key, $ready, $result, $mode], [['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'], ['file', $runtime.'/server.out', 'a'], ['file', $runtime.'/server.err', 'a']], $pipes, base_path(), null, ['create_new_console' => false]);
            try {
                $deadline = microtime(true) + 5;
                while (! is_file($ready) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($ready);
                $address = json_decode(file_get_contents($ready), true, flags: JSON_THROW_ON_ERROR)['address'];
                $port = (int) substr(strrchr($address, ':'), 1);
                $connector = app(SmtpConnector::class);
                try {
                    $connector->send(['host' => '127.0.0.1', 'port' => $port, 'security' => $mode, 'username' => 'synthetic-user', 'password' => 'synthetic-secret'], (new Email)->from('sender@example.test')->to('recipient@example.test')->subject('TLS probe')->text('Laboratorio'));
                    $this->fail('Se aceptó un certificado TLS no confiable.');
                } catch (SmtpUnconfirmed $e) {
                    $this->assertNull($e->getPrevious());
                    $this->assertStringNotContainsString('synthetic-secret', $e->getMessage());
                    $this->assertStringNotContainsString($runtime, $e->getMessage());
                }
                $deadline = microtime(true) + 5;
                while (! is_file($result) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertFileExists($result);
                $probe = json_decode(file_get_contents($result), true, flags: JSON_THROW_ON_ERROR);
                $this->assertTrue($probe['connected']);
                $this->assertFalse($probe['tls_accepted']);
                if ($mode === 'starttls') {
                    $this->assertTrue($probe['ehlo_seen']);
                    $this->assertTrue($probe['starttls_seen']);
                }
            } finally {
                if (proc_get_status($server)['running']) {
                    proc_terminate($server);
                }
                proc_close($server);
            }
        }
    }
}
