**Lente B · revisión global 13 · T-13 · intento 1/3: sin defectos de corrección confirmados.**

**Critical 0 · Important 0 · Minor 0.** Fallback genérico y runtime heredado. No atribuyo el tier del plugin a un modelo ejecutado.

Rutas de código relativas a `storage/phase3-t13-review1/sources/`; evidencias relativas al snapshot. No ejecuté aplicación, pruebas, SQL, Docker, servicios ni operaciones de escritura.

| Criterio | Veredicto | Evidencia y escenario contrastado |
|---|---|---|
| Identidad y formato del trabajo | ✓ | `app/Services/DeliveryEnvelope.php:7`: límite de bytes/profundidad, versión, tipos, seis claves e identificadores. `DeliveryConsumer.php:15`: propiedades AMQP y `message_id` obligatorios. `WorkerRecoveryTest.php:637`: entradas inválidas se retiran sin transporte ni bucle de poison messages. |
| Procedencia SQL de la entrega | ✓ | `DeliveryLifecycle.php:36`: cuenta, entrega, generación y evento `delivery.ready` coinciden antes de reservar. No se confía en la cuenta aportada por el mensaje sin comprobar SQL. |
| Reserva exclusiva y deduplicación | ✓ | `DeliveryLifecycle.php:38`: mutex de cuenta y lock de entrega. `WorkerRecoveryTest.php:282`: dos consumidores Linux y copia duplicada terminan con un único comienzo, intento y `MailMessage`. |
| Fencing del comienzo durable | ✓ | `DeliveryLifecycle.php:124`: el propio UPDATE exige cuenta, generación, token, estado `reserved` y lease vigente. Un CAS perdido revierte cuota, capability y mensaje mediante `DeliveryLeaseLost`. |
| Heartbeat cercado | ✓ | `DeliveryLifecycle.php:61`: renovar no recupera una reserva vencida ni una generación sustituida. Regresión `WorkerRecoveryTest.php:304`. |
| Recuperación de `reserved` | ✓ | `DeliveryLifecycle.php:187`: reserva vencida crea continuación durable con nueva generación. La reserva anterior no consigue comenzar después de la recuperación. |
| Recuperación de `sending` | ✓ | `DeliveryLifecycle.php:192`: vencimiento termina en `ambiguous`; no crea otro evento de envío. `WorkerRecoveryTest.php:328`: el proceso antiguo sigue vivo y su resultado posterior no sobrescribe la ambigüedad. |
| Transporte fuera de transacciones exteriores | ✓ | `AccountMailTransport.php:43,52`: guard antes de writes/IO; transacción propia de comienzo y transporte posterior al commit. `WorkerRecoveryTest.php:410`: cero mensajes, cuotas y conexiones bajo transacción exterior. |
| Autorización y composición vigentes | ✓ | `DeliveryLifecycle.php:74`: campaña, revisión, destinatario, hash, revisión autorizada y estado se revalidan bajo el mutex. No encontré un camino nuevo que envíe un borrador o una fuente no autorizada. |
| Contacto original y consentimiento | ✓ | `DeliveryLifecycle.php:98`: exige el contacto original, su email y consentimiento trazable vigente. `WorkerRecoveryTest.php:711`: cambio de email, recreación y revocación terminan sin intento ni transporte. |
| Remitente y modo vigentes | ✓ | `DeliveryLifecycle.php:109`: remitente autorizado, identidad y snapshot coincidentes, modo permitido. Las pruebas cubren remitente deshabilitado y modificado. |
| Fecha y pausa | ✓ | `DeliveryLifecycle.php:83,93`: pausa/futuro producen continuación durable sin consumir intento. `WorkerRecoveryTest.php:488,711`: pausa, duplicado, reanudación real, fecha futura y borrador. |
| Supresión y orden respecto al comienzo | ✓ | `AccountMailTransport.php:57,64` y `SuppressionService.php:115`: mismo mutex de cuenta. `SuppressionStartTest.php:114` conserva la prueba de ausencia de locks SQL durante el IO y de bloqueo del comienzo posterior. |
| Posible aceptación SMTP | ✓ | `ObservedSmtpTransport.php:42`: marca incertidumbre **antes** de intentar escribir el terminador DATA. El fallo de escritura no queda clasificado automáticamente como rechazo seguro. |
| Negativos y fallos previos | ✓ | `ObservedSmtpTransport.php:61` y `SmtpConnector.php:41`: latch de negativo de comandos del mensaje; fallo demostrado antes del terminador distingue retry seguro. `WorkerRecoveryTest.php:354,606`. |
| Recuperación EHLO/HELO | ✓ | Los fallos de negociación no se convierten en negativos latched del mensaje. El escenario `fallback_lost` observa EHLO rechazado, aceptación de DATA y respuesta final perdida; termina ambiguo. |
| Limpieza posterior a aceptación | ✓ | `SmtpConnector.php:38,50`: aceptación ya observada prevalece frente a errores posteriores de limpieza. `WorkerRecoveryTest.php:627` conserva aceptación tras pérdida en QUIT. |
| Resultado durable antes del ack | ✓ | `DeliveryConsumer.php:24`: exige `readyToSettle()` antes del ack. `DeliveryLifecycle.php:132`: resultado de mensaje y entrega/continuación se persisten conjuntamente. |
| Crash y pérdida SQL tras aceptación | ✓ | `WorkerRecoveryTest.php:433,460`: KILL tras `transport_result` y tras `durable_result`, y corte real mediante proxy SQL. Reentrega conserva respectivamente ambigüedad o aceptación sin otro transporte. |
| Retry acotado y outbox de fallo | ✓ | `DeliveryLifecycle.php:143`: backoff acotado, identidad estable y nueva generación; agotamiento persiste `failed` y `delivery.failed` en la misma transacción. Prueba de tres rechazos y recuperación de publicación del fallo en `WorkerRecoveryTest.php:354`. |
| Cola de fallos sin reenvío automático | ✓ | `ConfirmedDeliveryPublisher.php:39,60`: destino explícito de fallo; el consumidor solo acepta `delivery.ready`. El replay terminal no añade cuarto intento. |
| Colisión entre rutas y colas | ✓ | Guards en `ConfirmedDeliveryPublisher.php:21,24` y `DeliveryConsumer.php:40,43`. `WorkerRecoveryTest.php:807` comprueba cero conexiones ante ambas colisiones y recuperación del mismo pendiente tras corregir configuración. |
| Cuotas compartidas y atómicas | ✓ | `DeliveryQuota.php:20,27,45`: cuenta y pool del servidor, orden estable de locks y cargo conjunto. `WorkerRecoveryTest.php:535,568`: competencia entre cuentas y autoridad común entre envío legacy y worker. |
| UTC y espera que cruza ventana | ✓ | `DeliveryQuota.php:18,36`: reloj SQL y revalidación tras adquirir locks. `WorkerRecoveryTest.php:666,876`: sesión `+05:30` y lock real cruzando minuto; cero cuota e intentos antes de continuar. |
| CLI, prefetch y señales | ✓ | `DeliveryConsumer.php:55,66`: prefetch acotado, consumo continuo y ack manual. `WorkDeliveries.php:26`: closure observa el estado actualizado de parada. Prueba real SIGTERM en `WorkerRecoveryTest.php:751`. |
| Timeout y lease | ✓ | `SmtpConnector.php:29`: operación SMTP acotada a cinco segundos frente al mínimo lease de quince. La prueba `WorkerRecoveryTest.php:780` mide la fase final SMTP separadamente del cierre y de la duración del proceso. |
| Compatibilidad legacy | ✓ | `send()` mantiene firma y retorno; conserva `findOrFail` para remitente ajeno. Leídos los consumidores de `MailSettingsService` y `TemplateService`; sus llamadas quedan fuera de transacciones. Las tres suites adaptan aislamiento a `DatabaseMigrations`, sin retirar el guard del transporte. |
| Migración incremental | ✓ | Migración `000010:11`; `OutboxIntegrationTest.php:595` baja/recrea las migraciones respetando dependencias y comprueba índice, lease, intentos y snapshots. No encontré evidencia de defecto de la migración productiva. |
| Limpieza causal de campaña | ✓ | `ConcurrentCampaignTest.php:102,134,160`: retry acotado de eliminación, preservación del error primario y asentamiento independiente. `campaign-action.php:10`: marcadores completos mediante rename. No se presenta el diagnóstico Windows como identificación del actor histórico. |
| Compose y trabajos Laravel | ✓ alcance de código | `compose.yaml:35`: consumidor de dominio separado de `emailing-jobs`. La configuración documentada separa ambas familias de colas. Esto no acredita despliegue o salud del nuevo Compose. |

