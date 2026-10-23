**Lente B · revisión global 11 · intento T-12 1/3 · sin defectos verificables.**

**0 Critical / 0 Important / 0 Minor.** Fallback genérico, runtime heredado. Revisión de solo lectura del snapshot congelado.

Rutas relativas a `storage/phase3-t12-review1/sources/`.

| Criterio de corrección/robustez | Resultado | Evidencia |
|---|---|---|
| Entrega y outbox atómicos | ✓ | `DeliveryMaterializer.php:19–57`: misma transacción para todos los lotes. `OutboxIntegrationTest.php:184–219`: INSERT de entrega ejecutado, rechazo real 3819 del outbox, rollback de ambas tablas y recuperación. |
| Autorización y fecha vigentes | ✓ | `DeliveryMaterializer.php:20–26`: mutex Account→Campaign, consulta de estado/autorización/revisión y reloj UTC SQL. Draft/review/futura/pausada/ajena cubiertos en `OutboxIntegrationTest.php:325–342`. |
| Identidad estable e idempotencia | ✓ | `DeliveryMaterializer.php:27–38`; migración `:28,46`. Dos procesos obtienen `['0','1']`; pausa/reanudación no recrea eventos (`OutboxIntegrationTest.php:274–310`). |
| Procedencia cuenta/campaña/revisión/destinatario | ✓ | Migración `:12–16,29–30,48`; rechazos SQL de cuenta y destinatario ajenos en `OutboxIntegrationTest.php:408–432`. |
| Corrección del bulk | ✓ | Lotes de hasta 500 pares, UUID individuales, generación 1 y misma transacción (`DeliveryMaterializer.php:15,36–57`). Prueba de volumen, conteos y repetición en `OutboxIntegrationTest.php:222–270`. |
| Payload mínimo versionado | ✓ | `DeliveryOutbox.php:22–24`: referencias y generación, sin HTML, secretos ni serialización PHP. Compatibilidad con cola Laravel conservada mediante destino distinto. |
| Reserva durable fuera de publicación | ✓ | `OutboxDispatcher.php:13–30,46–53`; guard de transacción exterior. Publicación ejecutada después del commit de claim. |
| Disponibilidad y recuperación del lease | ✓ | `OutboxDispatcher.php:20–27`: `available_at`, vencimiento y `SKIP LOCKED`, calculados con UTC SQL. |
| CAS de reconciliación | ✓ | `OutboxDispatcher.php:33–43`: identidad, cuenta, entrega, generación, estado, token y vigencia. Casos de token anterior/generación alterada en `OutboxIntegrationTest.php:450–468`. |
| Confirmación y no enrutables | ✓ | `ConfirmedDeliveryPublisher.php:33–74`: topología durable, persistencia, mandatory, ack/nack/return; return+ack conserva pending. Traza final observa `return→ack`. |
| Esperas y acción finita | ✓ en el alcance implementado | CLI limita 1–100 publicaciones (`DispatchDeliveryOutbox.php:16–25`); adaptador recibe timeouts de conexión/RPC/IO y confirm usa deadline (`ConfirmedDeliveryPublisher.php:30–31,55–61`). Esto no acredita un SLA de duración total bajo cualquier comportamiento de red. |
| Pérdida de confirm real | ✓ | `OutboxIntegrationTest.php:504–555`: proxy descarta `basic.ack` después de `confirm.select-ok`; broker contiene el primer evento y retry conserva payload/identidad. Las trazas finales registran `60:80` descartado. |
| Crash real entre confirm y CAS | ✓ | `OutboxIntegrationTest.php:571–623`; fixture `outbox-action.php:49–58`. Barrera completa validada, SQL todavía publishing, kill Linux propio exit 137 y reconciliación del mismo evento. |
| Pérdida SQL después del confirm | ✓ | `OutboxIntegrationTest.php:643–699`: conexión real por proxy interrumpida tras confirmed, escritura final falla, lease durable recuperable y mismo payload republicado. |
| Diagnósticos de publicación | ✓ | `ConfirmedDeliveryPublisher.php:75–84`: errores persistidos mediante códigos fijos. Runtime sin sockets tiene código explícito; no se persisten excepciones completas. |
| Barreras y causalidad de fixtures | ✓ | Temporal/rename y validación de JSON/identidad/plazo en `OutboxProtocolBarrier.php:7–53`; regresiones de marcador parcial, inválido y ajeno en `OutboxIntegrationTest.php:118–175`. |
| Migración reversible | ✓ | Migración `:52–69` restaura índices de soporte antes de retirar uniques; down/up repetido preserva snapshots (`OutboxIntegrationTest.php:435–447`). |
| Topología/ACL | ✓ componente comprobado | Cola de dominio separada; identidad runtime rechazada en vhost ajeno (`OutboxIntegrationTest.php:471–500`). CA-21 integral continúa abierto. |
| Consumidores existentes | ✓ sin regresión identificada | CampaignService/modelos/contratos anteriores conservados. Revisión T-11 previa no reabierta sin evidencia nueva; suite final ampliada incluye esos consumidores. |
| Alcance de aceptación | ✓ conservado | T-10/T-11/T-12 en progreso, ADR-003 propuesta. Publisher no acredita worker/SMTP ni ejecución exactamente una vez. CA-05/06/21/22 literales, E2E-08/09, M-01 y QA integral conservan sus pendientes. |

Los gates actuales corresponden al bulk final: focal **17/173**, full **158/1663**, regresión SQL/rollback **2/35**, todos exit 0. Verifiqué manifiestos, ZIP y streams. Los 25 SHA focales coinciden entre focal, full, benchmark, regresión y Pint acotado. Full conserva **193 capturas: 154 fuentes + 39 JSON runtime**. Los verdes históricos permanecen vinculados a sus fuentes anteriores.

La evidencia del rendimiento conserva una muestra por tamaño: 10.000 destinatarios pasan de **20.007 consultas / 214,0082381 s** a **47 / 3,2206176 s**. No acredita capacidad SMTP ni SLA. El mecanismo final de rollback usa CHECK propio sobre `type`; el intento de trigger fallido 1419 permanece histórico, sin ampliar privilegios. Teardown final declara cero recursos propios pendientes y preserva el PID histórico no atribuido.

**Cobertura e integridad:** lectura completa del patch —18 partes, 20 hunks—, 18 fuentes cambiadas y dos preimágenes; reconstrucción normalizada por líneas coincide en las 18. Leídas en memoria las 218 fuentes inventariadas y 570 evidencias; 323 JSON parseados y 45 ZIP abiertos con sus 1.487 miembros leídos. Se contrastaron 90 recibos con sus streams, sin discrepancias. La salida privada sensible se trató en memoria; no se imprime ni reproduce ningún valor o argumento.

Hashes **antes y después: 791/791 rutas únicas correctas**, cero discrepancias: 218 fuentes + 570 evidencias + dos preimágenes + patch; los 18 cambios están incluidos en las 218 fuentes. Metadata sin cambios: `9087e3ab1482037f03bbd6d1a220b830139590f9e81530d0803daefba9f9bbc7`. Patch sin cambios: `2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`.

No ejecuté SQL, broker, red, servicios, procesos de aplicación, tests con efectos, escrituras ni mutaciones Git. Root conserva ledger, medidores y Git.
