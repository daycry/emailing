<div>
    <div class="mb-8"><p class="eyebrow">COMUNICACIONES DE LA CUENTA</p><h1 class="page-title mt-2">Campañas</h1><p class="mt-3 text-slate-500">Prepara un borrador, revisa la versión guardada y autoriza su fecha efectiva en UTC.</p></div>
    <x-feedback/>
    
@if(session('success'))<div role="status" class="rounded-lg bg-emerald-50 text-emerald-800 p-4 mb-6">{{ session('success') }}</div>
@endif
    <p wire:loading role="status" class="text-sm text-indigo-700 mb-4">Actualizando campaña…</p>
    <section class="card mb-6"><div class="flex flex-wrap justify-between items-center gap-4 mb-5"><h2 class="text-lg font-semibold">Campañas compartidas</h2>
@if($canCompose)<button class="btn-primary" wire:click="create" wire:loading.attr="disabled">Nueva campaña</button>
@endif</div>
        <ul class="divide-y divide-slate-100">
@forelse($campaigns as $item)<li class="py-4 flex flex-wrap justify-between gap-4 break-words" wire:key="campaign-{{ $item->id }}"><div><p class="font-semibold">{{ $item->name }}</p><p class="text-sm text-slate-500 mt-2">{{ \App\Models\Campaign::LABELS[$item->status] ?? $item->status }} · versión {{ $item->version }}
@if($item->effective_at) · {{ $item->effective_at->utc()->format('Y-m-d H:i:s') }} UTC
@endif</p></div><button class="text-indigo-700 font-medium focus-visible:outline-2" wire:click="open({{ $item->id }})">Abrir campaña</button></li>
@empty<li class="py-6 text-sm text-slate-500">No hay campañas en esta cuenta. 
@if($canCompose)Crea un borrador para empezar.
@endif</li>
@endforelse</ul><x-campaign-pagination :paginator="$campaigns" label="Paginación de campañas"/>
    </section>
    
@if($editorOpen)
        
@if($stale)<div role="alert" class="card mb-6 text-amber-800">Otra persona cambió esta campaña. <button class="underline focus-visible:outline-2" wire:click="open({{ $editing }})">Volver a abrir la versión actual</button></div>
@endif
        
@if(!$campaign || in_array($campaign->status, ['draft', 'reviewed'], true))
            
@if($canCompose)
            <section class="card mb-6"><h2 class="text-lg font-semibold mb-4">Composición del borrador</h2><p class="text-sm text-slate-500 mb-5">Guardar invalida la revisión anterior. La revisión usa los recursos actuales de la cuenta y la versión guardada. Una campaña autorizada conserva su contenido; para cambiarlo, crea otra campaña.</p>
            <form wire:submit="save" class="space-y-5"><label class="field">Nombre de campaña<input wire:model="name" maxlength="150" required></label>
                <fieldset><legend class="font-medium text-sm mb-3">Plantilla (seleccionada: {{ $templateId ?? 'ninguna' }})</legend>
@forelse($templates as $template)<label class="flex items-center gap-3 py-2 text-sm"><input type="radio" wire:model="templateId" value="{{ $template->id }}" name="campaign-template"><span class="min-w-0 break-all">{{ $template->name }}</span></label>
@empty<p class="text-sm text-slate-500">No hay plantillas. Crea una en Plantillas.</p>
@endforelse<x-campaign-pagination :paginator="$templates" label="Paginación de plantillas"/></fieldset>
                
@if($canSend)<fieldset><legend class="font-medium text-sm mb-3">Remitente autorizado para laboratorio (seleccionado: {{ $senderId ?? 'ninguno' }})</legend>
@forelse($senders as $sender)<label class="flex items-center gap-3 py-2 text-sm"><input type="radio" wire:model="senderId" value="{{ $sender->id }}" name="campaign-sender"><span class="min-w-0 break-all">{{ $sender->name }} · {{ $sender->email }}</span></label>
@empty<p class="text-sm text-slate-500">Un administrador debe añadir un remitente autorizado.</p>
@endforelse<x-campaign-pagination :paginator="$senders" label="Paginación de remitentes"/></fieldset>
@else<p class="text-sm text-slate-500">Una persona con permiso de envío debe fijar el remitente y autorizar la revisión.</p>
@endif
                <div class="rounded-lg border border-slate-200 p-4"><p class="text-sm text-slate-500 mb-3">Puedes vaciar todas las listas y contactos seleccionados, incluidos los que se hayan eliminado, y elegir una audiencia nueva. Solo cambia este formulario: guarda y revisa de nuevo para actualizar el borrador.</p><button type="button" class="btn-secondary focus-visible:outline-2" wire:click="clearAudience" wire:confirm="¿Quitar todas las listas y contactos seleccionados del formulario? El borrador guardado se conserva hasta que guardes los cambios." wire:loading.attr="disabled"
