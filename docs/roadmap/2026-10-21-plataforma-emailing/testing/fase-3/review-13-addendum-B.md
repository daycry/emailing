**Lente B · adenda de revisión global 13 · T-13 · intento 1/3: sin defectos de corrección confirmados.**

**Critical 0 · Important 0 · Minor 0.** Revisor genérico, runtime heredado. Se conservan los cuatro dictámenes primarios y la aclaración A; esta adenda no representa un fix ni una repetición de los gates primarios.

Referencias relativas a `storage/phase3-t13-review13-addendum/`, salvo indicación expresa:

- `H` = `evidence/storage/phase3-t13/implementation/review13-supplement-handoff-20261023T202228778686Z`.
- `V` = `H/attributed-evidence/supplement-published-unsubscribe-22abde58-ba89-4a53-b128-de235a1b938a`.
- `R` = `H/attributed-evidence/runtime-79b7a596-d0f8-4ac2-9fa2-811880f24be1`.
- `T` = `storage/phase3-t13-review13-addendum-terminals/`.

| Comprobación | Dictamen | Evidencia y alcance |
|---|---|---|
| Orden de la evidencia primaria | ✓ delimitado | `prior-verdicts/review13-A-clarification.md:1` y `V/WorkerRecoveryTest.preimage.php:515`: materialización SQL → POST → publicación AMQP → consumidor. Esa prueba primaria no acredita baja posterior a publicar AMQP. |
| Nueva secuencia AMQP → baja → consumidores | ✓ acreditada | `V/WorkerRecoveryPublishedBeforeUnsubscribeSupplementTest.php:542` publica ambos eventos antes del POST de `:556`. `R/queue-before-unsubscribe.json:7` registra ready=2, consumidores=0 y dos eventos publicados; `R/unsubscribe-response.json:6` registra HTTP 200, ready=2, ambas entregas todavía pendientes y cero mensajes. Los workers se inician después, en `V:564`. |
| Correlación de cuentas y entregas | ✓ | `V:253` crea la segunda cuenta conservando el mismo email y restaura el contexto original; `V:538` comprueba los dos destinatarios iguales. Los IDs de los eventos, entregas y cuentas de `R/queue-before-unsubscribe.json:19` coinciden con `R/after-workers.json:19` y `R/sql-final.json:2`. |
| Supresión de A e independencia de B | ✓ | `R/sql-final.json:4` muestra A `suppressed`, diagnóstico `recipient_suppressed`, cero intentos y ningún mensaje. `:16` muestra B `smtp_accepted`, un intento y su mensaje. `V:572` exige esos resultados y un único mensaje perteneciente a B. |
| Consumidor real y procedencia de código | ✓ | `R/loaded-account0:1` y `loaded-account1:1` registran Linux y ocho hashes cada uno, todos coincidentes con el manifiesto32f y las fuentes primarias congeladas. `storage/phase3-t13-review1/sources/tests/support/delivery-action.php:60` rechaza una entrega distinta de la asignada al fixture. Los dos workers y el relay terminan con exit 0. |
| Remitente SMTP observado | ✓ con límite explícito | `V/envelope-observer.py:23` observa exclusivamente la llamada pública `fact('mail')`, lee la línea `MAIL FROM` del llamador y registra una dirección sintética normalizada. `R/envelope-observer.jsonl:1` contiene exactamente `second@example.test`; inicio, observación y cierre comparten PID, token y hashes. No se deduce el envelope a partir de `MailMessage.sender_email`. |
| Relay y aceptación | ✓ | `V/delivery-relay.preimage.py:42` registra `mail` al recibir MAIL; `:62` registra DATA recibido y `:70` devuelve el 250. `R/relay.jsonl:1` contiene una sesión, un MAIL, un DATA aceptado y un `final_250`. `R/smtp-account1.jsonl:4` conserva las fases del transporte sin errores. |
| Resultado durable y asentamiento | ✓ | Los marcadores `R/before_ack-account0:1`, `acked-account0:1` y sus equivalentes de B corresponden a las entregas correctas. El consumidor primario, `sources/app/Services/DeliveryConsumer.php:25`, exige resultado durable antes de ACK. `R/after-workers.json:7` registra ready=0, consumidores=0, cola de fallos vacía y `basic_get` vacío. |
| Construcción y alcance de la variante | ✓ | Leídos el constructor, diff, preimagen y variante. `V/variant.diff:27` introduce el caso focal; los otros cambios adaptan nombre/ubicación, importan `ReflectionClass` y envuelven el relay con el observador. Preimagen y relay coinciden byte a byte con sus entradas del runtime185. |
| Rojo de instrumentación | ✓ histórico, no gate verde | El stdout original de `T/review13-published-unsubscribe-20261023T201637092157Z/stdout.txt:6` muestra `Tests\Feature\ReflectionClass` inexistente, tras 14 aserciones; receipt exit 2. La variante corregida importa la clase global en `V:38`. No atribuyo al rojo ejecución de B ni aserciones finales. |
| Verde focal | ✓ | El stdout original corregido registra **1 passed / 51 assertions**, duración 22,86 s; receipt exit 0. Hashes, fechas y manifiesto coinciden con el root-report previamente sellado. No equivale a repetir el full primario. |
| Limpieza propia | ✓ observación fechada | `supplement-final-owned-census-20261023T202043532892Z/supplement-owned-census.py:26` correlaciona los dos tokens con sus recursos. Recibos y streams acreditan tres contenedores ausentes y dos PID de relay ausentes; se conservan los marcadores de topología eliminada. No es una consulta actual de liveness. |
| SQL guard final | ✓ alcance comprobado | `supplement-final-db-guard-20261023T202107008226Z/supplement-final-db-guard.php:9` aplica el guard y comprueba identidad y tablas. `database-clean.json:4` registra únicamente `migrations`, con cero filas; no afirmo haber contado tablas de aplicación ya eliminadas por el aislamiento. |
| Metadata de publicación | ✓ | `publication-metadata.patch:7` añade `-text -whitespace` exclusivamente a Markdown bajo `testing/**/raw/`; `:15` declara `.gitattributes` en ArchivosT13. El diff reconstruido en memoria coincide exactamente con el patch. No modifica runtime ni reglas de envío. |

