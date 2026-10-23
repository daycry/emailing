Lente A · revisión global 11 · intento acotado 1/3 de T-12. Fallback genérico y runtime heredado; no atribuyo ejecución a Claude/Opus.

**Sin gaps nuevos de conformidad demostrados: 0 Critical / 0 Important / 0 Minor.** El componente de persistencia/publicación dispone de evidencia suficiente dentro del alcance indicado. Las pruebas de trabajadores y los criterios integrales pendientes conservan su estado abierto.

Las rutas de fuentes siguientes son relativas a `storage/phase3-t12-review1/sources/`. Las evidencias `evidence/...` son relativas a `storage/phase3-t12-review1/`.

| Tarea / criterio | Veredicto | Evidencia y alcance |
|---|---|---|
| T-12: persistir entrega y outbox atómicamente | ✓ componente | `app/Services/DeliveryMaterializer.php:20`, `:54`: una transacción engloba todos los lotes y ambas inserciones. |
| T-12: rollback después de INSERT real | ✓ | `tests/Feature/OutboxIntegrationTest.php:184`: QueryExecuted registra INSERT exitoso de deliveries antes del rechazo SQL 3819; posteriormente ambas tablas quedan vacías y la recuperación crea el evento. |
| T-12: caída SQL/broker sin pérdida silenciosa | ✓ publicación; **no verificable integralmente** | `OutboxIntegrationTest.php:394`, `:504`, `:571`, `:643`; pendiente demostrar la ejecución y deduplicación del consumidor de T-13. La casilla de `tasks.md:563` permanece abierta. |
| T-12: publicación repetida converge al mismo trabajo | ✓ identidad persistida | `OutboxIntegrationTest.php:550`, `:617`, `:694`: recuperación conserva exactamente payload, event_id y delivery_id. No acredita una sola ejecución SMTP. |
| T-12: implementar estado transaccional y dispatcher | ✓ componente | `app/Services/DeliveryMaterializer.php:18`; `app/Services/OutboxDispatcher.php:14`; `app/Console/Commands/DispatchDeliveryOutbox.php:15`. |
| T-12: inducir cortes y verificar confirms, no enrutable y reconciliación | ✓ ventanas de publicación examinadas | `OutboxIntegrationTest.php:363`, `:504`, `:571`, `:643`. No confundo el puerto cerrado de `:394` con pérdida de confirmación. |
| Identidad estable por cuenta, revisión y destinatario | ✓ | `DeliveryMaterializer.php:26`, `:37`; migración `database/migrations/2026_10_23_000009_create_delivery_outbox.php:27`: unicidad y FKs compuestas. |
| Procedencia SQL impide mezclar cuentas/revisiones | ✓ | `OutboxIntegrationTest.php:408`: MySQL rechaza account_id ajeno y destinatario perteneciente a otra revisión. |
| Dos materializadores reales convergen | ✓ | `OutboxIntegrationTest.php:274`: dos procesos arrancan mediante barrera; resultados `0/1`, una entrega y un evento; pausa/reanudación conserva identidad. |
| Autorización y fecha vigentes bajo mutex Account→Campaign | ✓ materialización | `DeliveryMaterializer.php:21`, `:22`, `:23`; coincide con el orden de `app/Services/CampaignService.php:42`, `:62`. |
| Borrador, revisada, futura, pausada o cuenta ajena no materializan | ✓ | `OutboxIntegrationTest.php:325`; no se convierte en prueba de worker ni de SMTP. |
| Payload mínimo JSON versionado | ✓ | `app/Models/DeliveryOutbox.php:22`: únicamente versión, identificadores, tipo y generación. `OutboxIntegrationTest.php:345` contrasta el mensaje recibido. |
| Dominio separado de trabajos Laravel serializados | ✓ | `config/delivery.php:4`; `ConfirmedDeliveryPublisher.php:21`; pruebas `OutboxIntegrationTest.php:350`, `:485`. |
| Reserva con lease y publicación fuera de transacción SQL | ✓ | `OutboxDispatcher.php:16`, `:20`, `:53`; `ConfirmedDeliveryPublisher.php:18`. Prueba de transacción exterior en `OutboxIntegrationTest.php:471`. |
| Fencing por generación, cuenta, entrega, estado, token y vencimiento | ✓ | `OutboxDispatcher.php:43`; `OutboxIntegrationTest.php:450` rechaza lease vencido, token antiguo, generación incorrecta y escritura tardía tras éxito. |
| Dos dispatchers/consumidores reales concurrentes | **No verificable** | Los dos procesos de `OutboxIntegrationTest.php:274` son materializadores. `:450` comprueba reclamaciones/fencing secuencialmente. No acredita todavía la obligación completa de ADR-003 `:91`–`:96`. |
| Reloj DB UTC y available_at | ✓ | `DeliveryMaterializer.php:23`, `:29`; `OutboxDispatcher.php:21`, `:27`, `:40`. `OutboxIntegrationTest.php:428` impide reclamar un evento futuro. |
| Invocación finita y espera de confirmación acotada | ✓ en el alcance implementado | CLI limita a 1–100 eventos (`DispatchDeliveryOutbox.php:17`, `:24`); publisher limita timeout y usa deadline (`ConfirmedDeliveryPublisher.php:30`, `:55`). Esto no acredita todavía el endpoint/autenticación Cronicle ni un SLA global. |
| Exchange/cola durables y mensaje persistente | ✓ | `ConfirmedDeliveryPublisher.php:33`, `:34`, `:52`; receptor verifica delivery_mode 2 en `OutboxIntegrationTest.php:79`. |
| Canal exclusivo, confirm_select y mandatory | ✓ | `ConfirmedDeliveryPublisher.php:31`–`:36`, `:53`; conexión propia por publicación. |
| Return seguido de ACK nunca se considera publicado | ✓ observado | `OutboxIntegrationTest.php:380`; `evidence/implementation/performance-fix1/protocol/return-ack-63a539ed-cf24-4414-9cf8-acbbafe11788.json:1`: secuencia return→ack y estado pending. |
| Confirm perdido después de publicación enrutada | ✓ observado | `OutboxIntegrationTest.php:541`–`:555`; `evidence/implementation/performance-fix1/protocol/confirm-lost-b24eb947-083b-4975-b907-5b060905334b.json:1`: proxy descarta método `60:80`, primer mensaje recibido, retry idéntico, pending→published. |
| Crash Linux después de confirm y antes del CAS | ✓ observado | `OutboxIntegrationTest.php:596`–`:623`; `evidence/implementation/performance-fix1/protocol/linux-crash-after-confirm-962d7d4e-903c-4b52-9706-44bddd96ab89.json:1`: secuencia completa, SQL publishing sin published_at, KILL y exit 137, recuperación del mismo evento. |
| Linux ejecuta las fuentes actuales del componente | ✓ | `OutboxIntegrationTest.php:601`–`:607`; `evidence/implementation/performance-fix1/protocol/linux-fixture-progress-5c9312b1-ae65-47ff-9970-44aa06fb16c3.json:1`: hashes de siete clases, lock y versión AMQP contrastados. |
| Pérdida SQL después de ACK conserva reconciliación durable | ✓ observado | `OutboxIntegrationTest.php:663`–`:699`; `evidence/implementation/performance-fix1/protocol/sql-loss-after-confirm-fcb04163-0d86-4a7d-804a-e813bdfc633a.json:1`: finalización SQL falla; estado publishing, misma identidad al recuperar. |
| Barreras completas, identidad y secuencia antes del KILL | ✓ | `tests/support/OutboxProtocolBarrier.php:7`, `:25`; `OutboxIntegrationTest.php:118`, `:150`, `:596`. El fixture se detiene en confirmed antes de que el padre lo mate. |
| Migración reversible sin destruir snapshots de campaña | ✓ | Migración `:53`; `OutboxIntegrationTest.php:435`: dos ciclos down/up conservan revisión/destinatario y permiten materializar después. |
| Topología propia y rechazo de vhost ajeno | ✓ componente | Nombres propios UUID y cleanup en `OutboxIntegrationTest.php:45`, `:60`; credencial propia accede a su vhost y recibe 403/530 en el ajeno (`:490`). |
| CA-21 literal: dos aplicaciones, publish/consume/manage y aislamiento completo | **No verificable integralmente** | `docs/roadmap/2026-10-21-plataforma-emailing/spec.md:188`; las pruebas nuevas no sustituyen T-19/E2E-11 ni prueban carga cruzada. No se modifica la infraestructura aprobada. |
| Corrección de coste SQL mantiene atomicidad e identidad | ✓ | `DeliveryMaterializer.php:16`, `:40`, `:54`: máximo 500 pares por lote, misma transacción y mutex. `OutboxIntegrationTest.php:222` conserva conteos, UUID, generación e idempotencia. |
| RED/GREEN significativo del presupuesto SQL | ✓ | RED 207 consultas para 100 frente a presupuesto 26; GREEN final 9/9/11 para 100/200/1.000. Método exacto RED/final SHA `8acd4ed7b3b83ef454e4d8df7c810ab7fa8802fc7e1c38d94427c734ee29702f`; primer GREEN coincide tras normalizar exclusivamente CRLF→LF. |
| Fixture SQL final y privilegios | ✓ | `OutboxIntegrationTest.php:193`–`:213`: guard, outbox vacío, CHECK propio sobre `type`, retirada en finally. Traza histórica `rollback-sql-rejection-b19aed89-70dd-4d62-8cee-eb3857fc914e.json:1` registra 1419 y cero INSERT exitosos; no se usa ese fallo como prueba de rollback. |
| Benchmark original y nuevo correctamente acotados | ✓ | `tasks.md:2255`, `:2347`; benchmark real conserva 100/200/10.000, conteos correctos, repetición cero y cleanup cero. Una muestra por tamaño: 10.000 pasa de 20.007 consultas/214,0082381 s a 47/3,2206176 s. No demuestra capacidad SMTP ni SLA. |
| Verificación declarada ejecutada sobre fuente final | ✓ | `tasks.md:2370`, `:2408`; focal **17/173**, full **158/1.663**, ambos exit 0. SQL/rollback final **2/35**, exit 0. Verificados receipts, streams y ZIP correspondientes. |
| Gates pertenecen al snapshot correcto | ✓ | Los manifests de focal, benchmark, SQL/rollback final y Pint final son idénticos: 25 fuentes; sus hashes coinciden con el full. Los verdes históricos quedan asociados a su versión anterior (`tasks.md:2294`–`:2298`). |
| Limpieza y liberación atribuibles | ✓ evidencia registrada | `evidence/implementation/performance-fix1/teardown-summary.json:3`–`:14`; cuatro receipts finales de teardown exit 0; cero recursos propios pendientes. PID histórico ajeno conservado. |
| Seguridad de la base de pruebas | ✓ | `tests/TestCase.php:11`, `app/Support/TestingDatabaseGuard.php:7`, `tests/support/outbox-action.php:27`; fixture CHECK vuelve a verificar guard antes de DDL. No ejecuté operaciones contra ninguna base. |
| Privacidad de evidencia y diagnósticos | ✓ tratamiento observado | Publisher persiste códigos fijos (`ConfirmedDeliveryPublisher.php:76`). Segundo stdout sensible procesado solo en memoria; resultado seguro **10 passed / 1 failed / 76 assertions**, exit 1. `evidence/root-second-progress-verification.json:17` mantiene su etiqueta privada. Ningún secreto o extracto raw se reproduce. |
| Alcance del incremento | ✓ | 18 archivos; `scope.stdout.json:1`: fuera de alcance, exclusiones de usuario, avisos e info vacíos. Las rutas corresponden a outbox y pruebas; docs/knowledge y ledger están cubiertos por `improvement-plan.md:171`. Inspeccionadas también declaraciones sin tocar. |
| Casillas hechas / tareas cerradas | ✓ continuidad | El diff cambia T-12 de borrador a en-progreso; no cierra tarea ni aceptación integral. No reabro aprobaciones T-10/T-11 sin evidencia nueva; su dictamen completo está preservado en `testing/fase-3/review/attempt-10-lens-a.md:5`. |
| GOT-003 y documentación descriptiva | ✓ | `docs/knowledge/gotchas/GOT-003-barreras-de-prueba-atomicas.md:5`, `:50`: propuesta, explicación causal y resultados históricos identificables; no se presenta como validación del bulk posterior. |
| CA-05/CA-06 y parte de ejecución de CA-22 | **No verificable; pendientes** | `spec.md:172`, `:173`, `:189`; publisher y materializador no son worker SMTP ni acreditan dos reservas llegando al transporte. |
| E2E-08/09, M-01, QA fase 3, T-13/T-15/T-16/T-19/T-20 | **No verificable; pendientes** | `test-plan.md:140`–`:155`; `tasks.md:2439`–`:2442`. Full/focal backend no se presentan como navegador, aceptación humana o validación completa de recuperación SMTP. |
| ADR-003 completo y promoción | **No verificable; propuesta conservada** | `docs/knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md:29`, `:113`; quedan consumidor, sending/ambiguo, cuotas, retries/fallos y scheduler. |
| Constitución | No aplica | No hay `docs/CONSTITUTION.md` en el contexto congelado. No invento principios adicionales. |
| Generación interop | No aplica | El diff no toca `commands/`, `agents/` ni `hooks/`; `app/Console/Commands/` es código de la aplicación. |
| Contrato OpenAPI | No aplica | Ninguna de las 18 rutas modificadas es una spec OpenAPI. |
| Cobertura y gates globales | ✓ límites conservados | No existe porcentaje de cobertura acreditado ni lo invento. Pint focal exit 0 no se convierte en Pint global ni en whitespace global verde (`tasks.md:2426`–`:2428`). |

