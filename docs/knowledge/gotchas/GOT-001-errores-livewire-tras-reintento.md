---
id: GOT-001
tipo: gotcha
area: frontend
estado: propuesta
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-22
---

# Errores anteriores después de un reintento correcto en Livewire

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

## Qué hacer

Limpiar la validación después de que el servicio complete la operación. Mantener
la excepción y su mensaje cuando el servicio rechaza la entrada. Probar sobre
la misma instancia: rechazo, estado persistido intacto, entrada corregida,
éxito persistido y ausencia del error anterior. No añadir limpiezas a operaciones
que ya resuelven correctamente esa transición.

## Evidencia

[Regresiones de Teammates](../../../tests/Feature/TeammateTest.php) y
[componente](../../../app/Livewire/Team.php): tres fallos antes de corregir,
cuatro casos focalizados aprobados después; el caso de retirada ya pasaba.
El [ledger](../../roadmap/2026-10-21-plataforma-emailing/tasks.md) identifica
FB-01, la revisión del incremento y el ciclo de QA. El stdout real RED/GREEN
está en `testing/fase-1/raw/team-feedback-{red,green}.txt` de esa iniciativa.
