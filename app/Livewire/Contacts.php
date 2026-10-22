<?php

namespace App\Livewire;

use App\Services\AccountContext;
use App\Services\ContactService;
use App\Services\Permissions;
use Livewire\Component;
use Livewire\WithPagination;

class Contacts extends Component
{
    use WithPagination;

    public string $search = '';

    public ?int $listId = null;

    public ?int $editingContact = null;

    public bool $contactForm = false;

    public string $email = '';

    public string $name = '';

    public string $consentStatus = 'unknown';

    public string $consentSource = '';

    public string $consentedAt = '';

    public ?int $editingList = null;

    public bool $listForm = false;

    public string $listName = '';

    public string $listDescription = '';

    public ?int $associationList = null;

    public function boot(AccountContext $context): void
    {
        $context->authorize('contacts.read');
    }

    public function updatedSearch(): void
    {
        app(AccountContext::class)->authorize('contacts.read');
        $this->resetPage();
    }

    public function selectList(?int $id, ContactService $contacts): void
    {
        app(AccountContext::class)->authorize('contacts.read');
        if ($id !== null) {
            $contacts->list($id);
        }
        $this->listId = $id;
        $this->resetPage();
    }

    public function newContact(AccountContext $context): void
    {
        $context->authorize('contacts.write');
        $this->resetValidation();
        $this->reset('editingContact', 'email', 'name', 'consentStatus', 'consentSource', 'consentedAt');
        $this->contactForm = true;
    }

    public function editContact(int $id, ContactService $contacts, AccountContext $context): void
    {
        $context->authorize('contacts.write');
        $contact = $contacts->contact($id);
        $this->resetValidation();
        $this->editingContact = $contact->id;
        $this->email = $contact->email;
        $this->name = $contact->name;
        $this->consentStatus = $contact->consent_status;
        $this->consentSource = $contact->consent_source ?? '';
        $this->consentedAt = $contact->consented_at?->format('Y-m-d\TH:i') ?? '';
        $this->contactForm = true;
    }

    public function saveContact(ContactService $contacts): void
    {
        $contacts->saveContact($this->editingContact, ['email' => $this->email, 'name' => $this->name, 'consent_status' => $this->consentStatus, 'consent_source' => $this->consentSource, 'consented_at' => $this->consentedAt ?: null]);
        $this->resetValidation();
        $this->reset('contactForm', 'editingContact', 'email', 'name', 'consentStatus', 'consentSource', 'consentedAt');
        session()->flash('success', 'Contacto guardado.');
    }

    public function deleteContact(int $id, ContactService $contacts): void
    {
        $contacts->deleteContact($id);
        $this->resetValidation();
        $this->reset('contactForm', 'editingContact');
        session()->flash('success', 'Contacto eliminado de las listas. Su registro se conserva para evitar reactivaciones.');
    }

    public function newList(AccountContext $context): void
    {
        $context->authorize('contacts.write');
        $this->resetValidation();
        $this->reset('editingList', 'listName', 'listDescription');
        $this->listForm = true;
    }

    public function editList(int $id, ContactService $contacts, AccountContext $context): void
    {
        $context->authorize('contacts.write');
        $list = $contacts->list($id);
        $this->resetValidation();
        $this->editingList = $list->id;
        $this->listName = $list->name;
        $this->listDescription = $list->description;
        $this->listForm = true;
    }

    public function saveList(ContactService $contacts): void
    {
        $contacts->saveList($this->editingList, ['name' => $this->listName, 'description' => $this->listDescription]);
        $this->resetValidation();
        $this->reset('listForm', 'editingList', 'listName', 'listDescription');
        session()->flash('success', 'Lista guardada.');
    }

    public function deleteList(int $id, ContactService $contacts): void
    {
        $contacts->deleteList($id);
        $this->resetValidation();
        if ($this->associationList === $id) {
            $this->associationList = null;
        }
        if ($this->listId === $id) {
            $this->listId = null;
            $this->resetPage();
        }
        $this->reset('listForm', 'editingList');
        session()->flash('success', 'Lista eliminada. Sus contactos se conservan.');
    }

    public function chooseAssociationList(int $id, ContactService $contacts, AccountContext $context): void
    {
        $context->authorize('contacts.write');
        $contacts->list($id);
        $this->associationList = $id;
        $this->listId = null;
        $this->resetPage();
    }

    public function associate(int $contactId, ContactService $contacts): void
    {
        app(AccountContext::class)->authorize('contacts.write');
        $this->validate(['associationList' => ['required', 'integer']]);
        $contacts->associate($this->associationList, $contactId, true);
        $this->resetValidation();
        session()->flash('success', 'Contacto añadido a la lista.');
    }

    public function detach(int $listId, int $contactId, ContactService $contacts): void
    {
        $contacts->associate($listId, $contactId, false);
        $this->resetValidation();
        session()->flash('success', 'Contacto retirado de la lista. El contacto se conserva.');
    }

    public function render(ContactService $contacts, AccountContext $context)
    {
        $membership = $context->authorize('contacts.read');

        return view('livewire.contacts', [
            'account' => $membership->account,
            'canWrite' => Permissions::allows($membership, 'contacts.write'),
            'contacts' => $contacts->contacts($this->listId, $this->search)->paginate(15),
            'lists' => $contacts->lists()->paginate(10, ['*'], 'listsPage'),
            'selectedList' => $this->listId === null ? null : $contacts->list($this->listId),
            'associationTarget' => $this->associationList === null ? null : $contacts->list($this->associationList),
        ])->layout('components.app-layout', ['title' => 'Contactos y listas']);
    }
}
