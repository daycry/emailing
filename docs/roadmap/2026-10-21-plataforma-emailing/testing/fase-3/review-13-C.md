Lente C · revisión global 13 · incremento T-13 · intento acotado 1/3. Fallback genérico, runtime heredado; el tier del plugin no acredita un modelo Claude.

**Sin hallazgos de seguridad introducidos o reabiertos. Critical: 0 / Important: 0 / Minor: 0.** No identifico un escenario de explotación concreto que justifique un defecto o CWE reportable.

Las rutas de código siguientes son relativas a `storage/phase3-t13-review1/sources/`; `evidence/...` es relativo al snapshot. Revisión limitada al incremento: conservo los veredictos anteriores de T-10/T-11/T-12 y no reabro sus componentes aprobados sin evidencia nueva.

| Criterio examinado | Veredicto | Evidencia y escenario contrastado | Gravedad |
|---|---|---|---|
| Payload no confiable; deserialización | ✓ | `DeliveryEnvelope.php:9–24` limita bytes, profundidad, estructura, versión, tipos, generación y UUID. `DeliveryConsumer.php:15–19` exige propiedades AMQP coherentes y rechaza entradas inválidas. No incorpora deserialización PHP. `WorkerRecoveryTest.php:637–663` incluye mensajes reales inválidos sin transporte ni bucle de reentrega. | — |
| Identidad y aislamiento entre cuentas | ✓ | `DeliveryLifecycle.php:38–47` exige correspondencia SQL de cuenta, entrega, evento, generación y tipo. `AccountMailTransport.php:36–40` reconstruye revisión y destinatario dentro de esa cuenta. Alterar `account_id` en el mensaje no proporciona autoridad sobre una entrega ajena. | — |
| Autorización y políticas vigentes antes del comienzo | ✓ | `DeliveryLifecycle.php:74–116` comprueba campaña, revisión autorizada, hash de origen, estado, fecha, contacto original, consentimiento, remitente y modo permitido. `WorkerRecoveryTest.php:711–748` ejecuta las variantes con workers reales y cero comienzos SMTP. | — |
| Supresión posterior al encolado y aislamiento A/B | ✓ nueva evidencia integral | `AccountMailTransport.php:57–65` comparte el mutex de cuenta con `SuppressionService.php:114–120`. `WorkerRecoveryTest.php:513–532` utiliza la baja pública después de materializar: A termina suprimida sin intento; B conserva su envío con su propio remitente. | — |
| Fencing del comienzo durable | ✓ | `DeliveryLifecycle.php:58`, `:63`, `:124–129` cercan por cuenta, entrega, generación, token, estado y vencimiento. El CAS fallido revierte cuota, capability y mensaje antes de IO. `WorkerRecoveryTest.php:304–325` mantiene vivo al propietario antiguo y comprueba que solo el nuevo llega al transporte. | — |
| Transporte fuera de transacción exterior | ✓ | `AccountMailTransport.php:43–56`, `:118–131` exige una transacción independiente y realiza IO después del commit. `WorkerRecoveryTest.php:410–429` rechaza la transacción exterior sin mensaje, cuota ni conexión. | — |
| Dos consumidores y duplicados | ✓ nueva evidencia de consumidor | `DeliveryConsumer.php:21–32` y `DeliveryLifecycle.php:38–50` reservan mediante SQL. `WorkerRecoveryTest.php:282–301` ejecuta dos consumidores Linux con dos copias y observa un único comienzo e intento SMTP. | — |
| Reserva vencida frente a envío iniciado | ✓ | `DeliveryLifecycle.php:187–199` recupera `reserved` con nueva generación y clasifica `sending` vencido como ambiguo. Las pruebas `:304` y `:328` conservan vivo al proceso antiguo; no habilitan otro envío después de comenzar. | — |
| Posible aceptación SMTP y limpieza | ✓ | `ObservedSmtpTransport.php:42–64` marca incertidumbre antes de escribir el terminador, distingue respuestas negativas y conserva el primer fallo del mensaje. `SmtpConnector.php:37–56` conserva aceptación observada y depura excepciones. Pruebas `WorkerRecoveryTest.php:606–634` cubren desconexión previa, recuperación EHLO/HELO y pérdida durante QUIT. | — |
| Crash, pérdida de SQL y ack | ✓ | `AccountMailTransport.php:132–135`, `DeliveryLifecycle.php:134–157`, `DeliveryConsumer.php:25–32` exigen resultado durable antes de asentamiento. `WorkerRecoveryTest.php:433–485` dispone de KILL reales y corte de conexión SQL posterior al 250; la recuperación conserva aceptación o ambigüedad sin reenvío. | — |
| Reintentos y fallo durable | ✓ | `DeliveryLifecycle.php:143–168` limita intentos y persiste nueva generación/outbox o fallo/outbox dentro de la transacción. `ConfirmedDeliveryPublisher.php:39–41`, `:60` utiliza la cola de fallos separada. `WorkerRecoveryTest.php:354–392` comprueba backoff, agotamiento, publicación recuperable y replay terminal sin SMTP adicional. | — |
| Pausa y fecha efectiva | ✓ | `DeliveryLifecycle.php:83–96` aplaza pausas y campañas futuras. `WorkerRecoveryTest.php:488–510` acredita pausa, duplicado y reanudación reales sin inflar intentos durante la espera. | — |
| Cuotas por cuenta y pool del servidor | ✓ | `DeliveryQuota.php:14–46` valida el pool, adquiere locks ordenados y revalida la época después de esperarlos. `WorkerRecoveryTest.php:535`, `:568`, `:666`, `:876` cubre competencia A/B, envío directo/worker, sesión no UTC y cruce real del minuto. El cliente no elige el pool. | — |
| Inyección SQL, comando o cabeceras | ✓ | Las consultas nuevas utilizan parámetros del ORM; los intervalos SQL se construyen con enteros acotados (`DeliveryLifecycle.php:25–27`, `:182–184`). `MailInput.php:9–24` rechaza CR/LF y limita los campos; el transporte construye objetos Mime. No aparece un ejecutor de comandos del producto controlable por el destinatario o payload. | — |
| SSRF y activación de SMTP externo | ✓ | `AccountMailTransport.php:69`, `:113–116` del lifecycle y `:164` del transporte limitan el recorrido a log/Mailpit y endpoint del servidor. El incremento no convierte credenciales SMTP de cuenta en un endpoint libre para el consumidor. | — |
| Artefactos privados y traversal | ✓ | `AccountMailTransport.php:147–153` construye el nombre con cuenta y UUID/generación persistidos; el envelope exige UUID. No añade rutas de descarga ni parámetros de path HTTP. | — |
| Secretos, diagnósticos y TLS | ✓ | `.env.example` añade parámetros sin secretos. `SmtpConnector.php:31` conserva verificación TLS; `:48–56`, `WorkDeliveries.php:32–36` y `ConfirmedDeliveryPublisher.php:84–90` evitan propagar errores crudos. Los observadores nuevos reciben etapas, códigos y tiempos, sin comandos, AUTH o cuerpos. | — |
| Separación de consumidores y cola de fallos | ✓ | `DeliveryConsumer.php:40–44`, `ConfirmedDeliveryPublisher.php:21–25` rechazan colisiones; `compose.yaml:34–43` separa dominio y jobs. `WorkerRecoveryTest.php:807–873` acredita rechazo previo a conexiones y recuperación tras corregir configuración. | — |
| CLI, prefetch, señales y timeout/lease | ✓ alcance comprobado | `DeliveryConsumer.php:55–75` limita prefetch y consume continuamente; `WorkDeliveries.php:16–27` observa las señales por referencia. Pruebas `:751–804` ejecutan CLI Linux y miden el comando SMTP por separado de limpieza/SQL. Esto no acredita el despliegue completo del nuevo Compose. | — |

