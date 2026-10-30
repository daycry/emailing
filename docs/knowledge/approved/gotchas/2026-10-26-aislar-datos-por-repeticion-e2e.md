---
area: testing
category: GOTCHA
estado: aprobado
evidencia: validated_case
fecha: 2026-10-26
fuentes:
  - tests/e2e/mcp-google.spec.mjs
  - app/Services/ContactService.php
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/qa2/report.md
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix2/report.md
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix2/evidence.json
  - docs/knowledge/gotchas/GOT-002-limpieza-de-uploads-de-qa.md
  - docs/knowledge/gotchas/GOT-012-proxy-y-redirecciones-playwright.md
id: emailing.GOT-015
iniciativa: 2026-10-24-mcp-google
proposed_by: debug-root-cause
tags:
  - stack:laravel
  - tool:playwright
  - area:testing
tipo: gotcha
titulo: Aislar los datos de cada repetición E2E dentro de la tanda compartida
version: 1
---
Cuando un E2E pasa aislado y sólo la primera repetición pasa en una tanda,
comprueba si cada invocación tiene sus propios identificadores de datos antes
de cambiar la validación de negocio o aumentar reintentos.

## Síntoma y causa probada

E2E-08 creó un contacto cuyo email dependía exclusivamente del UUID común de
la tanda. Las repeticiones compartían cuenta y fixture; su finally cerraba los
clientes, mientras el contacto permanecía como dependencia de una campaña
inmutable hasta el teardown global. La primera repetición pasó y las otras
dos fallaron en contacts_create. El reporter histórico sólo observó HTTP200
y result.isError=true; no conservó el código cerrado de la respuesta.

Un experimento HTTP real sobre la base exclusiva de testing reprodujo tres
creaciones con el mismo email: una aceptada y dos validation_failed, con un
único contacto. Al usar tres emails separados por índice de repetición,
las tres creaciones pasaron y persistieron tres contactos. Dos casos y25
aserciones comprobaron esa diferencia, sin modificar el dominio. El código
validation_failed pertenece al experimento controlado; no se presenta como
una observación recuperada de la respuesta histórica ausente.

## Qué hacer

Usar un namespace de escenario que combine el UUID de la tanda con el
repeatEachIndex real de Playwright para el email de contacto y remitente,
el nombre de plantilla y el nombre de campaña. Conservar la identidad de
las cuentas y las guardas de red, y mantener las aserciones, timeouts y
retry0. La limpieza final sigue limitada a los recursos atribuidos a esa
tanda; no borrar dependencias inmutables para que la siguiente repetición
parezca aislada.

No convertir contacts_create en un upsert ni relajar su unicidad para
acomodar la colisión de un fixture. Diferenciar un filtro que ejecuta cero
casos de una regresión real y conservar ambos resultados sin reintentar
hasta obtener verde.

## Evidencia y límites

QA2 conserva NO-VERDE1/3. La regresión de desarrollo tras el cambio pasó3/3
con el reporter nativo, sin fallos, flaky, omitidos o reintentos; guardas,
SQL propia28 categorías cero/tx0 y binds exclusivos comprobados. La suite
completa de navegador y sus fuentes finales se contrastan en el informe
fix2 antes de proponer esta memoria. No acredita QA3, Google o clientes
reales, SMTP externo ni aceptación de la tarea o la iniciativa.

GOT-002 trata la limpieza atribuible de temporales y GOT-012 la frontera de
red de Playwright. Esta propuesta documenta la identidad de datos entre
repeticiones; complementa esas entradas sin reemplazarlas. La recuperación
local consultada tiene avisos de estado desconocido/presupuesto de resultados;
no se presenta como búsqueda exhaustiva ni como aprobación de Curator.
