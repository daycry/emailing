# Revisión 15 — Lente A — T-14, intento acotado 2/3

**Resultado: ✓ A-01 corregido; sin nuevos gaps de conformidad confirmados. Critical: 0 · Important: 0 · Minor: 0.**

Revisión de contexto fresco mediante fallback genérico y runtime heredado. El tier del plugin no identifica un runtime Claude ejecutado. Se aplicó el prompt de `storage/phase3-t14-review2/lens-A.md` y se leyeron los tres informes anteriores y la fusión completos en `previous-verdicts.md`. No existen rebuttals ni deudas aceptadas. Se conservan las aprobaciones anteriores con sus límites; no se reabren componentes aprobados sin evidencia nueva.

La revisión fue exclusivamente de lectura sobre la cápsula. No se ejecutaron aplicación, pruebas, SQL, broker, SMTP, navegador ni consultas a servicios o procesos vivos. No se modificaron archivos ni Git.

## Integridad y cobertura real

| Artefacto | Comprobación |
|---|---|
| Base | `defc14eb7ad40ef1b8c651a36d338a8365d75669` |
| `increment.patch` | SHA256 `0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29`; 198.535 bytes, 2.397 líneas y 24 ficheros |
| `capsule-manifest.json` | SHA256 `a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4` |
| `correction.patch` | SHA256 `a466bca1c7add79ada5e67844c6527d0baa49daca1d78648b0e112f3b3143652`; 518 líneas |
| Integridad de cápsula | **4.419 de 4.420 entradas verificadas**, sin faltantes ni diferencias entre las comprobadas. Se omitió voluntariamente `sources/.env.example`. El stdout privado T12 prohibido no figura en el inventario. |
| Fuentes modificadas | Coinciden los 24 hashes finales y los 15 hashes de versiones anteriores declarados |
| Índice completo | `increment.patch.json` parseado íntegramente: 24 cambios, 785 fuentes y 3.607 registros de evidencia |

Se recorrieron los cambios del incremento mediante sus hunks y fuentes correspondientes, incluidos documentación, ledger y los informes anteriores incorporados. Se leyeron completos los nuevos componentes de Actividad, servicio, vista, migración, guía y `ActivityTest.php`; para servicios existentes, Outbox y documentación histórica, se contrastaron los deltas y el contexto pertinente. Se consultaron los requisitos de T-14, spec, plan y diseño, ADR-003, autorización de cuenta, descarga privada y transición durable de inicio.

**Estos hashes y catálogos no equivalen a lectura semántica de las 785 fuentes ni de los 3.607 artefactos.** La evidencia semántica se concentró en la corrección, los ocho runs indicados abajo, protocolos del focal final, planes SQL y antecedentes necesarios.

El scope archivado declara **24/24 ficheros dentro de alcance**, sin exclusiones efectivas, fuera de alcance, avisos ni información adicional. Las exclusiones vigentes son `CONTINUE-HERE*.md`, `.claude/**` y `docs/knowledge/journal/**`; no ocultan cambios de este incremento. El alcance real corresponde a `tasks.md:612`: actividad, pruebas, migración, documentación relacionada y trazabilidad de revisión.

## T-14 → criterios

Las referencias siguientes son rutas relativas del repositorio, examinadas en las copias selladas de `sources/`.

