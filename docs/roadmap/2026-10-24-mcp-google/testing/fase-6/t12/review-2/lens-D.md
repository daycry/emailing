# T-12 — Lente D — intento 2 de 3

Procedencia: proyección estructurada de root del mensaje final del revisor nativo
`t12_review_d2`, en lectura, sin escritura atribuida. Modelo heredado. D se activa
por la ruta `MigrationReplayTest.php`; C no se activa. Misma base/patch que
[A](lens-A.md),39 archivos; se reevaluó el delta nuevo respecto al intento1.

**Sin hallazgos: 0 Critical / 0 Important / 0 Minor.**

| Criterio | Resultado y evidencia |
|---|---|
| Replay normal | Conforme: `ReplaysMigrationHistory.php:29,87,97` es idéntico a la base y ya recorría siete migraciones; ajustar expectativas no añade DDL. |
| Reparación | Conforme: `MigrationReplayTest.php:153–158` recorre siete migraciones; cuatro dependientes adicionales son necesarias para el teardown. |
| Consultas adicionales | Acotadas: seis comprobaciones por invocación, sin crecimiento por emails, workers o filas. |
| Esperas y casos | No se añaden: reloj congelado y expectativa de estado no introducen consultas ni esperas. |

Comprobaciones propias puras: selector exit0, patch,39 hashes, JSON/XML válidos
y helper idéntico a base. Foco aportado8/194,213,306 segundos; no es un benchmark
causal ni demuestra degradación del delta. No se ejecutaron SQL, red, Docker,
bootstrap ni carga. Suite, build, E2E y QA completos siguen pendientes.
