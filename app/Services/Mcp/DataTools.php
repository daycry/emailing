<?php

namespace App\Services\Mcp;

/** One implemented catalogue for discovery and direct-call authorization. */
final class DataTools
{
    public const MAP = [
        'contacts_create' => Tools\CreateContact::class,
        'contacts_delete' => Tools\DeleteContact::class,
        'contacts_get' => Tools\GetContact::class,
        'contacts_list' => Tools\ListContacts::class,
        'contacts_update' => Tools\UpdateContact::class,
        'imports_get' => Tools\GetImport::class,
        'imports_list' => Tools\ListImports::class,
        'lists_attach_contact' => Tools\AttachContactToList::class,
        'lists_create' => Tools\CreateList::class,
        'lists_delete' => Tools\DeleteList::class,
        'lists_detach_contact' => Tools\DetachContactFromList::class,
        'lists_get' => Tools\GetList::class,
        'lists_list' => Tools\ListLists::class,
        'lists_update' => Tools\UpdateList::class,
        'templates_create' => Tools\CreateTemplate::class,
        'templates_delete' => Tools\DeleteTemplate::class,
        'templates_get' => Tools\GetTemplate::class,
        'templates_list' => Tools\ListTemplates::class,
        'templates_preview' => Tools\PreviewTemplate::class,
        'templates_update' => Tools\UpdateTemplate::class,
    ];
}
