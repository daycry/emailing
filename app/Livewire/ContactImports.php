<?php

namespace App\Livewire;

use App\Services\AccountContext;
use App\Services\ContactImportService;
use App\Services\ContactService;
use App\Services\Permissions;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class ContactImports extends Component
{
    use WithFileUploads;
    use WithPagination;

    public $file;

    public ?int $destinationList = null;

    public ?int $reportId = null;

    public string $listSearch = '';

    public function import(ContactImportService $imports): void
    {
        app(AccountContext::class)->authorize('contacts.write');
        app(AccountContext::class)->authorize('contacts.read');
        $this->validate(['file' => ['required', 'file']]);
        $report = $imports->import($this->file, $this->destinationList);
        $this->resetValidation();
        $this->file->delete();
        $this->reset('file');
        $this->reportId = $report->id;
        $this->resetPage('rowsPage');
        session()->flash('success', 'Importación finalizada. Revisa el resultado de cada registro.');
    }

    public function selectReport(int $id, ContactImportService $imports): void
    {
        $imports->report($id);
        $this->reportId = $id;
        $this->resetPage('rowsPage');
    }

    public function selectList(?int $id, ContactService $contacts): void
    {
        app(AccountContext::class)->authorize('contacts.write');
        app(AccountContext::class)->authorize('contacts.read');
        if ($id !== null) {
            $contacts->list($id);
        }
        $this->destinationList = $id;
    }

    public function updatedListSearch(): void
    {
        app(AccountContext::class)->authorize('contacts.read');
        $this->resetPage('destinationPage');
    }

    public function render(ContactImportService $imports, ContactService $contacts, AccountContext $context)
    {
        $member = $context->authorize('contacts.read');
        $this->validateOnly('listSearch', ['listSearch' => ['string', 'max:150']]);
        $report = $this->reportId === null ? null : $imports->report($this->reportId);
        $lists = $contacts->lists();
        if ($this->listSearch !== '') {
            $lists->where('name', 'like', '%'.$this->listSearch.'%');
        }

        return view('livewire.contact-imports', [
            'canWrite' => Permissions::allows($member, 'contacts.write'),
            'reports' => $imports->imports()->paginate(10, ['*'], 'importsPage'),
            'report' => $report,
            'rows' => $report ? $imports->rows($report->id)->paginate(25, ['*'], 'rowsPage') : null,
            'lists' => $lists->paginate(10, ['*'], 'destinationPage'),
            'destination' => $this->destinationList === null ? null : $contacts->list($this->destinationList),
            'maxBytes' => (int) config('emailing.import_max_bytes'),
            'maxRows' => (int) config('emailing.import_max_rows'),
        ])->layout('components.app-layout', ['title' => 'Importar contactos']);
    }
}
