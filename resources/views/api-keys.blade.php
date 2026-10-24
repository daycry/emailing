<x-app-layout title="Llaves API">
    <p class="eyebrow">INTEGRACIONES DE LA CUENTA</p>
    <h1 class="text-3xl font-bold mt-2">Llaves API</h1>
    <p class="text-slate-600 mt-3">Las llaves pertenecen a esta cuenta y siguen vigentes cuando su creador deja el equipo. Revócalas cuando ya no se utilicen.</p>
    <x-feedback/>
    @if($created)
        <section class="card mt-6" role="status">
            <h2 class="text-xl font-semibold">Guarda el secreto ahora</h2>
            <p class="mt-2">Solo se muestra en esta respuesta. Guárdalo en tu gestor de secretos. Si lo pierdes, revoca la llave y crea otra. Recargar este formulario puede crear otra llave.</p>
            <label for="new-key-secret" class="block font-semibold mt-4">Secreto de {{ $created['key']['name'] }}</label>
            <textarea id="new-key-secret" readonly autocomplete="off" spellcheck="false" class="w-full border rounded p-3 mt-2 font-mono">{{ $created['secret'] }}</textarea>
            <a href="{{ route('api-keys') }}" class="btn-secondary mt-4">Ya lo he guardado: ocultar secreto</a>
        </section>
    @endif
    @if(in_array('api-keys.write', $ceiling, true))
        <section class="card mt-6">
            <h2 class="text-xl font-semibold">Crear llave</h2>
            <form method="post" action="{{ route('api-keys.store') }}" class="space-y-4 mt-4">
                @csrf
                <label class="block">Nombre <input name="name" required maxlength="150" class="input" value="{{ old('name') }}"></label>
                <fieldset><legend class="font-semibold">Permisos</legend>
                    @foreach($ceiling as $scope)
                        <label class="block mt-2"><input type="checkbox" name="scopes[]" value="{{ $scope }}"> {{ \App\Services\Permissions::SCOPES[$scope] }}</label>
                    @endforeach
                </fieldset>
                <label class="block">Caducidad UTC opcional <input name="expires_at" class="input" placeholder="2027-01-01T00:00:00Z" value="{{ old('expires_at') }}"></label>
                <p class="text-sm text-slate-500">Sin fecha, la llave no caduca. Selecciona solo los permisos necesarios; una plantilla almacenada requiere consultar plantillas y enviar emails.</p>
                <button class="btn-primary">Crear llave</button>
            </form>
        </section>
    @endif
    <section class="card mt-6 overflow-x-auto">
        <h2 class="text-xl font-semibold">Llaves de esta cuenta</h2>
        <table class="w-full mt-4 text-sm"><thead><tr><th class="text-left">Nombre y referencia</th><th class="text-left">Permisos</th><th class="text-left">Vigencia UTC</th><th class="text-left">Acciones</th></tr></thead><tbody>
            @forelse($page['data'] as $key)
                <tr class="border-t"><td class="py-4">{{ $key['name'] }}<br><small>{{ $key['id'] }}</small></td><td>{{ implode(', ', $key['scopes']) }}</td><td>{{ $key['revoked_at'] ? 'Revocada: '.$key['revoked_at'] : ($key['expires_at'] ? 'Caduca: '.$key['expires_at'] : 'Sin caducidad') }}<br><small>Último uso: {{ $key['last_used_at'] ?? 'Sin uso registrado' }}</small></td><td>
                    @if(in_array('api-keys.write', $ceiling, true) && !$key['revoked_at'])
                        <form method="post" action="{{ route('api-keys.destroy', $key['id']) }}">@csrf @method('DELETE')<button class="btn-secondary" onclick="return confirm('¿Revocar esta llave de la cuenta? Su siguiente uso será rechazado.')">Revocar {{ $key['name'] }}</button></form>
                    @endif
                </td></tr>
            @empty
                <tr><td colspan="4" class="py-6 text-slate-500">{{ in_array('api-keys.read', $ceiling, true) ? 'Todavía no hay llaves API en esta cuenta.' : 'Tu membresía no permite consultar los metadatos de las llaves.' }}</td></tr>
            @endforelse
        </tbody></table>
        @if($page['next_cursor'])<a class="btn-secondary mt-4" href="{{ route('api-keys', ['cursor' => $page['next_cursor']]) }}">Siguientes llaves</a>@endif
    </section>
    <p class="text-sm text-slate-500 mt-6">El envío se acepta en cola y mantiene bajas y supresiones. El laboratorio solo permite log y Mailpit; aceptación no demuestra entrega al buzón.</p>
</x-app-layout>