@disabled($stale)>Vaciar selección de destinatarios</button></div>
                <fieldset><legend class="font-medium text-sm mb-3">Listas ({{ count($listIds) }} seleccionadas)</legend>
@forelse($lists as $list)<label class="flex items-center gap-3 py-2 text-sm"><input type="checkbox" wire:model="listIds" value="{{ $list->id }}"><span class="min-w-0 break-all">{{ $list->name }}</span></label>
@empty<p class="text-sm text-slate-500">No hay listas. También puedes elegir contactos individuales.</p>
@endforelse<x-campaign-pagination :paginator="$lists" label="Paginación de listas"/></fieldset>
                <fieldset><legend class="font-medium text-sm mb-3">Contactos individuales ({{ count($contactIds) }} seleccionados)</legend>
@forelse($contacts as $contact)<label class="flex items-start gap-3 py-2 text-sm"><input type="checkbox" class="mt-1" wire:model="contactIds" value="{{ $contact->id }}"><span class="break-all">{{ $contact->name }} · {{ $contact->email }}<span class="block text-xs text-slate-500">Consentimiento: {{ ['granted' => 'concedido', 'revoked' => 'revocado', 'unknown' => 'desconocido'][$contact->consent_status] ?? 'desconocido' }} · {{ $contact->consent_source ?: 'sin origen' }} · {{ $contact->consented_at?->utc()->format('Y-m-d H:i:s') ?? 'sin fecha' }} UTC</span></span></label>
@empty<p class="text-sm text-slate-500">No hay contactos. Añádelos en Contactos y listas.</p>
@endforelse<x-campaign-pagination :paginator="$contacts" label="Paginación de contactos"/></fieldset>
                <p class="text-sm text-slate-500">La audiencia se deduplica entre listas y contactos. Solo se revisan destinatarios con consentimiento trazable vigente y sin supresión. Máximo 10.000 contactos por campaña.</p><button class="btn-primary" wire:loading.attr="disabled" 
@disabled($stale)>Guardar borrador</button>
            </form>
            
@if($campaign)<button class="btn-secondary mt-5" wire:click="review" wire:loading.attr="disabled" 
@disabled($stale)>Revisar versión guardada</button>
@endif</section>
            
@else<p class="card mb-6 text-sm text-slate-500">Para componer necesitas gestionar campañas y consultar contactos y plantillas.</p>
@endif
        
@endif
        
@if($campaign)
        <section class="card mb-6"><h2 class="text-lg font-semibold mb-4">Resumen de {{ $campaign->name }}</h2><p class="mb-3"><span class="badge">{{ \App\Models\Campaign::LABELS[$campaign->status] ?? $campaign->status }}</span> <span class="text-sm text-slate-500">Versión {{ $campaign->version }}</span></p>
            
@if($campaign->effective_at)<p class="text-sm mb-3">Fecha efectiva: <strong>{{ $campaign->effective_at->utc()->format('Y-m-d H:i:s') }} UTC</strong></p>
@endif
            <p class="text-sm text-slate-500 mb-4">Este estado registra preparación y autorización. No acredita encolado, aceptación SMTP ni entrega.</p>
            
@if($revision)
                <p class="text-sm mb-3">Revisión {{ $revision->version }} · {{ $revision->created_at->utc()->format('Y-m-d H:i:s') }} UTC</p>
                
@if($contactsRead)<p class="text-sm mb-4">{{ $revision->recipient_count }} destinatarios únicos elegibles; {{ $revision->excluded_count }} excluidos por consentimiento incompleto, revocado o supresión. Los datos siguientes corresponden a esta revisión.</p>
@endif
                
