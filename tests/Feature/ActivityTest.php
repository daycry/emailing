<?php

namespace Tests\Feature;

use App\Livewire\Activity;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryOutbox;
use App\Models\MailMessage;
use App\Models\MailSender;
use App\Models\MailSetting;
use App\Models\Membership;
use App\Models\Suppression;
use App\Models\User;
use App\Services\AccountMailTransport;
use App\Services\ActivityService;
use App\Services\DeliveryLifecycle;
use App\Services\DeliveryMaterializer;
use App\Services\MailSettingsService;
use App\Services\TemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Routing\Exceptions\StreamedResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Concerns\InteractsWithCampaigns;
use Tests\TestCase;

#[Group('delivery')]
class ActivityTest extends TestCase
{
    use DatabaseMigrations;
    use InteractsWithCampaigns { setUp as private campaignSetUp; }

    private ?Process $relay = null;

    private ?string $relayDirectory = null;

    protected function setUp(): void
    {
        $this->campaignSetUp();
        Storage::fake('mail-artifacts');
        config(['delivery.backoff_seconds' => 1, 'delivery.quotas.account.minute' => 1000, 'delivery.quotas.pool.minute' => 1000]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        if ($this->relay) {
            file_put_contents($this->relayDirectory.'/relay-stop', 'stop');
            $this->relay->wait();
            file_put_contents($this->relayDirectory.'/stdout.txt', $this->relay->getOutput());
            file_put_contents($this->relayDirectory.'/stderr.txt', $this->relay->getErrorOutput());
            file_put_contents($this->relayDirectory.'/receipt.json', json_encode(['pid' => $this->relay->getPid(), 'exit' => $this->relay->getExitCode(), 'command' => $this->relay->getCommandLine(), 'at_utc' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
        parent::tearDown();
    }

    private function record(string $name, array $facts): void
    {
        $loaded = [];
        foreach ([ActivityService::class, AccountMailTransport::class, DeliveryLifecycle::class, MailSettingsService::class, TemplateService::class, Activity::class] as $class) {
            $loaded[$class] = hash_file('sha256', (new \ReflectionClass($class))->getFileName());
        }
        $directory = getenv('T14_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t14/implementation');
        if (! is_dir($directory.'/protocol')) {
            mkdir($directory.'/protocol', 0700, true);
        }
        file_put_contents($directory.'/protocol/'.$name.'-'.Str::uuid().'.json', json_encode(['at_utc' => gmdate('c'), 'monotonic_ns' => hrtime(true), 'pid' => getmypid(), 'platform' => PHP_OS_FAMILY, 'run' => getenv('T14_EVIDENCE_RUN') ?: null, 'loaded' => $loaded] + $facts, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    private function event(): DeliveryOutbox
    {
        $campaign = $this->authorize($this->reviewed());
        app(DeliveryMaterializer::class)->materialize($this->account->id, $campaign->id);
        MailSetting::firstOrCreate(['account_id' => $this->account->id], ['mode' => 'log']);

        return DeliveryOutbox::where('delivery_id', Delivery::where('campaign_id', $campaign->id)->value('id'))->sole();
    }

    private function process(DeliveryOutbox $event): ?MailMessage
    {
        $claim = app(DeliveryLifecycle::class)->claim(['v' => 1, 'type' => 'delivery.ready', 'event_id' => $event->id, 'account_id' => $event->account_id, 'delivery_id' => $event->delivery_id, 'generation' => $event->generation]);
        $this->assertNotNull($claim);

        return app(AccountMailTransport::class)->sendDelivery($claim);
    }

    private function next(DeliveryOutbox $event): DeliveryOutbox
    {
        $delivery = Delivery::findOrFail($event->delivery_id);
        DB::table('deliveries')->where('id', $delivery->id)->update(['available_at' => DB::raw('DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 1 SECOND)')]);

        return DeliveryOutbox::where('delivery_id', $delivery->id)->where('generation', $delivery->generation)->where('type', 'delivery.ready')->sole();
    }

    private function relay(string $mode): void
    {
        $this->relayDirectory = (getenv('T14_EVIDENCE_DIRECTORY') ?: storage_path('phase3-t14/implementation')).'/relay-'.Str::uuid();
        mkdir($this->relayDirectory, 0700, true);
        file_put_contents($this->relayDirectory.'/mode', $mode);
        $source = base_path('tests/support/delivery-relay.py');
        copy($source, $this->relayDirectory.'/relay-source.py');
        $this->relay = new Process(['python', $source, $this->relayDirectory], base_path());
        $this->relay->setTimeout(90);
        file_put_contents($this->relayDirectory.'/request.json', json_encode(['command' => $this->relay->getCommandLine(), 'source_sha256' => hash_file('sha256', $source), 'at_utc' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $this->relay->start();
        file_put_contents($this->relayDirectory.'/attribution.json', json_encode(['pid' => $this->relay->getPid(), 'command' => $this->relay->getCommandLine(), 'at_utc' => gmdate('c')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $deadline = microtime(true) + 10;
        do {
            if (is_file($this->relayDirectory.'/relay-ready')) {
                $ready = json_decode(file_get_contents($this->relayDirectory.'/relay-ready'), true, flags: JSON_THROW_ON_ERROR);
                $this->assertIsInt($ready['port']);
                $this->assertGreaterThan(0, $ready['port']);
                $this->assertLessThan(65536, $ready['port']);
                config(['emailing.mailpit.host' => '127.0.0.1', 'emailing.mailpit.port' => $ready['port'], 'emailing.smtp_timeout' => .5]);

                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline && $this->relay->isRunning());
        $this->fail('Owned local relay did not become ready.');
    }

    private function message(array $fields = []): MailMessage
    {
        return MailMessage::create($fields + ['account_id' => $this->account->id, 'mail_sender_id' => $this->sender->id, 'actor_id' => $this->owner->id, 'mode' => 'log', 'status' => 'logged', 'sender_email' => $this->sender->email, 'recipient' => $this->contact->email, 'subject' => 'Actividad sintética', 'origin' => 'unknown']);
    }

    private function foreignMessage(): MailMessage
    {
        $user = User::factory()->create();
        $account = Account::create(['name' => 'Cuenta B', 'owner_id' => $user->id]);
        Membership::create(['account_id' => $account->id, 'user_id' => $user->id, 'role' => 'admin', 'scopes' => []]);
        $sender = MailSender::create(['account_id' => $account->id, 'email' => 'second@example.test', 'name' => 'Segundo', 'lab_authorized' => true]);

        return $this->message(['account_id' => $account->id, 'mail_sender_id' => $sender->id, 'actor_id' => $user->id, 'sender_email' => $sender->email, 'subject' => 'Sólo cuenta B', 'artifact_path' => $account->id.'/foreign.eml']);
    }

    public function test_http_and_livewire_include_no_attempt_deliveries_and_distinct_states_without_leaking_account_b(): void
    {
        $foreign = $this->foreignMessage();
        foreach (ActivityService::STATES as $status => $label) {
            $event = $this->event();
            Delivery::findOrFail($event->delivery_id)->update(['status' => $status]);
        }
        $this->get('/activity')->assertOk()->assertSee('Actividad de la cuenta')->assertSee('desconocidos')->assertDontSee('Sólo cuenta B');
        $component = Livewire::test(Activity::class)->assertViewHas('records', fn ($records) => $records->total() === 9)
            ->assertViewHas('metrics', fn ($metrics) => $metrics['campaign']['total'] === 9 && $metrics['unknown']['total'] === 0);
        foreach (ActivityService::STATES as $status => $label) {
            $component->assertSee($label);
        }
        $component->call('open', 'delivery:'.Delivery::firstOrFail()->id)->assertSee('Sin registros de intento');
        $this->get(route('mail-artifacts.download', $foreign->id))->assertNotFound();
        $component->call('open', 'message:'.$foreign->id)->assertNotFound();
    }

    public function test_real_safe_retries_keep_one_delivery_all_attempts_and_original_authorizer_without_worker_session(): void
    {
        $event = $this->event();
        MailSetting::where('account_id', $this->account->id)->update(['mode' => 'mailpit']);
        $this->relay('reject');
        auth()->forgetGuards();
        session()->flush();
        $first = $this->process($event);
        $second = $this->process($this->next($event));
        file_put_contents($this->relayDirectory.'/mode', 'accept');
        $third = $this->process($this->next($event));
        $this->assertSame(['smtp_rejected', 'smtp_rejected', 'smtp_accepted'], [$first->fresh()->status, $second->fresh()->status, $third->fresh()->status]);
        $this->assertDatabaseCount('deliveries', 1);
        $this->assertDatabaseCount('mail_messages', 3);
        $this->assertSame([1, 2, 3], MailMessage::orderBy('id')->pluck('delivery_generation')->map(fn ($value) => (int) $value)->all());
        $this->assertSame([$event->delivery_id], MailMessage::pluck('delivery_id')->unique()->all());
        $this->assertSame([$this->owner->id], MailMessage::pluck('actor_id')->unique()->map(fn ($value) => (int) $value)->all());
        $viewer = $this->member(['activity.read']);
        $this->actingAs($viewer->user)->withSession(['account_id' => $this->account->id]);
        $component = Livewire::test(Activity::class)->call('open', 'delivery:'.$event->delivery_id)
            ->assertViewHas('attempts', fn ($rows) => $rows->total() === 3)
            ->assertViewHas('detail', fn ($row) => $row->actor_id === $this->owner->id)
            ->assertViewHas('metrics', fn ($rows) => $rows['campaign']['total'] === 1 && $rows['campaign']['acceptance_percentage'] === 100.0 && $rows['unknown']['total'] === 0);
        $component->assertSee($this->owner->name)->assertSee('Aceptado por SMTP');
        $this->record('retry-provenance', ['delivery' => Delivery::findOrFail($event->delivery_id)->toArray(), 'attempts' => MailMessage::all()->toArray(), 'authorizer_id' => $this->owner->id, 'viewer_id' => $viewer->user_id, 'relay_directory' => $this->relayDirectory]);
    }

    public function test_real_ambiguous_result_is_visible_without_resend_action(): void
    {
        $event = $this->event();
        MailSetting::where('account_id', $this->account->id)->update(['mode' => 'mailpit']);
        $this->relay('lost');
        $message = $this->process($event);
        $this->assertSame('smtp_unconfirmed', $message->fresh()->status);
        $this->assertSame('ambiguous', Delivery::findOrFail($event->delivery_id)->status);
        Livewire::test(Activity::class)->call('open', 'delivery:'.$event->delivery_id)->assertSee('Aceptación ambigua')->assertSee('no reenviar automáticamente')->assertDontSee('Reenviar mensaje');
    }

    public function test_live_runtime_records_log_suppression_and_pause_quota_waits(): void
    {
        $logged = $this->event();
        $this->assertSame('logged', $this->process($logged)->fresh()->status);
        $suppressed = $this->event();
        Suppression::create(['account_id' => $this->account->id, 'normalized_email' => $this->contact->email, 'source' => 'manual']);
        $this->assertNull($this->process($suppressed));
        Suppression::where('account_id', $this->account->id)->delete();
        $paused = $this->event();
        $campaign = Campaign::findOrFail(Delivery::findOrFail($paused->delivery_id)->campaign_id);
        $this->service->pause($this->account, $this->owner->id, $campaign->id, $campaign->version);
        $this->assertNull($this->process($paused));
        $quota = $this->event();
        config(['delivery.quotas.account.day' => 1]);
        $this->assertNull($this->process($quota));
        $this->assertSame('quota_wait', Delivery::findOrFail($quota->delivery_id)->diagnostic);
        $component = Livewire::test(Activity::class);
        $component->assertSee('En espera de cuota')->assertSee('En espera de reanudación')->assertSee('Suprimido')->assertSee('Archivo privado (log)')
            ->assertViewHas('metrics', fn ($rows) => $rows['campaign']['quota_wait'] === 1 && $rows['campaign']['paused'] === 1);
        $this->assertSame(1, MailMessage::count());
        $component->call('open', 'delivery:'.$logged->delivery_id)->assertSee('Descargar archivo privado');
        $this->get(route('mail-artifacts.download', MailMessage::sole()->id))->assertOk();
    }

    public function test_both_ui_test_entrypoints_are_explicit_tests_while_direct_legacy_origin_stays_unknown(): void
    {
        $input = ['recipient' => $this->contact->email, 'subject' => 'Prueba explícita', 'text' => 'Texto sintético'];
        $settings = app(MailSettingsService::class)->test($this->sender->id, $input);
        $this->assertSame('test', $settings->origin);
        $template = app(TemplateService::class)->test($this->template->id, $this->sender->id, $this->contact->email, ['name' => 'Ana', 'email' => $this->contact->email]);
        $this->assertSame('test', $template->origin);
        $unknown = app(AccountMailTransport::class)->send($this->account, $this->sender->id, $input, $this->owner->id);
        $this->assertSame('unknown', $unknown->origin);
        Livewire::test(Activity::class)->assertViewHas('metrics', fn ($rows) => $rows['campaign']['total'] === 0 && $rows['test']['total'] === 2 && $rows['unknown']['total'] === 1);
    }

    public function test_real_exhausted_smtp_failure_is_a_local_incident_and_never_a_reputation_claim(): void
    {
        $event = $this->event();
        config(['delivery.max_attempts' => 1]);
        MailSetting::where('account_id', $this->account->id)->update(['mode' => 'mailpit']);
        $this->relay('reject');
        $message = $this->process($event);
        $this->assertSame('smtp_rejected', $message->fresh()->status);
        $this->assertSame('failed', Delivery::findOrFail($event->delivery_id)->status);
        Livewire::test(Activity::class)->call('open', 'delivery:'.$event->delivery_id)
            ->assertSee('Fallido')->assertSee('Rechazo SMTP del destinatario')->assertSee('Incidencias locales observadas')->assertSee('No hay proveedor de feedback integrado')
            ->assertViewHas('metrics', fn ($rows) => $rows['campaign']['states']['failed'] === 1 && $rows['campaign']['total'] === 1);
        $this->record('smtp-failed', ['delivery' => Delivery::findOrFail($event->delivery_id)->only(['id', 'status', 'attempts', 'diagnostic']), 'mail_message' => $message->fresh()->only(['id', 'account_id', 'delivery_id', 'delivery_generation', 'status', 'diagnostic'])]);
    }

    public function test_independent_suppressed_blocked_and_legacy_records_do_not_invent_transport_starts(): void
    {
        $this->relay('accept');
        MailSetting::create(['account_id' => $this->account->id, 'mode' => 'mailpit']);
        Suppression::create(['account_id' => $this->account->id, 'normalized_email' => $this->contact->email, 'source' => 'manual']);
        $input = ['recipient' => $this->contact->email, 'subject' => 'Prueba sin IO', 'text' => 'Contenido sintético'];
        $suppressed = app(MailSettingsService::class)->test($this->sender->id, $input);
        Suppression::where('account_id', $this->account->id)->delete();
        MailSetting::where('account_id', $this->account->id)->update(['mode' => 'smtp']);
        $blocked = app(MailSettingsService::class)->test($this->sender->id, $input);
        $legacy = $this->message(['mode' => 'mailpit', 'status' => 'smtp_accepted', 'transport_started_at' => null]);
        $this->assertSame(['suppressed', 'blocked'], [$suppressed->status, $blocked->status]);
        foreach ([$suppressed, $blocked] as $record) {
            $this->assertSame('test', $record->origin);
            $this->assertNull($record->transport_started_at);
            Livewire::test(Activity::class)->call('open', 'message:'.$record->id)
                ->assertViewHas('detail', fn ($row) => $row->attempts === 0)
                ->assertSee('Registros independientes conservados')->assertSee('No acredita una conexión SMTP');
        }
        Livewire::test(Activity::class)->call('open', 'message:'.$legacy->id)
            ->assertViewHas('detail', fn ($row) => $row->attempts === null)->assertSee('Desconocidos');
        $facts = is_file($this->relayDirectory.'/relay.jsonl') ? file($this->relayDirectory.'/relay.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $this->assertSame([], $facts);
        $this->assertSame([], Storage::disk('mail-artifacts')->allFiles());
        $csv = $this->get('/activity/export')->assertOk()->streamedContent();
        $parsed = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\n", trim($csv)));
        foreach ($parsed as $row) {
            $this->assertCount(count($parsed[0]), $row);
        }
        $this->record('independent-start-units', ['records' => [$suppressed->only(['id', 'origin', 'status', 'transport_started_at']), $blocked->only(['id', 'origin', 'status', 'transport_started_at']), $legacy->only(['id', 'origin', 'status', 'transport_started_at'])], 'relay_connections' => 0, 'artifact_count' => 0, 'legacy_start_count' => null]);
    }

    public function test_activity_only_member_reads_detail_artifact_export_but_not_settings_and_no_scope_denies_all(): void
    {
        $message = app(MailSettingsService::class)->test($this->sender->id, ['recipient' => $this->contact->email, 'subject' => 'Privado', 'text' => 'Texto privado']);
        $member = $this->member(['activity.read']);
        $this->actingAs($member->user)->withSession(['account_id' => $this->account->id]);
        $this->get('/activity')->assertOk();
        $this->get('/settings/mail')->assertForbidden();
        Livewire::test(Activity::class)->call('open', 'message:'.$message->id)->assertSee('Descargar archivo privado');
        $this->get(route('mail-artifacts.download', $message->id))->assertOk();
        $this->get('/activity/export')->assertOk()->assertDownload('actividad.csv');
        $member->update(['scopes' => []]);
        $this->get('/activity')->assertForbidden();
        $this->get('/activity/export')->assertForbidden();
        $this->get(route('mail-artifacts.download', $message->id))->assertForbidden();
    }

    public function test_revocation_and_account_change_after_mount_and_client_locked_context_tamper_are_denied(): void
    {
        $message = $this->message();
        $member = $this->member(['activity.read']);
        $this->actingAs($member->user)->withSession(['account_id' => $this->account->id]);
        $component = Livewire::test(Activity::class);
        $member->delete();
        $component->set('search', 'Actividad')->assertForbidden();
        $this->actingAs($this->owner)->withSession(['account_id' => $this->account->id]);
        $component = Livewire::test(Activity::class);
        $this->withSession(['account_id' => $this->foreignMessage()->account_id]);
        $component->call('open', 'message:'.$message->id)->assertForbidden();
        $this->withSession(['account_id' => $this->account->id]);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(Activity::class)->set('accountId', 999999);
    }

    public function test_stream_callbacks_revalidate_before_any_output_after_scope_reduction(): void
    {
        $message = app(MailSettingsService::class)->test($this->sender->id, ['recipient' => $this->contact->email, 'subject' => 'Privado', 'text' => 'Contenido privado']);
        $member = $this->member(['activity.read']);
        $this->actingAs($member->user)->withSession(['account_id' => $this->account->id]);
        $csv = app(ActivityService::class)->export([]);
        $artifact = app(MailSettingsService::class)->download($message->id);
        $member->update(['scopes' => []]);
        foreach ([$csv, $artifact] as $response) {
            ob_start();
            try {
                $response->sendContent();
                $this->fail('Stream must reauthorize.');
            } catch (StreamedResponseException $wrapper) {
                $error = $wrapper->getInnerException();
                $this->assertInstanceOf(HttpException::class, $error);
                $this->assertSame(403, $error->getStatusCode());
                $this->assertSame('', ob_get_contents());
            } finally {
                ob_end_clean();
            }
        }
    }

    public function test_filters_pagination_and_utc_boundaries_keep_populations_and_denominators_honest(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-23T12:00:00Z'));
        foreach (range(1, 27) as $number) {
            $this->message(['origin' => 'test', 'status' => $number === 1 ? 'smtp_accepted' : 'logged', 'subject' => 'Fila '.$number, 'created_at' => '2026-10-23 00:00:00', 'updated_at' => '2026-10-23 00:00:00']);
        }
        $this->message(['created_at' => '2026-10-22 23:59:59', 'updated_at' => '2026-10-22 23:59:59']);
        $this->message(['created_at' => '2026-10-24 00:00:00', 'updated_at' => '2026-10-24 00:00:00']);
        $this->foreignMessage();
        $component = Livewire::test(Activity::class)->set('from', '2026-10-23')->set('to', '2026-10-23')
            ->assertViewHas('records', fn ($rows) => $rows->total() === 27 && $rows->count() === 25)
            ->assertViewHas('metrics', fn ($rows) => $rows['test']['total'] === 27 && $rows['test']['smtp_accepted'] === 1 && $rows['test']['acceptance_percentage'] === 3.7 && $rows['unknown']['acceptance_percentage'] === null);
        $component->call('nextPage', 'activityPage')->assertViewHas('records', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 2);
        $component->set('status', 'smtp_accepted')->assertViewHas('records', fn ($rows) => $rows->total() === 1 && $rows->currentPage() === 1);
        $component->set('search', 'no existe')->assertSee('Sin muestra')->assertViewHas('metrics', fn ($rows) => $rows['test']['acceptance_percentage'] === null);
        $csv = $this->get('/activity/export?from=2026-10-23&to=2026-10-23&population=test&status=smtp_accepted')->assertOk()->streamedContent();
        $this->assertStringContainsString('Fila 1', $csv);
    }

    public function test_untrusted_metadata_is_escaped_diagnostics_are_allowlisted_and_csv_is_formula_safe(): void
    {
        $subject = '=HYPERLINK("https://example.test")<script>window.activityLeak=1</script>';
        $message = $this->message(['subject' => $subject, 'diagnostic' => 'not-a-public-diagnostic']);
        $this->foreignMessage();
        Livewire::test(Activity::class)->call('open', 'message:'.$message->id)->assertSee($subject)->assertDontSee('<script>window.activityLeak=1</script>', false)->assertDontSee('not-a-public-diagnostic')->assertSee('Diagnóstico no reconocido');
        $csv = $this->get('/activity/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('Sólo cuenta B', $csv);
        $this->assertStringNotContainsString('not-a-public-diagnostic', $csv);
        foreach (["\t=1", ' +1', '@SUM(1)', '-1', '=1', "\u{FEFF}=1"] as $value) {
            $this->assertSame("'".$value, ActivityService::csvCell($value));
        }
    }

    public function test_invalid_filter_feedback_is_visible_and_clears_on_correction_in_the_same_component(): void
    {
        app()->setLocale('es');
        $this->message(['subject' => 'Resultado visible de actividad']);
        $component = Livewire::test(Activity::class)->assertSee('Resultado visible de actividad');
        $component->set('from', 'not-a-date')->assertHasErrors('from')->assertSee('role="alert"', false)
            ->assertSee('El campo inicio debe contener una fecha válida.')->assertDontSee('validation.');
        $component->set('from', CarbonImmutable::now('UTC')->subDay()->format('Y-m-d'))
            ->assertHasNoErrors('from')->assertDontSee('role="alert"', false)->assertSee('Resultado visible de actividad');

        $component->set('from', '2099-01-02')->set('to', '2000-01-01')->assertHasErrors('from')
            ->assertSee('La fecha inicial no puede ser posterior a la fecha final.')->assertDontSee('validation.');
        $component->set('to', CarbonImmutable::now('UTC')->format('Y-m-d'))
            ->set('from', CarbonImmutable::now('UTC')->subDay()->format('Y-m-d'))
            ->assertHasNoErrors()->assertDontSee('role="alert"', false)->assertSee('Resultado visible de actividad');
    }

    public function test_csv_stream_rechecks_scope_between_bounded_batches_and_preserves_window_and_source(): void
    {
        $prototype = $this->message(['origin' => 'test'])->getAttributes();
        unset($prototype['id']);
        $rows = [];
        foreach (range(1, 200) as $number) {
            $rows[] = array_replace($prototype, ['subject' => 'Lote sintético '.$number]);
        }
        DB::table('mail_messages')->insert($rows);
        $member = $this->member(['activity.read']);
        $this->actingAs($member->user)->withSession(['account_id' => $this->account->id]);
        $response = app(ActivityService::class)->export([]);
        $reads = 0;
        DB::listen(function ($query) use (&$reads, $member) {
            if (str_contains(strtolower($query->sql), 'limit 200') && str_contains($query->sql, 'activity')) {
                $reads++;
                $member->update(['scopes' => []]);
            }
        });
        ob_start();
        try {
            $response->sendContent();
            $this->fail('Second batch must reauthorize against current membership.');
        } catch (StreamedResponseException $wrapper) {
            $this->assertInstanceOf(HttpException::class, $wrapper->getInnerException());
            $this->assertSame(403, $wrapper->getInnerException()->getStatusCode());
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertSame(1, $reads);
        $this->assertCount(201, array_filter(explode("\n", trim($output))));
        $this->assertStringContainsString('Ventana desde UTC', $output);
        $this->assertStringContainsString('SQL local; estados actuales', $output);
        $this->record('csv-revoked-between-batches', ['authorized_batches' => $reads, 'maximum_rows_emitted' => 200, 'output_sha256' => hash('sha256', $output)]);
    }

    public function test_foreign_campaign_filter_and_valid_account_switch_after_mount_are_denied(): void
    {
        $foreign = $this->foreignMessage();
        $campaign = Campaign::create(['account_id' => $foreign->account_id, 'name' => 'Campaña B', 'template_id' => $this->template->id, 'sender_id' => $foreign->mail_sender_id, 'list_ids' => [], 'contact_ids' => []]);
        $this->get('/activity/export?campaign='.$campaign->id)->assertNotFound();
        Livewire::test(Activity::class)->set('campaign', (string) $campaign->id)->assertNotFound();
        Membership::create(['account_id' => $foreign->account_id, 'user_id' => $this->owner->id, 'role' => 'restricted', 'scopes' => ['activity.read']]);
        $component = Livewire::test(Activity::class);
        $this->withSession(['account_id' => $foreign->account_id]);
        $component->call('close')->assertStatus(409);
    }

    public function test_mysql_query_plans_use_account_window_indexes_and_provenance_has_no_rowwise_queries(): void
    {
        $event = $this->event();
        $delivery = (array) DB::table('deliveries')->where('id', $event->delivery_id)->first();
        $recipient = (array) DB::table('campaign_recipients')->where('id', $delivery['campaign_recipient_id'])->first();
        unset($recipient['id']);
        $contacts = [];
        foreach (range(1, 600) as $number) {
            $contacts[] = ['account_id' => $this->account->id, 'email' => sprintf('perf-%04d@example.test', $number), 'name' => 'Sintético '.$number, 'consent_status' => 'granted', 'consent_source' => 'Fixture de cardinalidad', 'consented_at' => '2025-01-01 00:00:00', 'created_at' => '2025-01-01 00:00:00', 'updated_at' => '2025-01-01 00:00:00'];
        }
        DB::table('contacts')->insert($contacts);
        $recipients = [];
        foreach (DB::table('contacts')->where('account_id', $this->account->id)->where('email', 'like', 'perf-%')->get() as $contact) {
            $recipients[] = array_replace($recipient, ['contact_id' => $contact->id, 'email' => $contact->email, 'name' => $contact->name]);
        }
        DB::table('campaign_recipients')->insert($recipients);
        $deliveries = [];
        foreach (DB::table('campaign_recipients')->where('account_id', $this->account->id)->where('email', 'like', 'perf-%')->get() as $index => $recipientRow) {
            $date = $index % 100 === 0 ? '2026-10-23 12:00:00' : '2025-01-01 00:00:00';
            $deliveries[] = array_replace($delivery, ['id' => (string) Str::uuid(), 'campaign_recipient_id' => $recipientRow->id, 'status' => 'smtp_accepted', 'created_at' => $date, 'updated_at' => $date]);
        }
        DB::table('deliveries')->insert($deliveries);
        $prototype = $this->message([
            'origin' => 'test',
            'subject' => 'Selección única de consulta',
            'created_at' => '2026-10-23 12:00:00',
            'updated_at' => '2026-10-23 12:00:00',
        ])->getAttributes();
        unset($prototype['id']);
        $foreign = $this->foreignMessage();
        $messages = [];
        foreach (range(1, 600) as $number) {
            $date = $number % 100 === 0 ? '2026-10-23 12:00:00' : '2025-01-01 00:00:00';
            $messages[] = array_replace($prototype, ['status' => 'smtp_accepted', 'subject' => 'Sintético '.$number, 'created_at' => $date, 'updated_at' => $date]);
            $messages[] = array_replace($prototype, ['account_id' => $foreign->account_id, 'mail_sender_id' => $foreign->mail_sender_id, 'actor_id' => $foreign->actor_id, 'status' => 'smtp_accepted', 'subject' => 'Exclusivo B '.$number, 'created_at' => '2026-10-23 12:00:00', 'updated_at' => '2026-10-23 12:00:00']);
        }
        foreach (array_chunk($messages, 200) as $chunk) {
            DB::table('mail_messages')->insert($chunk);
        }
        DB::select('ANALYZE TABLE deliveries, mail_messages, campaign_recipients');
        $activity = app(ActivityService::class);
        $plans = [];
        foreach (['campaign' => 'd', 'test' => 'm'] as $population => $tableAlias) {
            $filters = ['from' => '2026-10-23', 'to' => '2026-10-23', 'population' => $population, 'status' => 'smtp_accepted'];
            $query = $activity->query($filters, $this->account->id);
            $this->assertSame(6, $query->count());
            $plan = DB::select('EXPLAIN '.$query->toSql(), $query->getBindings());
            $selected = collect($plan)->firstWhere('table', $tableAlias);
            $this->assertNotNull($selected);
            $this->assertNotSame('ALL', $selected->type);
            $plans[$population] = ['sql' => $query->toSql(), 'bindings' => $query->getBindings(), 'plan' => $plan];
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $actualFilters = ['from' => '2026-10-23', 'to' => '2026-10-23', 'status' => 'smtp_accepted'];
        $activity->page($actualFilters, $this->account->id);
        $activity->metrics($actualFilters, $this->account->id);
        $activity->detail('delivery:'.$event->delivery_id, $this->account->id);
        $activity->detail('message:'.MailMessage::where('account_id', $this->account->id)->where('subject', 'Selección única de consulta')->value('id'), $this->account->id);
        $activity->attempts('delivery:'.$event->delivery_id, $this->account->id);
        $actualQueries = DB::getQueryLog();
        DB::disableQueryLog();
        $actualPlans = [];
        foreach ($actualQueries as $actualQuery) {
            if (str_contains($actualQuery['query'], '`activity`') || str_contains($actualQuery['query'], '`mail_messages`')) {
                $actualPlans[] = ['sql' => $actualQuery['query'], 'bindings' => $actualQuery['bindings'], 'plan' => DB::select('EXPLAIN '.$actualQuery['query'], $actualQuery['bindings'])];
            }
        }
        $this->record('mysql-actual-ui-queries', ['synthetic_only' => true, 'not_a_capacity_benchmark' => true, 'delivery_rows' => DB::table('deliveries')->count(), 'message_rows' => MailMessage::count(), 'actual_ui_query_plans' => $actualPlans]);
        DB::enableQueryLog();
        $wide = ['from' => '2025-01-01', 'to' => '2026-10-23'];
        DB::flushQueryLog();
        $page = $activity->page($wide, $this->account->id);
        $many = count(DB::getQueryLog());
        $this->assertCount(25, $page);
        DB::flushQueryLog();
        $single = $activity->page($wide + ['search' => 'Selección única de consulta'], $this->account->id);
        $one = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(1, $single);
        $this->assertSame($one, $many);
        $this->record('mysql-explain', ['synthetic_only' => true, 'not_a_capacity_benchmark' => true, 'delivery_rows' => DB::table('deliveries')->count(), 'message_rows' => MailMessage::count(), 'plans' => $plans, 'page25_query_count' => $many, 'page1_query_count' => $one]);
        $pointPlans = array_values(array_filter($actualPlans, fn ($plan) => str_ends_with($plan['sql'], 'limit 1') && str_contains($plan['sql'], '`activity`')));
        $this->assertCount(3, $pointPlans); // delivery detail, message detail, attempt-parent detail
        foreach ($pointPlans as $pointPlan) {
            $businessLookups = array_filter($pointPlan['plan'], fn ($row) => in_array($row->table, ['d', 'm'], true));
            $this->assertCount(1, $businessLookups);
            foreach ($businessLookups as $lookup) {
                $this->assertSame('PRIMARY', $lookup->key);
                $this->assertLessThanOrEqual(1, $lookup->rows);
                $this->assertNotSame('ALL', $lookup->type);
            }
        }
    }

    public function test_migration_backfills_only_unique_account_matching_current_pointer_and_never_guesses_generation(): void
    {
        $first = $this->event();
        $second = $this->event();
        $third = $this->event();
        $fourth = $this->event();
        $current = $this->message();
        $shared = $this->message();
        $unlinked = $this->message();
        $foreign = $this->foreignMessage();
        Delivery::findOrFail($first->delivery_id)->update(['mail_message_id' => $current->id, 'generation' => 7]);
        Delivery::whereIn('id', [$second->delivery_id, $third->delivery_id])->update(['mail_message_id' => $shared->id]);
        Delivery::findOrFail($fourth->delivery_id)->update(['mail_message_id' => $foreign->id]);
        $migration = require database_path('migrations/2026_10_23_000011_add_activity_provenance.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('mail_messages', 'delivery_id'));
        $migration->up();
        $this->assertSame($first->delivery_id, $current->fresh()->delivery_id);
        $this->assertNull($current->fresh()->delivery_generation);
        $this->assertSame('campaign', $current->fresh()->origin);
        foreach ([$shared, $unlinked, $foreign] as $unknown) {
            $this->assertNull($unknown->fresh()->delivery_id);
            $this->assertSame('unknown', $unknown->fresh()->origin);
        }
        Livewire::test(Activity::class)->call('open', 'delivery:'.$first->delivery_id)
            ->assertViewHas('metrics', fn ($rows) => $rows['campaign']['total'] === 4 && $rows['unknown']['total'] === 2 && $rows['test']['total'] === 0)
            ->assertViewHas('attempts', fn ($rows) => $rows->total() === 1 && $rows->first()->delivery_generation === null)
            ->assertSee('Generación desconocida');
        $this->assertTrue(Schema::hasIndex('deliveries', 'activity_delivery_window'));
        $this->assertTrue(Schema::hasIndex('mail_messages', 'activity_message_status'));
        $this->record('backfill', ['current_delivery_id' => $current->fresh()->delivery_id, 'historical_generation' => $current->fresh()->delivery_generation, 'unknown_count' => MailMessage::whereNull('delivery_id')->count()]);
    }

    public function test_historical_starts_without_reconstructible_links_and_real_zero_have_a_neutral_page_empty_state(): void
    {
        $historical = [$this->event(), $this->event()];
        $zero = $this->event();
        $shared = $this->message();
        foreach ($historical as $index => $event) {
            Delivery::findOrFail($event->delivery_id)->update(['mail_message_id' => $shared->id, 'attempts' => 2, 'status' => ['smtp_accepted', 'ambiguous'][$index]]);
        }
        $migration = require database_path('migrations/2026_10_23_000011_add_activity_provenance.php');
        $migration->down();
        $migration->up();
        $this->assertNull($shared->fresh()->delivery_id);
        $this->assertSame('unknown', $shared->fresh()->origin);
        $observed = [];
        foreach ($historical as $event) {
            $delivery = Delivery::findOrFail($event->delivery_id);
            $this->assertSame(2, $delivery->attempts);
            $this->assertSame(0, MailMessage::where('delivery_id', $delivery->id)->count());
            Livewire::test(Activity::class)->call('open', 'delivery:'.$delivery->id)
                ->assertViewHas('detail', fn ($detail) => $detail->attempts === 2 && $detail->status === $delivery->status)
                ->assertViewHas('attempts', fn ($rows) => $rows->total() === 0)
                ->assertDontSee('La entrega aún no ha iniciado el transporte o se detuvo antes de hacerlo.')
                ->assertSee('Sin registros de intento vinculados en esta página.');
            $observed[] = ['delivery_id' => $delivery->id, 'status' => $delivery->status, 'transport_starts' => $delivery->attempts, 'linked_records' => 0];
        }
        Livewire::test(Activity::class)->call('open', 'delivery:'.$zero->delivery_id)
            ->assertViewHas('detail', fn ($detail) => $detail->attempts === 0 && $detail->status === 'pending')
            ->assertViewHas('attempts', fn ($rows) => $rows->total() === 0)
            ->assertDontSee('La entrega aún no ha iniciado el transporte o se detuvo antes de hacerlo.')
            ->assertSee('Sin registros de intento vinculados en esta página.');
        $linked = $this->event();
        $this->assertNotNull($this->process($linked));
        Livewire::test(Activity::class)->call('open', 'delivery:'.$linked->delivery_id)
            ->assertViewHas('attempts', fn ($rows) => $rows->total() === 1 && $rows->count() === 1)
            ->call('setPage', 2, 'attemptsPage')
            ->assertViewHas('detail', fn ($detail) => $detail->attempts === 1 && $detail->status === 'logged')
            ->assertViewHas('attempts', fn ($rows) => $rows->total() === 1 && $rows->count() === 0 && $rows->currentPage() === 2)
            ->assertSee('Sin registros de intento vinculados en esta página.')
            ->assertDontSee('La entrega aún no ha iniciado el transporte o se detuvo antes de hacerlo.');
        $this->record('historical-attempt-links', ['historical' => $observed, 'real_zero_delivery_id' => $zero->delivery_id, 'real_zero_starts' => 0, 'shared_message_delivery_id' => $shared->fresh()->delivery_id, 'page_two_delivery_id' => $linked->delivery_id, 'page_two_total_records' => 1, 'page_two_visible_records' => 0, 'view_source_sha256' => hash_file('sha256', resource_path('views/livewire/activity.blade.php'))]);
    }

    public function test_composite_provenance_rejects_cross_account_and_duplicate_generation_without_partial_rows(): void
    {
        $event = $this->event();
        $message = $this->process($event);
        $foreign = $this->foreignMessage();
        foreach ([['id' => $foreign->id, 'delivery_id' => $event->delivery_id, 'delivery_generation' => 2], ['id' => $this->message()->id, 'delivery_id' => $event->delivery_id, 'delivery_generation' => 1]] as $case) {
            try {
                MailMessage::whereKey($case['id'])->update(['delivery_id' => $case['delivery_id'], 'delivery_generation' => $case['delivery_generation']]);
                $this->fail('Invalid provenance must be rejected by MySQL.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
            $this->assertNull(MailMessage::findOrFail($case['id'])->delivery_id);
        }
        $this->assertSame($event->delivery_id, $message->fresh()->delivery_id);
    }

    public function test_campaign_and_account_cleanup_cascade_attempts_and_deleted_actor_stays_unknown(): void
    {
        $event = $this->event();
        $message = $this->process($event);
        $second = $this->event();
        $secondMessage = $this->process($second);
        $viewer = $this->member(['activity.read']);
        // Remove the original user only after transferring the protected owner reference.
        $this->account->update(['owner_id' => $viewer->user_id]);
        $this->owner->delete();
        $this->actingAs($viewer->user)->withSession(['account_id' => $this->account->id]);
        Livewire::test(Activity::class)->call('open', 'delivery:'.$event->delivery_id)->assertViewHas('detail', fn ($row) => $row->actor_name === null)->assertSee('Desconocido');
        $this->assertNull($message->fresh()->actor_id);
        Campaign::whereKey(Delivery::findOrFail($event->delivery_id)->campaign_id)->delete();
        $this->assertNull($message->fresh());
        $this->assertNotNull($secondMessage->fresh());
        $this->assertDatabaseCount('deliveries', 1);
        $this->account->delete();
        $this->assertDatabaseCount('mail_messages', 0);
        $this->assertDatabaseCount('deliveries', 0);
    }
}
