<div x-data x-on:activity-detail-opened.window="$nextTick(() => $refs.activityDetail?.focus())">
    <p class="eyebrow">RESULTADOS OBSERVADOS</p>
    <h1 class="text-3xl font-bold mt-2 mb-3">Actividad de la cuenta</h1>
    <p class="text-slate-600 mb-6 max-w-3xl">Aceptado por SMTP indica la aceptación del relay de laboratorio. No acredita entrega, apertura ni clic. Un archivo log no conecta SMTP. Los resultados ambiguos no se reenvían desde este panel.</p>
    <x-feedback/>
    <section class="card mb-6" aria-labelledby="activity-filters">
        <h2 id="activity-filters" class="text-lg font-semibold mb-4">Filtrar actividad</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <label class="field">Desde (UTC)<input type="date" class="input mt-1 w-full" wire:model.live="from"></label>
            <label class="field">Hasta, incluido (UTC)<input type="date" class="input mt-1 w-full" wire:model.live="to"></label>
            <label class="field">Población<select class="input mt-1 w-full" wire:model.live="population"><option value="">Todas, con contadores separados</option>@foreach($populations as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label class="field">Estado<select class="input mt-1 w-full" wire:model.live="status"><option value="">Todos los estados</option>@foreach($states as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
            <label class="field">Destinatario o asunto<input type="search" maxlength="200" class="input mt-1 w-full" wire:model.live.debounce.300ms="search"></label>
            <label class="field">ID de campaña (opcional)<input type="number" min="1" class="input mt-1 w-full" wire:model.live="campaign"></label>
        </div>
        <p class="text-xs text-slate-500 mt-4">La ventana usa la fecha de creación de la entrega o del registro independiente. Inicio incluido, fin al comienzo del día siguiente excluido; zona UTC. Filtros y estado se aplican también a los contadores y al CSV.</p>
        @if($validFilters)<a class="btn-secondary mt-4 inline-block" href="{{ route('activity.export', $this->filters()) }}">Descargar CSV de esta selección</a>@endif
    </section>
    @if($validFilters)
    <section aria-label="Métricas por población" class="grid md:grid-cols-3 gap-4 mb-6">
        @foreach($metrics as $populationMetric)
            <article class="card">
                <h2 class="font-semibold">{{ $populationMetric['label'] }}</h2>
                <p class="text-3xl font-bold mt-3">{{ $populationMetric['total'] }}</p>
                <p class="text-sm text-slate-600 mt-2">Aceptadas SMTP: {{ $populationMetric['smtp_accepted'] }} / {{ $populationMetric['total'] }} ({{ $populationMetric['acceptance_percentage'] === null ? 'sin muestra' : $populationMetric['acceptance_percentage'].' %' }})</p>
                <ul class="mt-3 text-sm space-y-1">@foreach($populationMetric['states'] as $state => $count)<li>{{ $states[$state] ?? 'Estado desconocido' }}: {{ $count }}</li>@endforeach</ul>
                @if($populationMetric['quota_wait'] || $populationMetric['paused'])<p class="text-sm text-amber-800 mt-3">Pendientes por cuota: {{ $populationMetric['quota_wait'] }} · Por pausa: {{ $populationMetric['paused'] }}</p>@endif
                @if(($populationMetric['states']['failed'] ?? 0) + ($populationMetric['states']['ambiguous'] ?? 0) + ($populationMetric['states']['blocked'] ?? 0) > 0)<p class="text-sm text-amber-800 mt-3" role="status">Incidencias locales observadas en esta población. Consulta los estados y el detalle; no son una medida de reputación externa.</p>@endif
            </article>
        @endforeach
    </section>
    <section class="card mb-6" aria-labelledby="feedback-limits">
        <h2 id="feedback-limits" class="text-lg font-semibold">Feedback y alertas</h2>
        <p class="text-sm text-slate-600 mt-2">Fuente de los contadores: SQL local, estados actuales de la cohorte filtrada. Cada entrega lógica cuenta una vez, aunque tenga varios intentos. Pruebas explícitas e históricos sin procedencia son poblaciones independientes; no constituyen emails únicos de campaña. Un histórico desconocido podría ser un intento anterior sin vínculo reconstruible.</p>
        <p class="text-sm text-slate-600 mt-2">Los estados Fallido, Bloqueado y Aceptación ambigua muestran incidencias locales comprobadas. Una espera por cuota o pausa sigue pendiente; no es un fallo final. Las incidencias de intentos anteriores se consultan en el detalle.</p>
        <p class="text-sm font-semibold mt-3">Entrega final, aperturas, clics, rebotes, quejas y listas de bloqueo: desconocidos.</p>
        <p class="text-sm text-slate-600 mt-2">No hay proveedor de feedback integrado. La ausencia de datos no equivale a 0 % de incidencias ni demuestra reputación saludable. Estos porcentajes describen estados locales, no tasas de entrega ni un SLA.</p>
    </section>
    <section class="card" aria-labelledby="activity-list">
        <h2 id="activity-list" class="text-lg font-semibold mb-4">Entregas y registros independientes</h2>
        <ul class="divide-y divide-slate-100">
            @forelse($records as $record)
                <li class="py-4 space-y-2" wire:key="activity-{{ $record->record_key }}">
                    <div class="flex flex-wrap justify-between gap-3"><p class="font-semibold break-all">{{ $record->recipient }}</p><span class="text-sm font-semibold">{{ $states[$record->status] ?? 'Estado desconocido' }}</span></div>
                    <p class="text-sm break-words">{{ $record->subject }}</p>
                    <p class="text-xs text-slate-500">{{ $record->occurred_at }} UTC · {{ $populations[$record->population] }} · {{ $record->population === 'api' ? 'Llave: '.$record->api_key_name.' · '.$record->api_key_id : ($record->actor_name ?? 'Actor desconocido') }}</p>
                    @if($record->population === 'mcp')<p class="text-xs text-slate-500 break-all">Concesión MCP: {{ $record->mcp_grant_id }} · Cliente: {{ $record->mcp_client_name }} · Petición: {{ $record->api_request_id }}</p>@endif
                    @if($record->diagnostic)<p class="text-sm text-amber-800">{{ \App\Services\ActivityService::diagnostic($record->diagnostic) }}</p>@endif
                    <button type="button" class="btn-secondary" wire:click="open(@js($record->record_key))">Ver detalle e intentos</button>
                </li>
            @empty
                <li class="py-6 text-slate-500">No hay actividad en esta selección. Sin muestra para calcular porcentajes.</li>
            @endforelse
        </ul>
        <x-campaign-pagination :paginator="$records" label="Páginas de actividad"/>
    </section>
    @if($detail)
        <section class="card mt-6" aria-labelledby="activity-detail">
            <div class="flex flex-wrap justify-between gap-3"><h2 id="activity-detail" x-ref="activityDetail" tabindex="-1" class="text-lg font-semibold">Detalle de actividad</h2><button type="button" class="btn-secondary" wire:click="close">Cerrar detalle</button></div>
            <dl class="grid sm:grid-cols-2 gap-3 mt-4 text-sm">
                <div><dt class="text-slate-500">Referencia</dt><dd class="break-all">{{ $detail->record_key }}</dd></div>
                <div><dt class="text-slate-500">Estado actual</dt><dd>{{ $states[$detail->status] ?? 'Estado desconocido' }}</dd></div>
                <div><dt class="text-slate-500">Origen</dt><dd>{{ $populations[$detail->population] }}</dd></div>
                <div><dt class="text-slate-500">Autor de la autorización / actor registrado</dt><dd>{{ $detail->population === 'api' ? 'Credencial de cuenta' : ($detail->actor_name ?? 'Desconocido') }}</dd></div>
                @if($detail->population === 'api')<div><dt class="text-slate-500">Llave API</dt><dd>{{ $detail->api_key_name }} · {{ $detail->api_key_id }}</dd></div><div><dt class="text-slate-500">Petición API</dt><dd>{{ $detail->api_request_id }}</dd></div>@endif
                @if($detail->population === 'mcp')<div><dt class="text-slate-500">Concesión MCP</dt><dd class="break-all">{{ $detail->mcp_grant_id }}</dd></div><div><dt class="text-slate-500">Cliente MCP</dt><dd class="break-all">{{ $detail->mcp_client_name }} · {{ $detail->mcp_client_id }}</dd></div><div><dt class="text-slate-500">Petición transaccional</dt><dd class="break-all">{{ $detail->api_request_id }}</dd></div>@endif
                <div><dt class="text-slate-500">Campaña</dt><dd>{{ $detail->campaign_name ?? 'Sin procedencia de campaña registrada' }}</dd></div>
                <div><dt class="text-slate-500">Remitente</dt><dd class="break-all">{{ $detail->sender_email }}</dd></div>
                <div><dt class="text-slate-500">Comienzos durables registrados</dt><dd>{{ $detail->attempts ?? 'Desconocidos' }}</dd></div>
                @if(!in_array($detail->population, ['campaign', 'api', 'mcp'], true))<div><dt class="text-slate-500">Registros independientes conservados</dt><dd>1</dd></div>@endif
                <div><dt class="text-slate-500">Próxima disponibilidad UTC</dt><dd>{{ $detail->available_at ?? 'No registrada' }}</dd></div>
            </dl>
            <p class="text-xs text-slate-500 mt-3">Un comienzo durable es el inicio registrado en SQL antes de realizar IO. No acredita una conexión SMTP. En históricos sin esa marca la cantidad es desconocida, no cero.</p>
            @if($detail->diagnostic)<p class="text-sm mt-4">{{ \App\Services\ActivityService::diagnostic($detail->diagnostic) }}</p>@endif
            <h3 class="font-semibold mt-6">Registros de intento conservados</h3>
            <p class="text-xs text-slate-500 mt-2">Generación histórica desconocida significa que el dato no pudo reconstruirse. La lista conserva todos los intentos vinculados, no solo el último.</p>
            <ul class="divide-y divide-slate-100 mt-3">
                @forelse($attempts as $attempt)
                    <li class="py-3 text-sm" wire:key="attempt-{{ $attempt->id }}">
                        <p>Registro {{ $attempt->id }} · {{ $attempt->created_at }} UTC · Generación {{ $attempt->delivery_generation ?? 'desconocida' }}</p>
                        <p>{{ $states[match($attempt->status) { 'smtp_rejected', 'local_failed' => 'failed', 'smtp_unconfirmed' => 'ambiguous', default => $attempt->status }] ?? 'Estado desconocido' }} · {{ $attempt->mode }} · {{ $attempt->api_key_id ? 'Llave: '.$attempt->api_key_name.' · '.$attempt->api_key_id.' · petición '.$attempt->api_request_id : ($attempt->actor_name ?? 'Actor desconocido') }}</p>
                        @if($attempt->mcp_grant_id)<p class="break-all">Concesión MCP: {{ $attempt->mcp_grant_id }} · Cliente: {{ $attempt->mcp_client_name }} · Petición: {{ $attempt->api_request_id }}</p>@endif
                        @if($attempt->diagnostic)<p>{{ \App\Services\ActivityService::diagnostic($attempt->diagnostic) }}</p>@endif
                        @if($attempt->has_artifact)<a class="text-indigo-700 underline" href="{{ route('mail-artifacts.download', $attempt->id) }}">Descargar archivo privado {{ $attempt->id }}</a>@endif
                    </li>
                @empty
                    <li class="py-4 text-slate-500">Sin registros de intento vinculados en esta página.</li>
                @endforelse
            </ul>
            <x-campaign-pagination :paginator="$attempts" label="Páginas de intentos"/>
        </section>
    @endif
    @else
        <section class="card" aria-label="Filtros pendientes de corrección"><p>Corrige los filtros para consultar la actividad. No se muestran contadores ni se exporta una selección inválida.</p></section>
    @endif
</div>
