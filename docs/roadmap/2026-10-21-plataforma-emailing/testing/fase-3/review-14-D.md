# Revisión 14 — Lente D — T-14, intento 1/3

**Resultado: sin hallazgos de rendimiento atribuibles al incremento.** Critical: **0** · Important: **0** · Minor: **0**.

Revisión independiente de contexto fresco mediante fallback genérico y runtime heredado. `opus/high` corresponde al frontmatter declarado; no se afirma haber ejecutado Claude/Opus. La lente D se activó por la ruta de la nueva migración, según `storage/phase3-t14-review1/lenses.stdout.json`.

La conclusión se limita al diff frente a `defc14eb7ad40ef1b8c651a36d338a8365d75669`. No acredita capacidad, SLA, QA de navegador, T-19 ni cierre de T-14.

## Integridad y cobertura

- Leído íntegramente `lens-D.md`; recorrido completo de los hunks de `increment.patch`, incluidos los 21 ficheros. SHA256 del patch: `238dd30a77e00290d55df57abfaaca143d377a23191cedde3e599c46d60716e9`.
- Parseado íntegramente `increment.patch.json`: 21 entradas de cambio, 782 fuentes y 1958 entradas de evidencia. Inspección semántica de metadatos de revisión, tier, lentes, alcance y gates seleccionados; el catálogo íntegro no equivale a lectura semántica de todos sus archivos.
- SHA256 de `capsule-manifest.json`: `f430fbc70590076bca6d86b885e9d65126430cd8746f59bc22231d1c8e63b460`. Verificadas sus **2766 entradas**, sin ausencias ni diferencias. Contrastados además los **782 hashes** de `all_source_files` con sus copias.
- Lectura completa de los archivos nuevos de runtime, migración, vista y `ActivityTest`; lectura completa de los servicios modificados de transporte/configuración/plantillas y rutas. En documentación y `OutboxIntegrationTest` la cobertura es de todos los hunks y del contexto pertinente, no de cada sección histórica ajena al incremento.
- Contexto consultado exclusivamente dentro de la cápsula: T-14 y tareas posteriores del ledger; requisitos de actividad, volumen y CA-08/12/13/14 de spec; diseño/plan pertinentes; ADR-003 íntegra; `AccountContext`, `MailMessage`, paginador compartido, migraciones 000009/000010 y puntos de lock/inicio de `DeliveryLifecycle`.
- `scope.stdout.json`: 21 cambios dentro de alcance; ninguno fuera, ninguna exclusión aplicada ni avisos/info. Los patrones vigentes de exclusión no eliminan archivos de este diff.

Cobertura de los 21 ficheros del diff:

| Fichero | Lectura pertinente a D |
|---|---|
| `app/Livewire/Activity.php` | Render, actualizaciones, filtros y detalle completos |
| `app/Services/AccountMailTransport.php` | Servicio completo y cambio de procedencia |
| `app/Services/ActivityService.php` | Servicio completo: UNION, filtros, agregados, páginas, puntos y CSV |
| `app/Services/MailSettingsService.php` | Servicio completo, entrada de prueba y stream privado |
| `app/Services/TemplateService.php` | Servicio completo y entrada de prueba |
| `database/migrations/2026_10_23_000011_add_activity_provenance.php` | Up/down, índices, FK y backfill completos |
| `docs/RAG-INDEX.md` | Todos los hunks |
| `docs/README.md` | Todos los hunks |
| `docs/aplicacion/actividad-y-metricas.md` | Guía nueva completa |
| `docs/aplicacion/arquitectura.md` | Todos los hunks |
| `docs/aplicacion/desarrollo.md` | Todos los hunks |
| `docs/aplicacion/entrega-y-recuperacion.md` | Todos los hunks |
| `docs/aplicacion/plantillas-y-transporte.md` | Todos los hunks |
| `docs/infraestructura/integracion-aplicaciones.md` | Todos los hunks |
| `docs/infraestructura/operacion.md` | Todos los hunks |
| `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md` | Todos los hunks; contrato T-14 y alcance T-15–T-20 |
| `resources/views/components/app-layout.blade.php` | Línea completa antes/después, enlace añadido |
| `resources/views/livewire/activity.blade.php` | Vista nueva completa y sus bucles |
| `routes/web.php` | Archivo completo |
| `tests/Feature/ActivityTest.php` | Archivo completo, construcción del fixture y captura real de consultas |
| `tests/Feature/OutboxIntegrationTest.php` | Todos los hunks y escenario incremental down/up |

