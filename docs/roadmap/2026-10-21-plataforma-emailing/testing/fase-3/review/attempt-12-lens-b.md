**Lente B · revisión global 12 · intento T-12 2/3: sin defectos verificables.**

**Critical 0 · Important 0 · Minor 0.** Fallback genérico y runtime heredado; no atribuyo ejecución a Claude/Opus.

Reevalué la corrección de D-01 y sus posibles regresiones. Leí íntegros los cuatro dictámenes del intento 11, incluida la aclaración de root: **129 fuentes comparables directamente y 25 recursos verificados dentro del ZIP**. Las aprobaciones previas se conservan; no encontré evidencia nueva que justifique reabrirlas.

Las rutas de código siguientes son relativas a `storage/phase3-t12-review2/sources/`; las evidencias, a `storage/phase3-t12-review2/`.

| Criterio de corrección y robustez | Resultado | Evidencia y alcance |
|---|---|---|
| Conjunto elegible conservado | ✓ | `database/migrations/2026_10_23_000009_create_delivery_outbox.php:45` y `app/Services/OutboxDispatcher.php:22`: pending utiliza available_at; publishing exige tanto available_at como lease vencidos mediante GREATEST; otros estados quedan excluidos. Comprobación algebraica en memoria: 48 combinaciones de estado, fecha pasada/igual/futura y lease nulo/pasado/igual/futuro, sin diferencias respecto al predicado anterior. |
| Fechas futuras y leases activos | ✓ | `tests/Feature/OutboxIntegrationTest.php:340`: contempla pending futuro, lease activo y available_at futuro con lease vencido. Ninguno se reclama prematuramente. |
| Lease nulo | ✓ | Migración `:45`; prueba `OutboxIntegrationTest.php:349,360`: GREATEST con lease NULL mantiene eligible_at NULL y el evento excluido, igual que el predicado anterior. |
| Historial publicado excluido | ✓ | Migración `:45,48`; prueba `OutboxIntegrationTest.php:346,359`. El benchmark final registra una lectura Handler y ningún resultado con 10.000 publicados; también una lectura con 9.999 publicados y un pending futuro. |
| Orden de selección explícito | ✓ | `OutboxDispatcher.php:20–22`, prueba `OutboxIntegrationTest.php:351–375` y `evidence/implementation/claim-fix1/handoff.md`: el orden cambia a eligible_at,id. La prueba exige pending antiguo antes de un lease recién vencido aunque este tenga available_at anterior. El cambio está declarado; no encontré promesa de FIFO global en el contrato congelado. |
| Fecha generada sin sincronización manual | ✓ | Migración `:45`: la columna almacenada deriva de estado, disponibilidad y lease. Claim y complete actualizan esos campos; ningún camino nuevo debe mantener eligible_at manualmente. |
| Reserva atómica y publicación fuera de SQL | ✓ conservado | `OutboxDispatcher.php:15–30,46–53`: guard de transacción exterior, selección bloqueante y actualización dentro de una transacción propia; publicación después del commit. |
| SKIP LOCKED y reclamaciones concurrentes | ✓ observado | `OutboxIntegrationTest.php:378–431` y `tests/support/outbox-action.php:76–81`: dos hijos reales terminan con eventos distintos mientras el padre mantiene bloqueado el primer candidato. Tras rollback del padre se reclama ese mismo candidato. Los fixtures se materializan antes de abrir la transacción que retiene el lock. |
| CAS, generación y token | ✓ conservado y vuelto a ejecutar | `OutboxDispatcher.php:43`; `OutboxIntegrationTest.php:610–628`: estado, identidad, cuenta, entrega, generación, token y lease vigente siguen cercando la escritura final. La corrección no modifica este código. |
| Identidad, procedencia e idempotencia | ✓ aprobación conservada | `DeliveryMaterializer.php:20–38`, migración `:27–30,47,49`. Comparación con el manifest final anterior: únicamente cambian dispatcher, migración y las dos fuentes de prueba declaradas. |
| Atomicidad y bulk anterior | ✓ aprobación conservada | `DeliveryMaterializer.php:19–57`; rollback real en `OutboxIntegrationTest.php:185–220`. El materializador conserva sus bytes. Los gates actuales vuelven a ejecutar sus pruebas; el benchmark actual mantiene 9/9/47 consultas para 100/200/10.000 destinatarios, conteos exactos y repetición cero. |
| Autorización y fecha bajo mutex | ✓ aprobación conservada | `DeliveryMaterializer.php:20–26`: Account→Campaign, revisión y autorización persistidas, UTC SQL y fecha efectiva. La corrección de claim no cambia este contrato. |
| Payload y consumidores existentes | ✓ sin regresión identificada | `app/Models/DeliveryOutbox.php:22–24`: payload v1 e identidad estables. El destino de dominio continúa separado de la cola Laravel. No se añade consumidor SMTP. |
| Confirm, return y reconciliación | ✓ aprobación conservada y pruebas actuales | `OutboxIntegrationTest.php:505–565,664–872`: los casos siguen presentes en el focal actual; cubren return→ack, confirm descartado por proxy, KILL Linux después del confirm y pérdida SQL posterior. No uso puerto cerrado como prueba de confirm perdido. |
| Migración reversible | ✓ | `OutboxIntegrationTest.php:595–607`: dos ciclos down/up preservan snapshots y permiten materializar después. Esta prueba pertenece al focal/full actuales; no atribuyo su ejecución al filtro aislado de tres regresiones. |
| Acción finita | ✓ alcance conservado | `app/Console/Commands/DispatchDeliveryOutbox.php:16–24`: máximo de 100 reservas por invocación. Los timeouts del publisher permanecen intactos. Esto no demuestra un SLA total ni el scheduler pendiente. |
| Regresión de trabajo SQL significativa | ✓ | `OutboxIntegrationTest.php:308–337`: mide Handler del mismo PDO alrededor del claim global, sin eventId; completion queda fuera. El método RED y final coincide normalizando exclusivamente CRLF→LF, sin relajar presupuestos. |
| Captura de fuentes y gates actuales | ✓ | Los seis `final_gate_names` tienen exit 0; sus 25 hashes focales coinciden entre sí y con sources/. El full contiene 154 fuentes, sin JSON runtime: 129 comparables directamente y 25 comprobadas dentro de su ZIP. |
| Alcance de aceptación y privacidad | ✓ límites conservados | T-10/T-11/T-12 y ADR-003 mantienen su estado pendiente. El stdout histórico sensible se procesó solo en memoria; no reproduzco extractos, valores ni trazas de argumentos. |

