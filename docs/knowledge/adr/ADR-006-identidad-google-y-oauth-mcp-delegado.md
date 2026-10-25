---
id: ADR-006
titulo: Identidad Google y OAuth MCP delegado por cuenta sobre Sanctum
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 1)
fecha: 2026-10-24
actualizado: 2026-10-25
iniciativa: mcp-google
---

# ADR-006: Identidad Google y OAuth MCP delegado por cuenta sobre Sanctum

## Contexto

El usuario solicita Google para la web y el acceso MCP desde Claude, Codex y OpenCode. La referencia solicitada `daycry/social-scheduler`, fijada a `7f542a548613eb535e767fc10081fc21dc5b792a`, separa login Socialite y OAuth MCP propio sobre Sanctum. Emailing añade cuentas compartidas y Teammates, por lo que aislar por usuario resulta insuficiente.

La [spec](../../roadmap/2026-10-24-mcp-google/spec.md), [evaluación](../../roadmap/2026-10-24-mcp-google/evaluation.md) y [análisis de referencia](../../roadmap/2026-10-24-mcp-google/reference-analysis.md) documentan el requisito y los límites del examen estático. No existe todavía interoperabilidad real comprobada. La decisión afecta identidad, persistencia, middleware, consentimiento y herramientas, y por ello se registra como propuesta transversal.

## Decisión

La implementación propuesta utiliza Socialite para identidad Google, Laravel MCP para Streamable HTTP y Sanctum para tokens opacos propios, con un servidor OAuth acotado de Authorization Code + PKCE S256. El grant une usuario, cliente, cuenta, recurso y scopes consentidos. Un guard exclusivo acepta sólo tokens de propósito MCP y revalida permisos Teammates vigentes en cada llamada y punto de mutación.

La identidad Google usa emisor/sub validado en servidor. La vinculación a usuario existente es explícita y autenticada; no hay autoenlace por coincidencia de email. Registro e invitaciones conservan sus reglas, sin roles derivados de Google. Google no se utiliza como SMTP ni concede acceso a Gmail/Drive.

Los códigos se guardan con hash, duran 300 segundos y se consumen atómicamente junto con la emisión de la credencial. Los access tokens opacos de Sanctum llevan recurso/propósito/grant verificables, con vida máxima de 3600 segundos; el grant también caduca como máximo a la hora. No se emite refresh inicialmente, como en la referencia; al expirar se reautoriza con consentimiento y un grant nuevo, y se rechaza ese grant_type. No hay caso real de cliente que justifique una familia de refresh todavía. Si un cliente requerido demuestra necesitarla, se revisan ADR/contrato y sus pruebas antes de declarar compatibilidad, conservando el alcance original.

El [contrato propuesto 0.1.0](../../aplicacion/mcp-contrato.md), el [catálogo de 37 operaciones](../../api/mcp-tools.json) y el [OpenAPI OAuth](../../api/mcp-oauth.openapi.json) fijan el detalle verificable. Preregistro público con `none` es el perfil inicial; DCR queda controlado y desactivado por defecto, y CIMD no se anuncia ni descarga URLs arbitrarias. Los redirects son exactos, con excepción de puerto loopback sólo para perfiles explícitamente admitidos. Emisor/recurso HTTPS vienen de configuración confiable, nunca de Host. Se publica descubrimiento y se exige `resource` al autorizar/canjear, así como `iss` exacto en respuestas de autorización.

Se fijan perfiles MCP objetivo `2025-11-25` (initialize) y `2026-07-28` (discovery y metadatos por petición). Su existencia en la fuente candidata del SDK no acredita ejecución. Codex CLI 0.163.0, Claude Code 2.1.304 y OpenCode V2 2.0.12 son candidatos inventariados, pendientes de T-11/T-14. Claude Desktop se mantiene separado de los tres objetivos iniciales. Las limitaciones reales de cliente que impidan este perfil reabren ADR/contrato; no autorizan desactivar autenticación o declarar éxito parcial.

