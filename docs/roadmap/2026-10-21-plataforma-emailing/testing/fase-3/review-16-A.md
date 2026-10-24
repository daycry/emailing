Lente A — revisión global 16, intento 1/3 de T-15. Fallback genérico; runtime heredado. Revisión de solo lectura sobre `storage/phase3-t15-review1`.

**Resultado: sin gaps de requisitos encontrados en el incremento T-15 leído. Critical: 0 · Important: 0 · Minor: 0.** La cobertura histórica parcial del ledger se detalla abajo y no se presenta como lectura íntegra.

| Tarea / criterio | Dictamen | Evidencia y escenario comprobado |
|---|---|---|
| T-15: disparo exitoso describe trabajo durable y distingue finalización de emails | ✓ | `app/Support/SchedulerRunResult.php:16` devuelve creaciones/claims y `emails_completed: null`. `app/Http/Controllers/SchedulerTriggerController.php:24` reconoce progreso desconocido tras una excepción. `tests/Feature/SchedulerTriggerTest.php:403` conserva el commit y comprueba replay sin duplicación. |
| T-15: repetir no duplica trabajo | ✓ | `app/Services/SchedulerActions.php:55` omite destinatarios ya materializados. `app/Services/DeliveryMaterializer.php:18` conserva transacción, locks y parejas delivery/outbox. `tests/Feature/SchedulerTriggerTest.php:172`, `:298` y `:349` comprueban replay, concurrencia e identidades estables. |
| T-15: sin credencial válida no ejecuta | ✓ | `app/Http/Middleware/CronicleToken.php:17` deshabilita configuración inválida; `:23` exige Bearer exacto y comparación constante. `tests/Feature/SchedulerTriggerTest.php:113` comprueba rechazo sin consultas de dominio. |
| Endpoint interno y secreto separado | ✓ | `config/cronicle.php:5`, `.env.example:2` y `routes/internal.php:9`. No se reutiliza autenticación interactiva. |
| Preservación de sesión/CSRF del navegador | ✓ | `bootstrap/app.php:11` mantiene las rutas web; `:14` carga separadamente la ruta interna. No cambia el grupo web ni introduce exclusiones globales de CSRF. Leído también `routes/web.php`. |
| Métodos, query, media, tamaño, profundidad y acciones cerradas | ✓ | `CronicleToken.php:27`, `SchedulerTriggerInput.php:15` y `:26`. Los tests de `SchedulerTriggerTest.php:156` y los unitarios comprueban los rechazos antes del trabajo. No se aceptan cuenta, límite, comando ni credencial aportados mediante cuerpo/query. |
| UTC efectivo e identidad de cuenta/campaña/revisión | ✓ | `SchedulerActions.php:44` filtra estado/autorización/UTC y correlaciona revisión con cuenta y campaña. `DeliveryMaterializer.php:20` revalida bajo locks. `SchedulerTriggerTest.php:256` y `:274` cubren cambio posterior a selección y punteros inválidos. |
| Progreso tras prefijos completos | ✓ | `SchedulerActions.php:55` selecciona destinatarios sin entrega; `SchedulerTriggerTest.php:172` crea cinco campañas completas antes de las pendientes y comprueba progreso. |
| Atomicidad y frontera de 10.000 destinatarios | ✓ | `DeliveryMaterializer.php:18` mantiene una transacción por campaña, con inserciones por lotes de 500. `SchedulerTriggerTest.php:349` comprueba 10.000 entregas/outbox y replay estable. El EXPLAIN posterior no se presenta como benchmark. |
| Dispatcher finito y presupuesto honesto | ✓ | `SchedulerActions.php:22` limita claims a 1–100; `:23` limita presupuesto a 1–60.000 ms; `:27` comprueba reloj monotónico entre intentos. `SchedulerTriggerTest.php:199` demuestra 20+3; `:381` demuestra que una operación lenta puede superar el presupuesto y evita el segundo claim. |
| Claim separado de publicación confirmada/SMTP | ✓ | `OutboxDispatcher.php:46` devuelve claim realizado, incluso con publicación incierta. `SchedulerTriggerTest.php:370` comprueba tres claims y backoff durable sin afirmar publicación ni SMTP. La guía explica esa semántica. |
| Leases, backoff y fencing existentes | ✓ | El incremento utiliza el dispatcher existente. Leídos `OutboxDispatcher.php:13`, `:33` y `:46`: claim independiente, `SKIP LOCKED`, token/vencimiento y finalización condicionada. No introduce un bypass. |
| Cliente Shell fijo y configuración privada | ✓ | `scripts/cronicle-trigger.cjs:9`, `:17` y `:117` separan acción, configuración y argv. `docs/aplicacion/programacion-con-cronicle.md:108` documenta los scripts fijos con `json=0`/`annotate=0`. |
| Deadline, respuesta acotada y diagnóstico depurado | ✓ | `cronicle-trigger.cjs:26` establece deadline; `:33` impide redirecciones; `:49` limita respuesta a 4096 bytes; `:60` valida contrato; `:78` construye resultados depurados. Leídos íntegros los siete tests Node. |
| Infraestructura existente, dos montajes RO y workers propios | ✓ | `infra/compose.emailing-cronicle.yaml:4` amplía únicamente `cronicle` con dos binds RO y orígenes obligatorios. No añade broker, servicio duplicado, socket Docker ni consumidor permanente. |
| Daemon real y perfiles nativo/Docker | ✓ | Evidencia `cronicle-real-daemon-v4-20261024T020743248472Z`: 15 resultados correlacionados, 12 positivos y tres negativos esperados; UID/GID 1000, cliente montado, configuración legible, HTTP alcanzable y lotes 20+3 en ambos perfiles. El nativo es un listener PHP alcanzable; no acredita Apache ni despliegue operativo. |
| Preservación de jobs y asentamiento final | ✓ | Snapshots y hashes conservan los tres jobs de base. `final-cronicle-api-20261024T034955856035Z/cronicle-final-state.json:1` registra ocho IDs propios comprobados, cero eventos y cero jobs propios activos. El helper consulta schedule/active, no historial completado. |
| Verificación declarada ejecutada | ✓ | `tasks.md:645` declara el focal literal. Recibo final `scheduler-calendar-final-20261024T030805918044Z`: exit 0, 13 pruebas/155 aserciones sobre `d265`, streams verificados. |
| Alcance declarado | ✓ | `tasks.md:642` incluye las familias modificadas y el override. `scope.stdout.json:1`: 25 paths, ninguno fuera de alcance, sin avisos, exclusiones de usuario ni información oculta. La corrección de Activity es fixture únicamente y tiene evidencia causal. |
| Constitución | No aplica | No hay `docs/CONSTITUTION.md` en las fuentes archivadas; no se inventan principios. |
| Generación interop / E2–E3 | No aplica | El diff no toca `commands/`, `agents/` ni `hooks/`. No ejecuté exportadores. |
| Contrato OpenAPI | No aplica | Ningún archivo cambiado es una spec OpenAPI. |
| T-16–T-20 / ADR-003 | ✓ delimitación | El ledger conserva T-15 en progreso y 14/20 cerradas. No exige ni declara terminados navegador/fault QA/M-01, API de envío/keys, carga cruzada/restauración o despliegue. ADR-003 sigue propuesta. |

