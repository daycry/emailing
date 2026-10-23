**Lente D — revisión global 11, intento T-12 1/3: 1 Important.** Sin Critical ni Minor.

| Criterio | Resultado | Evidencia |
|---|---|---|
| N+1 del materializador | ✓ | `app/Services/DeliveryMaterializer.php:40`: dos INSERT por lote de hasta 500 destinatarios. Para 10.000, 40 INSERT y siete SELECT. |
| Bloqueo SQL durante publicación | ✓ | `app/Services/OutboxDispatcher.php:49`: publica después de cerrar la transacción de reclamación. |
| Trabajo repetido con volumen | ✗ | `app/Services/OutboxDispatcher.php:20`: selección global ordenada sin índice compatible, repetida por evento. D-01. |
| Memoria del materializador | ✓ | Acumulación de inserciones limitada a 500 pares; conjunto de existentes proporcional a la revisión, limitada actualmente a 10.000 destinatarios. |
| Conexiones y confirms secuenciales | ✓ | Hay conexión y confirmación por publicación, pero las evidencias no sustentan otro defecto medible ni un umbral de latencia incumplido. |
| Corrección del problema histórico | ✓ | Benchmark, RED/GREEN y fuentes finales acreditan la eliminación de los dos INSERT por destinatario. |
| Procedencia y límites de resultados | ✓ | Benchmark histórico separado del final; sin extrapolación a capacidad SMTP, SLA, worker o QA integral. |

**D-01 — Important: la reclamación global vuelve a examinar el backlog para cada evento.**

Ubicación principal: `app/Services/OutboxDispatcher.php:20–22`. Relacionadas: `database/migrations/2026_10_23_000009_create_delivery_outbox.php:47` y `app/Console/Commands/DispatchDeliveryOutbox.php:23`.

La consulta sin `eventId` combina dos valores de `status` mediante OR y exige `ORDER BY available_at, id LIMIT 1`. El índice disponible comienza por `status` y continúa con `available_at, lease_expires_at`; ninguno proporciona el orden global solicitado entre ambas ramas. La selección necesita evaluar los candidatos y resolver ese orden antes de devolver un evento. El CLI repite esta operación para cada publicación.

Escenario concreto: una campaña admitida de **10.000 destinatarios**, materializada y pendiente de publicación, con confirmaciones satisfactorias. Las primeras 100 reclamaciones examinan del orden de **995.050 candidatos elegibles**; vaciar las 10.000 implica aproximadamente **50.005.000**, frente a un recorrido indexado proporcional al trabajo despachado.
El límite finito por invocación reparte ese coste entre ejecuciones, pero mantiene su crecimiento cuadrático al vaciar el backlog.

Estas cifras expresan **trabajo estructural estimado**, no latencia medida ni un EXPLAIN ejecutado. El benchmark conservado mide únicamente `materialize()`. Los casos focales dirigidos por `eventId` utilizan búsqueda por clave y no demuestran el comportamiento de esta selección global con backlog.

La corrección previa del materializador queda acreditada:

| Destinatarios | Histórico: SQL / segundos | Final: SQL / segundos |
|---:|---:|---:|
| 100 | 207 / 1,1661848 | 9 / 0,0643694 |
| 200 | 407 / 3,6606002 | 9 / 0,1311307 |
| 10.000 | 20.007 / 214,0082381 | 47 / 3,2206176 |

Una muestra por tamaño, PHP nativo Windows y MySQL; incluye la transacción de materialización y excluye preparación, comprobaciones, repetición y limpieza. **Los 214 segundos son históricos, no un defecto vigente del bulk final.**

Verificación de evidencia:

- Benchmark histórico ejecutó materializador SHA `288429358c4eb4ccbb5ff64190d6efa55e0b4eb65dccccd770505bb4ffae0312`; benchmark y gates finales ejecutaron `86c00af64a02fc953a02ec8fe9f102373232e024fa819768523aab469f898500`.
- RED: 100 destinatarios, 207 consultas frente a presupuesto 26. Final: 100/200/1.000 → 9/9/11 consultas. Método idéntico RED/final; primer GREEN equivalente normalizando exclusivamente CRLF.
- Rollback final: un INSERT real de deliveries precede al rechazo MySQL **3819** del outbox; ambas tablas quedan vacías y la recuperación posterior funciona. El fallo histórico **1419** ocurrió antes de crear el trigger y no acredita ese rollback.
- Focal final: **17 passed / 173 assertions**. Full final: **158 passed / 1.663 assertions**. ZIP full: **193 entradas = 154 fuentes + 39 JSON runtime**; las 25 entradas focales coinciden. Los 25 recursos UI ausentes del contexto `sources/` están íntegros en el ZIP full.
- **90 recibos** contrastados con sus streams/manifests, sin discrepancias. Cleanup final registra cero recursos propios pendientes y handles terminales.

Cobertura: prompt completo y diff íntegro de 18 archivos leídos; contexto contractual y evidencias necesarias examinados. Integridad **antes y después: 791/791 rutas únicas** —218 fuentes, 570 evidencias, dos preimágenes y patch—, sin discrepancias. Esto es cobertura de hash; no afirma lectura semántica exhaustiva de los 218 archivos de contexto ni de las 570 evidencias.

Metadata SHA `9087e3ab1482037f03bbd6d1a220b830139590f9e81530d0803daefba9f9bbc7`; patch SHA `2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`.

El stream histórico sensible se procesó únicamente en memoria, emitiendo agregados seguros. Sin escrituras, pruebas, SQL, broker, servicios ni mutaciones Git.

**Conteos finales: Critical 0 · Important 1 · Minor 0.**
