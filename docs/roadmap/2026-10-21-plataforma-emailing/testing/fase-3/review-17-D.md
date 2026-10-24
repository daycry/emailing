**Lente D — revisión global 17, T-15, intento 2/3. D-01 corregido. Sin hallazgos nuevos de rendimiento identificados.**

**Critical: 0 · Important: 0 · Minor: 0.**

Revisor genérico por indisponibilidad del tipo dedicado; runtime heredado. Revisión de solo lectura sobre `storage/phase3-t15-review2`, aplicando el alcance incremental y conservando los veredictos aprobados del intento anterior.

| Criterio de rendimiento | Veredicto | Evidencia |
|---|---|---|
| Excluir el histórico completo antes de recorrer destinatarios | ✓ D-01 corregido | `app/Services/SchedulerActions.php:45–57` selecciona mediante `materialization_pending`; desaparece la subconsulta recurrente sobre destinatarios/entregas. |
| Índice útil con histórico poblado | ✓ | `database/migrations/2026_10_24_000012_add_materialization_checkpoint.php:54`. El `EXPLAIN ANALYZE` poblado observa acceso por `campaign_materialization_due`, sin recorrer destinatarios históricos. |
| Backfill único frente a trabajo repetido en cada tick | ✓ | La clasificación de snapshots completos está en `Migration::up():22–51`. El selector habitual no ejecuta esa clasificación. |
| Checkpoint ligado a la revisión vigente | ✓ | La columna generada compara las identidades de revisión (`migration:16`). El dominio valida cuenta/campaña/revisión antes del replay rápido (`DeliveryMaterializer.php:20–28`). |
| Atomicidad y rollback del checkpoint | ✓ | `DeliveryMaterializer.php:19–78`: pares y checkpoint pertenecen a la misma transacción, bajo locks de cuenta y campaña. Regresión en `SchedulerTriggerTest.php:147`. |
| Replay, concurrencia y progreso posterior | ✓ | Replay directo evita cargar destinatarios; pruebas de histórico, concurrencia, revisión nueva y prefijo vacío en `SchedulerTriggerTest.php:114`, `:182`, `:203` y `:484`. |
| Coste de una nueva materialización | ✓ Sin defecto nuevo | Cursor por revisión, escrituras por lotes de 500 y comprobación final de pares (`DeliveryMaterializer.php:30–75`). Ese trabajo corresponde a una revisión pendiente, no a cada tick posterior. |
| Dispatcher finito | ✓ Conservado | `SchedulerActions.php:22–38`: máximo servidor de 100 claims y presupuesto cooperativo comprobado entre operaciones. |
| Cliente y E/S | ✓ Sin hallazgo | Cliente HTTP y lectura de configuración asíncronos, entradas/respuestas acotadas y deadlines. No aparece trabajo recurrente nuevo proporcional al histórico. |

**Estado de D-01: corregido, sin deuda aceptada ni rebate.**

La evidencia poblada contrasta el escenario anual del hallazgo original:

| Campañas completas / destinatarios | `Handler_read_key` anterior por tick | Corregido por tick |
|---|---:|---:|
| 0 / 0 | 1 | 1 |
| 10 / 2.000 | 2.011 | 2 |
| 50 / 10.000 | 10.051 | 2 |
| 365 / 73.000 | 73.366 | 2 |

Cada etapa conserva tres ticks vacíos medidos después del calentamiento. Con 73.000 destinatarios, el anterior registra además **73.000 `Handler_read_next` por tick**; el corregido registra **0**. Se mantienen las 73.000 entregas y sus 73.000 filas de outbox.

Los archivos `history-cost.json` de los runs `fix1-history-red-20261024T043714442655Z` y `fix1-history-green-20261024T045425563158Z` contienen los contadores. Leí el productor `SchedulerHistoryCostTest.php`: utiliza el selector/materializador reales y deltas de contadores de la misma sesión MySQL. Las copias del test rojo y verde tienen el mismo SHA `df70ef69…`; sus recibos terminan respectivamente con 1 y 0.

Estos son **contadores observados**, no 73.366 consultas SQL independientes. La proyección original de 105,1 millones de comprobaciones diarias continúa siendo una deducción estática; no la presento como CPU, latencia ni capacidad medida.

La corrección mantiene los invariantes relevantes para evitar una optimización incorrecta:

- El checkpoint acredita materialización SQL de entrega/outbox inicial, nunca publicación, SMTP o entrega externa.
- Una revisión nueva vuelve a estar pendiente por comparación de identidad; no depende de resetear un booleano en cada entrada de edición.
- Pausa/reanudación y replay conservan identidades. El rollback inyectado después del SQL del checkpoint revierte también ambos lados de los pares.
- El backfill comprueba cardinalidad del snapshot, procedencia de revisión, entrega/hash y evento inicial. Certifica los completos y el vacío coherente; deja pendientes los parciales y punteros inválidos.
- Un par histórico sin outbox no se certifica ni recibe un reenvío inventado. La prueba conserva su estado ambiguo y exige reconciliación controlada.
- La verificación de pares añade trabajo por revisión materializada. No encontré un escenario dentro del volumen admitido que lo convierta en una nueva degradación recurrente comparable a D-01.

**Integridad y evidencia de ejecución**

Verifiqué **7.690/7.690 entradas** del manifiesto, sin ausencias ni discrepancias. SHA del manifiesto: `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`. SHA del patch: `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1`.