**Evidencia contrastada directamente**

- Los diez ZIP seleccionados de daemon/gates/censo/API contienen 213 fuentes y coinciden con sus manifiestos; no registran cambios durante ejecución.
- Los streams de los **29 recibos hijos del daemon** y los **107 del censo** coinciden con sus hashes.
- Daemon histórico conserva `ad773`. Comparación propia con el full final `d265`: cambia exclusivamente `tests/Feature/ActivityTest.php`; **212 fuentes idénticas**, proyección `f66a02c5e904751e5377879275b8590776f893dc4d4b831544abb3d594e213fb`.
- Leídos fallo full original, RED/GREEN de calendario y reemplazo exacto: se fijan únicamente `created_at`/`updated_at` del prototipo en `ActivityTest.php:457`; permanecen ventana y asercciones.
- Gates finales: Activity **20/237**, Scheduler **13/155**, PHP puro **11/80**, Node nativo **7**, Linux **7**, Pint verde y full **227/2701**. Los recibos y streams respaldan ejecución; estos números no sustituyen la lectura de requisitos/tests.
- Leída la cadena causal Docker: imagen con 10 migraciones frente a 15 congeladas, fixture fallida con exit 0 y SQLSTATE 42S02, recuperación atribuida del estado parcial, bind mínimo de `database`, prueba deliberada de fixture con exit 1 y daemon v4 corregido. No utilicé `exit 0` ni `cleanup.errors=[]` como prueba suficiente de inicialización o limpieza.
- Censo final: cero firmas de procesos propios, 105 contenedores ausentes, 100 nombres de broker comprobados como queue/exchange mediante 200 respuestas 404 y SQL con únicamente `migrations: 0`. Seis números PID reutilizados carecían de firma propia; no se consideran procesos propios vivos.

**Cobertura de lectura, separada de integridad**

Integridad: **6.306/6.306 entradas del manifiesto verificadas, cero discrepancias**, y SHA de `increment.patch` correcto. `increment.patch.json` se analizó completo como estructura y metadatos; esto no equivale a lectura semántica de cada artefacto referenciado.

Lectura semántica completa de estos **24 archivos cambiados**:

```text
.env.example
app/Http/Controllers/SchedulerTriggerController.php
app/Http/Middleware/CronicleToken.php
app/Services/SchedulerActions.php
app/Support/SchedulerRunResult.php
app/Support/SchedulerTriggerInput.php
bootstrap/app.php
config/cronicle.php
docs/aplicacion/programacion-con-cronicle.md
docs/infraestructura/integracion-aplicaciones.md
docs/infraestructura/operacion.md
docs/infraestructura/servicios.md
docs/knowledge/README.md
docs/knowledge/gotchas/GOT-005-runtimes-de-qa-con-fuentes-mezcladas.md
infra/compose.emailing-cronicle.yaml
routes/internal.php
scripts/cronicle-trigger.cjs
tests/Feature/ActivityTest.php
tests/Feature/SchedulerTriggerTest.php
tests/Unit/CronicleTokenTest.php
tests/Unit/SchedulerTriggerControllerTest.php
tests/Unit/SchedulerTriggerInputTest.php
tests/support/cronicle-trigger.test.cjs
tests/support/scheduler-request.php
```

Para el archivo cambiado `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md` leí íntegros T-15, sus criterios/subtareas y toda la traza nueva hasta el cierre de entrega, además de tareas y referencias pertinentes. **No leí íntegramente sus 3.750 líneas históricas anteriores ni reaudité los componentes previamente aprobados.** Por tanto, no afirmo cumplimiento literal de lectura semántica completa de los 25 archivos.

Contexto adicional leído: spec/plan/diseño pertinentes, ADR-003, brief formal, materializador/dispatcher, rutas web/console, migraciones de campañas/outbox y helpers archivados de daemon/API/censo. La evidencia seleccionada se leyó por sus escenarios relevantes; los 5.487 paths de evidencia no se presentan como leídos semánticamente en su totalidad.

No ejecuté aplicación, pruebas, SQL, HTTP, SMTP, Docker, broker, navegador ni operaciones sobre servicios. Solo lecturas y verificaciones puras de archivos. No modifiqué ningún archivo.
