<x-auth-layout title="Confirmar cuenta">
    <h1 class="page-title">Confirma tu nueva cuenta</h1>
    <p class="mt-3 mb-6 text-slate-500">Google ha verificado tu identidad. Crearás tu propio espacio al confirmar.</p>
    <x-feedback/>
    <form method="post" action="{{ route('google.register') }}" class="space-y-5" onsubmit="const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Creando tu cuenta…'; this.setAttribute('aria-busy', 'true')">
        @csrf
        <label class="field">Tu nombre<input name="name" value="{{ old('name', $pending['identity']['name']) }}" required maxlength="100" autocomplete="name"></label>
        <label class="field">Nombre de la cuenta<input name="account_name" value="{{ old('account_name') }}" required maxlength="100"></label>
        <p class="text-sm">Dirección verificada: {{ $pending['identity']['email'] }}</p>
        <label class="field">Contraseña local (opcional)<input name="password" type="password" minlength="12" autocomplete="new-password"></label>
        <label class="field">Repite la contraseña<input name="password_confirmation" type="password" autocomplete="new-password"></label>
        <p class="text-sm text-slate-500">Si no eliges contraseña, podrás establecerla mediante Recuperar acceso antes de desvincular Google.</p>
        <label class="flex gap-2"><input type="checkbox" name="confirm_registration" value="1" @checked(old('confirm_registration') === '1') required> Quiero crear mi cuenta y vincular Google.</label>
        <button class="btn-primary w-full">Crear mi cuenta</button>
    </form>
    <a href="{{ route('login') }}" class="text-indigo-600 inline-block mt-5">Volver al acceso</a>
</x-auth-layout>