## Criterios de rendimiento contrastados

| Criterio | Veredicto | Evidencia y alcance |
|---|---|---|
| Listado y procedencia sin consultas por fila | ✓ Sin defecto demostrado | `app/Services/ActivityService.php:72` obtiene procedencia con joins; `resources/views/livewire/activity.blade.php:44` consume campos ya proyectados. El fixture compara página de 25 con selección de una fila: **6 consultas en ambos casos**. La cifra corresponde a llamadas a `page()`, no a un render completo de Livewire. |
| Cuenta, ventana y estados en consultas/agregados | ✓ Sin defecto demostrado | `app/Services/ActivityService.php:72`, `:86`, `:128`: filtros antes del UNION, agregación en SQL, sin cargar toda la cohorte en PHP para contar. Planes finales de COUNT/lista/métricas: d y m con acceso `range`, índices `activity_delivery_status` y `activity_message_status`, **6 filas estimadas** por rama en esa selección sintética. |
| Paginación de actividad | ✓ Sin defecto demostrado | `app/Services/ActivityService.php:123`: 25 resultados por página; orden estable por fecha/referencia. El plan guardado de la primera página muestra `Using filesort` sobre el derivado, con **14 filas estimadas**. Esto no demuestra una degradación relevante para el volumen inicial confirmado de 100–200 emails/día. No hay consulta por resultado en la vista. |
| Detalle por identidad y rama opuesta | ✓ Corrección presente | `app/Services/ActivityService.php:89` limita la rama correcta por ID y hace imposible la otra. Los tres planes finales d/m/d de detalle/parent de intentos usan `const`, `PRIMARY`, **1 fila estimada** y rama opuesta `Impossible WHERE`. El diagnóstico previo de 975 filas estimadas en derivado y 601 en m corresponde a fuentes históricas, no al patch actual. |
| Intentos paginados | ✓ Sin defecto demostrado | `app/Services/ActivityService.php:154` y `:162`: filtro cuenta/entrega o mensaje y página de 10; actor resuelto mediante join. El COUNT de intentos guardado usa `activity_delivery_generation_once`, acceso `ref`, **1 fila estimada**. La prueba SMTP causal conserva tres intentos de una sola entrega. |
| CSV y descarga privada | ✓ Sin defecto demostrado | `app/Services/ActivityService.php:177` y `:188`: stream, lotes de 200 y cursor fecha/referencia; no acumula todas las filas en una colección PHP. El registro final del ensayo de revocación acredita un lote autorizado y máximo 200 filas emitidas antes del rechazo siguiente. `app/Services/MailSettingsService.php:114` lee el artefacto como stream. No se añade IO de archivo por fila al listado/CSV. |
| Transporte y trabajo bajo lock | ✓ Sin defecto demostrado | `app/Services/AccountMailTransport.php:109` añade una consulta puntual de campaña por intento dentro del arranque ya transaccional. No añade bucles de consulta, esperas, red ni almacenamiento bajo ese lock. El IO del transporte permanece después de finalizar la transacción. Una consulta constante adicional para registrar actor no constituye por sí sola un defecto relevante demostrado. |
| Migración histórica | ✓ Sin defecto demostrado | `database/migrations/2026_10_23_000011_add_activity_provenance.php:12`: índices por cuenta/fecha/estado y asociación entrega/generación. Backfill set-based en SQL mediante agrupación de referencias únicas, sin bucle PHP por mensaje. El escenario down/up final y las regresiones de backfill/lifecycle pasan sobre las mismas fuentes. El coste de DDL/backfill sobre un histórico de producción no fue medido. |

