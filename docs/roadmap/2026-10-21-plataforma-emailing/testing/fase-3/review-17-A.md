Lente A — revisión global 17, intento 2/3 de T-15. Revisor genérico, runtime heredado. Revisión de solo lectura de `storage/phase3-t15-review2`.

**Sin gaps nuevos de requisitos identificados en el incremento examinado. Critical: 0 · Important: 0 · Minor: 0.** La corrección aporta evidencia para resolver D-01. Este dictamen conserva los límites de lectura indicados abajo; no representa una reauditoría integral del proyecto.

| Tarea / criterio | Dictamen | Evidencia |
|---|---|---|
| T-15: confirmar trabajo durable sin afirmar emails finalizados | ✓ | `app/Support/SchedulerRunResult.php:16` conserva `emails_completed: null`; `SchedulerTriggerController.php:24` devuelve progreso desconocido ante fallo posterior a commits. |
| T-15: repetir no duplica trabajo | ✓ | `DeliveryMaterializer.php:27` comprueba el checkpoint de la revisión vigente bajo locks; `SchedulerTriggerTest.php:147`, `:484` y `:594` comprueban rollback, replay, concurrencia e interrupción posterior al commit. |
| T-15: sin credencial válida no ejecuta | ✓ | `CronicleToken.php:17` y `:23`; tests de middleware y `SchedulerTriggerTest.php:299`. La corrección no altera el contrato aprobado. |
| Endpoint interno y secreto separado | ✓ | `routes/internal.php:9`, `config/cronicle.php:5` y `bootstrap/app.php:14`. |
| Sesión/CSRF del navegador | ✓ | `bootstrap/app.php:11` mantiene el registro web y carga por separado las rutas internas. No se introduce una exclusión global de CSRF. |
| JSON y acciones acotados; sin cuenta, límite, URL o comando del llamante | ✓ | `SchedulerTriggerInput.php:15` y `:26`; `SchedulerTriggerTest.php:342`. |
| UTC, autorización e identidad cuenta/campaña/revisión | ✓ | `SchedulerActions.php:45` filtra estado y vencimiento UTC; `:51` correlaciona revisión, cuenta y campaña. `DeliveryMaterializer.php:20` revalida bajo locks antes del checkpoint. |
| D-01: excluir el histórico completo antes de consultar destinatarios | ✓ | `SchedulerActions.php:45` utiliza `materialization_pending`; la migración añade `campaign_materialization_due`. La evidencia poblada muestra el cambio real de contadores descrito abajo. |
| Checkpoint vinculado a la revisión actual | ✓ | Migración `2026_10_24_000012_add_materialization_checkpoint.php:16`: indicador generado por comparación de identidades. `SchedulerTriggerTest.php:182` comprueba una revisión nueva sin depender de resets en las entradas de edición. |
| Atomicidad y rollback del checkpoint con entregas/outbox | ✓ | `DeliveryMaterializer.php:19`, `:54` y `:75`: transacción única, comprobaciones y actualización final. Test de fallo después del SQL del checkpoint en `SchedulerTriggerTest.php:147`. |
| Pausa/reanudación y cambio concurrente de vencimiento | ✓ | Revalidación anterior al checkpoint en `DeliveryMaterializer.php:22`; pruebas en `SchedulerTriggerTest.php:147` y `:442`. |
| Vacío y progreso tras prefijos completos | ✓ | `SchedulerTriggerTest.php:203` certifica el snapshot vacío y permite continuar; `:358` conserva progreso tras cinco campañas completas. |
| Referencias inválidas o ajenas | ✓ | Correlación del selector en `SchedulerActions.php:51`; pruebas de selección y backfill en `SchedulerTriggerTest.php:217` y `:460`. |
| Migración incremental y conservación de datos anteriores | ✓ | Migración `:24` clasifica revisiones completas mediante snapshot y pares iniciales. `SchedulerTriggerTest.php:217` compara valores antes/después, repite down/up y distingue completo, parcial, vacío, referencia inválida y par sin outbox. |
| No inventar reenvíos para pares históricos dañados | ✓ | `DeliveryMaterializer.php:60` rechaza hash/par inicial incompleto; `SchedulerTriggerTest.php:284` conserva el estado ambiguo sintético sin crear una republicación. |
| Frontera de 10.000 destinatarios | ✓ | Escrituras por lotes dentro de la transacción; `SchedulerTriggerTest.php:537` comprueba 10.000 pares, checkpoint y replay estable. |
| Dispatcher finito y presupuesto cooperativo | ✓ | `SchedulerActions.php:22` acota claims a 1–100 y presupuesto a 1–60.000 ms. Las comprobaciones permanecen entre operaciones; no prometen cancelar SQL/AMQP en curso. |
| Leases, backoff y fencing | ✓ | Se reutiliza `OutboxDispatcher`; no aparece un bypass nuevo. El test incremental de outbox incorpora la nueva migración en el orden correspondiente. |
| Cliente fijo, configuración privada y límites de E/S | ✓ | `scripts/cronicle-trigger.cjs:9`, `:26`, `:49` y `:117`; se conservan acciones cerradas, deadline, límite de respuesta y diagnósticos depurados. |
| Un Cronicle compartido, montajes RO y workers de aplicación | ✓ | `infra/compose.emailing-cronicle.yaml:4`: dos binds RO sobre el servicio existente, sin socket Docker ni consumidores permanentes añadidos. |
| Evidencia real corregida, perfiles nativo y Docker | ✓ | Daemon fix1 sobre `c074241d`: 15 resultados, 12 positivos y tres negativos esperados; materialización/replay, lotes 20+3 y migraciones actuales en ambos perfiles. |
| Verificación declarada ejecutada | ✓ | `tasks.md:646` exige el focal literal. Recibo final `fix1-scheduler-final-v2-20261024T050714277949Z`: salida 0, 18 pruebas y 247 aserciones, fuentes estables. |
| Alcance declarado | ✓ | `tasks.md:643` incluye migraciones y familias necesarias. `scope.stdout.json`: 33 paths, cero fuera de alcance, avisos e información vacíos, sin exclusiones de usuario. Los patrones declarados sin tocar son información de alcance, no trabajo realizado. |
| Constitución | No aplica | No figura `docs/CONSTITUTION.md` entre las fuentes archivadas. |
| Interop E2/E3 | No aplica | El incremento no modifica `commands/`, `agents/` ni `hooks/`; no corresponde ejecutar exportadores. |
| OpenAPI | No aplica | Ningún path cambiado es una especificación OpenAPI. |
| T-16–T-20 y decisiones propuestas | ✓ | T-15 permanece abierto; T-16 está en borrador. ADR-003, ADR-005 y GOT-005 no se presentan como aceptados ni como prueba de QA integral. |