**Integraciones pendientes de T-10/T-11/T-12 reevaluadas**

- **Baja después de encolar en dos cuentas:** respaldada por el nuevo recorrido real de `WorkerRecoveryTest.php:513–532`.
- **Borrador/futura sin transporte prematuro:** respaldada por las comprobaciones vivas y los workers de `WorkerRecoveryTest.php:711–748`, con cero intentos, cuota y mensajes. Complementa la materialización previamente aprobada.
- **Publicación repetida convergiendo a un comienzo:** respaldada por la identidad SQL previamente aprobada y el consumidor duplicado real de `WorkerRecoveryTest.php:282–301`. No equivale a SMTP exactamente una vez.
- El cierre formal de esas casillas corresponde a root. No doy por completos E2E de navegador, M-01, T-14–T-20 ni ADR-003, que continúa como propuesta.

**Evidencia causal original contrastada**

El gate actual `evidence/implementation/full-campaign-final-frozen-20261023T190743204457Z/stdout.txt:109–129`, `:196–220` registra **183 pruebas / 2227 aserciones**, incluyendo **20 OutboxIntegration y 21 WorkerRecovery**, exit 0. Los recibos anteriores se conservaron como históricos.

En las asociaciones del **full actual** comprobé:

- `runtime-e94d1b20-319b-4428-8a45-2f41b5cda744`: barreras de `transport_result` y `durable_result`, dos KILL con salida 137 y redeliveries sin nuevos comienzos; resultados finales ambiguo/aceptado. Son cortes dentro de las ventanas de la prueba, separados del teardown.
- `runtime-b0ae09a8-0248-4840-a69f-5dba128c73d7`: 250 registrado a `1792783482.78798`, cierre del proxy a `1792783482.863655`, salida controlada 42 por PDO y recuperación ambigua, con un único MAIL.
- `runtime-00dcd110-ea73-4724-a4db-3d6ff047e09c`: A suprimida y B aceptada; un único MAIL.
- `runtime-f44c6bb2-d32f-4db9-9f32-3123ce9b4767`: siete cambios de políticas, incluidos draft/future; cero MAIL.
- `runtime-dc416454-143e-41a1-a5fd-ddefc3362608`: dos consumidores, una aceptación y un intento.
- `runtime-aa8ae2ab-42d7-4f53-bacf-23aeadac8f3e`: cuota observada antes de la frontera, liberación en el minuto siguiente, cero cargo/intentos antes de reanudar.

