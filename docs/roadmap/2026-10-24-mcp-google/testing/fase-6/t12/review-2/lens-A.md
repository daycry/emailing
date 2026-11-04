# T-12 — Lente A — intento 2 de 3

Procedencia: proyección estructurada de root del mensaje final del revisor nativo
`t12_review_a2`. El revisor operó en lectura y no escribió este archivo. Modelo
heredado, sin override. Tablas A/B completas del intento1 leídas primero.

Base `d04c89f7cb0b1bb2a9957da5a0c4a8900be61919`; patch
`c24f32aa1878689b7257777c82168a90ac83ea46dfda69e7339c6613ceef9431`.
39 archivos de cápsula comprobados; alcance exit0 sin criterios alterados.

**Sin defectos nuevos: 0 Critical / 0 Important / 0 Minor. T-12 abierta.**

| Criterio | Resultado y evidencia |
|---|---|
| CA1, catálogo, negativos, aislamiento y concurrencia completos | Pendiente; conserva aprobación del incremento McpSend previo. |
| CA2, fuentes, perfiles, migraciones, terminales y limpieza | Pendiente completo; recibos focales no acreditan ambos perfiles. |
| CA3, simulación y pruebas reales diferenciadas | Conforme; no se acepta Google real, clientes ni manuales. |
| CA4, regresión cruzada completa | Pendiente; global rojo conservado, nueva suite necesaria. |
| Métricas | Conforme: `McpBusinessToolsTest.php:212` conserva igualdad estricta y añade `api_accepted`, coherente con `ActivityService.php:18`. |
| TTL OAuth | Conforme: `McpOAuthTest.php:65–94` mantiene300/3600, canje, replay, revocación y rechazo de refresh, con reloj controlado. |
| Ventana y reparación | Conforme: `MigrationReplayTest.php:22–56,138–177` conserva orden, snapshots y fallo antes de reparar; añade esquema e índices de proveedores. |
| Tests y puertas | Conforme: no se eliminan pruebas, añaden skips ni relajan umbrales. Foco8/194 no se presenta como suite completa. |
| Alcance y verificaciones | Conforme parcial: MigrationReplayTest explícito y justificado; ninguna tarea nueva cerrada. |
| Matriz y evidencias | Conforme; conserva originales y clasifica foco frente a QA completa. |
| Constitución, interop, OpenAPI | No aplican: constitución ausente, esas piezas no cambian. |
| Memoria vinculante | ADR-006 aceptada consultada, sin contradicción observada. |

Comprobaciones propias: patch y39 archivos exactos; índices, hashes, IDs y JUnit
concordantes. De485 fuentes públicas comparadas,53 exactas y432 equivalentes al
normalizar CRLF→LF del checkout, sin diferencias de contenido. Selector y guía
Laravel consultados. No se ejecutaron SQL, red, Docker, build, app ni E2E.
No se atribuye porcentaje de cobertura. Los cierres globales siguen pendientes.
