# Lente D — T-10, intento 1

Fecha: 2026-10-28. Base: `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`.

**Veredicto: sin hallazgos de rendimiento introducidos o reabiertos que sean reportables con la carga indicada. Critical: 0; Important: 0; Minor: 0.**

Revisión de contexto fresco mediante subagente genérico, siguiendo `custom-agents:adversarial-review` 2.0.1 y su prompt D. Alcance leído: diff completo `git diff <base>` (53 archivos seguidos), más `tests/support/DeliveryFixtureProtocol.php` y `tests/support/test_delivery_sql_proxy.py` no seguidos: 55 archivos. Se contrastaron T-10 y los fragmentos pertinentes de spec/plan/contrato, y llamantes enumerados abajo. No se modificaron fuentes, configuración, Git ni datos; la única escritura es este informe.

## Activación y carga

El selector facilitado `storage/worker-recovery-review-20261027/lenses-20261028.json` activa D, no C. Motivos por ruta: migración compartida, `MigrationReplayTest`, `WorkerRecoveryTest`, proxy SQL y su prueba Python. La activación no constituye por sí misma un defecto.

Dominio considerado: plataforma interna, 100–200 envíos diarios con crecimiento y 5–10 workers sobre broker compartido. El contrato conserva lotes de hasta 100 destinatarios y paginación acotada. No se presupone un SLA ni un volumen de miles de solicitudes concurrentes que no se haya declarado.

## Comprobaciones y evidencia

| Área | Resultado estático y magnitud considerada |
|---|---|
| Aceptación e idempotencia | `SendingApiService.php:43` sustituye el lookup bloqueante del recibo por lectura consistente bajo el mutex ya adquirido. `SendingPrincipal.php:27` y `DelegatedContext.php:49` conservan la adquisición de autoridad antes del servicio. No añade una espera externa ni un bloqueo de intervalo en el lookup ausente. Las carreras de `McpApiIdempotencyTest.php` conservan la prueba entre cuentas, pero no se ejecutaron en esta revisión. |
| Lotes y SQL | Los bucles de elegibilidad, expansión y tres inserciones por destinatario en `SendingApiService.php:61`, `:77` y `:88` ya existían en la base. Con 100 destinatarios siguen siendo aproximadamente 300 inserciones más la política por destinatario, compartidas por REST/MCP; el diff no introduce un segundo recorrido de persistencia ni un segundo trabajo por replay. No se registra ese coste heredado como una regresión nueva. |
| Estado | `SendingApiService.php:97` obtiene solicitud, población de entregas y destinatarios mediante consultas por solicitud antes de proyectar el recibo. Para 100 entregas no añade 100 consultas al proyectar estado. El nuevo adaptador usa este mismo servicio (`McpSendAdapter.php:52`). |
| Procedencia durante envío | `AccountMailTransport.php:120` y `MailMessage.php:17` añaden dos lecturas puntuales por identidad de grant al inicio de un intento MCP. Para 200 inicios son 400 lecturas adicionales, no una consulta por cada elemento de una lista histórica. Son un coste lineal pequeño para esta carga, sin evidencia de degradación material que justifique un gap. SMTP/almacenamiento siguen fuera de la transacción de inicio. |
| Inventario de remitentes | `McpSendAdapter.php:80–86` filtra cuenta/autorización y usa keyset por ID más `limit + 1`. Con límite 100 recupera hasta 101 candidatos, no todos los remitentes ni una consulta por DTO. La proyección no accede a relaciones. |
| Actividad y DTO | `ActivityService.php:95–97` y `:184–185` añaden joins por claves primarias/identidades únicas de grant, cliente y usuario. `BusinessDto.php:113` proyecta columnas ya recuperadas; no hace SQL por fila. La página web y el export por bloques de 200 conservan su mecanismo previo. Los nuevos joins no crean multiplicidad de filas por esas claves. Los índices de ventana y de procedencia existentes se comprobaron en las migraciones. |
| Esquemas y serialización | `ContractArguments.php:49–53` conserva compilación cacheada, ahora diferenciada por fragmento/raíz. La normalización adicional de envío recorre un mensaje acotado; no crece con el historial de envíos ni recompila el catálogo por destinatario. No se eleva una repetición constante a hallazgo sin demostrar impacto material. |
| Workers, broker y fixtures | El diff no cambia el consumidor ni el dispatcher de producción. Los waits, demoras deliberadas y capturas completas añadidos pertenecen a helpers de pruebas. `DeliveryFixtureProtocol.php:98–115` delimita/cancela su inventariador antes de `go`; el proxy tiene presupuesto explícito y distingue agotamiento de corte solicitado. No se atribuyen sus 100 s de demora deliberada, sus plazos de preparación ni las decenas de miles de archivos de inventario al throughput del envío de negocio. |