**D-01: corrección acreditada dentro del componente.** El benchmark histórico registra 10.005/100.050/1.000.500 lecturas Handler para 1/10/100 claims. El final registra **3/30/300**. El EXPLAIN ANALYZE final muestra rango sobre eligible_at, una fila real y ausencia de sort; también hay medición específica del historial publicado. Son resultados de selección SQL, con una muestra de laboratorio; no acreditan capacidad SMTP ni latencia contractual.

La regresión aislada actual pasa **3 pruebas / 120 assertions**. El focal final pasa **20 / 293** y el full final **161 / 1.783**, todos exit 0. Verifiqué recibos, streams, manifests y archivos capturados; no ejecuté nuevamente esas pruebas.

**Defectos graduados nuevos: ninguno.** Los pendientes de worker, dos consumidores llegando al transporte, CA-05/06/21/22 integrales, E2E-08/09, M-01, QA de fase 3 y T-13/T-15/T-16/T-19/T-20 conservan sus límites previos. Dos procesos reclamando outbox no prueban dos workers SMTP ni deduplicación del transporte. Pint acotado tampoco acredita Pint o whitespace globales.

**Cobertura e integridad:** procesados íntegramente patch y metadata. El patch contiene **9.349 líneas, 83 secciones y 76 hunks**; reconstrucción en memoria contra las dos preimágenes: **83/83 fuentes coincidentes**. Leídos en memoria los **283 archivos de fuentes y 836 evidencias**, con **519 JSON válidos**, **71 ZIP abiertos y sus 2.031 miembros leídos**. Contrastados **126 receipts** con los hashes de streams/manifests que declaran, sin discrepancias.

La lectura semántica se centró en las cuatro fuentes corregidas, el componente relacionado, contrato, handoff final, tablas anteriores y evidencias pertinentes. No equiparo hashing o parsing con una nueva auditoría semántica exhaustiva de todo el contexto histórico.

Hashes antes y después conformes: **1.122 rutas únicas inventariadas**, incluyendo 283 fuentes, 836 evidencias, dos preimágenes y patch; metadata comprobada adicionalmente. El directorio congelado contiene 1.135 archivos físicos.

- Patch: `93c5525c37f1c49b1bd54376009fd0dc4f684959dfc24b02febc165f5f596e3a`.
- Metadata: `dc9ac7d8e810e4e726ea3fe06c2c42b8ff5df00fe344a90b3323fea275383f97`.

Se conserva la limitación declarada del primer helper de auditoría sin streams originales y de la captura retrospectiva de helpers iniciales; no se reconstruyen recibos ni se presenta esa ejecución como QA. No realicé escrituras, Git, SQL, broker, servicios, navegador ni pruebas con efectos.