| Criterio | Veredicto | Evidencia y límite |
|---|---|---|
| Estados SMTP aceptado, fallido, suprimido y ambiguo distinguibles (`tasks.md:619`) | ✓ Conservado | `app/Services/ActivityService.php:17`, `:87`, `:104`; `resources/views/livewire/activity.blade.php:4`, `:43`, `:77`; regresiones `tests/Feature/ActivityTest.php:165`, `:192`, `:203`, `:239`. La corrección no modifica estados. |
| Listados, agregados y descargas solo de la cuenta autorizada (`tasks.md:620`) | ✓ Conservado | `ActivityService.php:34`, `:65`, `:126`, `:142`, `:151`, `:172`; `app/Services/MailSettingsService.php:108`, `:118`. Filtros de cuenta preceden al UNION; búsqueda y cursor agrupan sus OR. |
| Alertas comprobables, ventana/denominador/fuente y desconocidos distintos de cero (`tasks.md:621`) | ✓ Conservado | `ActivityService.php:69`, `:128`; vista `:24`, `:31`; guía `docs/aplicacion/actividad-y-metricas.md:99`. No se inventa feedback ni entrega al buzón. |
| UI, navegación, filtros, paginación, detalle, CSV y archivos privados | ✓ Conservado | `routes/web.php:28`, navegación en `resources/views/components/app-layout.blade.php:1`, `app/Livewire/Activity.php:34`, `:54`, `:68`. Páginas de 25 registros y diez intentos. La revisión no sustituye QA visual. |
| Entregas sin intento y todos los intentos sucesivos | ✓ Conservado | `ActivityService.php:71`, `:151`; `ActivityTest.php:147`, `:165`. Se consulta la asociación durable, no solo el puntero actual. |
| Procedencia de cuenta/entrega/generación/actor/origen en la misma transacción de inicio | ✓ Conservado | `app/Services/AccountMailTransport.php:61`, `:109`, `:110`, `:112`; `app/Services/DeliveryLifecycle.php:74`, `:122`. El IO sucede después de la transacción; perder fencing revierte las escrituras. |
| Pruebas explícitas y legado desconocido | ✓ Conservado | `AccountMailTransport.php:29`, `app/Services/MailSettingsService.php:105`, `app/Services/TemplateService.php:137`; `ActivityTest.php:227`. Un actor no convierte por sí solo el histórico en prueba. |
| Migración up/down/backfill y generación histórica desconocida | ✓ Conservado | `database/migrations/2026_10_23_000011_add_activity_provenance.php:12`, `:31`, `:47`; `ActivityTest.php:525`; delta de `OutboxIntegrationTest.php:597`. Solo se asocia un puntero único de la misma cuenta y no se reconstruye generación por conjetura. |
| FK compuesta y lifecycle de cuenta/campaña/usuario | ✓ Conservado | Migración `:18`, `:20`; regresiones ahora en `ActivityTest.php:600`, `:617`. Cruce de cuenta y generación duplicada rechazados; cascades y actor eliminado conservan la semántica aprobada. |
| Reautorización tras mount, cambios de cuenta y revocación | ✓ Conservado | `Activity.php:41`, `:48`, `:54`, `:62`, `:68`; `ActivityTest.php:302`, `:319`, `:423`. Teammate con solo `activity.read` no necesita `settings.read` para actividad/CSV/archivo (`ActivityTest.php:286`). |
| Filtros inválidos visibles y recuperación sin datos obsoletos | ✓ Conservado | `Activity.php:71` captura únicamente `ValidationException`; autorización y 403/409 quedan fuera. Vista `:17`, `:19`, `:88`; regresión `ActivityTest.php:377`. |
| Escape, diagnósticos depurados y CSV seguro/acotado | ✓ Conservado | Blade escapa metadatos; `ActivityService.php:21`, `:165`, `:172`; `ActivityTest.php:319`, `:362`, `:386`. Streaming por lotes de 200 con reautorización; sin cuerpos ni respuestas SMTP crudas. |
| Presentación honesta del vacío de intentos | **✓ A-01 corregido** | Vista `:83`, guía `:80` y nueva regresión `ActivityTest.php:558`. El texto solo afirma ausencia de registros vinculados **en esa página**. |
| Unidades separadas, esperas y ambigüedad | ✓ Conservado y corregido el vacío | `ActivityService.php:78`, `:87`, `:128`, `:190`; vista `:66`; `ActivityTest.php:254`, `:343`, `:558`. Registros independientes, comienzos durables y entregas lógicas siguen separados; no se añade reenvío automático. |
| Consultas, índices y N+1 | ✓ Conservado, con límites | `ActivityService.php:65`, `:121`, `:151`; `ActivityTest.php:435`. El focal final vuelve a conservar 601 deliveries/1.202 mensajes, seis consultas para page25 y page1, y tres puntos d/m/d con PRIMARY/const y una fila estimada. No acredita capacidad ni T-19. |

## A-01 — corregido

El defecto anterior estaba en `resources/views/livewire/activity.blade.php:83`: una página sin asociaciones deducía que el transporte no había comenzado. La nueva frase, «Sin registros de intento vinculados en esta página», describe exactamente la selección mostrada. No contradice un contador histórico positivo ni convierte una página fuera de rango en una historia global vacía.

La regresión de `tests/Feature/ActivityTest.php:558` comprueba:

- Dos entregas históricas, estados `smtp_accepted` y `ambiguous`, cada una con `attempts = 2`, pero cero asociaciones recuperables después de ejecutar down/up con un puntero compartido.
- Una entrega pendiente con cero comienzos reales.
- Una entrega procesada mediante transporte log, con un comienzo y un registro asociado: la segunda página tiene **total 1, filas visibles 0**, y muestra el mismo vacío neutral.