API keys de cuenta, cookies web y bearer Google no autentican MCP. Las herramientas comparten servicios, outbox, idempotencia por cuenta, cuotas y supresiones. El origen del primer commit API/MCP permanece inmutable; un replay desde otro canal se audita sin reatribuir el trabajo ni crear API keys, contactos o campañas ficticios. Actividad/métricas distinguen la población MCP y su grant/cliente/actor reales. El contrato REST T-17 conserva su SHA-256 `e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`; el transporte sigue en Mailpit/log.

La recuperación local exige un método que la persona controle: un hash de contraseña aleatoria no prueba esa capacidad. Desvincular Google no elimina el último método viable y conserva Fortify/2FA/registro/invitaciones. La auditoría de identidad se ancla al usuario y requiere persistencia adecuada; no se inventa `account_id` para satisfacer el AuditEvent de negocio actual. La auditoría de herramientas sí identifica cuenta, usuario, cliente y grant.

Las claves Laravel actuales/anteriores son privadas y persistentes; los cursores cifrados tienen TTL de 900 segundos y tolerancia de 30. La rotación conserva la clave anterior durante esa ventana o invalida explícitamente los cursores. Tras compromiso o restauración antigua se invalidan grants/tokens/códigos potencialmente reactivados antes de reabrir admisión, conservando recibos idempotentes y tombstones de procedencia. Tokens/códigos sólo se guardan con hash; los recibos no dependen de APP_KEY.

## Alternativas descartadas

- **Passport como base inicial**: no se incorpora en paralelo al patrón de referencia. No resuelve por sí solo cuenta/Teammates y el wrapper MCP general no sustituye scopes de negocio. Un requisito demostrado puede reabrir la decisión con evidencia.
- **Bearer Google o API key de cuenta para MCP**: confunden identidad, propósito, audiencia y ciclo de vida. Una conexión delegada debe perder acceso al revocarse su membresía.
- **Copiar aislamiento user_id y autoenlace de email de la referencia**: no conserva el modelo multi-cuenta ni la política de vínculo explícito de Emailing.
- **Refresh rotatorio inicial sin requisito demostrado**: añade persistencia, replay y revocación de familias sin prueba de necesidad; la interoperabilidad debe acreditar reautorización o justificar el cambio.
- **Herramientas con lógica propia de envío**: duplican autoridad y permiten divergencias de consentimiento, idempotencia, revisión o bajas.

## Consecuencias

OAuth propio exige pruebas protocolarias, revisión de seguridad y control de concurrencia. No se afirma conformidad o interoperabilidad a partir de la referencia. La matriz real de clientes y perfiles debe demostrar expiración, reautorización, permisos y revocación. Las credenciales y callbacks de Google se suministran privadamente y ninguna evidencia pública contiene datos personales o secretos.

El contexto común reutiliza T-17/T-18 aceptadas y se integra antes del cierre global T-19/T-20. La propuesta no modifica ADR-001/002/004/005/007 ni promueve ADR-003. T-01 está en progreso; esta entrada no representa aprobación humana nueva.

La factibilidad Composer ya resuelta de forma aislada conserva Laravel 13.38.0/Sanctum 4.3.3 y propone Laravel MCP 1.1.1, Socialite 5.31.0, firebase/php-jwt 7.2.1 y league/oauth1-client 1.12.0. No actualizó/eliminó paquetes existentes ni creó vendor, ejecutó scripts/plugins o ignoró plataforma. No es instalación ni cualificación Linux/funcional. Las fixtures públicas y `scripts/check-mcp-contract.py` prueban consistencia de forma, no OAuth, autorización ni cifrado reales.

El handoff T-14 entrega pruebas propias y escenarios/fixtures/resultados MCP antes de ejecutar el QA global. No depende de que T-19/T-20 estén cerradas. Root conserva la obligación posterior de probar en T-19 QA cruzado, carga y restauración con MCP/Google y de incluir documentación y revisión final en T-20; aceptar el componente no acredita ese cierre global.

## Estado

`aceptada` técnicamente en revisión de dos lentes A+B+C, 2026-10-25, intento 1. T-01 acredita el contrato y sus comprobaciones estáticas. La implementación, seguridad runtime, Google y compatibilidad de clientes siguen sujetas a sus pruebas y puertas posteriores; no se atribuye aprobación humana nueva.
