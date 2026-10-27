<?php

namespace App\Services\Mcp;

use App\Services\AccountContext;
use App\Services\ContactImportService;
use App\Services\ContactService;
use App\Services\Mcp\Tools\DataTool;
use App\Services\TemplateService;
use App\Support\ContactCsv;
use App\Support\TemplatePreview;
use App\Support\TemplateRenderer;

/** Small adapters over the same services used by the web and REST API. */
final class DataOperations
{
    public function run(DataTool $tool, DelegatedContext $context, array $arguments): array
    {
        if (in_array($tool->name(), McpSendAdapter::NAMES, true)) {
            return app(McpSendAdapter::class)->run($tool, $context, $arguments);
        }
        if (in_array($tool->name(), BusinessOperations::NAMES, true)) {
            return app(BusinessOperations::class)->run($tool, $context, $arguments);
        }
        $account = new AccountContext($context);
        $contacts = new ContactService($account);
        $templates = new TemplateService($account, app(TemplateRenderer::class), app(TemplatePreview::class));
        $imports = new ContactImportService($account, app(ContactCsv::class));
        $name = $tool->name();
        $page = app(DataPage::class);
        if ($name === 'contacts_list') {
            $filters = ['list_id' => isset($arguments['list_id']) ? (string) $arguments['list_id'] : null, 'search' => $arguments['search'] ?? ''];

            return $page->make($tool, $context, $contacts->contacts($arguments['list_id'] ?? null, $filters['search']), $arguments, $filters, DataDto::contact(...));
        }
        if ($name === 'lists_list') {
            return $page->make($tool, $context, $contacts->lists(), $arguments, [], DataDto::list(...));
        }
        if ($name === 'imports_list') {
            return $page->make($tool, $context, $imports->imports(), $arguments, [], DataDto::report(...));
        }
        if ($name === 'imports_get') {
            $report = $imports->report($arguments['import_id']);

            return $page->make($tool, $context, $imports->rows($report->id), $arguments, ['import_id' => (string) $report->id],
                DataDto::row(...), ['report' => DataDto::report($report)], rows: true);
        }
        if ($name === 'templates_list') {
            $search = $arguments['search'] ?? '';

            return $page->make($tool, $context, $templates->templates($search), $arguments, ['search' => $search], fn ($template) => DataDto::template($template, false));
        }
        $data = match ($name) {
            'contacts_get' => ['contact' => DataDto::contact($contacts->contact($arguments['contact_id']))],
            'contacts_create', 'contacts_update' => $this->saveContact($contacts, $arguments),
            'contacts_delete' => $this->deleteContact($contacts, $arguments['contact_id']),
            'lists_get' => ['list' => DataDto::list($contacts->list($arguments['list_id']))],
            'lists_create', 'lists_update' => $this->saveList($contacts, $arguments),
            'lists_delete' => $this->deleteList($contacts, $arguments['list_id']),
            'lists_attach_contact', 'lists_detach_contact' => $this->associate($contacts, $arguments, $name === 'lists_attach_contact'),
            'templates_get' => ['template' => DataDto::template($templates->template($arguments['template_id']))],
            'templates_create', 'templates_update' => $this->saveTemplate($templates, $arguments),
            'templates_delete' => $this->deleteTemplate($templates, $arguments['template_id']),
            'templates_preview' => $this->preview($templates, $arguments),
            default => throw new \LogicException('Unimplemented data operation'),
        };

        return ['data' => $data, 'meta' => DataDto::meta($context)];
    }

    private function saveContact(ContactService $service, array $arguments): array
    {
        $contact = $service->saveContact($arguments['contact_id'] ?? null, $arguments['contact']);
        $this->reference('contact', $contact->id);

        return ['contact' => DataDto::contact($contact)];
    }

    private function deleteContact(ContactService $service, int $id): array
    {
        $service->deleteContact($id);
        $this->reference('contact', $id);

        return ['id' => $id, 'deleted' => true];
    }

    private function saveList(ContactService $service, array $arguments): array
    {
        $list = $service->saveList($arguments['list_id'] ?? null, $arguments['list']);
        $this->reference('contact_list', $list->id);

        return ['list' => DataDto::list($list, false)];
    }

    private function deleteList(ContactService $service, int $id): array
    {
        $service->deleteList($id);
        $this->reference('contact_list', $id);

        return ['id' => $id, 'deleted' => true];
    }

    private function associate(ContactService $service, array $arguments, bool $attach): array
    {
        $service->associate($arguments['list_id'], $arguments['contact_id'], $attach);
        $this->reference('contact_list', $arguments['list_id']);

        return ['list_id' => $arguments['list_id'], 'contact_id' => $arguments['contact_id'], 'attached' => $attach];
    }

    private function saveTemplate(TemplateService $service, array $arguments): array
    {
        $template = $service->save($arguments['template_id'] ?? null, $arguments['template']);
        $this->reference('template', $template->id);

        return ['template' => DataDto::template($template)];
    }

    private function deleteTemplate(TemplateService $service, int $id): array
    {
        $service->delete($id);
        $this->reference('template', $id);

        return ['id' => $id, 'deleted' => true];
    }

    private function preview(TemplateService $service, array $arguments): array
    {
        $source = $arguments['source'];
        $id = $source['type'] === 'stored' ? $source['template_id'] : null;
        $fields = $id === null ? $source['content'] : $service->template($id)->only(['subject', 'html', 'text']);
        $preview = $service->preview($id, $fields, $arguments['sample'] ?? []);
        if (strlen($preview['document']) > 1048576) {
            throw new DataFailure('result_too_large');
        }

        return ['preview' => $preview];
    }

    private function reference(string $kind, int $id): void
    {
        request()->attributes->set('mcp.reference', ['kind' => $kind, 'id' => (string) $id]);
    }
}
