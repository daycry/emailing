<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AuditEvent;
use App\Models\Contact;
use App\Models\ContactList;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ContactService
{
    public function __construct(private AccountContext $context) {}

    public function contacts(?int $listId = null, string $search = ''): Builder
    {
        $accountId = $this->context->authorize('contacts.read')->account_id;
        $query = Contact::where('account_id', $accountId);
        if ($listId !== null) {
            ContactList::where('account_id', $accountId)->findOrFail($listId);
            $query->whereHas('lists', fn ($lists) => $lists->where('contact_lists.id', $listId)->where('contact_lists.account_id', $accountId));
        }
        if ($search !== '') {
            Validator::make(['search' => $search], ['search' => ['string', 'max:150']])->validate();
            $query->where(fn ($q) => $q->where('email', 'like', '%'.$search.'%')->orWhere('name', 'like', '%'.$search.'%'));
        }

        return $query->orderBy('id', 'desc');
    }

    public function lists(): Builder
    {
        $accountId = $this->context->authorize('contacts.read')->account_id;

        return ContactList::where('account_id', $accountId)->withCount('contacts')->orderBy('id', 'desc');
    }

    public function contact(int $id): Contact
    {
        return $this->contacts()->findOrFail($id);
    }

    public function list(int $id): ContactList
    {
        return $this->lists()->findOrFail($id);
    }

    private function writableAccount(): Account
    {
        return $this->context->writable(['contacts.write']);
    }

    private function audit(int $accountId, string $action, array $metadata): void
    {
        AuditEvent::create(['account_id' => $accountId, 'actor_id' => $this->context->actorId(), 'action' => $action, 'metadata' => $metadata + $this->context->provenance()]);
    }

    public function saveContact(?int $id, array $input): Contact
    {
        return DB::transaction(function () use ($id, $input) {
            $account = $this->writableAccount();
            $contact = $id === null ? null : Contact::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            Validator::make($input, [
                'email' => ['bail', 'required', 'string', 'max:254'],
                'consent_source' => ['bail', 'nullable', 'string', 'max:255'],
            ])->validate();
            $input['email'] = mb_strtolower(trim($input['email']));
            $input['consent_source'] = isset($input['consent_source']) ? (trim($input['consent_source']) ?: null) : null;
            $data = Validator::make($input, [
                'email' => ['bail', 'required', 'string', 'max:254', 'email:filter_unicode'],
                'name' => ['present', 'string', 'max:150'],
                'consent_status' => ['required', Rule::in(['unknown', 'granted', 'revoked'])],
                'consent_source' => ['nullable', 'required_if:consent_status,granted', 'string', 'max:255'],
                'consented_at' => ['nullable', 'required_if:consent_status,granted', 'date_format:Y-m-d\TH:i', 'before_or_equal:now'],
            ])->validate();
            $duplicate = Contact::where('account_id', $account->id)->where('email', $data['email'])->when($contact, fn ($query) => $query->whereKeyNot($contact->id))->lockForUpdate()->first();
            if ($duplicate) {
                throw ValidationException::withMessages(['email' => 'Ya existe un contacto activo con este email en la cuenta.']);
            }
            if ($contact && $contact->email !== $data['email']) {
                throw ValidationException::withMessages(['email' => 'El email identifica al contacto. Crea otro contacto para otra dirección.']);
            }
            // A generic CRUD form cannot undo a withdrawal; a dedicated future consent flow must do that.
            if ($contact?->consent_status === 'revoked' && $data['consent_status'] !== 'revoked') {
                throw ValidationException::withMessages(['consent_status' => 'No se puede reactivar un consentimiento revocado desde este formulario.']);
            }
            if ($data['consent_status'] === 'granted') {
                $data['consented_at'] = Carbon::createFromFormat('Y-m-d\TH:i', $data['consented_at'], 'UTC')->startOfMinute();
            } else {
                $data['consent_source'] = null;
                $data['consented_at'] = null;
            }
            $previous = $contact ? $contact->only(['consent_status', 'consent_source', 'consented_at']) : null;
            if ($contact) {
                $contact->fill($data)->save();
            } else {
                $contact = Contact::create(['account_id' => $account->id] + $data);
            }
            $this->audit($account->id, $id === null ? 'contact.created' : 'contact.updated', ['contact_id' => $contact->id, 'previous_consent' => $previous, 'consent' => $contact->only(['consent_status', 'consent_source', 'consented_at'])]);

            return $contact;
        });
    }

    public function deleteContact(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->writableAccount();
            $contact = Contact::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            $contact->lists()->detach();
            $contact->delete();
            $this->audit($account->id, 'contact.deleted', ['contact_id' => $id]);
        });
    }

    public function saveList(?int $id, array $input): ContactList
    {
        return DB::transaction(function () use ($id, $input) {
            $account = $this->writableAccount();
            $list = $id === null ? null : ContactList::where('account_id', $account->id)->lockForUpdate()->findOrFail($id);
            $data = Validator::make($input, ['name' => ['required', 'string', 'max:150'], 'description' => ['present', 'string', 'max:500']])->validate();
            if ($list) {
                $list->update($data);
            } else {
                $list = ContactList::create(['account_id' => $account->id] + $data);
            }
            $this->audit($account->id, $id === null ? 'contact_list.created' : 'contact_list.updated', ['list_id' => $list->id]);

            return $list;
        });
    }

    public function deleteList(int $id): void
    {
        DB::transaction(function () use ($id) {
            $account = $this->writableAccount();
            ContactList::where('account_id', $account->id)->findOrFail($id)->delete();
            $this->audit($account->id, 'contact_list.deleted', ['list_id' => $id]);
        });
    }

    public function associate(int $listId, int $contactId, bool $attach): void
    {
        DB::transaction(function () use ($listId, $contactId, $attach) {
            $account = $this->writableAccount();
            $list = ContactList::where('account_id', $account->id)->lockForUpdate()->findOrFail($listId);
            $contact = Contact::where('account_id', $account->id)->lockForUpdate()->findOrFail($contactId);
            if ($attach) {
                $list->contacts()->syncWithoutDetaching([$contact->id => ['account_id' => $account->id]]);
            } else {
                $list->contacts()->detach($contact->id);
            }
            $this->audit($account->id, $attach ? 'contact_list.attached' : 'contact_list.detached', ['list_id' => $listId, 'contact_id' => $contactId]);
        });
    }
}