**Integraciones pendientes de revisiones anteriores**

| Criterio | Evaluación con evidencia nueva |
|---|---|
| T-10: baja y aislamiento entre cuentas | El consumidor real queda probado en `WorkerRecoveryTest.php:513`: mismo email, una cuenta suprimida y otra aceptada, cero intentos en la primera y remitente correcto en la segunda. Precisión sobre el orden: entrega/outbox ya están materializados antes de la baja pública; la publicación AMQP se hace después. Acredita baja después de crear trabajo durable y antes del transporte. No afirmo que ese método pruebe una baja posterior a la publicación AMQP. |
| T-11: borradores/futuras sin transporte prematuro | Materializador conserva el filtro previo (`DeliveryMaterializer.php:22`) y el consumidor vuelve a comprobar estado/fecha (`DeliveryLifecycle.php:88,93`). `WorkerRecoveryTest.php:711` ejecuta consumidores reales con cambio a borrador/futuro: cero transporte, cuotas e intentos. |
| T-12: publicación repetida converge | Las pruebas anteriores de publicación/reconciliación se repiten en el full final. La nueva prueba de dos consumidores con evento duplicado (`WorkerRecoveryTest.php:282`) termina con un único comienzo SMTP, intento y mensaje. Se conserva la separación entre demostrar publicación reconciliable y demostrar deduplicación del transporte. |
| Criterios aprobados en revisiones 10/12 | Conservados. Leídos ambos dictámenes agregados y sus informes B. No encontré evidencia nueva para reabrir B-01 de selección eliminada ni D-01 de claim global. |
| E2E-08/09, M-01 y QA de fase 3 | No inferidos de estas pruebas backend. |
| T-14–T-20 y ADR-003 | No exigí implementar tareas posteriores. ADR-003 conserva estado de propuesta. Sin claim de exactly-once SMTP ni cobertura porcentual inventada. |

