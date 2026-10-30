# Lente A — T-10 — intento 1

Revisión estática de requisitos contra `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`: `git diff <base>` completo (commits y cambios locales), más `tests/support/DeliveryFixtureProtocol.php` y `tests/support/test_delivery_sql_proxy.py`, no seguidos en Git. Cobertura: 55 archivos. Subagente genérico con contexto fresco; se aplicó el prompt A de `custom-agents:adversarial-review` 2.0.1.

**Resultado: sin gaps de requisitos confirmados. Critical 0 / Important 0 / Minor 0.** Esto no acepta T-10 ni da QA verde. La conformidad de implementación se juzga por código y tests leídos; la validación final del árbol actual continúa pendiente.

## Criterios de aceptación de T-10

En esta tabla ✓ significa conformidad estática de lo implementado y presencia de un oráculo pertinente; no significa ejecución por este revisor. Los cinco criterios permanecen sin aceptar en `tasks.md:499`–`503`, coherentemente con el estado `en-progreso` de `tasks.md:484`.

| Criterio | Veredicto | Evidencia y escenario revisado |
| --- | --- | --- |
| T-10.1: 37 operaciones reales, información de conexión más 36 de negocio | ✓ | `app/Services/Mcp/DataTools.php:8` registra las tres operaciones restantes; `app/Services/Mcp/DataOperations.php:19` las dirige al adaptador. `tests/Feature/McpSendTest.php:52` compara los 37 nombres y cada esquema/anotación con el catálogo fijado, no sólo el número. `McpTransportTest.php:45`, `McpConsentToolsTest.php:55`, `McpDataToolsTest.php:146` actualizan discovery sin eliminar los negativos. `McpConnectionInfoTest.php:20`, `:63`, `:97` prueban proyección por cuenta, cursor ligado al grant, scopes efectivos, expiración y revocación. |
| T-10.2: mismas modalidades y destinatarios que T-17/T-18, con templates.read condicional | ✓ | `app/Services/Mcp/McpSendAdapter.php:20` y `:39` llaman a `SendingApiInput::send`, igual que `app/Http/Controllers/SendingApiController.php:48`; no se crea otra política. `app/Services/SendingPrincipal.php:49` exige templates.read para plantilla antes de buscar un replay. `app/Services/SendingApiService.php:54` conserva snapshot/renderizado y validación de valores expandidos. `tests/Unit/McpMailWireTest.php:18`, `tests/Unit/McpMailCanonicalInputTest.php:45`, `tests/Feature/McpSendTest.php:172`, `:455`, `:493`, `:577` cubren ramas, IDs exactos, normalización compartida, duplicados, contenido literal, lotes100 y orden de destinatarios. |
| T-10.3: namespace idempotente común por cuenta, conflicto de payload y aislamiento de otra cuenta | ✓ | `app/Services/SendingApiService.php:24` y `:29` convergen en `acceptSource` (`:35`), misma cuenta/hash/fingerprint y recibo original (`:43`); `SendingPrincipal.php:24`/`:33` revalida la autoridad bajo el mutex de cuenta antes del replay. Llamantes seguidos: `DataTool.php:59` abre la transacción y `DelegatedContext.php:47` mantiene el mutex; REST pasa por `AccountKeyService.php:47`/`:79`. El lookup consistente no se usa sin esa autoridad. `tests/Feature/McpApiIdempotencyTest.php:34`, `:39`, `:44`, `:59`, `:100` ejercitan ambas órdenes, conflictos y otra cuenta con barreras reales; `:194` comprueba que el waiter no termina antes de liberar y cuenta trabajo. `McpSendTest.php:87`, `:135`, `:158`, `:279` conservan recibo/procedencia y ausencia de trabajo parcial. |
| T-10.4: mail.send no inventa consentimiento; controles vigentes, cuotas y supresiones antes de SMTP | ✓ | `SendingApiService.php:64` reutiliza `ApiRecipientPolicy::eligible` (`app/Services/ApiRecipientPolicy.php:12`), incluida la política existente de destinatarios directos de T-17, sin crear contactos concedidos. `AccountMailTransport.php:70` toma el mutex, `:79` llama al guard común `DeliveryLifecycle::beforeStart`, `:89` consume cuota; `DeliveryLifecycle.php:127` revalida fuente, identidad/consentimiento ligado al destinatario, supresión, snapshot y autorización actual del remitente/transporte. `McpSendTest.php:189`, `:353`, `:370` prueban supresión/consentimiento/remitente posteriores a aceptación sin comienzo de transporte; `McpPipelineTest.php:99` cubre cuota y supresión tardía a través del broker. Revocar un grant corta llamadas nuevas/replays (`McpSendTest.php:328`, `McpApiIdempotencyTest.php:73`, `:114`); el trabajo ya aceptado mantiene el contrato común, no se reinventa como una autorización pendiente del token. |
| T-10.5: aceptación, publicación, SMTP e incertidumbre se distinguen; sólo Mailpit/log | ✓ | El recibo `accepted` se crea en SQL/outbox en `SendingApiService.php:86`; `:97` devuelve estados observados y `delivery_feedback=not_available`, sin prometer entrega. `tests/Feature/McpPipelineTest.php:99` comprueba outbox pending, luego published y published_at, mientras la consulta sigue pending y smtp_accepted=0 antes del consumer; después distingue logged/smtp_accepted/ambiguous y redelivery sin otro intento. `:181` comprueba exactamente un mensaje Mailpit atribuido. Es la separación semántica del contrato heredado (`docs/api/openapi.yaml:23`, `docs/api/decisiones.md:100`), no se exige inventar un nuevo campo published en el DTO fijado. `AccountMailTransport.php:83` permite log/mailpit y `:181` utiliza únicamente el conector Mailpit; `DeliveryLifecycle.php:164` conserva ambiguous sin reenvío automático. Los tres escenarios originales de WorkerRecovery mantienen sus aserciones de dominio (`WorkerRecoveryTest.php:646`, `:985`, `:1054`); su ejecución actual no se da por terminada. |