El contraste poblado de D-01 conserva la misma prueba privada antes y después. Con 365 campañas y 73.000 parejas retenidas, cada uno de los tres disparos vacíos anteriores registra **73.366 `Handler_read_key` y 73.000 `Handler_read_next`**; después registra **2 y 0**, respectivamente. Las etapas intermedias de 10 y 50 campañas muestran la misma separación. Son operaciones reales de sesión MySQL. El escenario original de 105,1 millones diarios era una deducción estática; estos resultados no se convierten en capacidad, SLA ni medición de CPU.

El verde poblado conserva su manifiesto histórico `42e71e8f`. Los nueve gates finales utilizan **217 fuentes `c074241d`**. Verifiqué sus entradas ZIP, hashes de streams, ausencia de cambios y salidas 0: Scheduler **18/247**, Outbox **20/299**, Activity **20/237**, PHP puro **11/80** y full **232/2793**. Los resultados Node conservan siete casos por perfil. Los antiguos daemon `ad773` y full `d265` mantienen sus identidades históricas; no prueban la corrección actual.

También contrasté los cinco diagnósticos fix1 con salida no cero: regresión histórica, comparación inicial de objetos en migración, observación de estado no vacío, atribución inicial insuficiente y dos observadores que buscaban la consulta retirada. No concedo cobertura a aserciones no alcanzadas. La evidencia posterior separa recuperación atribuida, corrección de comparación/observadores y gates finales.

