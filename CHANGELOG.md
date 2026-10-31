# Registro de cambios

## [Sin publicar]

### Added — iniciativa `proveedores-email` (2026-10-30)

- **T-01 — Persistir configuración y autorización externa por cuenta/proveedor** Configuración cifrada de Brevo y SendGrid por cuenta y autorización independiente de remitentes externos.
- **T-02 — Fijar contratos y resultado común antes de los adaptadores** Los contratos distinguen aceptación API, aceptación SMTP y feedback posterior del proveedor.
- **T-03 — Integrar Brevo SMTP y SendGrid SMTP con aceptación observada** Envío SMTP de Brevo/SendGrid con TLS, negativos observados y bloqueo de degradación EHLO.
- **T-04 — Integrar API transaccional Brevo y SendGrid sin reenvíos inciertos** Envío API Brevo/SendGrid con respuestas acotadas y sin reintentos de aceptación incierta.
- **T-05 — Procesar feedback autenticado con idempotencia y supresiones** Webhooks autenticados y deduplicados con feedback separado y supresiones por cuenta.
- **T-06 — Integrar campañas/API/MCP, operar la UI y acreditar QA local** Configuración y actividad de proveedores integradas con campañas, API y MCP.
- **T-07 — Completar documentación y manual Markdown/PDF para público no técnico** Manual completo en Markdown y PDF con instalación desde cero, ejemplos, diagramas y cobertura de uso y administración.

