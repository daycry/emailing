# Revisión 15 — T-14 — Lente B — intento 2/3

**Resultado: A-01 corregido; sin nuevos defectos de corrección confirmados. Critical: 0 · Important: 0 · Minor: 0 pendientes.**

Revisión independiente de contexto fresco mediante fallback genérico y runtime heredado. El tier `opus/high` corresponde al frontmatter del plugin; no identifica un runtime Claude ejecutado. Se siguieron el prompt de `lens-B.md` y el perfil backend incorporado.

Fuente exclusiva de código y evidencia: `storage/phase3-t14-review2/`. No se modificaron archivos ni Git. No se ejecutaron aplicación, bootstrap, tests, SQL, SMTP, broker, navegador ni operaciones sobre servicios o procesos vivos. Las comprobaciones propias fueron lecturas y verificaciones offline en memoria.

## Integridad y alcance

Se contrastaron los siguientes hashes:

| Artefacto | SHA256 |
|---|---|
| `increment.patch` | `0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29` |
| `correction.patch` | `a466bca1c7add79ada5e67844c6527d0baa49daca1d78648b0e112f3b3143652` |
| `capsule-manifest.json` | `a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4` |

- Manifiesto: **4420 entradas verificadas**, sin ausencias ni discrepancias.
- Índice de fuentes: **785 hashes verificados**, sin discrepancias.
- Cambios: **24 fuentes finales y 15 versiones anteriores** verificadas contra `increment.patch.json`.
- Incremento: 198.535 bytes, 2397 líneas; corrección: 57.697 bytes, 518 líneas.
- Scope archivado: **24/24 archivos en alcance**, sin fuera de alcance, avisos, información adicional ni exclusiones efectivas.
- Leído `previous-verdicts.md` completo, incluida la fusión raíz y los informes A/B/D anteriores. Se comprobó que las tres copias de informes añadidas al incremento reproducen los textos ya contenidos en ese traspaso.

`increment.patch.json` se procesó completo como índice: metadatos, cambios, fuentes, 3607 entradas de evidencia, gates e historial. Verificar esos inventarios no equivale a leer semánticamente cada archivo indexado.

## Reevaluación de A-01

**Veredicto: corregido.**

`resources/views/livewire/activity.blade.php:83` muestra ahora:

> Sin registros de intento vinculados en esta página.

El mensaje describe únicamente la página consultada. No deduce ausencia de transporte, cero comienzos ni un histórico global vacío. Es compatible con los tres escenarios exigidos:

1. Entregas históricas `smtp_accepted` y `ambiguous`, con dos comienzos registrados y ninguna asociación reconstruible.
2. Una entrega pendiente con cero comienzos.
3. Una entrega procesada mediante transporte log, con un comienzo y un registro vinculado, cuya segunda página está vacía.

La regresión de `tests/Feature/ActivityTest.php:558` ejecuta realmente el down/up de la migración con un puntero compartido, comprueba que no se reconstruye una asociación y abre ambos detalles históricos. Mantiene visibles contador y estado, excluye la explicación falsa y exige el mensaje neutral. Después comprueba el cero real y la página fuera de rango, distinguiendo expresamente `total() === 1` de `count() === 0`.

La explicación de `docs/aplicacion/actividad-y-metricas.md:81` conserva la misma distinción entre comienzos registrados y vínculos disponibles.

### Evidencia causal contrastada

- `fix1-causal-red-20261023T232829154345Z`: **1 fallo / 7 aserciones**, exit 1. El stdout original identifica el fallo en la exclusión de la frase que negaba el transporte, en el nuevo caso histórico. Ese rojo no acredita las aserciones posteriores al primer fallo.
- `fix1-causal-green-20261023T233018388447Z`: **1 prueba / 25 aserciones**, exit 0.
- La comparación de ambos ZIP muestra cambios únicamente en la vista y en el test. El verde añade el escenario de segunda página después de conservar el prefijo causal anterior.
- El protocolo verde confirma dos históricos con dos comienzos y cero vínculos, cero comienzos en la entrega pendiente y una página segunda con un registro total y cero visibles. Sus hashes de clases cargadas y vista coinciden con las fuentes revisadas.

Los comienzos históricos son datos deliberados de fixture. No se presentan como conversaciones SMTP observadas. El escenario vinculado usa transporte log.

## Criterios y escenarios de corrección

Las aprobaciones anteriores se conservan con sus límites. La lectura del incremento completo no aporta evidencia nueva que justifique reabrirlas.

