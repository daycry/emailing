# Revisión adversarial — lente D — intento 1 de 3

**Veredicto: sin hallazgos de rendimiento.** Critical: 0; Important: 0; Minor: 0.

## Contrato y alcance real

Revisor independiente de contexto fresco; subagente genérico real heredado porque el agente nativo `reviewer` no está disponible. Se han leído `adversarial-review/SKILL.md` y `references/lens-prompts.md` de custom-agents 2.3.0. Lente D activada por las cuatro migraciones y el trabajo de callbacks/worker. Base recibida: `74970e0af4c5e68bd3b8e139a0988a119208493e`; comparación contra el árbol de trabajo, incluidos nuevos archivos públicos. Puerta de alcance exit 0 y universo de 110 rutas recibidos del orquestador.

La revisión cubre los cambios productivos en `app/`, `bootstrap/`, `config/`, `routes/`, `resources/` y las cuatro migraciones; sigue los llamantes de transporte, consumidor y ciclo de vida, las consultas nuevas, índices, mutex, leases, límites de red y proyecciones API/MCP/UI. Se han leído los tests pertinentes de lote, carrera y pipeline, los límites de entrada y los resúmenes públicos citados. La documentación/contratos y el resto de arneses se han considerado para delimitar rutas y carga; esta lente no emite conformidad documental ni sustituye la lectura semántica completa de cada artefacto de las lentes A/B.

Carga de referencia suministrada: 100–200 emails/día, crecimiento, 5–10 workers y RabbitMQ compartido en laboratorio sin HA. Se excluyen `.claude/**` y `tests/.runtime/**`. No se han leído secretos, cuerpos privados, PEM privados, ZIP ni las dos rutas prohibidas. No se han ejecutado SQL, E2E, proveedores reales ni pruebas adicionales; la única escritura de esta lente es este informe.

## Comprobaciones y evidencia

| Criterio D | Resultado | Evidencia y escenario analizado |
|---|---|---|
| N+1 en callbacks | ✓ Sin hallazgo | `app/Services/MailProviders/ProviderWebhookService.php:38` limita a 1000 eventos. Las consultas de mensajes y dedupe se hacen por conjuntos en líneas 61–66; los bucles 69 y 97 no ejecutan consultas por evento. Recepciones, auditoría, actualizaciones y supresiones se escriben por lote en líneas 91, 92, 105 y 110. Para un lote de 1000 eventos no se añaden 1000 SELECT/INSERT independientes. |
| Índices de correlación/dedupe | ✓ Sin hallazgo | `database/migrations/2026_10_30_000003_add_mail_provider_receipts_to_messages.php:16` crea índice único sobre `provider_reference`. La migración `000004_create_mail_provider_events.php:20` crea la unicidad `(account_id, provider, event_key)` usada por el lookup de dedupe. La migración `000001_create_mail_provider_connections.php:21` cubre `(account_id, provider)`. El índice previo de `suppressions` sobre cuenta/email respalda `insertOrIgnore`. No se observa un scan histórico nuevo obligado por falta de índice en estas búsquedas. No se ejecutó EXPLAIN. |
| Mutex de cuenta e I/O | ✓ Sin hallazgo | `app/Services/AccountMailTransport.php:75` resuelve/inicia duraderamente dentro de una transacción y devuelve el snapshot en línea 136; `perform` se llama después, en línea 158. El envío al proveedor de línea 192 sucede fuera de esa transacción. Con 5–10 workers, las llamadas lentas de red no mantienen el mutex de cuenta adquirido durante el inicio. El mutex del callback abarca autenticación y aplicación local del lote, sin red, con cuerpo y número de eventos acotados. |
| Espera/reintentos | ✓ Sin hallazgo | `app/Services/DeliveryLifecycle.php:172` finaliza con fencing; la rama retryable reprograma en SQL/outbox y respeta `Retry-After` mediante `available_at`, sin dormir al worker. Un 429 admite como máximo dos intentos incluso con `max_attempts=20`. No hay retry HTTP implícito ni failover en el adaptador nuevo. |
| Tiempo/memoria de API | ✓ Sin hallazgo | `app/Services/MailProviders/ProviderApiTransport.php:20` fija respuesta máxima de 65536 bytes y línea 22 petición máxima de 4194304. Las líneas 71–79 limitan tiempo total a 5 s, conexión a 2 s, impiden redirects y acotan el sink durante escritura. La lectura por chunks termina con el mismo límite. Los concatenados del cuerpo recibido y del CASE SQL están acotados a 64 KiB y 1000 eventos, respectivamente; no constituyen una degradación sustentada al volumen declarado. |
| Reserva y consumidor | ✓ Sin hallazgo | `app/Services/DeliveryLifecycle.php:28` mantiene reserva mínima de 15 s y `AccountMailTransport.php:154` la renueva antes de transporte; la API renueva también antes de su llamada de máximo 5 s. Se siguieron `DeliveryConsumer::process/run`, `SmtpConnector` y `ObservedSmtpTransport::executeCommand`; SMTP conserva la renovación por comando y timeout por operación. La naturaleza síncrona del transporte por mensaje está dentro del modelo existente de workers y no acredita por sí misma un cuello de botella para 100–200 mensajes/día. |
| Proyección de feedback y UI | ✓ Sin hallazgo | `app/Services/SendingApiService.php:108` obtiene en una consulta los mensajes actuales y usa un mapa dentro del bucle de entregas. Para los 100 destinatarios máximos admitidos por `SendingApiInput.php:45`, la ampliación añade una consulta por petición de estado, no 100. `app/Livewire/MailSettings.php:129` carga perfiles una vez; sus bucles tienen dos proveedores por dos canales. Los listados de remitentes/mensajes siguen paginados y los helpers de vista no consultan SQL por fila. Las proyecciones de `ActivityService` amplían columnas de joins existentes. |
| Operaciones síncronas en arneses | ✓ No son hotpath de producto | `tests/e2e/mail-providers.spec.mjs`, `tests/support/mail-provider-browser-*`, `mail-provider-wire-fixtures.php` y fixtures SMTP/HTTP contienen lecturas síncronas, bucles de servidor y esperas de coordinación deliberadas de pruebas. No se atribuyen al camino productivo ni se reportan como regresión de rendimiento. |

