---
id: GOT-007
tipo: gotcha
area: frontend
estado: aceptada (validada: revisión de dos lentes, 2026-10-24, intento 19)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Los hooks updated pueden borrar una confirmación del mismo batch Livewire

## Síntoma

La programación futura de una campaña revisada rechaza la confirmación aunque
el formulario la envía como `true`. El request lleva la fecha efectiva y la
confirmación; la respuesta vuelve con `confirmed=false` y error de aceptación.

## Causa raíz

Livewire aplica los valores de todo el batch antes de ejecutar sus hooks
`updated`. `Campaigns::updated(effectiveUtc)` invalidaba incondicionalmente la
confirmación, incluida la que acababa de llegar en el mismo request. El orden
de las claves del payload no evita ese borrado. La traza de QA observó el
cambio true→false y la regresión ejecutada sobre el producto anterior falló
con `confirmed => validation.accepted`.

## Qué hacer en su lugar

Identificar durante `updatingConfirmed` que el batch incluye confirmación
explícita, mediante una marca privada que dura sólo ese request. Conservar
esa decisión al actualizar fecha/momento del mismo batch. Una fecha o momento
en un request posterior debe invalidar una confirmación anterior. Un cambio
de composición siempre invalida y requiere guardar/revisar de nuevo; la marca
no reemplaza permisos, revisión vigente ni validación de la fecha.

Ejercitar un batch real con `Testable::update(calls, updates)` en ambos órdenes.
En la versión instalada, `set(array)` emite requests separados y no reproduce
este caso. Comprobar también confirmación falsa, composición pendiente y la
invalidación en requests posteriores.

## Evidencia

[Regresiones de CampaignTest](../../../tests/Feature/CampaignTest.php):
`test_explicit_confirmation_survives_the_same_livewire_batch_as_future_timing_in_either_key_order`
y `test_batched_timing_still_requires_confirmation_and_composition_changes_still_require_review`.
El [ledger de T-16](../../roadmap/2026-10-21-plataforma-emailing/tasks.md)
conserva los recibos privados `t16-fix1-red-20261024T133359121289Z` (3 fallos,
11 aserciones junto con la regresión de traducción) y
`t16-fix1-green-20261024T133709434666Z` (3 pasadas, 45 aserciones).
El [informe QA anterior](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/t16-attempt-1/report.md)
identifica P-02. La revisión de dos lentes del 24-10-2026, intento 19,
validó esta entrada y la reparación. La revisión y la validación focal no
acreditan el QA de navegador ni M-01.
