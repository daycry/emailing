<?php

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Middleware\AuthorizeContactUpload;
use App\Livewire\ContactImports;
use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\Membership;
use App\Models\User;
use App\Services\AccountContext;
use App\Services\ContactImportService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContactImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('contact-imports');
        Storage::fake('tmp-for-tests');
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

    private function file(string $csv): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('contactos.csv', $csv);
    }

    private function upload($component, string $csv)
    {
        // Livewire's test upload bypasses HTTP middleware. Reproduce its server-side binding explicitly.
        $component->set('file', $this->file($csv));
        $file = $component->get('file');
        session(['contact_import_uploads.'.hash('sha256', $file->getFilename()) => ['account_id' => app(AccountContext::class)->membership()->account_id, 'user_id' => auth()->id(), 'at' => time()]]);

        return $component;
    }

    private function assertRejected(string $csv): void
    {
        try {
            app(ContactImportService::class)->import($this->file($csv));
            $this->fail('Se aceptó un CSV globalmente inválido.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_imports', 0);
        $this->assertDatabaseCount('contact_import_rows', 0);
        $this->assertSame([], Storage::disk('contact-imports')->allFiles());
    }

    public function test_valid_invalid_and_duplicate_rows_have_consistent_account_scoped_results(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = $this->otherAccount();
        Contact::create(['account_id' => $other->id, 'email' => 'new@example.test', 'name' => 'Ajeno']);
        $existing = Contact::create(['account_id' => $account->id, 'email' => 'existing@example.test', 'name' => 'Anterior', 'consent_status' => 'revoked']);
        $list = ContactList::create(['account_id' => $account->id, 'name' => 'Destino']);
        $csv = "email,name,consent_status,consent_source,consented_at\nNEW@EXAMPLE.TEST,Nueva,,,\ninvalid,Error,,,\nnew@example.test,Duplicada,granted,Formulario,2025-01-10T12:30\nexisting@example.test,Intento,granted,Formulario,2025-01-10T12:30\ngranted@example.test,Con permiso,granted,Formulario,2025-01-10T12:30\nmissing@example.test,Sin evidencia,granted,,\n";
        $report = app(ContactImportService::class)->import($this->file($csv), $list->id);
        $this->assertSame([6, 2, 2, 2], [$report->total, $report->imported, $report->invalid, $report->duplicates]);
        $this->assertSame(6, app(ContactImportService::class)->rows($report->id)->count());
        $this->assertSame('unknown', Contact::where('account_id', $account->id)->where('email', 'new@example.test')->sole()->consent_status);
        $this->assertSame('revoked', $existing->fresh()->consent_status);
        $this->assertSame('Anterior', $existing->fresh()->name);
        $this->assertSame(2, $list->contacts()->count());
        $this->assertFalse($list->contacts()->whereKey($existing->id)->exists());
        $granted = Contact::where('account_id', $account->id)->where('email', 'granted@example.test')->sole();
        $this->assertSame('Formulario', $granted->consent_source);
        $this->assertSame('2025-01-10 12:30:00', $granted->consented_at->format('Y-m-d H:i:s'));
        Storage::disk('contact-imports')->assertExists($report->source_path);
        $this->assertSame($csv, Storage::disk('contact-imports')->get($report->source_path));
        $event = AuditEvent::where('action', 'contacts.imported')->sole();
        $this->assertSame($report->id, $event->metadata['import_id']);
        $this->assertArrayNotHasKey('email', $event->metadata);
        $this->assertSame('Ajeno', Contact::where('account_id', $other->id)->sole()->name);
    }

    public function test_utf8_bom_quoted_commas_newlines_and_distinct_accents_are_parsed(): void
    {
        $this->owner();
        $csv = "\xEF\xBB\xBFemail,name\r\njose@example.test,\"Persona, una\"\r\njosé@example.test,\"Persona\ncon \"\"comillas\"\"\"\r\n\r\n";
        $report = app(ContactImportService::class)->import($this->file($csv));
        $this->assertSame(2, $report->imported);
        $this->assertSame('Persona, una', Contact::where('email', 'jose@example.test')->sole()->name);
        $this->assertSame("Persona\ncon \"comillas\"", Contact::where('email', 'josé@example.test')->sole()->name);
        $this->assertSame([2, 3], app(ContactImportService::class)->rows($report->id)->pluck('row_number')->all());
    }

    public function test_global_csv_format_errors_never_write_partial_contacts_or_files(): void
    {
        $this->owner();
        foreach (['', "name\nPersona\n", "email,email\na@example.test,a@example.test\n", "email,extra\na@example.test,x\n", "email,name\nvalid@example.test,Valida\nwrong@example.test\n", "email,name\na@example.test,\"Sin cerrar", "email,name\na@example.test,\"cerrada\"resto\n", "email,name\na@example.test,sin\"abrir\n", "email\rvalid@example.test\r", "email,name\ninvalid@example.test,\xFF\n", "email\na@example.test\0\n"] as $csv) {
            $this->assertRejected($csv);
        }
    }

    public function test_global_byte_and_record_limits_reject_all_rows_before_import(): void
    {
        $this->owner();
        $this->assertSame(5242880, config('emailing.import_max_bytes'));
        $this->assertRejected(str_repeat('a', 5242881));
        $this->assertRejected("email\n".implode("\n", array_map(fn ($i) => 'person'.$i.'@example.test', range(1, 10001))));
        config(['emailing.import_max_bytes' => 30]);
        $this->assertRejected("email\nvery-long-address@example.test\n");
    }

    public function test_semantic_errors_are_reported_without_inventing_or_changing_consent(): void
    {
        $this->owner();
        $future = now()->addDay()->format('Y-m-d\TH:i');
        $report = app(ContactImportService::class)->import($this->file("email,consent_status,consent_source,consented_at\nunknown@example.test,,,\nfuture@example.test,granted,Formulario,$future\nwrong@example.test,granted,Formulario,ayer\nmissing@example.test,granted,,\nstatus@example.test,consented,,\n"));
        $this->assertSame([5, 1, 4, 0], [$report->total, $report->imported, $report->invalid, $report->duplicates]);
        $contact = Contact::sole();
        $this->assertSame('unknown', $contact->consent_status);
        $this->assertNull($contact->consent_source);
        $this->assertNull($contact->consented_at);
        $duplicate = app(ContactImportService::class)->import($this->file("email,consent_status,consent_source,consented_at\nunknown@example.test,granted,Alta,2025-01-10T12:30\n"));
        $this->assertSame(1, $duplicate->duplicates);
        $this->assertSame('unknown', $contact->fresh()->consent_status);
    }

    public function test_recreating_a_tombstone_does_not_copy_historical_consent(): void
    {
        $this->owner();
        $old = Contact::create(['account_id' => Account::sole()->id, 'email' => 'return@example.test', 'consent_status' => 'granted', 'consent_source' => 'Histórica', 'consented_at' => '2025-01-10 12:30:00']);
        $old->delete();
        $report = app(ContactImportService::class)->import($this->file("email\nreturn@example.test\n"));
        $this->assertSame(1, $report->imported);
        $new = Contact::sole();
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame('unknown', $new->consent_status);
        $this->assertNull($new->consented_at);
        $this->assertSame('Histórica', $old->fresh()->consent_source);
        $this->assertSoftDeleted($old);
    }

    public function test_optimized_row_validation_preserves_lengths_and_strict_calendar_dates(): void
    {
        $this->owner();
        $cases = [
            ['email' => str_repeat('a', 255).'@example.test'],
            ['name' => str_repeat('á', 151)],
            ['consent_source' => str_repeat('x', 256)],
            ['consented_at' => '2025-02-30T12:00'],
            ['consented_at' => '0000-01-10T12:00'],
            ['consented_at' => '2025-01-10T24:01'],
            ['consented_at' => '2025-01-10T12:30:00'],
            ['consented_at' => '2024-02-29T12:00'],
        ];
        $csv = "email,name,consent_status,consent_source,consented_at\n";
        foreach ($cases as $index => $case) {
            $row = array_replace(['email' => 'edge'.$index.'@example.test', 'name' => 'Persona', 'consent_status' => 'granted', 'consent_source' => 'Formulario', 'consented_at' => '2025-01-10T12:30'], $case);
            $csv .= implode(',', array_values($row))."\n";
        }
        $report = app(ContactImportService::class)->import($this->file($csv));
        $this->assertSame([8, 1, 7, 0], [$report->total, $report->imported, $report->invalid, $report->duplicates]);
        $this->assertSame('2024-02-29 12:00:00', Contact::sole()->consented_at->format('Y-m-d H:i:s'));
    }

    public function test_same_livewire_instance_recovers_from_invalid_to_valid_upload_without_old_errors(): void
    {
        $this->owner();
        $component = Livewire::test(ContactImports::class);
        $this->upload($component, "email,name\na@example.test,\"broken")->call('import')->assertHasErrors('file');
        $this->assertDatabaseCount('contacts', 0);
        $this->upload($component, "email,name\ncorrected@example.test,Corregida\n")->call('import')->assertHasNoErrors()->assertSee('Importación finalizada.');
        $this->assertDatabaseHas('contacts', ['email' => 'corrected@example.test', 'consent_status' => 'unknown']);
        $this->assertDatabaseCount('contact_imports', 1);
        $this->assertDatabaseCount('contact_import_rows', 1);
        $this->assertSame(ContactImport::sole()->id, $component->get('reportId'));
    }

    public function test_foreign_list_and_report_ids_are_rejected_without_affecting_either_account(): void
    {
        $this->owner();
        $account = Account::sole();
        $other = $this->otherAccount();
        $foreignList = ContactList::create(['account_id' => $other->id, 'name' => 'Lista ajena']);
        $foreignReport = ContactImport::create(['account_id' => $other->id, 'source_path' => 'private.csv', 'total' => 0, 'imported' => 0, 'invalid' => 0, 'duplicates' => 0]);
        Livewire::test(ContactImports::class)->call('selectList', $foreignList->id)->assertNotFound();
        Livewire::test(ContactImports::class)->call('selectReport', $foreignReport->id)->assertNotFound();
        Livewire::test(ContactImports::class)->set('reportId', $foreignReport->id)->assertNotFound();
        Livewire::test(ContactImports::class)->set('destinationList', $foreignList->id)->assertNotFound();
        $this->get(route('contact-imports.download', $foreignReport->id))->assertNotFound();
        $this->get('/contacts/imports/not-a-number/download')->assertNotFound();
        $component = $this->upload(Livewire::test(ContactImports::class), "email\na@example.test\n");
        // A forged destination is checked again by the service, even if render was bypassed.
        try {
            app(ContactImportService::class)->import($component->get('file'), $foreignList->id);
            $this->fail('Se aceptó una lista de otra cuenta.');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(ContactList::class, $e->getModel());
        }
        $this->assertDatabaseCount('contacts', 0);
        $this->assertSame(0, ContactImport::where('account_id', $account->id)->count());
        $this->assertModelExists($foreignList);
        $this->assertModelExists($foreignReport);
    }

    public function test_removing_read_or_write_scope_after_mount_blocks_import_before_any_effect(): void
    {
        $this->owner();
        $account = Account::sole();
        foreach ([['contacts.write'], ['contacts.read'], []] as $remaining) {
            $member = $this->member($account, ['contacts.read', 'contacts.write']);
            $this->actingAs($member->user);
            $component = $this->upload(Livewire::test(ContactImports::class), "email\nforbidden@example.test\n");
            $member->update(['scopes' => $remaining]);
            $component->call('import')->assertForbidden();
            $this->assertDatabaseCount('contacts', 0);
            $this->assertDatabaseCount('contact_imports', 0);
            $this->assertDatabaseCount('contact_import_rows', 0);
            $this->assertSame([], Storage::disk('contact-imports')->allFiles('sources'));
        }
    }

    public function test_upload_http_route_checks_permissions_and_binds_private_temporary_file_to_account(): void
    {
        $owner = $this->owner();
        $account = Account::sole();
        $url = URL::temporarySignedRoute('livewire.upload-file', now()->addMinutes(5), [], false);
        $response = $this->post($url, ['files' => [$this->file("email\nprivate@example.test\n")]])->assertOk();
        $path = TemporaryUploadedFile::extractPathFromSignedPath($response->json('paths.0'));
        $file = new TemporaryUploadedFile($path, FileUploadConfiguration::disk());
        $binding = session('contact_import_uploads.'.hash('sha256', $file->getFilename()));
        $this->assertSame($account->id, $binding['account_id']);
        $this->assertSame($owner->id, $binding['user_id']);
        $other = $this->otherAccount();
        Membership::create(['account_id' => $other->id, 'user_id' => $owner->id, 'role' => 'admin', 'scopes' => []]);
        session(['account_id' => $other->id]);
        try {
            app(ContactImportService::class)->import($file);
            $this->fail('Se aceptó un archivo temporal subido en otra cuenta.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('contacts', 0);
        session(['account_id' => $account->id]);
        $member = $this->member($account, ['contacts.read']);
        $this->flushSession();
        $this->withSession(['account_id' => $account->id]);
        $this->actingAs($member->user)->post($url, ['files' => [$this->file("email\nblocked@example.test\n")]])->assertForbidden();
        $this->assertContains(AuthorizeContactUpload::class, config('livewire.temporary_file_upload.middleware'));
        $this->assertFalse(config('filesystems.disks.contact-imports.serve'));
        $this->get('/storage/contact-imports/temporary/'.$path)->assertForbidden();
    }

    public function test_reports_are_private_paginated_and_download_neutralizes_csv_formulas(): void
    {
        $this->owner();
        $report = app(ContactImportService::class)->import($this->file("email\n".implode("\n", array_map(fn ($i) => 'person'.$i.'@example.test', range(1, 31)))."\n=1+1\n"));
        $component = Livewire::test(ContactImports::class)->call('selectReport', $report->id)->assertViewHas('rows', fn ($rows) => $rows->count() === 25 && $rows->total() === 32)->assertDontSee('person31@example.test');
        $component->call('setPage', 2, 'rowsPage')->assertSee('person31@example.test')->assertSee('=1+1');
        $download = $this->get(route('contact-imports.download', $report->id))->assertOk();
        $this->assertStringContainsString("'=1+1", $download->streamedContent());
        $this->assertStringNotContainsString('source_path', $component->html());
        $this->assertStringNotContainsString($report->source_path, $component->html());
        $member = $this->member(Account::sole(), []);
        $this->flushSession();
        $this->withSession(['account_id' => $member->account_id]);
        $this->actingAs($member->user)->get(route('contact-imports.download', $report->id))->assertForbidden();
        $this->get('/contacts/imports')->assertForbidden();
    }

    public function test_ten_thousand_rows_use_bounded_sql_batches_and_consistent_counts(): void
    {
        $this->owner();
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $csv = "email\n".implode("\n", array_map(fn ($i) => 'bulk'.$i.'@example.test', range(1, 10000)));
        DB::enableQueryLog();
        $started = hrtime(true);
        try {
            $report = app(ContactImportService::class)->import($this->file($csv));
            $queries = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
        $runtime = base_path('tests/.runtime');
        if (! is_dir($runtime)) {
            mkdir($runtime, 0775, true);
        }
        file_put_contents($runtime.'/t06-bulk-metrics.json', json_encode(['rows' => 10000, 'queries' => $queries, 'duration_seconds' => (hrtime(true) - $started) / 1e9], JSON_THROW_ON_ERROR));
        $this->assertLessThan(100, $queries, 'La importación debe usar lotes, no consultas individuales por fila.');
        $this->assertSame([10000, 10000, 0, 0], [$report->total, $report->imported, $report->invalid, $report->duplicates]);
        $this->assertDatabaseCount('contacts', 10000);
        $this->assertDatabaseCount('contact_import_rows', 10000);
        $this->assertSame(10000, app(ContactImportService::class)->rows($report->id)->count());
    }
}