## Evidencia de ejecuciones previas consultada

No se ejecutaron tests durante esta revisión. Los siguientes resultados se atribuyen a sus recibos existentes, sin sumar suites solapadas ni convertirlos en medición de latencia productiva:

- `storage/providers-email-20261030/t05-webhooks-https-20261030T222451183039Z/test-summary.json`: exit 0, 5 casos, 79 aserciones, sin fallos/errores/omitidos. Incluye `test_sendgrid_batch_is_bounded_and_all_permanent_suppression_kinds_are_idempotent`. El test en `tests/Feature/MailProviderWebhookTest.php:140` manda 1000 eventos y exige como máximo 16 consultas en línea 157. Su escenario usa **un mensaje con 1000 identidades de evento**, no 1000 referencias distintas. El recibo público de la misma carpeta declara `terminal_exit: 0` y fuentes estables.
- `storage/providers-email-20261030/t06-pipeline-final-20261030T225357098163Z/test-summary.json`: exit 0, 6 casos, 213 aserciones, sin fallos/errores/omitidos; cubre reprogramación limitada, I/O fuera de transacción, feedback anticipado, pérdida de reserva y replay concurrente. El recibo público declara `terminal_exit: 0` y fuentes estables.
- `storage/providers-email-20261030/t06-unit-final-20261030T225839848131Z/test-summary.json`: **exit 1**, 85 casos, 2 fallos en `ProviderApiWireTest` (datasets #6 y #7). Se comunicó al orquestador y no se usa como evidencia verde ni se oculta bajo el veredicto de esta lente.

## Límites y salida estructurada

No hay benchmark propio de 5–10 workers, medición de percentiles, plan de ejecución SQL ni prueba de saturación de RabbitMQ compartido. La revisión estática y la prueba de recuento de consultas permiten descartar los N+1 y bloqueos de red bajo mutex examinados; no acreditan capacidad ilimitada, rendimiento con miles de mensajes diarios ni latencia real de proveedor. El coste del CASE para 1000 referencias distintas no se ha medido; sin degradación acreditada al volumen declarado no se eleva a hallazgo. No se inventa porcentaje de cobertura.

```json
{
  "lens": "D",
  "attempt": 1,
  "status": "complete",
  "verdict": "sin hallazgos",
  "gaps": [],
  "counts": {"Critical": 0, "Important": 0, "Minor": 0},
  "base": "74970e0af4c5e68bd3b8e139a0988a119208493e",
  "target": "worktree including public untracked files",
  "reviewer": "generic inherited fallback; native reviewer unavailable",
  "own_tests_executed": [],
  "evidence_warning": "Existing t06-unit-final summary is red: 2 ProviderApiWireTest failures",
  "report": "storage/providers-email-20261030/review-D1.md"
}
```
