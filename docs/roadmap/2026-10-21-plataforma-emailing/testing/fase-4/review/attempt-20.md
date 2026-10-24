# Revisión 20 — contrato T-17 — intento 1 de 3

Fecha: 2026-10-24. Base: `eece2a5`. Alcance: contrato OpenAPI nuevo,
reglas, ejemplos y ADR-007 propuesta; siete archivos públicos. No incluye
endpoints, aplicación en ejecución ni aceptación funcional de CA-19/20/E2E-10.

El diff y sus evidencias se congelaron con sello
`5f4e055e81a35a757752b62a84fb9aa73bbadf5a76b88d1a61a4f0724f40bd20`.
Ambos revisores verificaron sus 95 hashes. Las puertas de alcance y ledger
terminaron con exit 0, sin avisos ni exclusiones de usuario. El selector dejó
C y D desactivadas por tratarse de documentación. A y B corrieron en paralelo
como subagentes genéricos de contexto fresco: el agente nombrado `reviewer`
no está disponible; no se atribuye un modelo a partir del frontmatter.

| Lente | Resultado |
|---|---|
| A: requisitos, alcance y evidencia | Conforme al alcance contractual; cero gaps. Los cinco endpoints se describen, sin afirmar que estén implementados. |
| B: corrección | Un defecto Important en la selección de variantes OpenAPI. |

| ID | Severidad | Evidencia y escenario | Veredicto |
|---|---|---|---|
| B-01 | Important | `docs/api/openapi.yaml:529`: el discriminador `content.type` carece de `mapping`. Los valores `inline` y `template` no coinciden con los nombres `InlineContent` y `TemplateContent`; un consumidor que aplique el discriminador no resuelve los dos ejemplos válidos. | Aceptado; corregir antes de aprobar T-17. |

El esquema JSON y sus 107 ejemplos pasaron, pero esas comprobaciones no
ejercitan esta semántica del discriminador. La regla se contrastó con la
[especificación oficial OpenAPI 3.0.3](https://spec.openapis.org/oas/v3.0.3.html#discriminator-object).
La corrección prevista añade los dos mappings explícitos y conserva los
payloads publicados. La siguiente revisión comprobará el cambio y recibirá
esta tabla completa; no reabrirá criterios aprobados sin evidencia nueva.

T-17 continúa en progreso y ADR-007 sigue propuesta. No se ejecutaron
servicios ni pruebas de comportamiento de la API. Las métricas de tokens,
coste y horas IA reales no están disponibles.