## Subtareas y controles de la Lente A

| Control | Veredicto | Evidencia |
| --- | --- | --- |
| Reutilización de T-18 sin campañas/contactos ficticios ni segundo SMTP | ✓ | `McpSendAdapter.php:36` utiliza `SendingApiService`; éste crea solicitudes/destinatarios/deliveries/outbox (`SendingApiService.php:87`), y nada ejecuta transporte en el adaptador. `McpSendTest.php:87` exige campaigns=0, contacts=0 y mail_messages=0 tras aceptación/replays. El único inicio de transporte sigue `AccountMailTransport::sendDelivery` (`:41`). |
| Correlación cliente/grant/request/outbox/auditoría sin token | ✓ | `SendingPrincipal.php:42` persiste sólo la procedencia verificada; `McpSendAdapter.php:40` asocia request y `:43` registra request/replay/provenance. `database/migrations/2026_10_26_000007_share_transactional_mail_origins.php:17` y `:31` fijan ramas/FKs compuestas; `AccountMailTransport.php:120` conserva grant/cliente/actor. `ActivityService.php:94` y `:183`, `BusinessDto.php:113`, vista `resources/views/livewire/activity.blade.php:45` proyectan esa misma procedencia. `McpSendTest.php:114`, `:221`, `:253`, `:384`, `:522` comprueban cliente largo, borrado de actor/pruning, ramas SQL, actividad/UI/CSV y ausencia de bearer/key en CSV. |
| Concurrencia real y conteos atribuidos tienen tests | ✓ | `McpApiIdempotencyTest.php:194` conserva kernels HTTP separados y barreras MySQL; `McpPipelineTest.php:181` atribuye búsqueda/validación/borrado sólo al mensaje sintético propio. La existencia de esos tests no se convierte aquí en aceptación de sus ejecuciones actuales. |
| Nada fuera del plan/Archivos; criterios conservados | ✓ | Scope aportado `storage/worker-recovery-review-20261027/scope-20261028.json`: 55 cambiados, fuera_de_alcance=[], criterios_alterados=[], avisos=[], info=[], excluidos_usuario={}. Contrastado con `tasks.md:493`: los adaptadores, autoridad, migración, actividad y fixtures se corresponden con T-10 y sus regresiones comunes; los globs de .gitignore sólo añaden artefactos locales del plugin. |
| Cada tarea marcada completada en este incremento cumple y tiene evidencia de Verificación | ✓ | El incremento no marca T-10 completada ni añade una tarea completada nueva. Mantiene nueve aceptadas y T-10 en progreso (`tasks.md:35`, `:484`; `improvement-plan.md:50`). El checkpoint conserva full477 rojo y acepta sólo los focales con su identidad histórica (`testing/fase-4/t10/checkpoint-2026-10-27.md:11` y JSON homónimo). No se exige fabricar una ejecución verde de una tarea aún abierta. |
| Los criterios con test tienen un oráculo pertinente | ✓ | Tabla anterior y E2E-09 `tests/e2e/mcp-google.spec.mjs:570`; trazabilidad explícita en `test-plan.md:135`. Se leen aserciones de estado/trabajo/autoridad, no se infiere cobertura de los contadores. No se afirma porcentaje de cobertura: el checkpoint lo deja null. |
| Constitución explícita | ✓ (no aplica) | `docs/CONSTITUTION.md` no existe. No se inventan principios adicionales. |
| Generados/interop y contrato OpenAPI | ✓ (no aplica) | Ningún cambio afecta commands/, agents/ o hooks/; no aplica export-interop. Ninguna spec OpenAPI cambia en este diff; no aplica openapi-lint --diff. Los contratos fijados se consumen y se comparan desde tests. No se ejecuta ningún script por la restricción de revisión estática. |
| Puertas relajadas sin justificación | ✓ | No se eliminan ni saltan tests ni se reduce cobertura o aserciones del dominio. Las nuevas expectativas37/connection_info corresponden al catálogo exigido. La comparación canónica del objeto recibo ordena sólo claves superiores y mantiene tipos/valores/orden de delivery_ids (`McpSendTest.php:625`, `McpApiIdempotencyTest.php:162`), documentada en `tasks.md:523`. Los presupuestos ampliados son preparación/inventario separados de acciones, con negativos nuevos; no cambian timeout SMTP, lease, cuota, publicación ni el resultado exigido del dominio. Véanse `ConcurrentCampaignTest.php:24`, `ApiProcessProtocol.php:16`, `OutboxIntegrationTest.php:738`, `DeliveryFixtureProtocol.php:12`, y notas `tasks.md:554`, `:562`, `:572`, `:579`. |
| Evidencia final suficiente para cerrar T-10 ahora | ✗ (pendiente, no gap nuevo) | No está reclamada. `focus-summary.json` permitido sólo acredita dos negativos/27 aserciones, native_exit0, inventory_valid=true, full_suite_executed=false y task_accepted=false. Los originales y preparación lenta están en ejecución según el llamante. Full Unit+Feature final y QA independiente siguen pendientes; no sustituyo esas puertas con esta lectura. |

