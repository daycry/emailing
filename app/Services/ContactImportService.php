<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactImportRow;
use App\Models\ContactList;
use App\Models\Membership;
use App\Support\ContactCsv;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ContactImportService
{
    public function __construct(private AccountContext $context, private ContactCsv $csv) {}

    public function imports(): Builder
    {
        return ContactImport::where('account_id', $this->context->authorize('contacts.read')->account_id)->orderByDesc('id');
    }

    public function report(int $id): ContactImport
    {
        return $this->imports()->findOrFail($id);
    }

    public function rows(int $id): Builder
    {
        $report = $this->report($id);

        return ContactImportRow::where('account_id', $report->account_id)->where('contact_import_id', $report->id)->orderBy('row_number');
    }

    public function import(UploadedFile $file, ?int $listId = null): ContactImport
    {
        $member = $this->context->authorize('contacts.write');
        $this->context->authorize('contacts.read');
        if ($file instanceof TemporaryUploadedFile) {
            $binding = session('contact_import_uploads.'.hash('sha256', $file->getFilename()));
            abort_unless(is_array($binding) && $binding['account_id'] === $member->account_id && $binding['user_id'] === auth()->id() && $binding['at'] >= time() - 3600, 403);
            if (! $file->exists()) {
                throw ValidationException::withMessages(['file' => 'La subida temporal ya no está disponible. Vuelve a seleccionar el archivo.']);
            }
        }
        if ($listId !== null) {
            ContactList::where('account_id', $member->account_id)->findOrFail($listId);
        }
        $maxBytes = (int) config('emailing.import_max_bytes');
        Validator::make(['file' => $file], ['file' => ['bail', 'required', 'file', 'max:'.(int) ceil($maxBytes / 1024), 'extensions:csv']])->validate();
        $contents = file_get_contents($file->getRealPath(), false, null, 0, $maxBytes + 1);
        if ($contents === false || strlen($contents) > $maxBytes) {
            throw ValidationException::withMessages(['file' => 'El archivo supera el límite de '.$maxBytes.' bytes o no se puede leer.']);
        }
        $rawRows = $this->csv->parse($contents, (int) config('emailing.import_max_rows'));
        [$rows, $candidates] = $this->validateRows($rawRows);
        $path = 'sources/'.Str::uuid().'.csv';
        Storage::disk('contact-imports')->put($path, $contents);
        try {
            return DB::transaction(function () use ($member, $listId, $rows, $candidates, $path) {
                $current = $this->context->authorize('contacts.write');
                $this->context->authorize('contacts.read');
                abort_unless($current->account_id === $member->account_id, 403);
                $account = Account::whereKey($current->account_id)->lockForUpdate()->firstOrFail();
                $lockedMember = Membership::where('account_id', $account->id)->where('user_id', auth()->id())->lockForUpdate()->first();
                abort_unless($lockedMember && Permissions::allows($lockedMember, 'contacts.write') && Permissions::allows($lockedMember, 'contacts.read'), 403);
                if ($listId !== null) {
                    ContactList::where('account_id', $account->id)->lockForUpdate()->findOrFail($listId);
                }
                $existing = [];
                foreach (array_chunk(array_keys($candidates), 500) as $emails) {
                    foreach (Contact::where('account_id', $account->id)->whereIn('email', $emails)->lockForUpdate()->get(['id', 'email']) as $contact) {
                        $existing[$contact->email] = $contact->id;
                    }
                }
                $new = [];
                $timestamp = now()->format('Y-m-d H:i:s');
                foreach ($candidates as $email => $candidate) {
                    $index = $candidate['index'];
                    if (isset($existing[$email])) {
                        $rows[$index]['status'] = 'duplicate';
                        $rows[$index]['message'] = 'Email activo existente en esta cuenta; no se modifica.';
                    } else {
                        $new[$email] = ['account_id' => $account->id, 'created_at' => $timestamp, 'updated_at' => $timestamp] + $candidate['data'];
                        $rows[$index]['status'] = 'imported';
                        $rows[$index]['message'] = 'Contacto creado.';
                    }
                }
                foreach (array_chunk(array_values($new), 500) as $chunk) {
                    Contact::insert($chunk);
                }
                if ($listId !== null) {
                    foreach (array_chunk(array_keys($new), 500) as $emails) {
                        $ids = Contact::where('account_id', $account->id)->whereIn('email', $emails)->lockForUpdate()->pluck('id');
                        DB::table('contact_list_memberships')->insert($ids->map(fn ($id) => ['account_id' => $account->id, 'contact_list_id' => $listId, 'contact_id' => $id])->all());
                    }
                }
                $counts = array_count_values(array_column($rows, 'status'));
                $report = ContactImport::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'destination_list_id' => $listId, 'source_path' => $path, 'total' => count($rows), 'imported' => $counts['imported'] ?? 0, 'invalid' => $counts['invalid'] ?? 0, 'duplicates' => $counts['duplicate'] ?? 0]);
                foreach (array_chunk($rows, 500) as $chunk) {
                    ContactImportRow::insert(array_map(fn ($row) => ['account_id' => $account->id, 'contact_import_id' => $report->id] + $row, $chunk));
                }
                AuditEvent::create(['account_id' => $account->id, 'actor_id' => auth()->id(), 'action' => 'contacts.imported', 'metadata' => ['import_id' => $report->id, 'source' => 'csv', 'total' => $report->total, 'imported' => $report->imported, 'invalid' => $report->invalid, 'duplicates' => $report->duplicates]]);

                return $report;
            });
        } catch (Throwable $e) {
            Storage::disk('contact-imports')->delete($path);
            throw $e;
        }
    }

    private function validateRows(array $rawRows): array
    {
        $rows = [];
        $candidates = [];
        $utc = new DateTimeZone('UTC');
        $now = now('UTC')->toDateTimeImmutable();
        foreach ($rawRows as $index => $raw) {
            $data = array_fill_keys(ContactCsv::HEADERS, '');
            $data = array_replace($data, $raw);
            $data['email'] = mb_strtolower(trim($data['email']));
            $data['consent_status'] = trim($data['consent_status']) ?: 'unknown';
            $data['consent_source'] = trim($data['consent_source']) ?: null;
            $data['consented_at'] = trim($data['consented_at']) ?: null;
            // CSV parsing supplies strings. Bound lengths before native format validators;
            // avoid constructing a full Laravel Validator for every one of 10,000 rows.
            $errors = [];
            if ($data['email'] === '' || mb_strlen($data['email']) > 254 || filter_var($data['email'], FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE) === false) {
                $errors[] = 'Email inválido o superior a 254 caracteres.';
            }
            if (mb_strlen($data['name']) > 150) {
                $errors[] = 'Nombre superior a 150 caracteres.';
            }
            if (! in_array($data['consent_status'], ['unknown', 'granted', 'revoked'], true)) {
                $errors[] = 'Estado de consentimiento inválido.';
            }
            if ($data['consent_source'] !== null && mb_strlen($data['consent_source']) > 255) {
                $errors[] = 'Fuente superior a 255 caracteres.';
            }
            $consentedAt = null;
            if ($data['consented_at'] !== null) {
                if (strlen($data['consented_at']) !== 16 || ! preg_match('/^[1-9]\d{3}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $data['consented_at'])) {
                    $errors[] = 'Fecha inválida: usa YYYY-MM-DDTHH:MM en UTC.';
                } else {
                    $consentedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $data['consented_at'], $utc);
                    if (! $consentedAt || $consentedAt->format('Y-m-d\TH:i') !== $data['consented_at'] || $consentedAt > $now) {
                        $errors[] = 'Fecha inexistente o futura.';
                    }
                }
            }
            if ($data['consent_status'] === 'granted' && ($data['consent_source'] === null || $data['consented_at'] === null)) {
                $errors[] = 'Otorgado requiere fuente y fecha explícitas.';
            }
            $row = ['row_number' => $index + 2, 'email' => mb_substr($data['email'], 0, 254), 'status' => 'invalid', 'message' => ''];
            if ($errors !== []) {
                $row['message'] = implode(' ', $errors);
            } elseif (isset($candidates[$data['email']])) {
                $row['status'] = 'duplicate';
                $row['message'] = 'Email repetido en el archivo; se conserva la primera fila válida.';
            } else {
                $data['consented_at'] = $data['consent_status'] === 'granted' ? $consentedAt->format('Y-m-d H:i:s') : null;
                $data['consent_source'] = $data['consent_status'] === 'granted' ? $data['consent_source'] : null;
                $candidates[$data['email']] = ['index' => $index, 'data' => $data];
                $row['status'] = 'pending';
            }
            $rows[] = $row;
        }

        return [$rows, $candidates];
    }

    public function download(int $id): StreamedResponse
    {
        $this->report($id);

        return response()->streamDownload(function () use ($id) {
            $query = $this->rows($id);
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['registro', 'email', 'resultado', 'detalle'], ',', '"', '');
            foreach ($query->cursor() as $row) {
                $cells = [$row->row_number, $row->email, $row->status, $row->message];
                $cells = array_map(fn ($value) => preg_match('/^[\s\x00-\x20]*[=+@-]/u', (string) $value) ? "'".$value : $value, $cells);
                fputcsv($stream, $cells, ',', '"', '');
            }
            fclose($stream);
        }, 'resultado-importacion-'.$id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
