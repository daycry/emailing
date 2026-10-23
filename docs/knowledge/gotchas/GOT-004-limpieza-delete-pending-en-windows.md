---
id: GOT-004
tipo: gotcha
area: testing
estado: "aceptada (validada: revisión de dos lentes, 2026-10-23, intento 13)"
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-23
---

# Unlink no garantiza la eliminación inmediata del directorio en Windows

## Síntoma

La suite de concurrencia termina con `Directory not empty` en un `rmdir` del
finalizador. Una excepción de limpieza puede ocultar el fallo original; ese
resultado no demuestra que las aserciones funcionales llegaran a ejecutarse.
La prueba aislada original pasa, por lo que no basta con repetirla en verde.

## Causa raíz del mecanismo reproducido

En Windows, un archivo abierto con permiso de eliminación compartida puede
quedar pendiente de eliminación después de un `unlink` correcto. El directorio
todavía no puede eliminarse mientras permanece abierto ese handle. La prueba
controlada observa `unlink=true`, `rmdir=false` y, después de cerrar el mismo
handle propio, `rmdir=true`.

El algoritmo de una sola pasada asumía que eliminar los nombres visibles hacía
inmediatamente eliminable el directorio. La regresión reproduce esa suposición
con un archivo propio. No identifica qué proceso o handle intervino en el fallo
histórico de la suite.

## Qué hacer en su lugar

Conservar y relanzar la excepción primaria; registrar por separado los errores
de rollback, parada y limpieza. Si no hubo error primario, un fallo de limpieza
también debe hacer fallar la prueba. Intentar todas las operaciones de
asentamiento aunque alguna falle.

Verificar raíz canónica, UUID y atribución antes de retirar archivos. Reintentar
la eliminación durante un plazo corto y acotado, sin eliminación recursiva ni
ampliar los plazos de readiness o transporte. El fixture usa dos segundos y
falla explícitamente si no termina. Para observar concurrencia, comprobar el
hilo MySQL propio esperando bajo el mutex, en lugar de deducirlo de una pausa.
Publicar los marcadores completos según [GOT-003](GOT-003-barreras-de-prueba-atomicas.md).

## Evidencia

La regresión
`test_windows_delete_pending_cleanup_waits_for_the_owned_handle_to_close`, en
[ConcurrentCampaignTest](../../../tests/Feature/ConcurrentCampaignTest.php),
falla con el algoritmo anterior y pasa con el reintento acotado. El mismo
archivo comprueba el rechazo 403 después de observar el bloqueo real y confirmar
la revocación, preservando por separado el asentamiento.

El [ledger de T-13](../../roadmap/2026-10-21-plataforma-emailing/tasks.md)
conserva RED de 1 prueba/1 aserción, GREEN de 1/3, focal de 5/34 y suite completa
de 183/2227 sobre las fuentes finales. El caso de eliminación pendiente se
ejecuta en Windows; los consumidores Linux se verifican en sus pruebas de
recuperación. Esta entrada permanece propuesta hasta revisión independiente.
