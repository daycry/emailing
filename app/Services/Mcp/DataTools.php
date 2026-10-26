<?php

namespace App\Services\Mcp;

/** One implemented catalogue for discovery and direct-call authorization. */
final class DataTools
{
    public const MAP = [
        'activity_attempts' => Tools\ListActivityAttempts::class,
        'activity_list' => Tools\ListActivity::class,
        'activity_metrics' => Tools\GetActivityMetrics::class,
        'campaigns_authorize' => Tools\AuthorizeCampaign::class,
        'campaigns_create' => Tools\CreateCampaign::class,
        'campaigns_get' => Tools\GetCampaign::class,
        'campaigns_list' => Tools\ListCampaigns::class,
        'campaigns_pause' => Tools\PauseCampaign::class,
        'campaigns_resume' => Tools\ResumeCampaign::class,
        'campaigns_review' => Tools\ReviewCampaign::class,
        'campaigns_update' => Tools\UpdateCampaign::class,
        'suppressions_add' => Tools\AddSuppression::class,
        'suppressions_list' => Tools\ListSuppressions::class,
        'suppressions_remove' => Tools\RemoveSuppression::class,
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