## Archivos del diff revisados

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
46. `tests/support/campaign-action.php`
47. `tests/support/delivery-action.php`
48. `tests/support/delivery-sql-proxy.py`
49. `tests/support/google-browser-fixtures.php`
50. `tests/support/mcp-api-concurrency.php`
51. `tests/support/outbox-action.php`
52. `tests/support/sending-api-action.php`
53. `tests/support/sending-api-deadlock.php`
54. `tests/support/DeliveryFixtureProtocol.php` (no seguido)
55. `tests/support/test_delivery_sql_proxy.py` (no seguido)

## Contexto adicional consultado

- Instrucciones: `adversarial-review/SKILL.md` y `references/lens-prompts.md` del plugin 2.0.1.
- Selector D facilitado, única lectura dentro de storage.
- `docs/roadmap/2026-10-24-mcp-google/spec.md` (criterios, límites y pipeline), secciones de plan/T-10 y `docs/api/mcp-tools.json` (búsqueda del contrato/límites).
- `app/Services/Mcp/DelegatedContext.php`, `app/Services/Mcp/DataPage.php`, `app/Http/Controllers/SendingApiController.php`, `app/Services/AccountKeyService.php`, `app/Services/ApiRecipientPolicy.php`, `app/Support/SendingApiInput.php`, `app/Services/DeliveryLifecycle.php` (llamantes, límites y escrituras pertinentes).
- Índices/FK: `2026_10_22_000004_create_mail_transport_tables.php`, `2026_10_22_000005_create_email_templates.php`, `2026_10_23_000009_create_delivery_outbox.php`, `2026_10_23_000010_add_delivery_reservations.php`, `2026_10_23_000011_add_activity_provenance.php`, `2026_10_24_000013_create_sending_api.php`, `2026_10_24_000014_index_historical_contact_email.php`, `2026_10_25_000003_create_mcp_authorization.php`, `2026_10_25_000004_create_mcp_oauth.php`, `2026_10_25_000005_create_mcp_sessions.php` (búsquedas de declaraciones de índices).
- `tests/support/runtime-inventory-capture.php` y `tests/support/runtime-source-inventory.php` (lectura de código para distinguir inventario y negocio; ninguno ejecutado).

## Límites

Revisión estática, sin ejecutar aplicación, tests, Docker, inventarios, consultas DB ni red; no se han inspeccionado procesos globales, credenciales, `.env`, configuración global, transcripciones ni requests/raw. Los tiempos, hashes y resultados citados por el ledger/checkpoint son declaraciones de esos documentos, no mediciones realizadas aquí. No se usan los focales verdes para dar QA ni para reemplazar el full rojo anterior. La ejecución activa de WorkerRecovery queda intacta.

No hay benchmark, plan `EXPLAIN`, perfil de memoria ni medición de contención con 5–10 workers de esta revisión. Este veredicto no certifica capacidad, SLA, ausencia de costes heredados ni la aceptación de T-10; la regresión completa y QA independiente conservan sus puertas. No se proponen microoptimizaciones ni refactors.