Existe una discrepancia **de metadatos del auditor** conservada expresamente: tanto `root-fix1-daemon-semantics.json` como `root-fix1-daemon-v2-semantics.json` contienen `corrected_runtime_migration_hashes_checked: 15`. `fix1-daemon-console-metadata-correction.json` explica ese literal frente a las **16 comprobaciones reales**. Los datos archivados de runtime muestran 16 fuentes de migración y 16 migraciones aplicadas, incluido el checkpoint. No atribuyo al campo JSON una corrección que no contiene ni lo interpreto como cambio del daemon.

Verifiqué los hashes de streams de **31 recibos hijos del daemon y 53 del censo**. Los resultados finales archivados muestran 51 contenedores ausentes, 100 respuestas broker 404, base de pruebas con únicamente `migrations: 0`, eventos propios ausentes, cero trabajos propios activos y tres hashes de jobs anteriores preservados. Son observaciones históricas. No se consultó el historial completado; ausencia de purgado no demuestra retención. AMQP no acredita SMTP ni recepción en bandeja.

La integridad y la lectura semántica se distinguen:

- **7.690/7.690 entradas** del manifiesto verificadas, sin discrepancias; SHA del manifiesto `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`.
- SHA del patch `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1` correcto.
- `increment.patch.json` procesado completo como estructura e inventarios: 33 paths, 806 fuentes y 6.862 evidencias; correspondencia comprobada.
- Patch completo de **2.866 líneas** recorrido mediante fragmentos, incluidos criterios y todos los hunks nuevos del ledger y los cuatro informes anteriores.

| Path cambiado | Cobertura semántica |
|---|---|
| `.env.example` | Hunk completo y plantilla consultada; salida completa comprimida |
| `app/Http/Controllers/SchedulerTriggerController.php` | Completa en diff |
| `app/Http/Middleware/CronicleToken.php` | Completa en diff |
| `app/Services/DeliveryMaterializer.php` | Completa, incluido contexto transaccional |
| `app/Services/SchedulerActions.php` | Completa en diff |
| `app/Support/SchedulerRunResult.php` | Completa en diff |
| `app/Support/SchedulerTriggerInput.php` | Completa en diff |
| `bootstrap/app.php` | Completa |
| `config/cronicle.php` | Completa en diff |
| Nueva migración de checkpoint | Completa en diff |
| `docs/aplicacion/programacion-con-cronicle.md` | Completa en diff |
| Tres documentos modificados de `docs/infraestructura/` | Hunks completos; contexto histórico parcial |
| `docs/knowledge/README.md` | Hunks completos; consulta adicional comprimida |
| ADR-005 y GOT-005 | Completos en diff |
| `tasks.md` | Todos los hunks nuevos y criterios T-15/T-16; no historial íntegro sin cambios |
| Cuatro informes `review-16-*.md` | Completos en diff |
| Override Compose y `routes/internal.php` | Completos en diff |
| `scripts/cronicle-trigger.cjs` | Completo en diff |
| `tests/Feature/ActivityTest.php` | Hunk y método afectado completos; contexto adicional recorrido con compresión |
| `tests/Feature/OutboxIntegrationTest.php` | Hunks y método afectado completos; resto histórico parcial |
| `tests/Feature/SchedulerTriggerTest.php` | Completo en diff |
| Tres nuevos tests unitarios | Completos en diff |
| Dos nuevos archivos de soporte | Completos en diff |

**Límite pendiente de cobertura:** no afirmo lectura semántica íntegra de los 33 archivos finales ni de todas las evidencias. En particular, la compresión de herramientas impide presentar como lectura exacta completa el contexto histórico de Activity, Outbox y documentos anteriores. Releí fragmentos críticos y obtuve hechos estructurados cuando fue necesario; los hashes no sustituyen esa lectura.

No encontré un escenario nuevo con `fichero:línea` que justifique graduar un gap de requisitos. No ejecuté aplicación, nuevos tests, SQL, HTTP, SMTP, Docker, broker, navegador ni operaciones sobre procesos o servicios. No modifiqué archivos.
