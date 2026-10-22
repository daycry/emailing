<div>
    <div class="mb-8"><p class="eyebrow">AUDIENCIA COMPARTIDA</p><h1 class="page-title mt-2">Contactos y listas</h1><p class="mt-3 text-slate-500">Los contactos de {{ $account->name }} están disponibles para su equipo autorizado.</p></div>
    <x-feedback/>
    @if(session('success'))<div role="status" class="rounded-lg bg-emerald-50 text-emerald-800 p-4 mb-6">{{ session('success') }}</div>@endif
    @if(!$canWrite)<p class="card mb-6 text-slate-600">Tienes acceso de consulta a los contactos y listas.</p>@endif
    @if($canWrite)
    <div class="flex flex-wrap gap-3 mb-6"><button class="btn-primary" wire:click="newContact">Nuevo contacto</button><button class="btn-secondary" wire:click="newList">Nueva lista</button></div>
    @if($contactForm)
    <section class="card mb-6"><h2 class="text-lg font-semibold mb-4">{{ $editingContact ? 'Editar contacto' : 'Nuevo contacto' }}</h2><form wire:submit="saveContact" class="space-y-4 max-w-xl">
        <label class="field">Email<input type="email" wire:model="email" required maxlength="254" @readonly($editingContact)></label>
        @if($editingContact)<p class="text-xs text-slate-500">El email identifica al contacto y no se modifica. Para otra dirección, crea un contacto nuevo.</p>@endif
        <label class="field">Nombre<input wire:model="name" maxlength="150"></label>
        <label class="field">Consentimiento<select wire:model.live="consentStatus"><option value="unknown">Desconocido</option><option value="granted">Otorgado</option><option value="revoked">Revocado</option></select></label>
        @if($consentStatus === 'granted')<label class="field">Fuente del consentimiento<input wire:model="consentSource" maxlength="255" required placeholder="Formulario o referencia de evidencia"></label><label class="field">Fecha del consentimiento (UTC)<input type="datetime-local" wire:model="consentedAt" required></label>@endif
        <p class="text-xs text-slate-500">Añadir un email no implica consentimiento. Otorgarlo requiere una fuente y fecha explícitas. Un consentimiento revocado no se reactiva desde este formulario.</p>
        <div class="flex gap-3"><button class="btn-primary" wire:loading.attr="disabled">Guardar contacto</button><button type="button" class="btn-secondary" wire:click="$set('contactForm', false)">Cancelar</button></div>
    </form></section>
    @endif
    @if($listForm)
    <section class="card mb-6"><h2 class="text-lg font-semibold mb-4">{{ $editingList ? 'Editar lista' : 'Nueva lista' }}</h2><form wire:submit="saveList" class="space-y-4 max-w-xl"><label class="field">Nombre de la lista<input wire:model="listName" maxlength="150" required></label><label class="field">Descripción<textarea wire:model="listDescription" maxlength="500"></textarea></label><div class="flex gap-3"><button class="btn-primary" wire:loading.attr="disabled">Guardar lista</button><button type="button" class="btn-secondary" wire:click="$set('listForm', false)">Cancelar</button></div></form></section>
    @endif
    @endif
    <section class="card mb-6"><div class="flex flex-wrap justify-between gap-3 mb-5"><h2 class="text-lg font-semibold">Listas</h2><button class="text-indigo-600 text-sm" wire:click="selectList(null)">Ver todos los contactos</button></div>
        <ul class="divide-y divide-slate-100">@forelse($lists as $list)<li class="py-4 flex flex-wrap justify-between gap-3" wire:key="list-{{ $list->id }}"><div><button class="font-semibold text-indigo-600 text-left" wire:click="selectList({{ $list->id }})">{{ $list->name }}</button><span class="badge ml-2">{{ $list->contacts_count }} contactos</span><p class="text-sm text-slate-500 mt-1">{{ $list->description }}</p></div>@if($canWrite)<div class="text-sm flex gap-4"><button class="text-indigo-600" wire:click="chooseAssociationList({{ $list->id }})">Añadir contactos</button><button class="text-indigo-600" wire:click="editList({{ $list->id }})">Editar lista</button><button class="text-rose-600" wire:click="deleteList({{ $list->id }})" wire:confirm="¿Eliminar esta lista? Sus contactos se conservan.">Eliminar lista</button></div>@endif</li>@empty<li class="py-4 text-sm text-slate-500">Todavía no hay listas. Crea una para organizar tu audiencia.</li>@endforelse</ul>
        <div class="mt-4">{{ $lists->links() }}</div>
    </section>
    @if($canWrite && $associationTarget)
    <div class="card mb-6 text-sm"><p>Añadiendo contactos a <strong>{{ $associationTarget->name }}</strong>. Usa «Añadir a lista» en los contactos que quieras incorporar.</p><button class="text-indigo-600 mt-3" wire:click="$set('associationList', null)">Terminar selección</button></div>
    @endif
    <section class="card overflow-x-auto"><h2 class="text-lg font-semibold mb-4">{{ $selectedList ? 'Contactos de '.$selectedList->name : 'Todos los contactos' }}</h2><label class="field mb-5 max-w-md">Buscar por nombre o email<input type="search" wire:model.live.debounce.300ms="search" maxlength="150" placeholder="Buscar contactos"></label>
        <table class="w-full text-sm text-left"><thead class="text-slate-500 border-b border-slate-100"><tr><th class="pb-3">Contacto</th><th class="pb-3">Consentimiento</th><th class="pb-3"><span class="sr-only">Acciones</span></th></tr></thead><tbody class="divide-y divide-slate-100">
        @forelse($contacts as $contact)<tr wire:key="contact-{{ $contact->id }}"><td class="py-4 pr-4"><p class="font-semibold">{{ $contact->name ?: 'Sin nombre' }}</p><p class="text-slate-500 mt-1">{{ $contact->email }}</p></td><td class="py-4 pr-4"><span class="badge">{{ ['unknown'=>'Desconocido','granted'=>'Otorgado','revoked'=>'Revocado'][$contact->consent_status] }}</span>@if($contact->consented_at)<p class="text-xs text-slate-500 mt-2">{{ $contact->consented_at->format('d/m/Y H:i') }} UTC</p><p class="text-xs text-slate-500">{{ $contact->consent_source }}</p>@endif</td><td class="py-4 text-right whitespace-nowrap">@if($canWrite)@if($associationTarget)<button class="text-indigo-600 mr-3" wire:click="associate({{ $contact->id }})">Añadir a lista</button>@endif<button class="text-indigo-600 mr-3" wire:click="editContact({{ $contact->id }})">Editar</button>@if($selectedList)<button class="text-indigo-600 mr-3" wire:click="detach({{ $selectedList->id }}, {{ $contact->id }})">Retirar de lista</button>@endif<button class="text-rose-600" wire:click="deleteContact({{ $contact->id }})" wire:confirm="¿Eliminar este contacto de todas las listas? Su registro se conservará sin reactivarlo.">Eliminar</button>@endif</td></tr>@empty<tr><td colspan="3" class="py-8 text-slate-500">No hay contactos en esta selección.</td></tr>@endforelse
        </tbody></table><div class="mt-5">{{ $contacts->links() }}</div>
    </section>
</div>
