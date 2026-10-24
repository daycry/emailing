# Revisión 21 — contrato T-17 — intento 2 de 3

Fecha: 2026-10-24. Base: `eece2a5`. Se revisó la corrección de B-01 de la
[revisión 20](attempt-20.md), conservando sus criterios aprobados. Alcance:
contrato y documentación; ocho archivos públicos, sin endpoints nuevos.

La cápsula tiene sello
`8ca2b62c47bf08634c26cbf1ffe84555191555e88679537251c382cf8030fc87`.
Ambas lentes verificaron sus 81 hashes. Alcance: exit 0, sin avisos ni
exclusiones de usuario. C/D desactivadas por prosa. A/B corrieron en paralelo
con contexto fresco y traspaso completo del veredicto anterior, mediante el
fallback genérico; no hay agente `reviewer` nombrado ni modelo real acreditado
por el frontmatter del plugin.

| Criterio | Veredicto |
|---|---|
| Operaciones, éxito/error, scopes, límites y cuenta derivada de key | Conforme; aprobación contractual anterior conservada. |
| Cuenta ajena y concesión superior a la autoridad del actor | Prohibidas contractualmente; aprobación conservada. |
| B-01: selección de contenido inline/template | Corregido. Mappings explícitos en `docs/api/openapi.yaml:531–533`, sin alterar payloads ni otros campos. |
| Evidencia causal | RED 0/2, exit 1; GREEN final 2/2, exit 0. Ambas variantes rechazan el mapping cruzado. El diagnóstico intermedio de indentación permanece fallido y conservado. |
| Validación y evidencia | Lint y diff independientes: exit 0, sin errores ni rompedores. Esquema oficial y 107/107 ejemplos corresponden al contrato corregido. |
| Alcance, estados y límites | Conformes; ninguna prueba documental se presenta como aceptación funcional. |

Resultado A y B: **cero Critical, Important o Minor pendientes; ningún defecto
nuevo**. No se reabrieron criterios aprobados. Las métricas de tokens, coste y
horas IA reales no están disponibles.

Contrato revisado SHA-256:
`e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`.
Root acepta técnicamente T-17 y promueve ADR-007 como decisión revisada.
La comprobación documental está cerrada; la QA de comportamiento de la fase 4,
CA-19/20 y E2E-10 requieren implementar y probar T-18. No se ejecutaron
aplicación, base de datos ni servicios para esta revisión. M-01 humana sigue
pendiente y el objetivo global permanece en progreso.
