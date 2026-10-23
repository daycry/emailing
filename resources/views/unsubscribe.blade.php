<x-auth-layout title="Baja de comunicaciones">
    <h1 class="text-2xl font-bold mb-5">Baja de comunicaciones</h1>
    @if(!$available)
        <p>El enlace no está disponible. Solicita un nuevo enlace al remitente.</p>
    @elseif($completed)
        <p role="status">Solicitud de baja registrada. Si ya la habías solicitado, se mantiene sin cambios.</p>
    @else
        <p class="mb-6">Confirma que deseas dejar de recibir comunicaciones de esta cuenta. La baja afecta a los envíos que todavía no han empezado.</p>
        <form method="post">@csrf<button class="btn-primary">Confirmar baja</button></form>
    @endif
</x-auth-layout>