La guía añade la explicación correspondiente en `docs/aplicacion/actividad-y-metricas.md:80`. La corrección no cambia asociaciones, métricas ni decisiones de envío.

La causalidad está conservada:

| Run | Observación |
|---|---|
| `fix1-causal-red-20261023T232829154345Z` | Exit 1; **1 failed / 7 assertions**, 18,86 s. El nuevo caso, con la vista original, falla al excluir la frase incorrecta. No acredita los escenarios posteriores a ese primer fallo. |
| `fix1-causal-green-20261023T233018388447Z` | Exit 0; **1 passed / 25 assertions**, 19,21 s. Incluye además la ampliación posterior del caso de página fuera de rango. |

Se contrastaron los ZIP rojo/verde: cambia la frase de la vista y se amplía el test con el caso paginado. El protocolo verde y el del focal final conservan dos históricos con comienzos positivos/cero vínculos, cero comienzos de la entrega pendiente y total uno/cero visibles en página dos. Los hashes de clases cargadas y vista concuerdan con las fuentes selladas.

Los comienzos históricos son datos de fixture; no se presentan como conversaciones SMTP observadas. El caso paginado usa log.

## Verificación declarada y gates finales

La verificación literal de `tasks.md:615` tiene evidencia ejecutada nueva. **No existe gap de verificación declarada sin evidencia.**

Solo se consideran finales los seis runs seleccionados:

| Run | Resultado observado en streams originales |
|---|---|
| `fix1-pint-check-20261023T233126541808Z` | Exit 0; Pint `--test`, `result: passed` |
| `fix1-build-final-20261023T233158836869Z` | Exit 0; build terminado |
| `fix1-activity-exact-20261023T233258528346Z` | Exit 0; **20 passed / 237 assertions**, 280,67 s |
| `fix1-migration-final-20261023T233853373319Z` | Exit 0; **1 passed / 15 assertions**, 26,54 s |
| `fix1-full-final-20261023T233955193754Z` | Exit 0; **203 passed / 2.466 assertions**, 2.710,15 s |
| `fix1-owned-settlement-20261024T002545020815Z` | Exit 0; censo archivado de cinco runs, 46 contenedores propios, 42 entidades broker, 77 PID históricos, cero coincidencias nativas y guard de pruebas true |

Para esos seis runs y ambos causales se contrastaron request/receipt, igualdad del comando registrado, hashes de stdout/stderr, stderr vacío y ausencia de cambios de fuentes. Se verificaron **195 entradas de cada ZIP**, omitiendo `.env.example`, contra sus respectivos manifiestos. Los seis finales comparten SHA256 de manifiesto:

`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`.

Los informes terminales root de rojo, verde y full tienen BOM UTF-16LE y se leyeron con detección de encoding, conservando bytes. Los informes separados de censo/liberación son UTF-8. El censo es evidencia archivada de recursos atribuidos; no representa una consulta nueva ni inventario global del host.

El focal final conserva tres intentos de la misma entrega, generaciones 1/2/3 y estados rechazado/rechazado/aceptado; el actor original difiere del viewer. El relay correspondiente registra tres conexiones, un DATA y un final 250. El escenario ambiguo registra DATA y pérdida de respuesta final, sin final 250. El escenario bloqueado/suprimido no registra conexiones. Estas observaciones no acreditan entrega al buzón, Internet ni exactly-once SMTP.

## Condicionales y límites

- **Constitución:** no existe `docs/CONSTITUTION.md` en el inventario sellado.
- **Interop:** no aplica; el incremento no toca `commands/`, `agents/` ni `hooks/`.
- **OpenAPI:** no hay spec OpenAPI modificada.
- **Cobertura:** no hay porcentaje medido del diff. Casos, aserciones y hashes no se convierten en cobertura.
- **Revisiones anteriores:** se conservan las aprobaciones de A/B/D con sus límites. No se encontró evidencia nueva que obligue a reabrirlas.
- **Históricos:** full202 sobre el manifiesto anterior, los verdes parciales, formatters y rojos originales siguen siendo diagnósticos históricos. No se promociona el intento inicial con puerto cero a prueba SMTP causal exitosa.
- **Pendientes posteriores:** T-15, T-16/E2E-08/E2E-09/M-01, T-17/T-18, T-19 y T-20 conservan íntegro su alcance. ADR-003 permanece propuesta. Esta revisión no ejecuta ni concede QA de fase, aceptación humana o despliegue.

**Gaps pendientes: ninguno. A-01: corregido. Totales: 0 Critical · 0 Important · 0 Minor.**