| Criterio | Veredicto | Evidencia y alcance |
|---|---|---|
| Estados distinguibles y métricas demostrables | ✓ | `ActivityService.php:17`, `:87`, `:128`; vista `:24`, `:34`, `:77`. Aceptación SMTP, log, fallo, supresión y ambigüedad siguen diferenciados. La corrección elimina la inferencia histórica incorrecta. |
| Cuenta y `activity.read` vigentes | ✓, aprobación conservada | `ActivityService.php:34`, `:47`, `:65`, `:142`; `Activity.php:43`, `:50`, `:68`. Cuenta y autorización quedan fuera del catch de validación. No se añade ningún camino nuevo de consulta o acción. |
| Identificadores ajenos, búsqueda y cursor | ✓, aprobación conservada | `ActivityService.php:72`, `:86`, `:89`, `:115`, `:186`. Ramas del UNION acotadas por cuenta, lookup puntual y OR agrupados. La corrección no modifica estos predicados. |
| Filtros inválidos y recuperación | ✓, aprobación conservada | `Activity.php:71` captura únicamente `ValidationException`; vista `:17`, `:19`, `:89` oculta resultados, métricas y CSV hasta corregir. No absorbe 403/409. |
| UI, detalle y paginación | ✓ en alcance backend | `Activity.php:54`, `:58`; `ActivityService.php:121`, `:151`; vista `:83`. La regresión nueva verifica una página vacía con histórico existente. No constituye QA visual de navegador. |
| Procedencia durable y compatibilidad de consumidores | ✓, aprobación conservada | `AccountMailTransport.php:29`, `:61`, `:109`, `:110`, `:112`; `DeliveryLifecycle.php:74`, `:124`. Asociación y transición de comienzo comparten transacción y fencing. El argumento opcional de origen conserva llamadas anteriores; las dos fachadas UI indican `test`. |
| Migración, backfill y lifecycle | ✓, aprobación conservada | Migración `000011:18`, `:20`, `:31`, `:47`; `ActivityTest.php:525`, `:600`, `:617`. La regresión nueva utiliza el backfill real y conserva desconocida la asociación ambigua. No se adivina generación histórica. |
| Descarga y CSV | ✓, aprobación conservada | `MailSettingsService.php:108`, `:113`, `:118`; `ActivityService.php:165`, `:172`, `:184`. Descargas reautorizadas, CSV protegido y lotes de 200. No se introduce dependencia de `settings.read` para actividad. |
| Registros, comienzos y feedback desconocido | ✓ | `ActivityService.php:87`, vista `:66`, documentación `:81`. Se mantienen separados los registros independientes y los comienzos acreditados; el vacío de una página ya no altera su interpretación. |
| Ambigüedad sin reenvío inseguro | ✓, aprobación conservada | La vista informa la incertidumbre y no introduce acciones de reenvío. La corrección es de presentación; no cambia decisiones de transporte. |

**Lista de defectos nuevos: sin defectos confirmados.**

## Gates finales contrastados

Se verificaron request/receipt originales, igualdad de sus comandos, hashes de stdout/stderr y los ZIP/manifiestos de los **seis gates finales seleccionados**. También se verificaron los dos runs causales: **ocho ZIP de 196 entradas**, sin discrepancias internas.

Los seis gates finales no registran cambios de fuentes y comparten manifiesto:

`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`

Sus 195 fuentes de producto coinciden con `sources/`; el auxiliar `run.py` coincide con su copia archivada bajo `evidence/`.

| Gate final | Resultado del stream original |
|---|---|
| `fix1-pint-check-20261023T233126541808Z` | Exit 0; Pint `result: passed`. |
| `fix1-build-final-20261023T233158836869Z` | Exit 0; build Vite terminado correctamente. |
| `fix1-activity-exact-20261023T233258528346Z` | **20 passed / 237 assertions**, 280,67 s. |
| `fix1-migration-final-20261023T233853373319Z` | **1 passed / 15 assertions**, 26,54 s. |
| `fix1-full-final-20261023T233955193754Z` | **203 passed / 2466 assertions**, 2710,15 s. |
| `fix1-owned-settlement-20261024T002545020815Z` | Exit 0; resumen archivado de cinco runs, recursos atribuidos y guard de pruebas. |

Los stderr de estos gates están vacíos. Los JSON root consultados se decodificaron atendiendo al BOM: el verificador terminal de Activity conserva UTF-16; los informes de auditoría separados son UTF-8. No se alteraron sus bytes.

La evidencia focal actual conserva tres generaciones rechazado/rechazado/aceptado de una sola entrega, con actor original distinto del viewer. Los streams originales del relay muestran tres conexiones y un DATA con 250 final; el escenario ambiguo separado muestra DATA y pérdida de respuesta final. Las pruebas explícitas bloqueada/suprimida conservan cero conexiones y cero artefactos. No se atribuye ese éxito causal al diagnóstico inicial de puerto cero.

Los planes actuales y el protocolo de consultas conservan el fixture de **601 entregas / 1202 mensajes** y **6 consultas frente a 6** para página de 25 y selección de una fila. Son evidencia limitada de consultas/cardinalidad y estimaciones del optimizador; no demuestran capacidad ni coste constante al crecer el histórico.

## Cobertura real y límites

Se recorrieron los 24 diffs completos, incluidos runtime, migración, vista, pruebas, documentación, ledger y los tres informes anteriores reproducidos. La corrección se distinguió expresamente del incremento original mediante `correction.patch` y la comparación de snapshots causales.

La lectura adicional comprendió los servicios modificados completos, `AccountContext`, `MailMessage`, los puntos de bloqueo/inicio/finalización de `DeliveryLifecycle`, la migración histórica de reservas y el paginador compartido. Se contrastaron el bloque T-14, los criterios pertinentes de spec/diseño/plan y ADR-003.

No se afirma lectura semántica de las 785 fuentes, las 3607 evidencias ni todo el historial del ledger. Tampoco se acredita cobertura de código, QA de navegador, aceptación humana, entrega al buzón, feedback externo, benchmark o exactly-once SMTP. Los recibos verdes respaldan los escenarios descritos; no sustituyen su análisis.

T-10–T-13 no se reabren sin regresión nueva. T-15–T-20 mantienen su alcance pendiente, incluido QA de fase/E2E-08/09/M-01, API, carga/restauración y despliegue. ADR-003 continúa como propuesta.

**Decisión de B: A-01 corregido; 0 Critical / 0 Important / 0 Minor pendientes.**