La nueva evidencia **sí cubre la secuencia que faltaba para la casilla T-10 «Probar baja después de encolar y antes del transporte»**, interpretando encolar como publicación AMQP efectiva. También respalda el criterio de supresión de pendientes en A conservando el envío de B para el mismo email. Root conserva el cierre formal.

La prueba ejecuta **dos consumidores secuenciales, una entrega por consumidor**, mediante el helper Linux que llama al consumidor productivo en modo de una lectura. No acredita consumo continuo, concurrencia de bajas con un transporte ya iniciado, replay, SMTP externo, entrega al destinatario ni exactly-once SMTP. Esas propiedades no se necesitan para demostrar esta secuencia concreta. La observación del envelope prueba el MAIL FROM recibido por el relay local; no inspecciona destinatarios SMTP ni cuerpos.

Los dos diagnósticos iniciales del auxiliar de censo permanecen históricos, según `H/supplement-handoff.json:63`; no los convierto en gates verdes ni afirmo haber leído originales que no forman parte de estas cápsulas. El censo final se contrasta mediante su código, resultados, recibos y streams propios.

La preparación inicial de la adenda omitía los originales raíz `receipt/stdout/stderr`: sus hashes y resúmenes estaban sellados en `root-reports`, pero eso no sustituía leerlos. La omisión quedó subsanada mediante la **cápsula adicional de 15 archivos**, vinculada al manifiesto158. Leí los originales y comprobé los cinco padres contra sus recibos y root-reports. No atribuyo esa lectura a la revisión inicial.

Integridad y cobertura real:

- **158/158 archivos**, hashes y tamaños correctos antes y después; manifiesto `f783fc674dbdd46d25c9bc2cc68bb995a7c1e46fe641b913b78bc9ec0d2b2b62`.
- **15/15 originales adicionales**, hashes y tamaños correctos antes y después; manifiesto `f47f742e36cc9c918b1f1c77b9eb8571775103342f661777d9b78ab6c498bb19`, vinculado al anterior.
- Cinco ZIP de **185 entradas** cotejados íntegramente con sus manifiestos externos, sin discrepancias. Las 185 entradas también coinciden con el snapshot primario, incluido el runner privado.
- Leídos semánticamente el prompt B completo, dictamen B previo, aclaración A, variante corregida completa, ambos diffs, constructor, observador, relay, protocolos focales, SQL final, auxiliares de cierre, handoff y originales terminales adicionales. Los hashes no equivalen a lectura semántica exhaustiva de todos los archivos.
- No repetí la auditoría de los 41 cambios primarios. T-11/T-12 conservan sus dictámenes y límites anteriores. No cierro T-14+, QA, despliegue Compose ni ADR-003.

Solo realicé lecturas y comprobaciones en memoria. Sin reparaciones, escrituras, aplicación, SQL, broker, Docker, servicios, navegador ni pruebas con efectos. **No hay defectos graduados ni una reproducción pendiente que entregar desde esta lente.**