**Comprobación directa de las evidencias finales**

Verifiqué los cinco `final_gate_names`: exit 0, hashes de stdout/stderr y manifiestos correctos. Cada ZIP contiene **185 entradas**, sin duplicados ni discrepancias; **184 entradas comparables** coinciden exactamente con `sources/`. La entrada restante es el runner privado.

El stdout original del full final registra **183 passed / 2227 assertions**, incluidos **21 WorkerRecovery**, **20 OutboxIntegration** y **5 ConcurrentCampaign**. No uso los verdes anteriores como gates de las fuentes finales.

Procesé los originales de los **21 protocolos runtime asociados al full**: hashes de clases `loaded-*` coincidentes, plataformas Linux en los consumidores, estados/intentos SQL y etapas del relay coherentes, y limpieza registrada en todos. En particular:

- Crash causal: dos procesos con exit 137, marcadores de aceptación en las ventanas previstas y reentregas sin nuevo SMTP.
- Corte SQL: aceptación final observada, cierre del proxy, worker exit 42 y recuperación ambigua con un solo transporte.
- Frontera de cuota: lock del bucket antiguo, observación previa al cambio de minuto, liberación en la frontera, cargo cero e intento cero, seguido de continuación.
- Baja entre cuentas: una entrega suprimida y otra aceptada, un único comienzo SMTP.
- Rechazos: tres intentos, cero DATA aceptados y entrega final `failed`.

Los recibos infantiles que no contienen hashes originales de streams mantienen esa limitación: el inventario congelado acredita integridad de sus bytes, sin inventar una certificación original. El censo final es una observación fechada, no una consulta actual de liveness.

**Integridad y cobertura real**

- Patch completo procesado y reconstruido en memoria: **41 secciones / 56 hunks**, **41/41 resultados coincidentes** tras normalización textual de EOL.
- Verificaciones iniciales y finales: **417 fuentes, 23 preimágenes, 6455 evidencias y patch**, **6896 comprobaciones**, cero discrepancias.
- Patch SHA256: `97d29208f5c7fd5f57683343746d0b61b78e74728e0e084a186c39eed8410c0e`.
- Metadata SHA256 final: `909d2afd2f3dbb6f85e1cfce91cb0a3eec18cf1a78e4c0ae57f6e3507391e29a`.

La lectura semántica integral se centró en todo el núcleo nuevo de producción, los 21 métodos y arnés de WorkerRecovery, fixtures SMTP/SQL, cambios de pruebas concurrentes/supresión, hunks de compatibilidad y migración, contrato T-10–T-13/ADR, documentación modificada pertinente, handoff y protocolos finales. El contexto histórico extenso del ledger y los cuerpos de pruebas legacy sin cambios no recibieron una nueva auditoría semántica exhaustiva. **No equiparo los 6896 hashes con 6896 archivos auditados semánticamente.**

No hay defectos graduados que corregir desde esta lente. Root conserva la decisión de cierre formal y las puertas de QA.
