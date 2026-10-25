<x-auth-layout title="Autorizar conexión">
    <h1 class="page-title">Autorizar conexión</h1>
    @if($feedback ?? false)<p role="alert" class="mt-4 text-rose-700">No se pudo autorizar esta solicitud con la cuenta y los permisos elegidos. Revisa tus opciones actuales o deniega la conexión. Este intento no ha concedido acceso.</p>@endif
    <p class="mt-3 text-slate-600"><strong>{{ $client->name }}</strong> solicita acceso a una de tus cuentas.</p>
    <p class="mt-2 text-sm text-slate-500">Volverás a {{ parse_url($pending->redirect_uri, PHP_URL_HOST) }} después de decidir.</p>
    <x-feedback/>
    @forelse($accounts as $account)
        <form method="post" action="{{ route('mcp.oauth.decision') }}" aria-label="Autorizar {{ $account['name'] }}" class="mt-6 space-y-4 border rounded-xl p-5">
            @csrf
            <input type="hidden" name="request_id" value="{{ $pending->id }}">
            <input type="hidden" name="account_id" value="{{ $account['id'] }}">
            <input type="hidden" name="scopes" value="[]">
            <h2 class="font-semibold text-lg">{{ $account['name'] }}</h2>
            <p class="text-sm text-slate-500">El acceso quedará limitado a esta cuenta y a las capacidades que elijas.</p>
            <fieldset class="space-y-3">
            <legend class="font-semibold mb-2">Permisos para {{ $account['name'] }}</legend>
            @foreach($pending->scopes as $scope)
                <label class="flex gap-2 items-start"><input type="checkbox" name="scopes[]" value="{{ $scope }}" @checked(in_array($scope, $account['scopes'], true)) @disabled(!in_array($scope, $account['scopes'], true))> <span>{{ $permissions[$scope] }}</span></label>
            @endforeach
            </fieldset>
            @if(array_diff($pending->scopes, $account['scopes']) !== [])<p class="text-sm">Los permisos desactivados no están disponibles en tu membresía actual.</p>@endif
            @if($pending->scopes === [])<p class="text-sm">Sólo información básica de la conexión. No permite leer datos ni enviar emails.</p>@endif
            <button class="btn-primary" name="decision" value="allow">Autorizar {{ $account['name'] }}</button>
        </form>
    @empty
        <p class="mt-6" role="status">No tienes una cuenta disponible para esta conexión.</p>
    @endforelse
    <form method="post" action="{{ route('mcp.oauth.decision') }}" class="mt-6">
        @csrf
        <input type="hidden" name="request_id" value="{{ $pending->id }}">
        <button class="btn-secondary" name="decision" value="deny">Denegar conexión</button>
    </form>
    <p class="mt-6 text-sm text-slate-500">Autorizar al cliente no sustituye el consentimiento de los destinatarios ni la revisión de cada envío. Podrás revocar la conexión desde tu perfil.</p>
</x-auth-layout>