@if($canSend)<p class="text-sm mb-4 break-all">Remitente revisado: {{ $revision->sender_snapshot['name'] }} · {{ $revision->sender_snapshot['email'] }}</p>
@endif
                
@if($sample)<h3 class="font-semibold mb-3">Contenido revisado para el primer destinatario</h3><p class="text-sm mb-3">Asunto: {{ $sample['subject'] }}</p><iframe title="Preview aislada de la campaña" sandbox="" referrerpolicy="no-referrer" srcdoc="{{ $sample['document'] }}" class="w-full h-80 rounded-lg border border-slate-200"></iframe><details class="mt-3"><summary class="cursor-pointer text-sm">Versión de texto</summary><pre class="whitespace-pre-wrap break-words text-sm mt-3">{{ $sample['text'] }}</pre></details>
@elseif($revision && $templatesRead)<details class="mt-3"><summary class="cursor-pointer text-sm">Contenido revisado sin personalizar</summary><pre class="whitespace-pre-wrap break-words text-sm mt-3">{{ $revision->subject }}
{{ $revision->html }}
{{ $revision->text }}</pre></details>
@endif
                
@if($recipients)<h3 class="font-semibold mt-6 mb-3">Audiencia y consentimiento revisados</h3><ul class="divide-y divide-slate-100" data-testid="campaign-reviewed-audience">
@foreach($recipients as $recipient)<li class="py-3 text-sm break-all" wire:key="recipient-{{ $recipient->id }}">{{ $recipient->name }} · {{ $recipient->email }}<p class="text-xs text-slate-500 mt-1">Origen: {{ $recipient->consent_source }} · {{ $recipient->consented_at->utc()->format('Y-m-d H:i:s') }} UTC</p></li>
@endforeach</ul><x-campaign-pagination :paginator="$recipients" label="Paginación de audiencia revisada"/>
@endif
            
@else<p class="text-sm text-slate-500">Este borrador todavía no tiene una revisión. Guarda los cambios y revisa antes de autorizar.</p>
@endif
            
@if($compositionChanged)<p role="status" class="text-sm text-amber-800 mt-5">Hay cambios sin guardar. Guarda y revisa de nuevo, o vuelve a abrir la versión guardada.</p>
@endif
            
@if($campaign->status === 'reviewed' && $canCompose && $canSend && !$stale && !$compositionChanged)
            <form wire:submit="authorizeCampaign" class="border-t border-slate-200 mt-6 pt-6 space-y-4"><h3 class="font-semibold">Autorización de esta revisión</h3><p class="text-sm text-slate-500">Al autorizar se fija la audiencia revisada. Si cambió un recurso, debes generar otro resumen. Antes de cada entrega se volverán a comprobar consentimiento, baja, identidad, remitente y pausa.</p><label class="field">Momento efectivo<select wire:model.live="timing"><option value="now">Autorizar para ahora</option><option value="future">Programar para una fecha futura UTC</option></select></label>
@if($timing === 'future')<label class="field">Fecha y hora en UTC<input type="datetime-local" wire:model="effectiveUtc" required></label><p class="text-xs text-slate-500">Introduce UTC directamente; no se convierte tu zona horaria ni el horario de verano.</p>
@endif<label class="flex items-start gap-3 text-sm"><input type="checkbox" wire:model="confirmed" class="mt-1"><span>He revisado el contenido, remitente y audiencia de la revisión {{ $revision->version }} y autorizo su procesamiento en la fecha efectiva elegida.</span></label><button class="btn-primary" wire:loading.attr="disabled">Confirmar autorización</button></form>
            
@endif
            
@if($canWrite && in_array($campaign->status, ['authorized', 'scheduled'], true))<button class="btn-secondary mt-5" wire:click="pause" wire:loading.attr="disabled" 
@disabled($stale)>Pausar campaña</button>
@endif
            
@if($canWrite && $canSend && $campaign->status === 'paused')<p class="text-sm text-slate-500 mt-5">Al reanudar se conservan contenido, audiencia y fecha efectiva originales. No se incluyen contactos añadidos después.</p><button class="btn-primary mt-3" wire:click="resume" wire:confirm="¿Reanudar esta campaña con su autorización y fecha originales?" wire:loading.attr="disabled" 
@disabled($stale)>Reanudar campaña</button>
@endif
        </section>
        
@endif
    
@endif
</div>