## Fixtures de recuperación incluidos en el examen

Leídos los cambios completos de campaña, API, outbox y delivery; se mantienen los casos originales y se añaden oráculos de preparación tardía, cancelación sin abrir go/dispatch, acción caducada, parada de procesos propios y ausencia de efectos de negocio. `DeliveryFixtureProtocol.php:93` captura el mismo reporter completo en un Process propio, comprueba cancelación/plazo y conserva recibo terminal; `:149` condiciona el inicio al permiso ligado al fixture. `delivery-action.php:29` sale con43 durante preparación fallida y con44 si se rechaza el inicio. `WorkerRecoveryTest.php:1101` y `:1121` exigen intentos0, lease nula y mail_messages0; los dos negativos tienen la evidencia limitada indicada arriba.

`delivery-sql-proxy.py:11` sustituye los plazos implícitos por lifetime finito y un motivo de cierre; sólo requested termina con éxito. `test_delivery_sql_proxy.py:74`–`:94` cubre ida/vuelta, corte antes de conexión y agotamiento antes/después de conexión. `WorkerRecoveryTest.php:666` exige requested antes de continuar el escenario de pérdida SQL. No se confunde agotamiento del fixture con el corte causal requerido tras250.

Las modificaciones del replay histórico incluyen la migración aditiva y restauran el conflicto deliberado únicamente después de preservar la evidencia del fallo (`MigrationReplayTest.php:114`); no se elimina la comprobación de que una dependencia rota detiene las migraciones posteriores. La migración rechaza el downgrade con trabajo MCP antes de DDL (`2026_10_26_000007_share_transactional_mail_origins.php:44`; `McpSendTest.php:553`). La guía describe el despliegue coordinado y ese límite (`docs/aplicacion/mcp-contrato.md:128`).

## Gaps graduados

Sin gaps de requisitos confirmados. No hay escenario reproducible de incumplimiento que justifique Critical, Important o Minor desde esta lente. Los resultados finales pendientes se conservan como límites de evidencia de una tarea abierta, no como una supuesta aceptación verde.

