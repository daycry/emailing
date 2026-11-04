---
proposed_by: debug-root-cause
category: GOTCHA
tipo: gotcha
area: testing
titulo: Distinguir reloj PHP, reloj SQL y precisión persistida en tests temporales
fecha: 2026-11-04
iniciativa: 2026-10-24-mcp-google
evidencia: validated_case
fuentes:
  - tests/Feature/McpOAuthTest.php
  - tests/Feature/McpSendTest.php
  - app/Models/McpAuthorizationCode.php
  - app/Services/SendingApiService.php
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-6/regresion-20261103/report.md
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-6/regresion-20261103/oauth-clock-proof.public.json
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-6/regresion-20261103/full-green/evidence-index.public.json
tags:
  - stack:laravel
  - database:mysql
  - area:testing
---
La próxima vez que veas un TTL exacto de300 devolver299 sólo en una suite larga,
o un DTO estable variar únicamente en observed_at, identifica el reloj que produce
cada campo y la precisión de su almacenamiento antes de relajar la aserción.

## Síntoma y causa probada

El TTL calculaba expiración y timestamps mediante lecturas distintas del reloj PHP.
Al cruzar un segundo y persistir con la precisión de fecha del modelo, la diferencia
era299. El ensayo del modelo real reproduce300 con reloj fijo y299 con lecturas
00:00:00.950000 y00:00:01.010000; no requiere SQL ni configuración privada.
En otro test, observed_at procede de SQL UTC_TIMESTAMP(6): congelar PHP no controla
ese reloj. Dos lecturas devolvieron microsegundos distintos sin cambiar los datos.

## Qué hacer en su lugar

Congelar el reloj PHP en el test que comprueba el TTL exacto, conservando300/3600 y
las verificaciones de canje, replay y revocación. Para observed_at, validar formato
UTC con seis decimales y progresión no decreciente. Comparar estrictamente todos
los demás campos, filas hidratadas y efectos. No cambiar plazos de producción ni
ignorar timestamps de negocio estables. Un reloj SQL que retroceda sigue fallando.

## Regresión y límites

Los rojos conservan la discrepancia299/300 y el delta único observed_at. El foco
temporal/migraciones pasa8 casos/194 aserciones. La suite completa posterior pasa
623 casos/13.739 aserciones sin fallos, errores u omitidos sobre538 fuentes estables;
build exit0. Sólo cambian tests, no el runtime OAuth ni los relojes de producción.
Estos resultados no acreditan E2E por perfil, Google real ni QA independiente.
