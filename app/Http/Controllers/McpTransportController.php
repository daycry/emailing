<?php

namespace App\Http\Controllers;

use App\Services\Mcp\Admission;
use App\Services\Mcp\BoundedHttpTransport;
use App\Services\Mcp\Concurrency;
use App\Services\Mcp\ContractArguments;
use App\Services\Mcp\DataTools;
use App\Services\Mcp\McpSendAdapter;
use App\Services\Mcp\OAuth\OAuthError;
use App\Services\Mcp\ProtocolError;
use App\Services\Mcp\RegistryCursor;
use App\Services\Mcp\TokenResolver;
use App\Services\Mcp\TransportServer;
use App\Services\Mcp\WireJson;
use App\Support\ApiError;
use App\Support\ApiNoStoreHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Mcp\Transport\HeaderValue;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class McpTransportController
{
    private const VERSIONS = ['2025-11-25', '2026-07-28'];

    public function __invoke(Request $request): mixed
    {
        $id = null;
        try {
            abort_unless(config('mcp.enabled'), 404);
            $origin = $request->header('Origin');
            if ($origin !== null && ! in_array($origin, array_merge([config('mcp.issuer')], config('mcp.allowed_origins', [])), true)) {
                throw new ProtocolError(403, -32600, 'Origin rejected');
            }
            [$token, $context] = app(TokenResolver::class)->resolve($request);
            $request->attributes->set('mcp.context', $context);
            app(Admission::class)->take([
                ['transport-token', (string) $token->id, 120, 60],
                ['transport-grant', $token->grant_id, 120, 60],
                ['transport-account', (string) $token->account_id, 600, 60],
            ]);
            if (! $request->isMethod('POST')) {
                throw new ProtocolError(405, -32600, 'Method not allowed');
            }
            $ast = $request->attributes->get('mcp.ast');
            if (! $ast instanceof stdClass || ($ast->jsonrpc ?? null) !== '2.0' || ! is_string($ast->method ?? null) || (property_exists($ast, 'params') && ! $ast->params instanceof stdClass)) {
                throw new ProtocolError;
            }
            if (property_exists($ast, 'id')) {
                if ((! is_int($ast->id) && ! is_string($ast->id)) || (is_int($ast->id) && abs($ast->id) > 9007199254740991) || (is_string($ast->id) && strlen($ast->id) > 128)) {
                    throw new ProtocolError;
                }
                $id = $ast->id;
            }
            $params = $ast->params ?? new stdClass;
            $request->attributes->set('mcp.method', $ast->method);
            $request->attributes->set('mcp.trace_id', $context->correlationId());
            $meta = $params->_meta ?? new stdClass;
            if (! $meta instanceof stdClass) {
                throw new ProtocolError;
            }
            $modern = property_exists($meta, 'io.modelcontextprotocol/protocolVersion') || property_exists($meta, 'io.modelcontextprotocol/clientCapabilities');
            $version = $modern ? ($meta->{'io.modelcontextprotocol/protocolVersion'} ?? null) : ($ast->method === 'initialize' ? ($params->protocolVersion ?? null) : $request->header('MCP-Protocol-Version'));
            if (! in_array($version, self::VERSIONS, true) || ($modern && $version !== '2026-07-28') || (! $modern && $version !== '2025-11-25')) {
                throw new ProtocolError(400, -32022, 'Unsupported protocol version');
            }
            if ($modern) {
                if (! ($meta->{'io.modelcontextprotocol/clientCapabilities'} ?? null) instanceof stdClass || $request->header('MCP-Session-Id') !== null || $ast->method === 'initialize') {
                    throw new ProtocolError;
                }
                $this->mirror($request, 'MCP-Protocol-Version', $version);
                $this->mirror($request, 'Mcp-Method', $ast->method);
                if ($ast->method === 'tools/call') {
                    $this->mirror($request, 'Mcp-Name', $params->name ?? null);
                }
            }
            $session = null;
            if (! $modern && $ast->method !== 'initialize') {
                $session = DB::table('mcp_sessions')->where('id', $request->header('MCP-Session-Id'))->where('token_id', $token->id)->where('expires_at', '>', now())->first();
                if (! $session || (! $session->initialized && $ast->method !== 'notifications/initialized')) {
                    throw new ProtocolError(404, -32600, 'Session unavailable');
                }
            }
            $allowed = ['initialize', 'server/discover', 'ping', 'tools/list', 'tools/call', 'notifications/initialized', 'notifications/cancelled'];
            if (! in_array($ast->method, $allowed, true)) {
                throw new ProtocolError(404, -32601, 'Method not found');
            }
            if ($ast->method === 'tools/call') {
                if (! is_string($params->name ?? null) || (property_exists($params, 'arguments') && ! $params->arguments instanceof stdClass)) {
                    throw new ProtocolError(400, -32602, 'Invalid arguments');
                }
                $contract = json_decode(file_get_contents(base_path('docs/api/mcp-tools.json')), true, 512, JSON_THROW_ON_ERROR);
                foreach ($contract['operations'] as $operation) {
                    if ($operation['name'] === $params->name) {
                        $request->attributes->set('mcp.operation', $operation['name']);
                        $request->attributes->set('mcp.outcome', $operation['annotations']['readOnlyHint'] ? 'not_applied' : 'unknown');
                        try {
                            $params->arguments = WireJson::integerInputs($params->arguments ?? new stdClass, $operation['inputSchema'], $operation['inputSchema']);
                            if ($operation['name'] === 'mail_send') {
                                $params->arguments = McpSendAdapter::normalizeArguments($params->arguments);
                            }
                        } catch (ProtocolError|ApiError $error) {
                            if (! isset(DataTools::MAP[$operation['name']])) {
                                throw $error;
                            }
                            $request->attributes->set('mcp.argument_error', true);
                        }
                        if (isset(DataTools::MAP[$operation['name']])) {
                            // Validate the typed JSON before SDK deserialization loses {} versus [].
                            $arguments = $params->arguments ?? new stdClass;
                            $request->attributes->set('mcp.arguments', $arguments);
                            if (! ContractArguments::valid($arguments, $operation['inputSchema'])) {
                                $request->attributes->set('mcp.argument_error', true);
                            }
                        }
                    }
                }
            }
            if ($ast->method === 'tools/list') {
                if (isset($params->per_page)) {
                    $params->per_page = WireJson::integerInputs($params->per_page, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], []);
                }
                if (isset($params->per_page) && (! is_int($params->per_page) || $params->per_page < 1 || $params->per_page > 100)) {
                    throw new ProtocolError(400, -32602, 'Invalid arguments');
                }
                if (isset($params->cursor) && (! is_string($params->cursor) || strlen($params->cursor) > 2048)) {
                    throw new ProtocolError(400, -32602, 'Invalid cursor');
                }
                if (isset($params->cursor)) {
                    app(RegistryCursor::class)->after($params->cursor);
                }
            }
            $ast->params = $params;
            $request->attributes->set('mcp.rpc_id', $id ?? str_repeat('x', 128));
            $request->attributes->set('mcp.registry_scopes', DB::transaction(function () use ($context): array {
                $context->authorize([], true);

                return $context->effectiveScopes();
            }, 3));
            $response = app(Concurrency::class)->run($token->account_id, function () use ($ast) {
                $wire = Request::create('/mcp', 'POST', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'], content: WireJson::encode($ast));
                $transport = new BoundedHttpTransport($wire);
                $server = app(TransportServer::class, ['transport' => $transport]);
                $server->start();
                $response = $transport->run();
                // The slot covers the complete synchronous SDK execution. Deferred
                // generators are refused before releasing it or sending any bytes.
                if ($response instanceof StreamedResponse) {
                    throw new ProtocolError(500, -32603, 'Internal error');
                }

                return $response;
            });
            if ($request->attributes->get('mcp.transport_failure') instanceof ProtocolError) {
                throw $request->attributes->get('mcp.transport_failure');
            }
            if ($response instanceof StreamedResponse) {
                throw new ProtocolError(500, -32603, 'Internal error');
            }
            $body = $response->getContent();
            if (strlen($body) > 8388608) {
                throw new ProtocolError(413, -32603, 'Result too large');
            }
            $decoded = $body === '' ? null : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (isset($decoded['error'])) {
                $code = (int) ($decoded['error']['code'] ?? -32603);
                throw new ProtocolError($response->getStatusCode(), $code, $code === -32602 ? 'Invalid arguments' : 'Internal error');
            }
            if (! $modern && $ast->method === 'initialize') {
                $sessionId = DB::transaction(function () use ($context, $token): string {
                    $context->authorize([], true);
                    $active = DB::table('mcp_sessions')->where('token_id', $token->id)->where('expires_at', '>', now())->count();
                    if ($active >= 8) {
                        throw new ProtocolError(429, -32000, 'Too many sessions');
                    }
                    $id = (string) Str::uuid();
                    DB::table('mcp_sessions')->insert(['id' => $id, 'token_id' => $token->id, 'initialized' => false, 'expires_at' => $token->expires_at, 'created_at' => now(), 'updated_at' => now()]);

                    return $id;
                });
                $response->headers->set('MCP-Session-Id', $sessionId);
            } elseif ($session && $ast->method === 'notifications/initialized') {
                DB::transaction(function () use ($context, $session): void {
                    $context->authorize([], true);
                    DB::table('mcp_sessions')->where('id', $session->id)->update(['initialized' => true, 'updated_at' => now()]);
                });
            }
            $response->headers = new ApiNoStoreHeaders($response->headers->all());

            return $response;
        } catch (OAuthError $error) {
            $response = $this->error($id, new ProtocolError($error->status, -32000, 'Rate limited'));
            $response->headers->set('Retry-After', (string) ($error->retryAfter ?? 1));

            return $response;
        } catch (ProtocolError $error) {
            return $this->error($id, $error);
        } catch (HttpExceptionInterface $error) {
            return $this->error($id, new ProtocolError($error->getStatusCode(), -32600, $error->getStatusCode() === 401 ? 'Authentication required' : 'Request rejected'));
        } catch (\Throwable $error) {
            report($error);

            return $this->error($id, new ProtocolError(500, -32603, 'Internal error'));
        }
    }

    private function mirror(Request $request, string $header, mixed $expected): void
    {
        $value = $request->header($header);
        if (! is_string($expected) || ! is_string($value) || ! HeaderValue::fromHeader($value)->matches($expected)) {
            throw new ProtocolError(400, -32020, 'Protocol headers do not match');
        }
    }

    private function error(mixed $id, ProtocolError $error): mixed
    {
        $rpc = ['code' => $error->rpcCode, 'message' => $error->safeMessage];
        if ($error->rpcCode === -32022) {
            $rpc['data'] = ['supported' => self::VERSIONS];
        }
        $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($error->status === 401) {
            $headers['WWW-Authenticate'] = 'Bearer resource_metadata="'.config('mcp.issuer').'/.well-known/oauth-protected-resource"';
        }
        if ($error->status === 429) {
            $headers['Retry-After'] = '1';
        }
        if ($error->status === 405) {
            $headers['Allow'] = 'POST';
        }
        $response = response()->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $rpc], $error->status, $headers);
        $response->headers = new ApiNoStoreHeaders($response->headers->all());

        return $response;
    }
}
