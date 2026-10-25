<?php

namespace App\Services\Mcp\Tools;

use App\Services\AccountContext;
use App\Services\ContactService;
use App\Services\Mcp\Audit;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\ToolError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class ContactListTool extends Tool
{
    public function toArray(): array
    {
        $operation = $this->contract();

        return array_intersect_key($operation, array_flip(['name', 'description', 'inputSchema', 'outputSchema', 'annotations']));
    }

    private function contract(): array
    {
        $operations = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR)['operations'];

        return collect($operations)->firstWhere('name', $this->name());
    }

    public function shouldRegister(): bool
    {
        $context = request()->attributes->get('mcp.context');

        return $context instanceof DelegatedContext && array_diff($this->contract()['minimum_scopes'], $context->effectiveScopes()) === [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $context = request()->attributes->get('mcp.context');
        abort_unless($context instanceof DelegatedContext, 401);
        $operation = $this->contract();
        request()->attributes->set('mcp.outcome', 'not_applied');
        $prepared = false;
        try {
            $data = DB::transaction(function () use ($context, $operation, $request, &$prepared): array {
                $prepared = false;
                request()->attributes->remove('mcp.reference');
                // Same account→membership→client→grant→token order for reads and writes.
                $context->authorize($operation['minimum_scopes'], true);
                if (request()->attributes->get('mcp.argument_error') || ! ContractArguments::valid($request->all(), $operation['inputSchema'])) {
                    throw ValidationException::withMessages(['arguments' => 'Invalid arguments']);
                }
                $service = new ContactService(new AccountContext($context));
                $write = $this->name() === 'lists_create';
                $list = $write ? $service->saveList(null, $request->get('list')) : $service->list($request->get('list_id'));
                $data = ['id' => (int) $list->id, 'name' => $list->name, 'description' => $list->description, 'created_at' => $list->created_at?->utc()->format('Y-m-d\TH:i:s.u\Z'), 'updated_at' => $list->updated_at?->utc()->format('Y-m-d\TH:i:s.u\Z')];
                if (! $write) {
                    $data['contacts_count'] = (int) $list->contacts_count;
                }
                if ($data['id'] < 1 || $data['id'] > 9007199254740991 || (! $write && ($data['contacts_count'] < 0 || $data['contacts_count'] > 9007199254740991))) {
                    throw new \RuntimeException('Unrepresentable resource identifier');
                }
                app(Audit::class)->tool($context, $this->name(), 'succeeded');
                if ($write) {
                    request()->attributes->set('mcp.reference', ['kind' => 'contact_list', 'id' => (string) $list->id]);
                    $prepared = true;
                }

                return $data;
            }, 3);
            if ($this->name() === 'lists_create') {
                request()->attributes->set('mcp.outcome', 'applied');
                request()->attributes->set('mcp.reference', ['kind' => 'contact_list', 'id' => (string) $data['id']]);
            }

            return Response::structured(['data' => ['list' => $data], 'meta' => ['trace_id' => $context->correlationId(), 'observed_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')]]);
        } catch (Throwable $error) {
            // The callback completed, but transaction() did not acknowledge completion.
            // This includes an exception from an afterCommit observer: never promise rollback.
            if ($prepared && request()->attributes->get('mcp.outcome') !== 'applied') {
                request()->attributes->set('mcp.outcome', 'unknown');
            }
            $code = match (true) {
                $error instanceof ValidationException => 'validation_failed',
                $error instanceof ModelNotFoundException => 'not_found',
                $error instanceof HttpExceptionInterface && $error->getStatusCode() === 403 => 'forbidden',
                default => 'internal_error',
            };
            if ($error instanceof HttpExceptionInterface && $error->getStatusCode() === 401) {
                request()->attributes->set('mcp.transport_failure', new ProtocolError(401, -32600, 'Unauthorized'));
            }
            app(Audit::class)->tool($context, $this->name(), 'failed');
            $result = ToolError::result($this->name(), $code, request()->attributes->get('mcp.outcome', 'not_applied'), request()->attributes->get('mcp.reference'));

            return Response::error($result['content'][0]['text']);
        }
    }
}
