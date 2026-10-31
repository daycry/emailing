<section class="card mb-6" aria-labelledby="provider-{{ $provider }}" wire:key="provider-{{ $provider }}">
    <h2 id="provider-{{ $provider }}" class="text-lg font-semibold">{{ $provider === 'brevo' ? 'Brevo' : 'SendGrid' }}</h2>
    <p class="text-sm text-slate-600 mt-2">SMTP: {{ $profile['ready']['smtp'] ? 'preparado' : 'sin preparar' }} · API: {{ $profile['ready']['api'] ? 'preparada' : 'sin preparar' }} · {{ $profile['has_secrets'] ? 'Credenciales guardadas y cifradas' : 'Sin credenciales' }}.</p>
    @if($profile['id'])
        <p class="text-sm mt-3 break-all">URL del webhook: <code>{{ \App\Support\TrustedUrl::route('mail-provider.webhook', ['provider' => $provider, 'connection' => $profile['id']]) }}</code></p>
    @else
        <p class="text-sm mt-3">Guarda el perfil para obtener su URL de webhook.</p>
    @endif
    @if($canWrite)
        <form class="space-y-4 max-w-xl mt-5" x-data="{ saving: false }"
              x-on:submit.prevent="saving = true; $wire.saveProviderSettings(@js($provider), { ...Object.fromEntries(new FormData($el)), enabled: $el.elements.enabled.checked, clear_credentials: $el.elements.clear_credentials.checked }).finally(() => { saving = false; $el.querySelectorAll('[data-secret]').forEach(input => input.value = ''); })">
            <p class="text-sm text-slate-500">Los campos vacíos conservan su valor. Los secretos se borran del formulario tras cada intento de guardado. Guardar no conecta con el proveedor.</p>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="enabled" @checked($profile['enabled'])> Habilitar este perfil</label>
            <fieldset class="space-y-3 border-t border-slate-100 pt-3">
                <legend class="font-semibold text-sm">Canal API</legend>
                <label class="field">Clave API nueva de {{ $provider === 'brevo' ? 'Brevo' : 'SendGrid' }}<input data-secret name="api_key" type="password" value="" maxlength="4096" autocomplete="new-password"></label>
            </fieldset>
            <fieldset class="space-y-3 border-t border-slate-100 pt-3">
                <legend class="font-semibold text-sm">Canal SMTP</legend>
                <p class="text-sm">Servidor fijo: <code>{{ $provider === 'brevo' ? 'smtp-relay.brevo.com' : 'smtp.sendgrid.net' }}</code>.</p>
                <label class="field">Seguridad SMTP<select name="smtp_security"><option value="starttls" @selected($profile['smtp_security'] === 'starttls')>STARTTLS obligatorio · 587</option><option value="smtps" @selected($profile['smtp_security'] === 'smtps')>TLS implícito · 465</option></select></label>
                @if($provider === 'brevo')
                    <label class="field">Usuario SMTP nuevo<input data-secret name="smtp_username" type="password" value="" maxlength="1024" autocomplete="new-password"></label>
                @else
                    <p class="text-sm">Usuario SMTP fijo: <code>apikey</code>.</p>
                @endif
                <label class="field">{{ $provider === 'brevo' ? 'Clave SMTP nueva (distinta de la clave API)' : 'Clave API nueva para autenticar SMTP' }}<input data-secret name="smtp_password" type="password" value="" maxlength="4096" autocomplete="new-password"></label>
                <p class="text-xs text-slate-500">Certificado y nombre del servidor verificados. No hay opción sin TLS.</p>
            </fieldset>
            <fieldset class="space-y-3 border-t border-slate-100 pt-3">
                <legend class="font-semibold text-sm">Autenticación del feedback</legend>
                @if($provider === 'brevo')
                    <label class="field">Bearer privado nuevo del webhook<input data-secret name="webhook_secret" type="password" value="" minlength="32" maxlength="512" autocomplete="new-password"></label>
                    <p class="text-xs text-slate-500">Configura el mismo Bearer en Brevo. Usa un valor aleatorio de al menos 32 caracteres; nunca lo añadas a la URL.</p>
                @else
                    <label class="field">Clave pública nueva de firma del Event Webhook<textarea data-secret name="webhook_public_key" maxlength="4096" rows="3" autocomplete="off"></textarea></label>
                    <p class="text-xs text-slate-500">Activa la firma del Event Webhook en SendGrid y copia su clave pública ECDSA P-256, en PEM o Base64.</p>
                @endif
            </fieldset>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="clear_credentials"> Eliminar todas las credenciales de este proveedor y deshabilitar el perfil</label>
            <button type="submit" class="btn-primary" x-bind:disabled="saving" wire:loading.attr="disabled" wire:target="saveProviderSettings">Guardar {{ $provider === 'brevo' ? 'Brevo' : 'SendGrid' }}</button>
            <span x-show="saving" x-cloak role="status" class="text-sm">Guardando…</span>
        </form>
    @else
        <p class="text-sm mt-4">Necesitas permiso de gestión para cambiar este perfil.</p>
    @endif
</section>