**Gaps graduados:** ninguno nuevo. Los elementos «no verificable» describen obligaciones explícitamente pendientes; no los presento como pruebas aprobadas ni como tareas cerradas sin verificación.

**Cobertura de lectura e integridad:** leídos los 18 archivos cambiados completos y el contexto necesario de spec, plan, diseño, ledger, ADR-003, pruebas auxiliares y dictamen anterior. Procesado y reconstruido en memoria el patch completo: **1.845 líneas, 18/18 secciones**, todas reproducen las fuentes congeladas usando las dos preimágenes.

Hashes iniciales y finales conformes: **218 fuentes + 570 evidencias + 2 preimágenes + patch = 791 rutas únicas**, sin discrepancias. Los 18 cambios ya están incluidos en las 218 fuentes. Metadata verificada adicionalmente:

- Patch: `2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`.
- Metadata: `9087e3ab1482037f03bbd6d1a220b830139590f9e81530d0803daefba9f9bbc7`.

Verificados independientemente **90 receipts y sus streams**, **43 ZIPs y 1.392 entradas de manifest**, sin errores de integridad. El full final contiene **193 entradas: 154 fuentes y 39 JSON runtime**. De las fuentes del full, **138** se comparan directamente con `sources/`; las otras **16 resources/** se verifican mediante su contenido/hash dentro del ZIP permitido. La coincidencia live previa de las 154 queda documentada por root en `evidence/root-performance-frozen-source-continuity.json` y `evidence/root-performance-release-verification.json`; no atribuyo esas 16 rutas a `sources/` ni exijo hashes live a los JSON efímeros.

Solo realicé lecturas, hashes, parsing, comparación de ZIP/manifests y reconstrucción en memoria. No ejecuté tests, SQL, broker, servicios, red, Git ni escrituras.