Evidencia SQL principal: `storage/phase3-t14-review1/evidence/storage/phase3-t14/implementation/activity-final-exact-20261023T215959177563Z/protocol/mysql-actual-ui-queries-17a9e2e8-dc11-411c-aed7-b86101d0d1ec.json` y `mysql-explain-bc35bcb6-0d8c-4dfb-96e0-69f010f9fc15.json` en el mismo directorio. La construcción del fixture y el alcance real del log están en `tests/Feature/ActivityTest.php:438`.

El fixture guarda **601 entregas y 1202 mensajes**, repartidos entre dos cuentas y fechas antiguas/seleccionadas. No representa 1202 mensajes de la misma cuenta en la ventana consultada ni un benchmark. El acceso `ALL` a `actor` tiene dos filas estimadas y join buffer; el `ALL` del derivado, filesort y temporal de agregación no prueban por sí solos una regresión. El SELECT por asunto registrado entre los puntos es preparación del test para localizar el ID, no una consulta introducida por el panel.

## Evidencia final aceptada y límites

Se contrastaron los request/receipt originales y los hashes de ambos streams de los seis gates seleccionados, con `exit_code=0`, stderr vacío y `source_changed_during_run=[]`. Sus ZIP conservan **196/196 archivos** conformes con cada manifiesto; el manifiesto común es `59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`. Se verificó también la continuidad de esas 196 fuentes con las copias de la cápsula, incluidas las fuentes auxiliares conservadas bajo evidencia.

- `activity-final-exact-20261023T215959177563Z`: 19 passed / 212 assertions.
- `migration-final-20261023T220944786414Z`: 1 passed / 15 assertions.
- `full-final-20261023T221135766386Z`: 202 passed / 2441 assertions; no resumen de casos omitidos/incompletos en el stream inspeccionado.
- `format-final-check-20261023T215926999922Z`: comprobación Pint aprobada, sin mutación.
- `build-final-20261023T215741456940Z`: build Vite aprobado.
- `final-owned-settlement-20261023T225545600570Z`: recibo final y salida estructurada contrastados como evidencia archivada, sin nuevas consultas al host/SQL/broker.

Los tiempos de PHPUnit incluyen fixtures/migraciones/escenarios y no son latencias de las consultas del panel. EXPLAIN contiene **estimaciones**, no filas visitadas ni mediciones de IO. El conteo 6/6 es una observación de número de consultas; no demuestra coste constante de cada consulta al crecer el histórico.

No hay plan guardado específico del CSV con cursor ni medición de memoria/throughput de una exportación extensa; el fixture de planes no aporta un SELECT paginado de intentos con filas porque esa entrega aún no tiene intentos. Tampoco hay EXPLAIN/medición del backfill sobre volumen operativo. Son límites de esta evidencia, sin un escenario demostrado que permita graduarlos como defectos del diff. Búsqueda con comodines, OFFSET, materialización del UNION y DDL requieren evaluación con los volúmenes/umbrales que corresponden a T-19 antes de prometer capacidad.

Los rojos y verdes parciales anteriores se usan únicamente como diagnóstico histórico. No se atribuye éxito SMTP causal al intento inicial de puerto cero, ni se atribuye su proyección de intentos a una versión posterior. La revisión D no reabre T-10–T-13 sin regresión nueva ni sustituye las lentes A/B.

No se ejecutaron app/bootstrap, tests, SQL, broker, SMTP, navegador ni servicios; no hubo mutaciones Git. Toda inspección fue de archivos de la cápsula o comprobaciones en memoria. Única escritura: este informe. T-15, T-16/E2E-08/09/M-01, T-17/T-18, T-19 y T-20 permanecen pendientes; ADR-003 conserva su alcance de propuesta y no se afirma SMTP exactamente una vez.
