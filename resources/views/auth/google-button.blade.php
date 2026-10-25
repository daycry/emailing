@if(\App\Services\Auth\GoogleLoginFlow::configured())
    <a class="btn-secondary w-full mt-6" href="{{ route('google.redirect', ['intent' => $googleIntent ?? 'login']) }}" onclick="this.setAttribute('aria-busy', 'true'); this.textContent = 'Conectando con Google…'">{{ ($googleIntent ?? 'login') === 'register' ? 'Crear cuenta con Google' : 'Continuar con Google' }}</a>
@else
    <p class="text-sm text-slate-500 mt-6" role="status">El acceso con Google no está configurado. Puedes seguir usando tu contraseña local.</p>
@endif
@if(($googleIntent ?? 'login') === 'register')
    <p class="text-sm text-slate-500 mt-4">Si Google usa un correo externo a Gmail o Workspace, crea tu cuenta con contraseña y después vincúlala desde Identidad y acceso.</p>
@endif
