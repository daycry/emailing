<?php

namespace App\Services\Mcp\Tools;

use App\Exceptions\DomainConflict;
use App\Services\Mcp\Audit;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\DataFailure;
use App\Services\Mcp\DataOperations;
use App\Services\Mcp\DelegatedContext;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\ResponseBudget;
use App\Services\Mcp\ToolError;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class DataTool extends Tool
{
    public function contract(): array
    {
        return ContractArguments::operation($this->name());
    }

    public function toArray(): array
    {
        return array_intersect_key($this->contract(), array_flip(['name', 'description', 'inputSchema', 'outputSchema', 'annotations']));
    }

    public function shouldRegister(): bool
    {
        $context = request()->attributes->get('mcp.context');
        if (! $context instanceof DelegatedContext) {
            return false;
        }
        $scopes = request()->attributes->get('mcp.registry_scopes') ?? $context->effectiveScopes();

        return array_diff($this->contract()['minimum_scopes'], $scopes) === [];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $context = request()->attributes->get('mcp.context');
        abort_unless($context instanceof DelegatedContext, 401);
        $operation = $this->contract();
        $write = ! $operation['annotations']['readOnlyHint'];
        $prepared = false;
        request()->attributes->set('mcp.outcome', 'not_applied');
        try {
            $dto = DB::transaction(function () use ($context, $operation, $request, $write, &$prepared): array {
                $prepared = false;
                request()->attributes->remove('mcp.reference');
                $context->authorize($operation['minimum_scopes'], true);
                $arguments = request()->attributes->get('mcp.arguments');
                if (! $arguments instanceof stdClass) {
                    // Internal SDK callers provide argument maps. HTTP always retains its typed AST.
                    $arguments = json_decode(json_encode((object) $request->all(), JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                    if (property_exists($arguments, 'sample') && $arguments->sample === []) {
                        $arguments->sample = new stdClass;
                    }
                }
                if (request()->attributes->get('mcp.argument_error') || ! ContractArguments::valid($arguments, $operation['inputSchema'])) {
                    throw ValidationException::withMessages(['arguments' => 'Invalid arguments']);
                }
                $input = json_decode(json_encode($arguments, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
                $dto = app(DataOperations::class)->run($this, $context, $input);
                $typed = json_decode(json_encode($dto, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
                if (! ContractArguments::valid($typed, $operation['outputSchema'])) {
                    throw new \LogicException('MCP result does not match its schema');
                }
                if (! app(ResponseBudget::class)->fits($this, $dto)) {
                    throw new DataFailure('result_too_large');
                }
                app(Audit::class)->tool($context, $this->name(), 'succeeded');
                $prepared = $write;

                return $dto;
            }, 3);
            if ($write) {
                request()->attributes->set('mcp.outcome', 'applied');
            }

            return Response::structured($dto);
        } catch (Throwable $error) {
            if ($prepared && request()->attributes->get('mcp.outcome') !== 'applied') {
                request()->attributes->set('mcp.outcome', 'unknown');
            } elseif (! $prepared) {
                request()->attributes->remove('mcp.reference');
            }
            $code = match (true) {
                $error instanceof DataFailure => $error->errorCode,
                $error instanceof DomainConflict => $error->reason,
                $error instanceof ValidationException => 'validation_failed',
                $error instanceof ModelNotFoundException => 'not_found',
                $error instanceof HttpExceptionInterface && $error->getStatusCode() === 404 => 'not_found',
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
