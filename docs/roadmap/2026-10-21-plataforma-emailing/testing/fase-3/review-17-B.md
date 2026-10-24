Lente B — revisión global 17, intento 2/3 de T-15. Revisor genérico, runtime heredado.

**Sin defectos nuevos de corrección identificados en lo examinado. Critical: 0 · Important: 0 · Minor: 0.** D-01 queda **corregido en el incremento examinado**. Este informe no declara aceptación global de T-15 ni cierre del QA posterior.

| Criterio reevaluado | Veredicto | Evidencia y escenario |
|---|---|---|
| Checkpoint de la revisión vigente | ✓ | `database/migrations/2026_10_24_000012_add_materialization_checkpoint.php:16` genera el indicador comparando identidades. Una nueva revisión vuelve a quedar pendiente sin depender de resets en cada entrada de edición. `SchedulerTriggerTest.php:182` prueba esa transición. |
| Revalidación bajo locks | ✓ | `app/Services/DeliveryMaterializer.php:18` conserva locks de cuenta/campaña y vuelve a comprobar autorización, estado, fecha SQL y pertenencia de revisión antes de consultar el checkpoint. `SchedulerTriggerTest.php:442` conserva el escenario de candidato modificado tras selección. |
| Atomicidad de entregas, outbox y checkpoint | ✓ | `DeliveryMaterializer.php:51–75` comprueba cantidad del snapshot y pares iniciales antes de actualizar el checkpoint dentro de la misma transacción. La excepción inyectada después del UPDATE y antes del commit revierte las tres escrituras; caso en `SchedulerTriggerTest.php:147`. |
| Replay, concurrencia y pausa/reanudación | ✓ | El retorno por checkpoint ocurre dentro de los locks, en `DeliveryMaterializer.php:27`. Las pruebas conservan identidades tras replay y pausa/reanudación; dos kernels separados convergen a una pareja y un checkpoint vigente en `SchedulerTriggerTest.php:484`. |
| Identidad cuenta/campaña/revisión | ✓ | `SchedulerActions.php:51` mantiene la correlación de los tres identificadores; el materializador la revalida. Los constraints de procedencia de entregas impiden asociar una entrega a una campaña o destinatario de otra revisión. No se introduce un bypass. |
| Vacío y progreso tras prefijos completos | ✓ | `SchedulerTriggerTest.php:203` certifica el snapshot vacío coherente y permite procesar la campaña siguiente. `:358` conserva progreso tras cinco campañas completas y replay estable. |
| Migración incremental con datos | ✓ | `2026_10_24_000012_add_materialization_checkpoint.php:23` certifica únicamente snapshots con cantidad correcta y entrega/hash/outbox inicial correspondiente. `SchedulerTriggerTest.php:217` prueba dos ciclos down/up conservando valores e identidades; distingue completo, parcial, vacío, puntero inválido, cuenta ajena y borrador. |
| Legado ambiguo sin outbox | ✓, con límite conocido | La migración no lo certifica. `DeliveryMaterializer.php:60` detecta el par incompleto y falla sin inventar un reenvío. El caso conserva estado `ambiguous`, ausencia de outbox y checkpoint nulo. Es una fixture SQL; no acredita SMTP observado. |
| Observadores corregidos | ✓ | `SchedulerTriggerTest.php:442` y `:537` observan ahora la selección de campañas sin `FOR UPDATE`. Conservan las aserciones de revalidación y frontera de 10.000 destinatarios. La tanda final alcanza 18 pruebas/247 aserciones. |
| Contratos anteriores | ✓, sin reapertura | Los cambios examinados preservan autenticación dedicada, JSON acotado, separación de sesión/CSRF, dispatcher finito, leases/backoff/fencing, cliente fijo y `emails_completed: null`. No apareció evidencia nueva que justificase reabrir los veredictos aprobados de revisión16. |

**D-01 — corregido.** `SchedulerActions.php:46` excluye las revisiones certificadas mediante `materialization_pending` antes de consultar destinatarios. La migración añade `campaign_materialization_due` en `:54`.

La prueba poblada archivada conserva el mismo SHA de prueba en rojo y verde. Sus tres repeticiones por etapa registran:

