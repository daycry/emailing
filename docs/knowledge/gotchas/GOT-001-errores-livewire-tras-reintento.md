---
id: GOT-001
tipo: gotcha
area: frontend
estado: aceptada (validada: revisión de dos lentes, 2026-10-23, intento 6)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-22
---

# Errores y mensajes visibles en Livewire

## Síntoma

Una transferencia de propiedad muestra éxito y mantiene el error de contraseña
incorrecta del intento anterior. La escritura SQL, los roles y la auditoría sí
son correctos. También ocurre al corregir una invitación o los permisos.

## Causa raíz

El servicio lanza `ValidationException` y Livewire conserva el error en la
instancia del componente. Una llamada posterior válida al servicio no ejecuta
la validación del propio componente ni vacía automáticamente ese error.
Reiniciar propiedades tampoco lo elimina. El test anterior creaba otra
instancia para la segunda llamada y ocultaba la transición real del usuario.

También puede ocurrir el caso inverso: una validación global que pasa durante
`render()` vacía el error de otra operación antes de mostrarlo. En importación
CSV, validar `listSearch` borraba el error de `file`: el rechazo existía, pero el
HTML no mostraba el aviso. `assertHasErrors()` por sí solo no detectaba el
problema: seguía pasando mientras la comprobación del aviso en el HTML fallaba.
El error interno no demostraba que el usuario recibiese el mensaje.

## Qué hacer

Limpiar la validación después de que el servicio complete la operación. Mantener
la excepción y su mensaje cuando el servicio rechaza la entrada. Probar sobre
la misma instancia: rechazo, estado persistido intacto, entrada corregida,
éxito persistido y ausencia del error anterior. No añadir limpiezas a operaciones
que ya resuelven correctamente esa transición.

Una validación de un campo auxiliar durante el renderizado debe conservar los
errores de otros campos; usar `validateOnly` cuando corresponda. Comprobar el
mensaje en el HTML renderizado además del error interno, y mantener una prueba
del límite del propio campo auxiliar.

Una respuesta HTTP 200 o un `memo.errors` vacío tampoco demuestran éxito.
En el rechazo del modo SMTP externo, el HTML contenía el aviso concreto aunque
el campo `mode` no apareciera en ese mapa. Comprobar el mensaje renderizado y
que los recursos persistidos no cambien; correlacionar la respuesta de la
operación antes de comprobar sus efectos en pantalla.

## Evidencia

[Regresiones de Teammates](../../../tests/Feature/TeammateTest.php) y
[componente](../../../app/Livewire/Team.php): tres fallos antes de corregir,
cuatro casos focalizados aprobados después; el caso de retirada ya pasaba.
El [ledger](../../roadmap/2026-10-21-plataforma-emailing/tasks.md) identifica
FB-01, la revisión del incremento y el ciclo de QA. El stdout real RED/GREEN
está en `testing/fase-1/raw/team-feedback-{red,green}.txt` de esa iniciativa.

[Importación](../../../app/Livewire/ContactImports.php) y
[regresiones visibles](../../../tests/Feature/ContactImportTest.php): las dos
pruebas de aviso fallan sobre el producto anterior; la suite corregida pasa
15 pruebas y 184 aserciones. El ledger registra el par definitivo RED2/GREEN2
de T-09, distinto del intento inicial que corregía precondiciones de la prueba.

[E2E-07](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/specs/E2E-07.spec.mjs)
comprueba el rechazo del modo externo mediante aviso y estado SQL, incluido el
fingerprint de configuración sin publicar los secretos. La reproducción focal
final pasa en ambos perfiles. Una observación anterior de sincronización de la
UI nativa no tiene causa demostrada; no se atribuye a una causa inventada.
