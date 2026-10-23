**Lente D · revisión global 12 · T-12 intento 2/3 · sin hallazgos actuales.**

**0 Critical / 0 Important / 0 Minor. D-01 corregido.** Revisor genérico fresco, runtime heredado. Revisión limitada al snapshot `storage/phase3-t12-review2`, sin escrituras ni ejecución de aplicación.

Leí el contrato completo de `lens-D.md`, el patch y metadata íntegros, las cuatro tablas/dictámenes de attempt-11 y el handoff final claim-fix1. Conservo sus aprobaciones y límites. La corrección nueva examinada es D-01; no reabro el bulk previamente aprobado.

Las referencias de código siguientes son relativas a `sources/`; las pruebas de ejecución son relativas a `evidence/`.

| Criterio | Resultado | Evidencia y escenario examinado |
|---|---|---|
| Reproducción del defecto D-01 | ✓ | `implementation/claim-fix1/claim-work-benchmark-160be132-ce36-42c4-9b48-94a955d34026.json:1`: con 10.000 pendientes, los acumulados para 1/10/100 claims globales fueron **10.005/100.050/1.000.500 lecturas Handler**. El `EXPLAIN ANALYZE` real muestra table scan de 10.000 filas y Sort. Sustituye la estimación estructural anterior por observación SQL real. |
| Selección global compatible con índice | ✓ | `app/Services/OutboxDispatcher.php:22`; migración `database/migrations/2026_10_23_000009_create_delivery_outbox.php:45,48`. Predicado y orden sobre `eligible_at,id`, con índice homónimo en ese orden. No queda el OR entre estados en la consulta global. |
| Trabajo SQL final con backlog | ✓ | `implementation/claim-fix1/claim-work-benchmark-030b61cd-bab4-4ced-9898-8b7708f50b1f.json:1`: 10.000 destinatarios y 1/10/100 claims producen **3/30/300 lecturas Handler**. El plan real usa `outbox_due`, rango `NULL < eligible_at <= UTC_TIMESTAMP(6)`, devuelve una fila y no contiene Sort. |
| Exclusión del histórico publicado | ✓ | Mismo benchmark final: 10.000 publicados → null con **1 lectura Handler**; 9.999 publicados más un pending futuro → null con **1**; volverlo elegible → mismo ID con **3**. La preparación, finalización de claims, EXPLAIN y limpieza quedan fuera de esa medición. |
| RED/GREEN significativo | ✓ | `tests/Feature/OutboxIntegrationTest.php:308–337`. Mide diez claims globales en la misma sesión PDO, sin eventId; retiene histórico del tamaño pequeño. RED: 1.050 frente a 101.050 lecturas, sobre presupuesto de crecimiento 4.700, exit 1. GREEN y final: 132/30, exit 0, sin cambiar expectativas ni introducir aserciones temporales. |
| Método de regresión conservado | ✓ | Comparación independiente de los ZIP RED/GREEN/final: cuerpo RED y GREEN SHA `8e68ac7ac8f0d41f0644cc096f3697ab043278146b02be073c3413cbcf0730ab`; final y los tres normalizados solo CRLF→LF SHA `e13cfd421cd92a73e8989e0900dc6ee947e812a85a6cae699ebb46cf1560d594`. Coincide con la verificación root. No afirmo identidad de todo el archivo. |
| Disponibilidad y leases preservados | ✓ | Migración `:45`; prueba `OutboxIntegrationTest.php:340–375`. Pending usa available_at; publishing usa GREATEST(available_at,lease_expires_at); publicado y publishing con lease NULL quedan fuera. Se prueban pending futuro, lease activo, lease NULL y available_at futuro con lease vencido. |
| Orden de elegibilidad documentado | ✓ | `OutboxDispatcher.php:20–22` y handoff final. El orden cambia a instante de elegibilidad,id. La prueba `OutboxIntegrationTest.php:366–375` lo verifica. Los contratos congelados no imponen FIFO global; no identifico incumplimiento nuevo por este cambio. |
| Concurrencia SQL real y ausencia de espera por candidato bloqueado | ✓ | `OutboxIntegrationTest.php:378–431`; `tests/support/outbox-action.php:76–81`. Dos procesos hijos reclaman IDs distintos y terminan mientras el padre mantiene bloqueado el primer candidato. Al liberar el lock, ese mismo ID resulta reclamable. Los protocolos y recibos correspondientes respaldan la secuencia; no se trata de dos llamadas secuenciales etiquetadas como concurrencia. |
| Lease, CAS y publicación fuera de transacción | ✓ | `OutboxDispatcher.php:19–30,33–43,46–53`: el cambio de selección conserva token/generación/estado, condiciones de finalización y commit previo a publicación. No se añade publicación dentro del mutex SQL. Las pruebas finales actuales incluyen estos caminos. |
| Acción finita | ✓ en alcance | `app/Console/Commands/DispatchDeliveryOutbox.php:16–24`: límite 1–100. D-01 elimina el recorrido global repetido observado en ese bucle. Esto no acredita un SLA de duración total ni el endpoint Cronicle pendiente. |
| Bulk, memoria y trabajo repetido del materializador | ✓ conservado | `DeliveryMaterializer.php:15,32–47,54–57`: lotes de 500, dos INSERT por lote, misma transacción. No hay cambio nuevo que reabra el defecto histórico. Benchmark actual: **9/9/47 SQL** para 100/200/10.000; conteos exactos, repetición cero y limpieza propia cero. |
| Conexiones y confirms secuenciales | ✓ conservado | No identifico evidencia nueva de degradación medible que reabra este criterio aprobado. La selección corregida no introduce conexiones o confirms adicionales por evento. |
| Gates sobre las fuentes finales | ✓ | Los seis `final_gate_names` tienen fuentes compatibles y exit 0. Regresión actual **3/120**; focal **20/293**; full **161/1.783**. Comprobé recibos, streams, manifests y contenido de ZIP, sin usar los verdes históricos como prueba de los cambios nuevos. |
| Procedencia y límites del benchmark | ✓ | La medición de claim usa deltas de handlers alrededor de claim global; incluye selección/update/fresh y excluye completion. **Handlers no equivalen a consultas SQL ni a filas únicas.** El benchmark completa directamente en SQL; no mide broker, SMTP, capacidad de entrega o SLA. |
| Límites de aceptación anteriores | ✓ conservados | CA-05/06/21/22 literales, worker/deduplicación/envío, E2E-08/09, M-01, QA de fase 3, T-13/T-15/T-16/T-19/T-20 y promoción de ADR-003 conservan sus pendientes y ámbitos. La concurrencia nueva demuestra reclamación global SQL; no acredita consumidores SMTP concurrentes. |

