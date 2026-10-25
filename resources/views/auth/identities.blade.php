<x-app-layout title="Identidad y acceso">
    <h1 class="page-title">Identidad y acceso</h1>
    <p class="mt-3 mb-6 text-slate-500">Gestiona el acceso a tu usuario. Tus cuentas y permisos se mantienen en Emailing.</p>
    <x-feedback/>
    @if(!\App\Services\Auth\GoogleLoginFlow::configured())
        <p role="status">El acceso con Google no está configurado. Puedes seguir usando tu contraseña local.</p>
    @else
        <form method="post" action="{{ route('identities.link') }}" onsubmit="const button = this.querySelector('button'); button.disabled = true; button.textContent = 'Abriendo Google…'; this.setAttribute('aria-busy', 'true')">@csrf<button class="btn-primary">Vincular Google</button></form>
    @endif
    @forelse($identities as $identity)
        <div class="mt-6 rounded-xl border p-5">
            <h2 class="font-semibold">Google vinculado</h2>
            @if(auth()->user()->local_password_enabled)
                <form method="post" action="{{ route('identities.unlink', $identity->id) }}" class="mt-4">@csrf @method('DELETE')<button class="btn-secondary">Desvincular Google</button></form>
            @else
                <p class="mt-3">Establece tu contraseña mediante <a class="text-indigo-600" href="{{ route('password.request') }}">Recuperar acceso</a> antes de desvincular Google.</p>
            @endif
        </div>
    @empty
        <p class="mt-6" role="status">Todavía no has vinculado Google.</p>
    @endforelse
</x-app-layout>
