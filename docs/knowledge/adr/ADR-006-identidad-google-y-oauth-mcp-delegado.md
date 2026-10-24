---
id: ADR-006
titulo: Identidad Google y OAuth MCP delegado por cuenta sobre Sanctum
estado: propuesta
fecha: 2026-10-24
iniciativa: mcp-google
---

# ADR-006: Identidad Google y OAuth MCP delegado por cuenta sobre Sanctum

## Contexto

El usuario solicita Google para la web y el acceso MCP desde Claude, Codex y OpenCode. La referencia solicitada `daycry/social-scheduler`, fijada a `7f542a548613eb535e767fc10081fc21dc5b792a`, separa login Socialite y OAuth MCP propio sobre Sanctum. Emailing añade cuentas compartidas y Teammates, por lo que aislar por usuario resulta insuficiente.

La [spec](../../roadmap/2026-10-24-mcp-google/spec.md), [evaluación](../../roadmap/2026-10-24-mcp-google/evaluation.md) y [análisis de referencia](../../roadmap/2026-10-24-mcp-google/reference-analysis.md) documentan el requisito y los límites del examen estático. No existe todavía interoperabilidad real comprobada. La decisión afecta identidad, persistencia, middleware, consentimiento y herramientas, y por ello se registra como propuesta transversal.

## Decisión

La implementación propuesta utiliza Socialite para identidad Google, Laravel MCP para Streamable HTTP y Sanctum para tokens opacos propios, con un servidor OAuth acotado de Authorization Code + PKCE S256. El grant une usuario, cliente, cuenta, recurso y scopes consentidos. Un guard exclusivo acepta sólo tokens de propósito MCP y revalida permisos Teammates vigentes en cada llamada y punto de mutación.

La identidad Google usa emisor/sub validado en servidor. La vinculación a usuario existente es explícita y autenticada; no hay autoenlace por coincidencia de email. Registro e invitaciones conservan sus reglas, sin roles derivados de Google. Google no se utiliza como SMTP ni concede acceso a Gmail/Drive.

Los códigos se guardan con hash, caducan y se consumen atómicamente. Los access tokens llevan recurso/propósito/grant verificables, con vida inicial propuesta de una hora. No se emite refresh inicialmente, como en la referencia; se reautoriza al expirar y se rechaza ese grant_type. No hay caso real de cliente que justifique una familia de refresh todavía. Si un cliente requerido demuestra necesitarla, se revisan ADR/contrato y sus pruebas antes de declarar compatibilidad, conservando el alcance original.

El [plan](../../roadmap/2026-10-24-mcp-google/improvement-plan.md) concreta registro controlado/preregistro, redirects, descubrimiento, catálogo inicial de 37 operaciones y evidencia exigida. T-01 valida paquetes/protocolo/contratos antes de instalar. API keys de cuenta, cookies web y bearer Google no autentican MCP. Las herramientas comparten servicios, outbox, idempotencia, cuotas y supresiones; el transporte sigue en Mailpit/log.

## Alternativas descartadas

- **Passport como base inicial**: no se incorpora en paralelo al patrón de referencia. No resuelve por sí solo cuenta/Teammates y el wrapper MCP general no sustituye scopes de negocio. Un requisito demostrado puede reabrir la decisión con evidencia.
- **Bearer Google o API key de cuenta para MCP**: confunden identidad, propósito, audiencia y ciclo de vida. Una conexión delegada debe perder acceso al revocarse su membresía.
- **Copiar aislamiento user_id y autoenlace de email de la referencia**: no conserva el modelo multi-cuenta ni la política de vínculo explícito de Emailing.
- **Refresh rotatorio inicial sin requisito demostrado**: añade persistencia, replay y revocación de familias sin prueba de necesidad; la interoperabilidad debe acreditar reautorización o justificar el cambio.
- **Herramientas con lógica propia de envío**: duplican autoridad y permiten divergencias de consentimiento, idempotencia, revisión o bajas.

## Consecuencias

OAuth propio exige pruebas protocolarias, revisión de seguridad y control de concurrencia. No se afirma conformidad o interoperabilidad a partir de la referencia. La matriz real de clientes y perfiles debe demostrar expiración, reautorización, permisos y revocación. Las credenciales y callbacks de Google se suministran privadamente y ninguna evidencia pública contiene datos personales o secretos.

El contexto común reutiliza la API T-17/T-18 y se integra antes del cierre global T-19/T-20. La propuesta no modifica ADR-001/002/004/005 ni promueve ADR-003. El plan y sus catorce tareas nacen en borrador; esta entrada no representa aprobación humana nueva.

El handoff T-14 entrega pruebas propias y escenarios/fixtures/resultados MCP antes de ejecutar el QA global. No depende de que T-19/T-20 estén cerradas. Root conserva la obligación posterior de probar en T-19 QA cruzado, carga y restauración con MCP/Google y de incluir documentación y revisión final en T-20; aceptar el componente no acredita ese cierre global.

## Estado

`propuesta` — pendiente de validación por revisión de dos lentes o por el usuario en la puerta correspondiente. La autorización vigente permite preparar e implementar el roadmap, pero no convierte esta decisión todavía no revisada en doctrina aceptada.
