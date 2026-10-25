<?php

namespace App\Http\Controllers;

use App\Services\Mcp\GrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class McpConnectionsController extends Controller
{
    public function index(Request $request)
    {
        // Never load token material or use the active account as authority.
        $connections = DB::table('mcp_grants as grants')
            ->join('accounts', 'accounts.id', '=', 'grants.account_id')
            ->join('mcp_clients as clients', 'clients.id', '=', 'grants.client_id')
            ->where('grants.user_id', $request->user()->id)
            ->select(['grants.id', 'grants.scopes', 'grants.expires_at', 'grants.revoked_at', 'grants.created_at', 'accounts.name as account_name', 'clients.name as client_name'])
            ->orderByDesc('grants.created_at')->orderByDesc('grants.id')->simplePaginate(20);

        $events = DB::table('mcp_audit_events')->where('actor_id', $request->user()->id)->select(['operation', 'outcome', 'account_id', 'client_id', 'correlation_id', 'created_at'])->orderByDesc('id')->limit(20)->get();

        return response()->view('mcp.connections', compact('connections', 'events'))->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function revoke(Request $request, string $grant, GrantService $service)
    {
        $service->revoke($grant, $request->user()->id);

        return redirect()->route('mcp.connections')->with('status', 'Conexión revocada. El cliente ya no puede utilizarla.');
    }
}
