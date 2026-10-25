<?php

namespace App\Services\Mcp;

use Laravel\Mcp\Server;

/** Business registrations are added by their roadmap tasks, never advertised speculatively. */
class TransportServer extends Server
{
    protected array $tools = DataTools::MAP;

    protected string $name = 'Correo MCP';

    protected string $version = '1.0.0';

    protected array $supportedProtocolVersion = ['2025-11-25', '2026-07-28'];

    public int $maxPaginationLength = 100;

    public int $defaultPaginationLength = 25;

    protected function boot(): void
    {
        $this->methods['tools/list'] = ListTools::class;
        $this->methods['tools/call'] = CallTool::class;
    }
}