Los **45 marcadores loaded del full** contienen **360 hashes de ocho clases**, todos coincidentes con las fuentes revisadas. Los cinco gates de `final_gate_names` contienen ZIP de **185 miembros**, sin discrepancias: 184 fuentes/contextos coinciden directamente con `sources/`; el runner privado coincide con su copia en `evidence/implementation/run.py`.

El smoke Windows conserva en original/replay la misma entrega `logged`, generación, intento, MailMessage y hash de artefacto, con cola vacía tras ack. La evidencia registra cero conexiones SMTP y limpieza propia. El censo final registra 342 identidades de contenedores ausentes y cero helpers nativos atribuidos presentes; es una observación fechada.

**Cobertura e integridad**

- Prompt literal completo leído. Leídos completos el runtime/configuración/migración afectados, WorkerRecoveryTest, los cuatro helpers, las nuevas guías y contexto causal de supresión, materialización, validación de correo y transacciones.
- Lectura semántica de todos los cambios de las **41 secciones del diff**, incluidos documentación y ledger; los tests legacy con cambio de trait y los historiales no afectados se revisaron por sus cambios y contexto pertinente. No afirmo lectura semántica completa de las 417 fuentes ni de las 6455 evidencias.
- Metadata íntegramente parseada y patch reconstruido en memoria: **3627 líneas, 41 secciones, 56 hunks; cero discrepancias** contra before/sources.
- Hashes iniciales y finales: **417 sources + 6455 evidence + 23 before**, sin discrepancias. Las 41 entradas modificadas están incluidas en sources.
- Patch SHA256 antes/después: `97d29208f5c7fd5f57683343746d0b61b78e74728e0e084a186c39eed8410c0e`.
- Metadata SHA256 final: `909d2afd2f3dbb6f85e1cfce91cb0a3eec18cf1a78e4c0ae57f6e3507391e29a`.
- Scope exit 0: 41/41 en alcance; fuera de alcance, exclusiones aplicadas, exclusiones de usuario, avisos e info vacíos. Las tres exclusiones vigentes son `CONTINUE-HERE*.md`, `.claude/**`, `docs/knowledge/journal/**`; ninguna ocultó cambios.
- C se activó automáticamente por `.env.example`. Apliqué criterio propio de seguridad; no utilicé referencias externas ni una auditoría completa.
- Solo lecturas y comprobaciones puras en memoria. Sin reparaciones, escrituras, pruebas ejecutadas, SQL, Docker, servicios, navegador o árbol vivo. Los hashes y resultados de suite no se presentan como cobertura semántica ni porcentaje de código.

**Defectos y reproducción:** ninguno reportable; no hay reproducción de explotación pendiente. Root conserva fusión, ledger, medidores y decisiones de cierre.
