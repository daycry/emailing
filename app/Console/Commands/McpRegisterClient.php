<?php

namespace App\Console\Commands;

use App\Services\Mcp\OAuth\AuthorizationServer;
use App\Services\Mcp\OAuth\OAuthError;
use Illuminate\Console\Command;

final class McpRegisterClient extends Command
{
    protected $signature = 'mcp:client-register {name} {--redirect=*} {--native-port : Explicit native loopback port exception}';

    protected $description = 'Preregister a public MCP client with exact redirects; prints only public client metadata.';

    public function handle(AuthorizationServer $server): int
    {
        try {
            $client = $server->register(['client_name' => $this->argument('name'), 'redirect_uris' => $this->option('redirect'), 'token_endpoint_auth_method' => 'none'], false, (bool) $this->option('native-port'));
            $this->line(json_encode(['client_id' => $client->id, 'client_name' => $client->name, 'redirect_uris' => $client->redirect_uris], JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        } catch (OAuthError $error) {
            $this->error($error->error);

            return self::FAILURE;
        }
    }
}