## Archivos cubiertos (55/55)

Cada ruta siguiente fue recorrida en el diff completo; los dos no seguidos se leyeron completos. No se extrapola la cobertura desde una muestra.

1. `.gitignore`
2. `app/Http/Controllers/McpTransportController.php`
3. `app/Models/ApiMailRequest.php`
4. `app/Models/MailMessage.php`
5. `app/Services/AccountMailTransport.php`
6. `app/Services/ActivityService.php`
7. `app/Services/Mcp/BusinessDto.php`
8. `app/Services/Mcp/ContractArguments.php`
9. `app/Services/Mcp/DataOperations.php`
10. `app/Services/Mcp/DataTools.php`
11. `app/Services/Mcp/McpSendAdapter.php`
12. `app/Services/Mcp/Tools/ConnectionInfo.php`
13. `app/Services/Mcp/Tools/DataTool.php`
14. `app/Services/Mcp/Tools/GetMailRequest.php`
15. `app/Services/Mcp/Tools/SendMail.php`
16. `app/Services/Mcp/WireJson.php`
17. `app/Services/SendingApiService.php`
18. `app/Services/SendingPrincipal.php`
19. `database/migrations/2026_10_26_000007_share_transactional_mail_origins.php`
20. `docs/aplicacion/mcp-contrato.md`
21. `docs/roadmap/2026-10-24-mcp-google/improvement-plan.md`
22. `docs/roadmap/2026-10-24-mcp-google/tasks.md`
23. `docs/roadmap/2026-10-24-mcp-google/test-plan.md`
24. `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/checkpoint-2026-10-27.json`
25. `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/checkpoint-2026-10-27.md`
26. `docs/roadmap/README.md`
27. `resources/views/livewire/activity.blade.php`
28. `tests/Concerns/ExercisesSendingApi.php`
29. `tests/Concerns/ExercisesSendingApiProcesses.php`
30. `tests/Feature/ConcurrentCampaignTest.php`
31. `tests/Feature/McpApiIdempotencyTest.php`
32. `tests/Feature/McpConnectionInfoTest.php`
33. `tests/Feature/McpConsentToolsTest.php`
34. `tests/Feature/McpDataToolsTest.php`
35. `tests/Feature/McpPipelineTest.php`
36. `tests/Feature/McpSendTest.php`
37. `tests/Feature/McpTransportTest.php`
38. `tests/Feature/MigrationReplayTest.php`
39. `tests/Feature/OutboxIntegrationTest.php`
40. `tests/Feature/SendingApiTest.php`
41. `tests/Feature/WorkerRecoveryTest.php`
42. `tests/Unit/McpMailCanonicalInputTest.php`
43. `tests/Unit/McpMailWireTest.php`
44. `tests/e2e/mcp-google.spec.mjs`
45. `tests/support/ApiProcessProtocol.php`
46. `tests/support/DeliveryFixtureProtocol.php` (no seguido)
47. `tests/support/campaign-action.php`
48. `tests/support/delivery-action.php`
49. `tests/support/delivery-sql-proxy.py`
50. `tests/support/google-browser-fixtures.php`
51. `tests/support/mcp-api-concurrency.php`
52. `tests/support/outbox-action.php`
53. `tests/support/sending-api-action.php`
54. `tests/support/sending-api-deadlock.php`
55. `tests/support/test_delivery_sql_proxy.py` (no seguido)

## Límites

- No ejecuté aplicación, tests, Docker, red, DB ni scripts de verificación; tampoco inventario de procesos, inspección de PIDs o señales. Sólo comandos de lectura de fuentes, diff y las dos evidencias JSON permitidas.
- No leí .env, configuración global, transcripciones o cuerpos raw. No recorrí storage ni abrí sus otros artefactos. La única escritura es este informe.
- El checkpoint y las notas del ledger se leen como atribución histórica. No se inspeccionan los originales privados fuera del permiso ni se certifica de nuevo su integridad. No se presenta el antiguo full477 ni los focales como suite actual verde.
- No acredité la preparación lenta ni los tres originales actualmente ejecutándose; sus resultados pueden completar la evidencia después de este informe. No se tocó ninguna fuente durante esa captura.
- Google real, clientes reales, perfiles de despliegue, manuales, carga/restauración global y privacidad histórica conservan sus tareas posteriores. Esta revisión no los cierra, ni acepta T-10 ni QA.