Procesé `increment.patch.json`: 33 paths cambiados, 806 fuentes y 6.862 artefactos de evidencia. La puerta archivada declara 33 paths en alcance, ninguno fuera, sin avisos, información oculta ni exclusiones de usuario. La lente D se activa por las rutas Scheduler y la migración.

Los **nueve gates finales** conservan 217 fuentes del manifiesto `c074241d…`, sin cambios durante ejecución. Contrasté sus ZIP y streams: cero diferencias con las fuentes archivadas correspondientes. La comparación entre el verde histórico `42e71e8f…` y el final encuentra exclusivamente `SchedulerTriggerTest.php`; las otras 216 fuentes coinciden. Por tanto, no reetiqueto el verde histórico como ejecución de una foto posterior.

El daemon actualizado conserva resultados nativo/Docker y checkpoints coincidentes con la revisión vigente, con `materialization_pending=0` y estado `authorized`. Sus inventarios visibles contienen 16 migraciones cuyos hashes coinciden con las fuentes revisadas. Los resultados conservan lotes 20+3 y replay sin nuevas creaciones. Los antiguos `ad773…` y `d265…` mantienen su identidad histórica y no acreditan esta corrección.

Leí los diagnósticos relevantes: rojo de coste histórico, fallo inicial de comparación de snapshots y dos observadores que aún buscaban la consulta retirada. Los verdes posteriores no sustituyen las aserciones que aquellos intentos no alcanzaron. Los gates finales registran Scheduler **18/247**, outbox **20/299**, Activity **20/237**, PHP puro **11/80** y full **232/2.793**; esos resultados no son por sí solos aprobación de rendimiento.

**Cobertura semántica real**

Recorrí los hunks completos de los **33 paths** del patch de 2.866 líneas. Recuperé fragmentos acotados cuando las salidas grandes comprimían contenido, especialmente selector, materializador, migración y tests de checkpoint.

| Fichero | Cobertura |
|---|---|
| `.env.example` | Contenido completo |
| `app/Http/Controllers/SchedulerTriggerController.php` | Completa |
| `app/Http/Middleware/CronicleToken.php` | Completa |
| `app/Services/DeliveryMaterializer.php` | Completa |
| `app/Services/SchedulerActions.php` | Completa |
| `app/Support/SchedulerRunResult.php` | Completa |
| `app/Support/SchedulerTriggerInput.php` | Completa |
| `bootstrap/app.php` | Completa |
| `config/cronicle.php` | Completa |
| `database/migrations/2026_10_24_000012_add_materialization_checkpoint.php` | Completa |
| `docs/aplicacion/programacion-con-cronicle.md` | Completa |
| `docs/infraestructura/integracion-aplicaciones.md` | Hunks y contexto pertinente |
| `docs/infraestructura/operacion.md` | Hunks y contexto pertinente |
| `docs/infraestructura/servicios.md` | Hunks y contexto pertinente |
| `docs/knowledge/README.md` | Contenido completo |
| `docs/knowledge/adr/ADR-005-checkpoint-de-materializacion-por-revision.md` | Completa |
| `docs/knowledge/gotchas/GOT-005-runtimes-de-qa-con-fuentes-mezcladas.md` | Completa |
| `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md` | Todos los hunks nuevos y criterios/subtareas T-15; no historial publicado íntegro |
| `…/testing/fase-3/review-16-A.md` | Informe completo recibido y copia contrastada |
| `…/testing/fase-3/review-16-B.md` | Completa |
| `…/testing/fase-3/review-16-C.md` | Completa |
| `…/testing/fase-3/review-16-D.md` | Informe completo recibido y copia contrastada |
| `infra/compose.emailing-cronicle.yaml` | Completa |
| `routes/internal.php` | Completa |
| `scripts/cronicle-trigger.cjs` | Completa |
| `tests/Feature/ActivityTest.php` | Completa, recuperada en fragmentos |
| `tests/Feature/OutboxIntegrationTest.php` | Hunks, método de migración completo y contexto; cuerpo histórico restante no releído íntegro |
| `tests/Feature/SchedulerTriggerTest.php` | Completa, 615 líneas |
| `tests/Unit/CronicleTokenTest.php` | Completa |
| `tests/Unit/SchedulerTriggerControllerTest.php` | Completa |
| `tests/Unit/SchedulerTriggerInputTest.php` | Completa |
| `tests/support/cronicle-trigger.test.cjs` | Completa |
| `tests/support/scheduler-request.php` | Completa |

La integridad de los inventarios no acredita lectura semántica de las 806 fuentes ni de las 6.862 evidencias completas. El límite sobre cuerpos históricos sin cambios corresponde al alcance incremental confirmado por root; no extrapolé su contenido desde hashes.

No ejecuté aplicación, nuevos tests, SQL, HTTP, SMTP, Docker, broker, navegador ni operaciones sobre procesos/servicios. No modifiqué archivos ni inspeccioné los PIDs excluidos o el stdout prohibido.

El presupuesto sigue siendo cooperativo: una operación iniciada puede excederlo. No se acredita capacidad de producción, retención consultada del historial completado, SMTP ni recepción externa. T-16–T-20 permanecen fuera de este cierre; ADR-003 y ADR-005 conservan su estado de propuesta.
