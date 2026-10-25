<x-app-layout title="Conexiones MCP">
    <h1 class="text-3xl font-bold">Conexiones MCP</h1>
    <p class="mt-3 text-slate-600">Estas conexiones delegan tus permisos a un cliente y pertenecen a la cuenta que autorizaste. Para cambiar permisos o renovar una conexión, vuelve a autorizar desde el cliente.</p>
    <x-feedback/>
    <div class="mt-6 space-y-4">
        @forelse($connections as $connection)
            @php($active = $connection->revoked_at === null && \Illuminate\Support\Carbon::parse($connection->expires_at)->isFuture())
            <article class="card" aria-labelledby="connection-{{ $connection->id }}">
                <h2 id="connection-{{ $connection->id }}" class="text-xl font-semibold break-words">{{ $connection->client_name }}</h2>
                <p class="mt-2">Cuenta: <strong>{{ $connection->account_name }}</strong></p>
                <p>Estado: {{ $connection->revoked_at ? 'Revocada' : ($active ? 'Activa' : 'Caducada') }}</p>
                <p>Caduca: <time datetime="{{ \Illuminate\Support\Carbon::parse($connection->expires_at)->toIso8601String() }}">{{ $connection->expires_at }} UTC</time></p>
                <p class="mt-3 font-semibold">Permisos autorizados</p>
                <ul class="list-disc pl-6">
                    @forelse(json_decode($connection->scopes, true, 16, JSON_THROW_ON_ERROR) as $scope)
                        <li>{{ \App\Services\Permissions::SCOPES[$scope] ?? $scope }}</li>
                    @empty
                        <li>Solo conexión y descubrimiento; sin acceso a datos.</li>
                    @endforelse
                </ul>
                @if($active)
                    <details class="mt-4">
                        <summary class="cursor-pointer font-semibold">Revocar conexión de {{ $connection->client_name }} en {{ $connection->account_name }}</summary>
                        <p class="mt-2">Se cortará el acceso de esta conexión. Puedes volver a autorizar desde el cliente cuando lo necesites.</p>
                        <form method="post" action="{{ route('mcp.connections.revoke', $connection->id) }}" class="mt-3">
                            @csrf
                            <button class="btn-primary">Confirmar revocación</button>
                        </form>
                    </details>
                @endif
            </article>
        @empty
            <p class="card">No tienes conexiones MCP. Inicia la autorización desde tu cliente para conectar una cuenta.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $connections->links() }}</div>
    <section class="card mt-6" aria-labelledby="mcp-audit-title">
        <h2 id="mcp-audit-title" class="text-xl font-semibold">Tu actividad MCP reciente</h2>
        <p class="mt-2 text-slate-600">Últimos 20 sucesos. La correlación permite localizar una operación sin guardar su contenido.</p>
        <ul class="mt-3 space-y-3">
            @forelse($events as $event)
                <li class="break-words">{{ $event->operation }} · {{ $event->outcome }} · {{ $event->created_at }} UTC<br>Cuenta: {{ $event->account_id ?? 'Sin cuenta elegida' }} · Cliente: {{ $event->client_id }}<br>Correlación: <code class="break-all">{{ $event->correlation_id }}</code></li>
            @empty
                <li>Todavía no hay actividad MCP.</li>
            @endforelse
        </ul>
    </section>
</x-app-layout>
