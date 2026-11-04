# T-12 — Lente B — intento 2 de 3

Procedencia: proyección estructurada de root del mensaje final del revisor nativo
`t12_review_b2`, en lectura, sin atribuirle escritura de este archivo. Modelo
heredado. Tablas completas del intento1 leídas; aprobaciones no reabiertas.
Misma base y patch que [A](lens-A.md),39 archivos.

**Sin defectos nuevos: 0 Critical / 0 Important / 0 Minor.**

| Criterio | Resultado y evidencia |
|---|---|
| Métricas estrictas | Conforme: test212 → `BusinessDto.php:80` → `ActivityService::STATES`; `BusinessOperations.php:193` es el llamante. |
| Reloj y TTL | Conforme: test65–95 conserva300/3600, canje/replay; `AuthorizationServer.php:177–179` usa lecturas de `now()`. |
| Snapshots y orden | Conforme: siete migraciones, quince snapshots y catorce pasos con orden completo en test22–56. |
| Reparación deliberada | Conforme: test150–162 repara los `up` pendientes; helper97–109 registra y corta dependientes ante fallo. Proveedores inspeccionados. |
| Conservación de pruebas | Conforme: sin eliminaciones, skips ni aserciones debilitadas; esquema e índices adicionales comprobados. |
| Regresión global y QA | Pendientes: foco8/194 verde, suite anterior roja. |

Comprobaciones propias: sintaxis PHP de tres tests exit0,39 hashes y patch
correctos. Manifiesto focal en candidato:62 coincidencias literales y475 tras
normalizar CRLF→LF, sin cambios de contenido; `.htaccess` ausente del checkout
de revisión. No equivale a538 bytes crudos: la coincidencia del árbol original
procede de evidencia aportada. Guías y ADR dirigidos sin contradicción.
Sin SQL, red, Docker, bootstrap, build ni ejecución de app. T-12 en progreso.