| Campañas completas | Destinatarios | Claves por tick, antes → después | Lecturas siguientes, antes → después |
|---:|---:|---:|---:|
| 0 | 0 | 1 → 1 | 0 → 0 |
| 10 | 2.000 | 2.011 → 2 | 2.000 → 0 |
| 50 | 10.000 | 10.051 → 2 | 10.000 → 0 |
| 365 | 73.000 | 73.366 → 2 | 73.000 → 0 |

Se conservan las 73.000 entregas y sus 73.000 eventos. El plan final usa el índice de pendientes y no ejecuta la búsqueda de revisiones en ese tick vacío. Son contadores reales de sesión y observaciones archivadas de ejecución; no los convierto en consultas SQL independientes, capacidad ni SLA.

El verde poblado conserva su identidad histórica `42e71e8f…`. Frente a la foto final `c074241d…`, coinciden 216 de 217 fuentes; cambia exclusivamente `SchedulerTriggerTest.php`, cuyos observadores y pruebas finales tienen evidencia posterior propia.

El legado sin outbox **requiere reconciliación manual**: permanece pendiente y puede devolver 503 repetidamente mientras siga siendo candidato. La guía declara ese comportamiento; no se presenta como recuperación automática ni como aceptación global del procesamiento de otras campañas ante datos dañados.

Se contrastaron los nueve gates finales mediante recibos, hashes de streams y **1.953 entradas ZIP**, sin discrepancias. Las 217 fuentes de cada gate coinciden con sus archivos archivados en la cápsula. Resultados observados: Scheduler **18/247**, Outbox **20/299**, Activity **20/237**, PHP puro **11/80**, Node nativo **7**, Pint aprobado y full **232/2.793**.

El daemon actualizado conserva `c074241d…`: 15 resultados, doce positivos y tres errores esperados, cliente coincidente, UID/GID 1000 y configuración legible. Los snapshots nativo/Docker acreditan las 16 migraciones aplicadas y sus hashes, checkpoint vigente, pendiente cero y estado `authorized`. El replay conserva 23 parejas en cada perfil; la respuesta perdida nativa conserva 24 parejas antes y después del replay. No se atribuyen estas ejecuciones a los históricos `ad773…` o `d265…`.

El censo archivado registra cero procesos propios presentes, 51 contenedores ausentes, 100 sondas broker con 404 y únicamente `migrations: 0` en la base de pruebas. La comprobación API registra ausencia de los dos eventos propios y trabajos propios activos, con los tres hashes de baseline conservados. No consultó historial completado. Estas observaciones no prueban estado vivo posterior, SMTP ni recepción en bandeja.

La **cobertura semántica real** es la siguiente:

- **Lectura completa:** controlador, middleware, `DeliveryMaterializer`, `SchedulerActions`, ambos DTO de soporte, `config/cronicle.php`, migración nueva, guía de programación, ADR-005, GOT-005, cuatro informes íntegros de revisión16, override Compose, ruta interna, cliente Node, `SchedulerTriggerTest`, tres pruebas unitarias, pruebas Node y `scheduler-request.php`.
- **Todos los hunks y contexto pertinente:** `.env.example`, `bootstrap/app.php`, las tres guías de infraestructura, `docs/knowledge/README.md`, `ActivityTest.php` y `OutboxIntegrationTest.php`. No afirmo relectura íntegra de sus cuerpos históricos sin cambios.
- **Ledger:** criterios/subtareas T-15 y todo el añadido del incremento, incluidas revisión16, corrección y entrega final. No se releyó toda la historia publicada anterior.
- **Diff:** recorrido completo de sus 2.866 líneas; los fragmentos comprimidos relevantes se contrastaron con lecturas acotadas o hechos obtenidos directamente de los archivos. No atribuyo lectura semántica completa a todas las fuentes o evidencias inventariadas.

Integridad separada de cobertura: **7.690/7.690 entradas del manifiesto verificadas, cero discrepancias**. El manifiesto coincide con `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`; el patch coincide con `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1`. Se procesó íntegramente la estructura de `increment.patch.json`: 33 paths cambiados, 806 fuentes y 6.862 evidencias. Estos hashes no sustituyen lectura.

Solo se realizaron lecturas y verificaciones puras de archivos. No hubo modificaciones, ejecución de aplicación, pruebas nuevas, SQL, HTTP, SMTP, Docker, broker ni inspección de los procesos protegidos. No se leyó el stdout prohibido ni configuración privada. T-16–T-20 y la aceptación integral de ADR-003 permanecen fuera de este veredicto.