**Resolución de D-01.** El escenario anterior de 100 publicaciones sobre 10.000 pendientes reproducía 1.000.500 lecturas Handler y un scan+sort por selección. En el snapshot final observado, ese mismo tipo de selección global acumula 300 lecturas Handler y usa rango de índice sin Sort. Las comprobaciones con histórico publicado evitan que el coste reaparezca al vaciar el backlog. No queda un gap Important de rendimiento sustentado por estas evidencias.

La muestra actual del materializador para 10.000 es **47 SQL / 6,9351635 s**. Los **3,2206176 s** pertenecen al checkpoint histórico anterior al índice nuevo. Son muestras individuales de laboratorio; no establecen una comparación controlada de latencia, regresión temporal ni SLA.

**Cobertura e integridad.** Lectura íntegra en memoria de las 283 fuentes y 836 evidencias inventariadas; 519 JSON parseados y 71 ZIP abiertos, con sus 2.031 miembros leídos, sin errores. Patch: 83 partes y 76 hunks; reconstrucción normalizada por líneas coincide en **83/83** fuentes modificadas. Las dos preimágenes coinciden con sus hashes.

Contrasté 126 recibos y 309 referencias disponibles a streams/manifests, sin discrepancias. Los manifests finales focales tienen 25 fuentes. El full contiene **154 fuentes y cero artefactos tests/.runtime**: 129 comparables directamente con `sources/` y 25 resources verificables dentro del ZIP. No repito el conteo anterior erróneo 138/16.

Los inventarios, preimágenes, patch y metadata mantienen los hashes esperados antes/después. El agregado de las **1.135 rutas** del paquete también permaneció idéntico durante el par de comprobaciones completas:

- Patch: `93c5525c37f1c49b1bd54376009fd0dc4f684959dfc24b02febc165f5f596e3a`.
- Metadata: `dc9ac7d8e810e4e726ea3fe06c2c42b8ff5df00fe344a90b3323fea275383f97`.
- Agregado completo: `8d60665ddf7c001dc566000545d3424ed83d2dd109cd9ba12f6ae2ca8d8b9c92`.

La captura tardía de los primeros helpers y el stdout original no preservado del helper de auditoría fallido están declarados en el handoff; no los convierto en capturas originales ni en ejecución de suite. El stdout privado sensible se trató exclusivamente en memoria y no se reproduce.

No ejecuté tests, SQL, broker, servicios, navegador, procesos de aplicación, operaciones Git ni escrituras. Root conserva ledger, medidores y cierre de tareas.
