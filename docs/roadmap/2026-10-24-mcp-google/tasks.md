---
# ventana compartida con improvement-plan.md
generacion:
  inicio: "2026-10-24T07:58:31Z"
  fin: "2026-10-24T08:14:16Z"
  fuente: "estimado"
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  duracion_reloj: "16m"
  ratio_usado: 300000
  ratio_origen: "default no calibrado (300000)"
  nota: "estima tokens/horas a juicio y márcalo como estimado"
  avisos: ["carpeta de transcripciones no disponible"]
estimacion_generacion:
  tokens: { entrada: 60000, salida: 20000 }
  horas_ia: 0.266667
  duracion: 16m
  metodo: Juicio para la ventana compartida de plan, ledger, test-plan y ADR; 80000 tokens divididos por el default no calibrado de 300000 tok/h. No es consumo real ni tiempo IA derivado del reloj. Sin atribución de T-16 ni del orquestador.
verificacion: obligatoria
---

# Checklist de Tareas — MCP y acceso con Google

| | |
|---|---|
| **Estado** | en-progreso |
| **Fecha** | 2026-10-24 |
| **Plan** | [`improvement-plan.md`](./improvement-plan.md) |
| **Diseño** | n/a; decisiones técnicas propuestas en el plan y T-01 |

> **⚠️ Ledger canónico de progreso.** Este fichero es la **fuente única de verdad** del avance del plan. **Cualquier** implementador —el agente `implementer`, el chat principal, o un orquestador SDD externo— **debe** marcar aquí cada tarea (checkbox + estado) al completarla y actualizar el resumen. Los ledgers propios de otras herramientas son **espejo**, no fuente.

Los IDs locales pertenecen a `2026-10-24-mcp-google`. Los IDs de la iniciativa principal llevan su slug completo. Estado actual: **3/14** aceptadas técnicamente; contratos e identidad/acceso Google completados en laboratorio tras revisión y QA independiente. OAuth, herramientas MCP, perfiles de despliegue y conexiones reales siguen pendientes, sin aprobación humana nueva.

Las horas y tokens de tareas/fases son **base** y heredan la evaluación; el total con margen es 201,6 h y 1.728.000 tokens. No se suma dos veces el +20 %. Tiempo/coste reales siguen desconocidos; el contador de progreso no mide consumo real.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---|---|---|---|---|---|---|
| Fase 1 — Contrato y decisiones | 1 | 1 | 100% | — / 8h | — / 0.333333h | — / 0.083333h | — / 100000 |
| Fase 2 — Identidad Google | 2 | 2 | 100% | — / 20h | — / 0.466667h | — / 0.116667h | — / 140000 |
| Fase 3 — Autorización delegada y MCP | 0 | 4 | 0% | — / 48h | — / 1.333333h | — / 0.333333h | — / 400000 |
| Fase 4 — Catálogo de negocio | 0 | 3 | 0% | — / 32h | — / 0.8h | — / 0.2h | — / 240000 |
| Fase 5 — Clientes y operación | 0 | 1 | 0% | — / 16h | — / 0.4h | — / 0.1h | — / 120000 |
| Fase 6 — QA, revisión y cierre | 0 | 3 | 0% | — / 44h | — / 1.466667h | — / 0.366667h | — / 440000 |
| **TOTAL** | **3** | **14** | **21%** | **— / 168h** | **— / 4.8h** | **— / 1.2h** | **— / 1440000** |

> **Horas → Jira.** Si se activa el opt-in, el worklog usa IA + supervisión de cada tarea. Esta planificación no crea issues ni publica documentos externos.

## Fase 1 — Contrato y decisiones

**Estado**: completado · **Estimado**: 8h base · **Real**: — · **Coste est.**: 400 € equivalentes + tokens por verificar · **Tokens est.**: 100000

### T-01 — Fijar autorización, contratos y dependencias

- **Descripción**: Convertir esta propuesta en un contrato técnico verificable antes de instalar paquetes. Conservar los dieciséis criterios y todos los módulos de la spec.
- **Partida**: P de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est. 8h · real null (sin telemetría)
- **Tiempo IA (ejec.)**: est. 0.333333h (estimado por tokens) · real null (sin telemetría)
- **Supervisión**: est. 0.083333h (25 % IA) · real null (sin telemetría)
- **Previsión IA**: 80000 in / 20000 out tok · coste 0.08 p_in + 0.02 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: 2026-10-21-plataforma-emailing/T-16 y /T-17 terminadas; sin dependencia inversa de T-18 hacia esta iniciativa
- **Archivos**: `scripts/check-mcp-contract.py`, `tests/Contracts/fixtures/mcp-v0.1.0/**`, `docs/aplicacion/mcp-contrato.md`, `docs/api/mcp-tools.json`, `docs/api/mcp-oauth.openapi.json`, `docs/knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md`, `docs/knowledge/README.md`, `docs/roadmap/2026-10-24-mcp-google/*.md`, `docs/roadmap/README.md`
- **Cubre (tests)**: fixtures contractuales positivos/negativos de las 37 operaciones, OAuth, errores y cursor; no son pruebas de aplicación ni E2E.
- **Verificación**: `python scripts/check-mcp-contract.py` → 37 operaciones, 76 schemas, 596 casos de herramienta, 26 de error, 48 OAuth y 34 del modelo de cursor, cero fallos; `openapi-lint.py docs/api/mcp-oauth.openapi.json` desde la instalación resuelta del plugin → `openapi-lint: spec VÁLIDA (0 errores)`, exit 0. El comando público no carga Laravel ni prueba cifrado, autorización o clientes reales.

**Criterios de aceptación**

- [x] Documentar decisión de OAuth acotado sobre Sanctum, con alternativas y condiciones de reversión; ADR sigue propuesta hasta su puerta de revisión.
- [x] Fijar PKCE S256, código de un uso, tokens de propósito MCP, reautorización sin refresh inicial, límites y políticas de redirección/registro descritos en el plan.
- [x] Alinear envío, consentimiento de destinatarios e idempotencia con el contrato T-17 sin inventar consentimiento ni sustituirlo por mail.send.
- [x] Verificar compatibilidad Composer de Laravel MCP y Socialite con el lock real; registrar versiones candidatas sin instalarlas durante esta tarea.

**Subtareas**

- [x] Registrar producto/versión objetivo y capacidades de cliente requeridas con fuentes oficiales; compatibilidad ejecutada continúa pendiente.
- [x] Definir esquemas de argumentos/resultados, errores, paginación y efectos; versionar el contrato y sus fixtures negativos.
- [x] Revisar nombres/rutas propuestos contra el T-16/T-18 aceptado antes de editar aplicación.

**Notas**:

- Implementación contractual preparada sobre `754935e9914fb92ca5632233ff60f2ea34c884c3` (T-18 aceptada en revisión 23 y QA 2/2). Las doce fuentes funcionales inventariadas y `composer.json`/`composer.lock` coinciden con la preparación v3; T-17 conserva SHA-256 `e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`.
- Alcance añadido expresamente: verificador y fixtures públicos versionados para que los negativos/esquemas sean reproducibles sin depender de `storage`. La preparación v1/v2/v3 permanece intacta. La matriz de permisos añadió explícitamente `mail.send` al solicitar remitentes desde `connection_info`.
- Contrato 0.1.0: OAuth acotado sobre Sanctum, código 300 s, token/grant 3600 s, S256, reautorización sin refresh, recurso/emisor configurados, registro público controlado y redirects exactos. ADR-006 se acepta técnicamente tras la revisión 1 registrada al final de este ledger. Recuperación Google-only y auditoría de identidad anclada al usuario quedan explícitas, sin cuenta arbitraria ni contraseña aleatoria presentada como recuperación.
- Composer aislado admitido: solver exit 0; Laravel MCP 1.1.1, Socialite 5.31.0, firebase/php-jwt 7.2.1 y league/oauth1-client 1.12.0; cero updates/removals existentes, vendor, scripts/plugins o requisitos de plataforma ignorados. Laravel 13.38.0/Sanctum 4.3.3 conservados. No se repite ni se confunde resolución Windows/PHP 8.4.18 con instalación o ejecución Linux.
- Los perfiles MCP 2025-11-25 (initialize) y 2026-07-28 (metadatos por petición, discovery opcional para cliente) son objetivos por cualificar en T-06. El inventario Codex CLI 0.163.0, Claude Code 2.1.304 y OpenCode V2 2.0.12 y sus fuentes oficiales no acreditan conexión; Google/clientes/ambos perfiles siguen pendientes en T-11/T-14. Claude Desktop es producto separado fuera de los tres objetivos iniciales.
- Excepción contractual explícita a 1 MiB/256 KiB propuestos: entrada cruda 4 MiB y salida completa 8 MiB para preservar payload válido de T-17 y ambas representaciones MCP, sin truncar. No es capacidad medida; serializador, bytes exactos, concurrencia, criptografía, 37 operaciones reales y permisos deben probarse en sus tareas posteriores.
- TDD n/a: contratos, fixtures y verificador estático; `.claude/dev.json` ausente, defaults false. No aplicación, SQL, broker, Docker, Google, navegador o cliente MCP ejecutados en T-01. Sin opt-in Jira/Confluence ni mensajes externos. Tier del rol es metadato del plugin; no se afirma un runtime Sonnet.
- Consumo real: tokens `null`, coste `null`, horas IA/humanas/supervisión `null`; medidor aislado sin transcripciones. El reloj no se convierte en horas IA. Marcador cerrado antes de revisión; sin imputación externa.
- Puerta contractual aceptada: revisión 1 A+B+C sin gaps y verificación estática independiente exit0; T-01/fase1 completadas, 1/14. No hay UI en esta tarea ni se fabrica qa-gate/E2E; la QA funcional de aplicación y los CA/manuales de Google/clientes permanecen en T-02–T-14.
- Gates estáticos finales: contrato `37 operaciones, 76 schemas, 596 herramienta / 26 error / 48 OAuth / 34 cursor; 0 fallos`; OpenAPI `spec VÁLIDA (0 errores)`; scope con `--base 754935e9914fb92ca5632233ff60f2ea34c884c3`: 15 archivos, exit 0, sin avisos ni exclusiones de usuario; privacidad `Public data check: 1577 files, 0 findings`; `git diff --check` exit 0. El scope sin base había incluido toda la historia de la rama: se acotó al incremento aceptado, sin excluir archivos. Ledger: 0 incoherencias; sus 13 avisos son Changelog aún no escrito de T-02–T-14, tareas que siguen en borrador y se rellenarán al implementarlas; no se inventa su changelog anticipadamente.
- **Changelog**: Se fijan los permisos, OAuth, límites y resultados de las 37 operaciones MCP, con ejemplos reproducibles y requisitos explícitos para Google y los clientes.


## Fase 2 — Identidad Google

**Estado**: completado · **Estimado**: 20h base · **Real**: — · **Coste est.**: 1000 € equivalentes + tokens por verificar · **Tokens est.**: 140000

### T-02 — Persistir identidades externas y resolver vínculos

- **Descripción**: Añadir identidad estable Google vinculada explícitamente al usuario local. Resolver conflictos sin depender de coincidencias de correo ni de su collation.
- **Partida**: C-01 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.186667h (estimado por tokens) · real —
- **Supervisión**: est. 0.046667h (25 % IA) · real —
- **Previsión IA**: 48000 in / 8000 out tok · coste 0.048 p_in + 0.008 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01
- **Tipo**: backend
- **Archivos**: `composer.json`, `composer.lock`, `app/Models/User.php`, `app/Models/ExternalIdentity.php`, `app/Services/Auth/GoogleIdentityResolver.php`, `database/migrations/*_create_external_identities.php`, `tests/Feature/GoogleIdentityTest.php`, `docs/aplicacion/acceso-google.md`, `app/Models/IdentityAuditEvent.php`, `app/Actions/Fortify/ResetUserPassword.php`, `tests/support/google-identity-action.php`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter=GoogleIdentityTest` → vínculos, conflictos, cambios de email y desvínculo sin acceso cruzado

**Criterios de aceptación**

- [x] Unicidad de proveedor/emisor/sub y relación local preservan la identidad si Google cambia el email.
- [x] Una coincidencia de email no vincula ni fusiona automáticamente; un sub ya vinculado a otra persona rechaza la operación.
- [x] Desvincular exige sesión autenticada y método de recuperación viable; no se almacenan tokens Google que no se necesitan.

**Subtareas**

- [x] Instalar Socialite estable resuelto y registrar el lock después de la validación de T-01.
- [x] Implementar resolución transaccional, vínculo explícito y desvínculo, con auditoría mínima.
- [x] Preparar fixtures ficticios, pruebas de unicidad concurrente y rollback.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.


- Scope T-02: user-owned identity audit and explicit local-password state require the additive migration, ResetUserPassword and independent-process fixture above; no account/session authority. Await formal acceptance after implementation.


- Implementation T-02 verified, pending root review/QA acceptance; phase counters remain 1/14. `php artisan test --filter=GoogleIdentityTest`: 6 passed (34 assertions), Duration 132.08s, exit0, unchanged source snapshot 704961568ab2937b673beb51fe665e3eb8978e0f254ab0c06b00c76a176f5c62. Evidence: storage/qa-delivery/mcp-google-t02-20261025T001903035355Z. Pint passed. Socialite5.31.0, php-jwt7.2.1, oauth1-client1.12.0 added; no existing dependencies updated/removed. First run: 6 passed (33 assertions), 155.43s; then exact VARBINARY coverage added. Real tokens/cost/hours null without telemetry.
- **Changelog**: Google identities now use exact stable keys, explicit transactional linking and recovery-aware unlinking, with user-owned audit and real concurrent tests.

### T-03 — Integrar Google en acceso, perfil y admisión

- **Descripción**: Ofrecer Google en login y vinculación del perfil sin alterar la política existente de registro e invitaciones. Retomar el consentimiento MCP tras autenticar.
- **Partida**: C-01 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.28h (estimado por tokens) · real —
- **Supervisión**: est. 0.07h (25 % IA) · real —
- **Previsión IA**: 72000 in / 12000 out tok · coste 0.072 p_in + 0.012 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-02
- **Tipo**: frontend
- **Archivos**: `app/Http/Controllers/Auth/GoogleLoginController.php`, `app/Services/Auth/GoogleLoginFlow.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Providers/FortifyServiceProvider.php`, `config/services.php`, `config/emailing.php`, `.env.example`, `routes/web.php`, `resources/views/auth/login.blade.php`, `resources/views/auth/register.blade.php`, `resources/views/auth/identities.blade.php`, `tests/Feature/GoogleLoginTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/aplicacion/acceso-google.md`, `docs/README.md`, `app/Services/Auth/GoogleTokenVerifier.php`, `playwright.config.mjs`, `tests/support/google-browser-server.php`, `tests/support/google-browser-fixtures.php`, `tests/support/google-browser-launch.mjs`, `tests/support/google-browser-teardown.mjs`, `package.json`, `package-lock.json`, `composer.json`, `composer.lock`, `database/migrations/*_create_google_login_attempts.php`, `tests/support/google-flow-consume.php`, `resources/views/auth/google-confirm.blade.php`, `resources/views/auth/google-button.blade.php`, `resources/views/components/app-layout.blade.php`, `docs/knowledge/gotchas/GOT-012-proxy-y-redirecciones-playwright.md`, `docs/knowledge/README.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-2/google/**`, `docs/RAG-INDEX.md`, `docs/roadmap/README.md`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, M-01
- **Verificación**: `php artisan test --filter=GoogleLoginTest` → callback, state, identidad inválida, admisión, invitación y retorno seguros; `npx playwright test tests/e2e/mcp-google.spec.mjs --grep "Google"` → E2E-01–03 con proveedor simulado

**Criterios de aceptación**

- [x] State, código, emisor/sub y datos de identidad inválidos o reutilizados no crean sesión, usuario ni vínculo.
- [x] Registro permitido crea cuenta propia sólo tras la confirmación explícita del flujo; invitación conserva la comprobación del destinatario.
- [x] Filtro Workspace configurable valida identidad de servidor y no sólo el sufijo visible del email; Google no concede rol.
- [x] Google sin configurar muestra un estado comprensible y conserva acceso local; mensajes no revelan existencia de cuentas ajenas.

**Subtareas**

- [x] Implementar redirect/callback con retorno local validado, sin open redirect ni confianza en Host.
- [x] Añadir login, vínculo/desvínculo del perfil y errores visibles, conservando protecciones Fortify existentes.
- [x] Documentar scopes mínimos de identidad, callbacks y configuración privada por entorno.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.


- Scope T-03: fixed-endpoint JWT verification and HTTP-boundary browser provider fixtures require the additional verifier, Playwright configuration/dev dependency and guarded browser helpers above. No Google real credentials or future MCP consent are simulated as completed.


- Direct JWT library use is declared in Composer in T-03, preserving the already installed 7.2.1 candidate and all existing versions. Google quotas remain 15 start, 30 callback, 5 confirmation and 15 unlink per IP/minute, with distinct counters per operation and an explicit 429 test.

- Implementación T-03 verificada, pendiente de revisión y QA independiente: no se cierran T-02/T-03 ni se eleva el contador aceptado 1/14. GoogleLoginTest final: 13 pruebas, 418 assertions, 277.15s, exit0; E2E-01–03: 3 passed (1.3m), exit0. Ambas cápsulas `mcp-google-login-final-invitation-20261025T011856716785Z` y `mcp-google-e2e-final-invitation-20261025T012437963803Z` contienen 289 fuentes inmutables. El retorno conserva el token alfanumérico exacto capturado de TeamService; el rojo original se conserva separado. Firma JWT, CSRF, consumo SQL concurrente, roles y mutaciones son reales; sólo proveedor HTTP simulado.
- La batería conjunta previa al ajuste de retorno (`mcp-google-phase2-final-20261025T010600891406Z`) pasó GoogleIdentityTest 6/34, GoogleLoginTest 13/415 y regresiones de acceso/recuperación/equipos 27/248. No se atribuye ese verde al snapshot posterior: el último ajuste sólo modifica retorno, su prueba y el token sintético; resolución de identidades, recuperación y servicios de equipo permanecen intactos. Se repitieron login completo y los tres E2E después del ajuste.
- Perfil acreditado: navegador Chromium real sobre PHP cli-server nativo Laragon, SQL emailing_testing guardada y proveedor sintético. No acredita Google real, Apache/Nginx/Docker ni clientes reales. M-01 humano y matriz real siguen pendientes en T-11/T-14; T-05/T-07/T-12 deberán integrar y acreditar el consentimiento MCP completo conservando el retorno seguro de servidor. Fallos originales, prueba causal de proxy, controles de red y fallo de arranque están conservados en `storage/mcp-google-phase2/handoff.md`; GOT-012 es propuesta pendiente de revisión. Tokens, coste y horas reales null por falta de telemetría; reloj no equivale a horas IA.
- **Changelog**: Acceso y registro explícito con Google, perfil y retorno seguro conservan Fortify, invitaciones y roles locales; JWT firmado y state de un solo uso se verifican con SQL y navegador reales.

## Fase 3 — Autorización delegada y MCP

**Estado**: borrador · **Estimado**: 48h base · **Real**: — · **Coste est.**: 2400 € equivalentes + tokens por verificar · **Tokens est.**: 400000

### T-04 — Introducir contexto delegado y autorización vigente

- **Descripción**: Extender el contexto común con actor/cuenta/grant explícitos y conservar el adaptador web. Compartir la autorización de servicios sin suplantar la sesión global.
- **Partida**: C-03 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01; 2026-10-21-plataforma-emailing/T-18 aceptada para reutilizar su contexto API y servicios
- **Tipo**: backend
- **Archivos**: `app/Services/AccountContext.php`, `app/Services/Permissions.php`, `app/Services/TeamService.php`, `app/Services/ContactService.php`, `app/Services/ContactImportService.php`, `app/Services/TemplateService.php`, `app/Services/ActivityService.php`, `app/Services/CampaignService.php`, `app/Services/SuppressionService.php`, `app/Support/ExecutionContext.php`, `app/Services/Mcp/DelegatedContext.php`, `tests/Feature/McpAuthorizationTest.php`, `tests/Feature/McpRevocationConcurrencyTest.php`, `docs/aplicacion/arquitectura.md`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter="McpAuthorizationTest|McpRevocationConcurrencyTest"` → intersección y revocación con barreras SQL; regresiones web/API aceptadas sin auth/session global artificial

**Criterios de aceptación**

- [ ] Permiso efectivo = scopes del grant ∩ scopes del token ∩ permisos actuales de membresía ∩ permisos de la operación.
- [ ] La cuenta procede del grant verificado; account_id y IDs del cliente nunca seleccionan otra cuenta.
- [ ] Cada mutación revalida grant y membresía en su transacción con orden de bloqueo definido; una revocación confirmada impide nuevas mutaciones.
- [ ] La revocación de membresía afecta al grant personal; el ciclo de vida de API keys de cuenta de T-18 se conserva separado.

**Subtareas**

- [ ] Reutilizar el contexto adoptado en T-18; añadir sólo el adaptador delegado y los cambios de servicio necesarios.
- [ ] Eliminar dependencias de auth()/session() de las rutas de negocio usadas por MCP mediante parámetros/adaptadores explícitos.
- [ ] Probar sesión ausente, multi-cuenta, role read-only/restricted/admin y carreras revocación-mutación.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-05 — Implementar servidor OAuth, códigos y reautorización

- **Descripción**: Adaptar el patrón OAuth de referencia al recurso y grants de Emailing. Emitir credenciales propias, opacas y separadas de Google y de las API keys.
- **Partida**: C-02 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.583333h (estimado por tokens) · real —
- **Supervisión**: est. 0.145833h (25 % IA) · real —
- **Previsión IA**: 150000 in / 25000 out tok · coste 0.15 p_in + 0.025 p_out €; tarifa por verificar
- **Coste estimado**: 1000 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01, T-04
- **Tipo**: backend
- **Archivos**: `app/Models/McpClient.php`, `app/Models/McpGrant.php`, `app/Models/McpAuthorizationCode.php`, `app/Models/PersonalAccessToken.php`, `app/Services/Mcp/OAuth/*`, `app/Http/Controllers/Mcp/OAuth/*`, `database/migrations/*_create_mcp_authorization.php`, `config/mcp.php`, `routes/mcp-oauth.php`, `tests/Feature/McpOAuthTest.php`, `tests/Feature/McpOAuthConcurrencyTest.php`, `docs/api/mcp-oauth.openapi.json`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter="McpOAuthTest|McpOAuthConcurrencyTest"` → PKCE, redirects, código de un uso, recurso, registro, expiración, reautorización y concurrencia pasan

**Criterios de aceptación**

- [ ] Metadatos de recurso/servidor y desafío apuntan al origen configurado y a los endpoints implementados; no se derivan de Host.
- [ ] Código con hash, TTL 300 s y consumo atómico se liga a cliente, redirect exacto, S256, recurso y grant consentido.
- [ ] Access token Sanctum opaco: propósito MCP, emisor/recurso, grant y TTL 3600 s; sin refresh inicial, con reautorización explícita al expirar.
- [ ] Grant_type refresh_token se rechaza; reautorizar vuelve a comprobar membresía y scopes sin recuperar acceso revocado.
- [ ] Preregistro y DCR controlado de clientes públicos siguen política de redirects y límites; errores previos a validar redirect nunca redirigen a destino no confiable.

**Subtareas**

- [ ] Crear persistencia e índices/constraints para códigos, grants, tokens y cliente; secretos de clientes confidenciales sólo hash si se habilitan.
- [ ] Implementar descubrimiento, autorización, canje y revocación con respuesta sin caché y errores protocolarios.
- [ ] Aplicar límites de registro y token; no recuperar metadatos de URLs arbitrarias ni habilitar grants password/client_credentials.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-06 — Servir MCP con guard dedicado y sesiones acotadas

- **Descripción**: Instalar Laravel MCP y exponer Streamable HTTP con validación Bearer específica antes de negociar o ejecutar herramientas. Una sesión MCP no sustituye una credencial vigente.
- **Partida**: C-02 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.35h (estimado por tokens) · real —
- **Supervisión**: est. 0.0875h (25 % IA) · real —
- **Previsión IA**: 90000 in / 15000 out tok · coste 0.09 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-05
- **Tipo**: backend
- **Archivos**: `composer.json`, `composer.lock`, `routes/ai.php`, `bootstrap/app.php`, `app/Mcp/Servers/EmailingServer.php`, `app/Http/Middleware/McpBearer.php`, `app/Services/Mcp/TokenResolver.php`, `config/mcp.php`, `tests/Feature/McpTransportTest.php`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter=McpTransportTest` → initialize/list/call, versión/transporte, límites y credenciales negativas; `composer validate --strict` → exit 0

**Criterios de aceptación**

- [ ] Guard dedicado acepta sólo el token MCP válido ligado al recurso, grant, cliente y usuario; no usa fallback stateful de Sanctum.
- [ ] Cookie web sin bearer, token Google, API key de cuenta, token otro recurso/propósito, expirado o revocado no ejecutan servicios.
- [ ] Sesiones persistentes vuelven a validar cada llamada; lista y llamadas directas aplican capacidades vigentes.
- [ ] Límites de cuerpo, tamaño de resultado, paginación y concurrencia rechazan excesos de forma definida; no activan otro transporte sin revisión.

**Subtareas**

- [ ] Instalar MCP estable resuelto compatible con Laravel 13 y conservar lock.
- [ ] Integrar protocolo y manejo de errores sin copiar user_id como frontera de cuenta.
- [ ] Verificar Origin/transporte, caché de metadatos y expiración en sesiones abiertas.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-07 — Añadir consentimiento, conexiones y auditoría

- **Descripción**: Permitir elegir cuenta y permisos, aprobar o denegar la conexión y revocarla desde la web. Registrar cuenta, actor, cliente y correlación en operaciones MCP.
- **Partida**: C-03 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-03, T-04, T-06
- **Tipo**: frontend
- **Archivos**: `app/Livewire/McpConnections.php`, `resources/views/livewire/mcp-connections.blade.php`, `resources/views/mcp/consent.blade.php`, `resources/views/components/app-layout.blade.php`, `routes/web.php`, `app/Services/Mcp/GrantService.php`, `app/Services/Mcp/McpAudit.php`, `app/Models/AuditEvent.php`, `tests/Feature/McpConsentTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/aplicacion/conexiones-mcp.md`, `docs/README.md`
- **Cubre (tests)**: E2E-04, E2E-05, E2E-06, M-02
- **Verificación**: `php artisan test --filter=McpConsentTest` → cuenta/scopes, rechazo, CSRF y revocación correctos; `npx playwright test tests/e2e/mcp-google.spec.mjs --grep "consentimiento|conexiones"` → E2E-04–06

**Criterios de aceptación**

- [ ] Pantalla presenta cliente, cuenta y capacidades concretas; sólo ofrece membresías y permisos actuales, con POST+CSRF para aprobar/denegar. El canje devuelve el token al cliente automáticamente: no exige crear/copiar una API key en cada cliente.
- [ ] Cambiar de cuenta exige un grant nuevo; no se modifica el account_id de una conexión emitida.
- [ ] La persona sólo gestiona sus conexiones; revocar corta tokens y sesiones de esa conexión sin tocar otra cuenta. Refresh no se emite y su grant_type se rechaza.
- [ ] Auditoría de éxito y denegación identifica actor, cuenta, cliente, operación y correlación sin tokens, secretos o contenido completo innecesario.

**Subtareas**

- [ ] Añadir vistas de consentimiento y conexiones con estados vacíos, errores y confirmación de revocación.
- [ ] Preservar solicitud OAuth validada durante login local/Google, sin aceptar retorno externo arbitrario.
- [ ] Documentar diferencia entre iniciar sesión, consentir cliente y autorizar un envío.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

## Fase 4 — Catálogo de negocio

**Estado**: borrador · **Estimado**: 32h base · **Real**: — · **Coste est.**: 1600 € equivalentes + tokens por verificar · **Tokens est.**: 240000

### T-08 — Herramientas de contactos, listas, importaciones y plantillas

- **Descripción**: Implementar veinte operaciones de datos con argumentos/resultados contractuales y servicios comunes. No crear una lógica paralela de validación o permisos.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.3h (estimado por tokens) · real —
- **Supervisión**: est. 0.075h (25 % IA) · real —
- **Previsión IA**: 75000 in / 15000 out tok · coste 0.075 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-06, T-07
- **Tipo**: backend
- **Archivos**: `app/Mcp/Tools/Contacts/*`, `app/Mcp/Tools/Lists/*`, `app/Mcp/Tools/Imports/*`, `app/Mcp/Tools/Templates/*`, `app/Mcp/Tools/EmailingTool.php`, `app/Services/ContactService.php`, `app/Services/ContactImportService.php`, `app/Services/TemplateService.php`, `tests/Feature/McpDataToolsTest.php`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter=McpDataToolsTest` → cada una de las 20 operaciones positiva/negativa, IDs ajenos, límites y ausencia de secretos

**Criterios de aceptación**

- [ ] Contactos 5, listas 7, reportes de importación 2 y plantillas 6 implementan las 20 operaciones del catálogo inicial.
- [ ] Cada herramienta cruza grant y membresía con contacts.read/write o templates.read/write; IDs ajenos se rechazan sin revelar existencia.
- [ ] Resultados paginados y errores siguen contrato; preview usa renderer seguro y no expone credenciales ni rutas internas.
- [ ] Mutaciones destructivas exigen confirmación y versión cuando el contrato de dominio la requiere; permisos se verifican también en llamadas directas.

**Subtareas**

- [ ] Crear clase base de autorización/auditoría/errores y adaptadores pequeños a servicios.
- [ ] Probar cada esquema, resultado, permiso, validación y límites.
- [ ] Mantener importación de archivos por su flujo existente; estas dos herramientas consultan reportes, no aceptan paths del host.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-09 — Herramientas de campañas, actividad y supresiones

- **Descripción**: Implementar catorce operaciones del flujo de negocio con revisiones, versiones, autorización de envío y controles actuales de bajas.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.3h (estimado por tokens) · real —
- **Supervisión**: est. 0.075h (25 % IA) · real —
- **Previsión IA**: 75000 in / 15000 out tok · coste 0.075 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-08
- **Tipo**: backend
- **Archivos**: `app/Mcp/Tools/Campaigns/*`, `app/Mcp/Tools/Activity/*`, `app/Mcp/Tools/Suppressions/*`, `app/Services/CampaignService.php`, `app/Services/ActivityService.php`, `app/Services/SuppressionService.php`, `tests/Feature/McpBusinessToolsTest.php`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter=McpBusinessToolsTest` → 14 operaciones, revisión/versiones, UTC, cuotas, aislamiento y supresiones

**Criterios de aceptación**

- [ ] Campañas 8, actividad 3 y supresiones 3 cubren el catálogo descrito, con permisos de cada operación.
- [ ] Programar/autorizar exige campaigns.write y mail.send, revisión vigente, confirmación e idempotencia según el dominio común.
- [ ] Pausa/reanudación y revisiones obsoletas conservan sus conflictos de negocio; no duplican materialización ni checkpoint.
- [ ] Gestionar supresiones no inventa una nueva prueba de consentimiento; se conservan los controles de baja y destinatario existentes.

**Subtareas**

- [ ] Adaptar servicios explícitos de campaña y las consultas de actividad/supresión.
- [ ] Añadir anotaciones de efectos e inputs verificables, con pruebas de invocación directa.
- [ ] Probar caída de broker y estado seguro sin envío alternativo.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-10 — Envío transaccional y estado sobre el pipeline común

- **Descripción**: Añadir información de conexión y dos operaciones de envío/estado. Compartir exactamente la autoridad de T-18 para contenido, destinatarios, idempotencia, outbox y supresiones.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-09; 2026-10-21-plataforma-emailing/T-18 terminada y aceptada
- **Tipo**: backend
- **Archivos**: `app/Mcp/Tools/ConnectionInfo.php`, `app/Mcp/Tools/SendMail.php`, `app/Mcp/Tools/GetMailRequest.php`, `app/Services/Mcp/McpSendAdapter.php`, `tests/Feature/McpSendTest.php`, `tests/Feature/McpApiIdempotencyTest.php`, `tests/support/mcp-api-concurrency.php`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter="McpSendTest|McpApiIdempotencyTest"` → concurrencia API/MCP un solo trabajo, conflicto de payload, otra cuenta aislada y entrega Mailpit/log

**Criterios de aceptación**

- [ ] 37 operaciones totales: información de conexión más las 36 operaciones de negocio; no se sustituye el catálogo por una demostración.
- [ ] Envío admite las mismas modalidades de contenido/plantilla y destinatarios del contrato T-17/T-18; templates.read se exige cuando corresponde.
- [ ] Misma cuenta, clave y payload MCP/API generan un único request/trabajo; otro payload genera conflicto; otra cuenta no comparte clave.
- [ ] mail.send no acredita consentimiento del destinatario; cuota, supresión y autorización se vuelven a comprobar en el pipeline y antes del SMTP.
- [ ] Estado distingue aceptación de solicitud, publicación, aceptación SMTP y resultado incierto; Mailpit/log sigue siendo el único transporte habilitado.

**Subtareas**

- [ ] Reutilizar el servicio de solicitudes de T-18 sin crear campañas/contactos ficticios ni un segundo camino SMTP.
- [ ] Correlacionar cliente/grant con request, outbox y auditoría sin exponer el token.
- [ ] Verificar concurrencia real con barreras y conteo de trabajos/mensajes atribuidos.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

## Fase 5 — Clientes y operación

**Estado**: borrador · **Estimado**: 16h base · **Real**: — · **Coste est.**: 800 € equivalentes + tokens por verificar · **Tokens est.**: 120000

### T-11 — Preparar clientes, Laragon, Docker y recuperación

- **Descripción**: Documentar y preparar configuración reproducible de los tres clientes y ambos perfiles. Conservar identidad y revocación tras reinicio/restauración, sin duplicar infra.
- **Partida**: C-05 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.4h (estimado por tokens) · real —
- **Supervisión**: est. 0.1h (25 % IA) · real —
- **Previsión IA**: 100000 in / 20000 out tok · coste 0.1 p_in + 0.02 p_out €; tarifa por verificar
- **Coste estimado**: 800 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-10
- **Tipo**: devops
- **Archivos**: `docs/aplicacion/acceso-google.md`, `docs/aplicacion/conexiones-mcp.md`, `docs/aplicacion/mcp-clientes.md`, `docs/aplicacion/desarrollo.md`, `docs/aplicacion/arquitectura.md`, `docs/README.md`, `.env.example`, `compose.yaml`, `docker/nginx.conf`, `scripts/mcp-profile-evidence.py`, `tests/Feature/McpPersistenceTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/roadmap/2026-10-24-mcp-google/testing/client-matrix.md`
- **Cubre (tests)**: E2E-01, E2E-04, E2E-06, M-01, M-03, M-04
- **Verificación**: `php artisan test --filter=McpPersistenceTest` → persistencia/revocación; `python scripts/mcp-profile-evidence.py --profile laragon` y `--profile docker` → evidencias de ambos perfiles y cleanup atribuido; lectura: matriz cliente producto/versión y callbacks

**Criterios de aceptación**

- [ ] Laragon y Docker usan mismos contratos, migraciones y claves/configuración persistentes; ambos perfiles prueban reinicio y revocación.
- [ ] Ejemplos Codex, Claude Code y OpenCode son ficticios; Claude Desktop se identifica por separado y no se confunde con Code ni con cliente alojado.
- [ ] Matriz registra producto/versiones realmente utilizadas, URL, transporte, registro y límites de red; una fila documental no figura aprobada.
- [ ] Backups/rotación restauran datos necesarios y conservan revocaciones; restaurar un snapshot antiguo exige invalidar grants/tokens potencialmente reactivados.
- [ ] Credenciales y callbacks reales se suministran por configuración privada del operador; documentación pública no contiene secretos ni datos personales.

**Subtareas**

- [ ] Implementar runner reproducible de perfil y registrar fuentes/hashes/request/recibos; nunca usar migrate:fresh sobre la base operativa.
- [ ] Definir configuración canónica de recurso/origen/proxy/TLS y callbacks exactos por cliente/entorno.
- [ ] Documentar instalación, configuración, revocación, rotación, backup/restore y diagnóstico.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

## Fase 6 — QA, revisión y cierre

**Estado**: borrador · **Estimado**: 44h base · **Real**: — · **Coste est.**: 2200 € equivalentes + tokens por verificar · **Tokens est.**: 440000

### T-12 — QA automatizado y regresión cruzada

- **Descripción**: Consolidar pruebas de identidad/OAuth/MCP, catálogo y concurrencia sobre fuentes congeladas. Las simulaciones y los recorridos externos conservarán categorías distintas.
- **Partida**: C-06 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 16h · real —
- **Tiempo IA (ejec.)**: est. 0.453333h (estimado por tokens) · real —
- **Supervisión**: est. 0.113333h (25 % IA) · real —
- **Previsión IA**: 110000 in / 26000 out tok · coste 0.11 p_in + 0.026 p_out €; tarifa por verificar
- **Coste estimado**: 800 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-11
- **Tipo**: test
- **Archivos**: `tests/Feature/*Mcp*Test.php`, `tests/Feature/Google*Test.php`, `tests/e2e/mcp-google.spec.mjs`, `tests/support/mcp-api-concurrency.php`, `playwright.config.mjs`, `package.json`, `package-lock.json`, `scripts/mcp-profile-evidence.py`, `docs/roadmap/2026-10-24-mcp-google/test-plan.md`, `docs/roadmap/2026-10-24-mcp-google/testing/**`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06
- **Verificación**: `php artisan test` → suite completa verde; `npx playwright test tests/e2e/mcp-google.spec.mjs` → E2E-01–06 en ambos perfiles; `npm run build` → exit 0

**Criterios de aceptación**

- [ ] Se ejecutan negativos protocolarios, 37 operaciones, aislamiento por cuenta, revocación concurrente y concurrencia API/MCP.
- [ ] Capturas y manifiestos identifican fuentes/runtime/migraciones exactos, resultado terminal y recursos creados; limpieza afecta sólo fixtures propios.
- [ ] No se declaran QA real de Google/clientes ni aprobación manual a partir de mocks, documentación o tests de unidad.
- [ ] Regresiones de autenticación local, Teammates, API keys, envíos, revisión, cuotas, supresiones, workers y scheduler pasan con Mailpit/log.

**Subtareas**

- [ ] Añadir los escenarios automatizados y configuración Playwright ausente sin duplicar runners existentes adoptados entretanto.
- [ ] Ejecutar QA focal y suite aceptada; reconciliar fuente de cada evidencia antes de publicar.
- [ ] Actualizar matriz CA y clasificar fallos con evidencia; conservar intentos previos.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-13 — Cerrar revisión independiente y corregir defectos

- **Descripción**: Presupuestar revisiones y correcciones de toda la ampliación, además de la revisión consolidada final. Cada incremento mantiene las puertas del workflow, sin diferirlas a esta tarea.
- **Partida**: R de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.8h (estimado por tokens) · real —
- **Supervisión**: est. 0.2h (25 % IA) · real —
- **Previsión IA**: 200000 in / 40000 out tok · coste 0.2 p_in + 0.04 p_out €; tarifa por verificar
- **Coste estimado**: 1000 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-12; revisiones de cada incremento ejecutadas durante T-02–T-12
- **Archivos**: `app/**`, `routes/**`, `config/**`, `database/migrations/*`, `resources/**`, `tests/**`, `scripts/mcp-profile-evidence.py`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `.env.example`, `compose.yaml`, `docker/**`, `docs/aplicacion/**`, `docs/api/**`, `docs/knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md`, `docs/knowledge/README.md`, `docs/roadmap/2026-10-24-mcp-google/testing/**`, `docs/roadmap/2026-10-24-mcp-google/tasks.md`
- **Cubre (tests)**: —
- **Verificación**: lectura: informes independientes de conformidad/corrección y lentes seguridad/rendimiento según selector; cero Critical/Important pendientes y fixes reprobados; `php artisan test` → regresión completa verde tras la última corrección

**Criterios de aceptación**

- [ ] Revisión cita el diff inmutable y los dieciséis criterios; evalúa OAuth, linking, redirects, audiencia, scopes, revocación, SSRF y secretos.
- [ ] Lentes adicionales se seleccionan con el script del workflow; no se presenta una autorrevisión como revisión independiente.
- [ ] Cada corrección tiene verificación y fuentes vigentes; el ciclo respeta máximo tres intentos y conserva hallazgos, rebajas y evidencia.
- [ ] Sólo una puerta limpia permite promover ADR propuesta; no se marca aceptación humana ni cierre de QA real pendiente.

**Subtareas**

- [ ] Distribuir esta partida entre puertas incrementales y consolidación sin sumar otras 20 h por cada tarea.
- [ ] Resolver defectos y repetir únicamente verificaciones justificadas por los cambios.
- [ ] Registrar límites pendientes y entregar fuente exacta al QA externo.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-14 — Acreditar Google y clientes y entregar al QA global

- **Descripción**: Ejecutar recorridos reales propios con configuración privada y dejar evidencia por producto/versión. Entregar escenarios, fixtures y resultados MCP/Google para el QA y la documentación global, conservando el objetivo original.
- **Partida**: C-06 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.213333h (estimado por tokens) · real —
- **Supervisión**: est. 0.053333h (25 % IA) · real —
- **Previsión IA**: 50000 in / 14000 out tok · coste 0.05 p_in + 0.014 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-13; credenciales Google configuradas privadamente, sesiones de clientes y conectividad del operador. Esta tarea precede a la ejecución y cierre globales de 2026-10-21-plataforma-emailing/T-19 y /T-20; no depende de que ya se hayan ejecutado o cerrado.
- **Tipo**: test
- **Archivos**: `docs/roadmap/2026-10-24-mcp-google/testing/**`, `docs/roadmap/2026-10-24-mcp-google/tasks.md`, `docs/roadmap/2026-10-24-mcp-google/spec.md`, `docs/roadmap/2026-10-24-mcp-google/improvement-plan.md`, `docs/roadmap/README.md`, `docs/aplicacion/mcp-clientes.md`, `docs/aplicacion/acceso-google.md`, `docs/aplicacion/conexiones-mcp.md`, `docs/README.md`, `CHANGELOG.md`
- **Cubre (tests)**: E2E-01, E2E-04, E2E-06, M-01, M-02, M-03, M-04
- **Verificación**: lectura: M-01–04 y CA-01–15 propios ejecutados con recibos/capturas sanitizados y matriz real completa; `python scripts/check-public-data.py` → cero hallazgos antes de publicación; lectura: CA-16 trazado mediante handoff de escenarios/fixtures/resultados y requisitos de integración incluidos en T-19/T-20 por root, sin exigir su ejecución global previa

**Criterios de aceptación**

- [ ] Google real accede/vincula/desvincula y vuelve a consentimiento en ambos perfiles con cuentas autorizadas, sin secretos publicados.
- [ ] Codex, Claude Code y OpenCode reales completan descubrimiento, consentimiento, llamada permitida/denegada, expiración/renovación y revocación.
- [ ] Claude Desktop conserva su estado separado; si se incluye como objetivo en T-01 debe ejecutar la misma matriz antes del cierre, sin etiquetar configuración como éxito.
- [ ] Se entregan a T-19 los escenarios, fixtures y resultados MCP/Google para QA cruzado, carga y restauración, y a T-20 los contratos, documentos y evidencias de revisión. Root registra y verifica esos requisitos de integración en el ledger principal; no se exige aquí que T-19/T-20 ya se hayan ejecutado o cerrado.
- [ ] Sin evidencia externa o validación manual propias, las casillas correspondientes y esta tarea siguen pendientes. Aceptar este componente no declara ejecutado el QA global; root conserva la obligación de ejecutarlo con MCP/Google y 20/20 originales no completa el objetivo ampliado por sí solo.

**Subtareas**

- [ ] Coordinar sesión del operador con credenciales fuera de Git/chat; ejecutar M-01–04 sin solicitar secretos en texto.
- [ ] Publicar sólo proyecciones ficticias o sanitizadas con manifiestos y conservar evidencia privada atribuible.
- [ ] Cerrar este componente, sus backlinks y changelog tras sus pruebas propias y el handoff; root coordina y verifica después las pruebas y el cierre globales en el ledger principal.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

## Changelog del ledger

- 2026-10-24: creación de 14 tareas en borrador; seis características y partidas P/R conservadas, sin cambios en el ledger principal ni ejecución de servicios.
- 2026-10-24: corrección previa a implementación: T-14 entrega evidencia propia antes del QA global, sin dependencia circular de T-19/T-20; OAuth sin refresh y ruta del linter precisados. No es un intento formal de revisión o QA.

- 2026-10-25: T-01 inicia implementación contractual 0.1.0 con fixtures/verificador públicos; permanece en-progreso hasta revisión y QA, sin aplicación Google/MCP ejecutada.


## Revisión de dos lentes — intento 1: Fase 1 (T-01) — contrato aceptado sin gaps pendientes

Lentes A+B+C frescas en paralelo, fallback genérico heredado: reviewer por nombre
no disponible; no se atribuye ejecución Opus. C activada por rutas de fixtures
OAuth/permisos; D no activada por el selector oficial. Alcance independiente
exit0, 15 archivos sin fuera de alcance, exclusiones o avisos. Cápsula privada
inmutable de35 archivos, manifiesto SHA-256
`08d42e3a65f903d2720db25c2ec4ca8c522603748f2d36cac95a45aef1174a25`.
Las tres lentes verificaron todos sus hashes antes y después; root verificó
además las 15 fuentes actuales. Base aceptada `754935e9914fb92ca5632233ff60f2ea34c884c3`.

| T-01: criterio de aceptación | Veredicto | Evidencia |
|---|---|---|
| OAuth acotado sobre Sanctum, alternativas y reversión | ✓ | ADR-006:18,24,36,44; contrato narrativo y estado técnico trazado. |
| S256, código de un uso, propósito, caducidades, reautorización, límites, redirects y registro | ✓ | mcp-oauth.openapi.json:2056,2069,2076; código300s/token y grant3600s, sin refresh; excepción4MiB/8MiB expresa. |
| Envío/consentimiento/idempotencia alineados con T17 | ✓ | mcp-contrato.md:27,29,31; namespace de cuenta, primer origen inmutable y consentimiento separado de mail.send; REST sin cambios. |
| Compatibilidad Composer contra lock, candidatos sin instalación | ✓ | Evidencia de solver aislado admitido y versiones concretas; resolución Windows no acredita runtime Linux. |
| Productos/versiones/capacidades y fuentes oficiales | ✓ | mcp-contrato.md:73,84; tres CLI candidatos, callbacks y perfiles explícitos; Desktop separado y compatibilidad pendiente. |
| Esquemas/errores/paginación/efectos/negativos versionados | ✓ | mcp-tools.json y fixtures públicos; 37 operaciones/76 schemas y positivos/negativos de entradas/salidas. |
| Nombres/rutas reconciliados con T16/T18 | ✓ | Contrato sobre servicios existentes, sin modificar aplicación ni crear otro pipeline. |
| Alcance y verificaciones declaradas | ✓ | 15 archivos declarados, verificador/fixtures justificados; seis gates reales exit0 y recibos privados. |
| Dieciséis CA y puertas posteriores conservados | ✓ | Matriz contractual mantiene Google, autorización, clientes, perfiles y cierre global sin acreditarlos por schema. |
| Constitución/interop/cobertura porcentual | N/A | No constitución, cambios commands/agents/hooks ni cobertura porcentual configurada. |
| Contrato OpenAPI | ✓ | Lint independiente exit0, spec VÁLIDA (0 errores); contrato nuevo sin base previa para diff. T17 conserva su hash aceptado. |

| Lente | Veredicto | Evidencia adicional |
|---|---|---|
| B, corrección | Sin defectos confirmados | Argumentos/resultados/permisos, OAuth, cursor, recuperación, dominio y verificador completos. |
| C, seguridad introducida | Sin hallazgos | Separación de credenciales, permisos actuales, S256, redirects/registro/SSRF, errores/cursor/claves y procedencia. |

Pendientes: **0 Critical, 0 Important, 0 Minor**. Dos sospechas preliminares por
output compacto se descartaron mediante consultas estructurales de los archivos
reales: todas las properties requeridas estaban declaradas y el enum de errores
tenía las 37 operaciones. No motivaron cambios de contrato ni deuda aceptada.

Validación contractual independiente de root y de las lentes: verificador
público exit0, **37 operaciones/37 permisos/76 schemas/596 casos herramienta/
26 errores/48 OAuth/34 cursor**, cero fallos. Esta es QA estática de la tarea
contractual: no hay app/UI nueva ni resultado Playwright o qa-gate fabricado.
No acredita autenticación, criptografía, SQL, SDK, Google, clientes o E2E reales.
Ledger, OpenAPI, alcance, privacidad y whitespace pasan; privacidad antes de
publicación1577 archivos/0 hallazgos. Comprobaciones finales de índice se registran
en el recibo de publicación. ADR-006 se promueve como decisión técnicamente
revisada, sin atribuir aprobación humana o conformidad runtime.

T-01/fase1 completadas: **1/14**, plan en-progreso; principal **18/20**. No se
marca ningún CA funcional/manual de esta ampliación como satisfecho. Siguiente:
T-02/T-03, persistencia de identidades y acceso/vínculo Google con recuperación,
admisión e invitaciones. T-04–T-14 y T-19/T-20 mantienen todo su alcance.
Medidores de implementación/revisión cerrados sin solape; tokens/coste/horas IA
reales desconocidos. Sin Jira/Confluence activado ni mensajes externos.

## Revisión de dos lentes — intento 1: Fase 2 (T-02, T-03) — corrección necesaria

La implementación se revisó sobre la base `ae91752f5a7457834441d13e4707205509fd28f1`.
La cápsula privada e inmutable `storage/mcp-google-phase2-review1/manifest.json`
conserva SHA-256 `b6e418ab767da6198a7fed92803b69cd76ee572ed733c496be8d201b99fc5b5b`:
41 archivos del incremento, contexto completo y 316 hashes verificados por cada
lente antes y después. Los archivos nuevos están incluidos completos.

La puerta de alcance independiente terminó exit 0: 41 archivos, ninguno fuera
de alcance, sin avisos ni exclusiones de usuario. El selector activó C por las
rutas de autenticación/configuración y D por las dos migraciones. A, B y C se
despacharon en paralelo; D comenzó al quedar un puesto libre. Todas las lentes
usaron revisores genéricos con contexto nuevo porque `reviewer` por nombre no
está disponible; el tier declarado por el plugin no acredita ejecución Opus.

| Lente | Critical | Important | Minor | Resultado |
|---|---:|---:|---:|---|
| A — requisitos y alcance | 0 | 0 | 0 | Conforme en el incremento T-02/T-03; puertas globales pendientes |
| B — corrección | 0 | 1 | 0 | B-01 pendiente: el formulario pierde la confirmación Google tras una contraseña inválida |
| C — seguridad | 0 | 0 | 0 | Sin vulnerabilidades introducidas o reabiertas confirmadas |
| D — rendimiento | 0 | 0 | 0 | Sin degradación sustentada con una carga concreta |

| Criterio revisado por A | Veredicto | Evidencia pública |
|---|---|---|
| T-02: clave exacta y estable, sin fusión por email | ✓ | Migración de identidades; `GoogleIdentityResolver`; `GoogleIdentityTest` |
| T-02: propiedad, transacciones, auditoría y carreras | ✓ | Bloqueo de usuario/identidad, auditoría propia y pruebas con procesos SQL independientes |
| T-02: desvínculo con recuperación local viable | ✓ | Estado protegido de contraseña local; acción Fortify; regresión de recuperación |
| T-03: firma, claims, state/código de un uso y actor | ✓ | `GoogleTokenVerifier`, consumo SQL y negativos/concurrencia de `GoogleLoginTest` |
| T-03: alta explícita, Workspace e invitaciones | ✓ | POST con CSRF, política firmada y token real emitido por `TeamService` |
| T-03: retorno confiable, perfil y acceso local | ✓ | Allowlist sobre APP_URL, UI de identidades, CSRF, Fortify y fallback |
| Alcance y evidencia declarados | ✓ | 41 archivos declarados y pruebas finales correctamente diferenciadas por snapshot |
| Constitución, interop y OpenAPI modificado | N/A | No hay constitución; no cambian commands/agents/hooks ni una spec OpenAPI |
| Cobertura porcentual | N/A | No se inventa un porcentaje; se revisan pruebas y evidencia concreta |

### B-01 — Conservar la confirmación tras errores corregibles del formulario

**Important, confirmado por lectura; pendiente de reproducción y corrección.**
`app/Services/Auth/GoogleLoginFlow.php:147` elimina `google.registration` antes de
que `app/Actions/Fortify/CreateNewUser.php:22` valide la contraseña.
`app/Http/Controllers/Auth/GoogleLoginController.php:56` transforma esa excepción
en un error genérico y devuelve a login. Con nombre/cuenta válidos, consentimiento
marcado y dos contraseñas de al menos doce caracteres distintas, no se puede
corregir y reenviar la confirmación: hay que repetir OAuth.

La corrección debe permitir reparar los errores de formulario conservando el
pendiente y su caducidad originales, sin revelar existencia de cuentas, reflejar
respuestas del proveedor ni almacenar o mostrar contraseñas. El alta válida debe
seguir siendo explícita, atómica y de un solo uso. Se requiere una regresión
que falle antes del fix, verifique la corrección sin otro OAuth y compruebe que
no hay usuario/cuenta/identidad creados durante el error.

### Evidencia conservada y siguiente puerta

Root contrastó los recibos y hashes de 19 cápsulas. La batería anterior al ajuste
de invitaciones dio identidad **6/34**, acceso **13/415** y regresiones locales
**27/248**, sobre 289 fuentes inmutables. Después, el acceso **13/418** y los tres
E2E pasaron sobre el mismo snapshot final `bff3d70e…`. La comparación de los dos
ZIP completos confirma que sólo cambiaron la gramática del retorno, su prueba y
el token de navegador; las verificaciones anteriores no se atribuyen al hash nuevo.

La comprobación independiente de privacidad del árbol actual terminó exit 0;
los puertos de prueba 18967/18968 estaban disponibles mediante bind exclusivo
tras la liberación del implementador. Esto no acredita limpieza del historial
público, Google real, Apache/Nginx/Docker ni consentimiento MCP completo.

T-02/T-03 siguen **en-progreso**, MCP **1/14**, plataforma principal **18/20**.
No hay aceptación QA ni promoción de GOT-012 mientras B-01 esté pendiente.
Se devuelve B-01 al implementador y la siguiente revisión conservará todos los
veredictos de este intento; no se cierra ni reduce ningún criterio global.

## Corrección B-01 de Fase 2 — implementación pendiente de revisión y QA

El gap B-01 se confirmó en código y con una regresión real: `GoogleLoginFlow` consumía el pendiente antes de que `CreateNewUser` validara la contraseña; el controlador devolvía el error genérico al login. RED: `test_registration_form_errors_preserve_verified_identity_and_original_expiry_for_correction` falló porque esperaba confirmación y recibió login · 2026-10-25. Cápsula `mcp-google-fix1-red-20261025T015410609867Z`: exit1, 1 prueba / 7 assertions, 19.98s, fuentes sin cambios durante la ejecución.

La validación corregible de nombre, cuenta, confirmación y contraseña ocurre ahora antes de consumir el pendiente y vuelve a la confirmación con errores de campo. Sólo se conserva nombre/cuenta acotados y la elección de confirmación; nunca contraseña, confirmación de contraseña, email enviado por formulario ni tokens. La identidad y su caducidad original permanecen intactas. Colisiones, proveedor, admisión y errores de transacción conservan la respuesta genérica original. El alta válida conserva transacción y consumo de un solo uso.

La prueba integra dos entradas inválidas, corrección sin otro OAuth, ausencia de filas tras inválidos y alta única. Se conservan dos fallos intermedios del arnés: exigir la clave de un campo vacío transformado a null por middleware, e intentar recibir un error al repetir el POST estando ya autenticado. Se corrigieron esas aserciones sin cambiar el código funcional ni las cuotas. Son evidencia separada, no intentos de QA formal.

Scope T-03 incorpora `docs/roadmap/2026-10-24-mcp-google/testing/fase-2/google/**` para informe, capturas y puertas de QA de esta fase, por coordinación con root. T-02/T-03 continúan **en-progreso**; MCP permanece **1/14** aceptado y la plataforma principal **18/20**. Revisión 2 y QA independiente pendientes; GOT-012 permanece propuesta. Apache/Nginx/Docker, Google real, clientes reales y consentimiento MCP completo conservan sus tareas posteriores.

Verificación del fix: `mcp-google-fix1-login-final-20261025T015745691631Z` pasó GoogleLoginTest **14/476**, 215.41s; `mcp-google-fix1-e2e-final-unsandboxed-20261025T020402382265Z` pasó **3 escenarios**, 50.1s, incluyendo error de contraseña y corrección por el formulario real con CSRF sin otro OAuth. Ambas ejecuciones tienen exit0, 289 fuentes sin cambios y pin `0669a041696f9b1cd28f8c5fa541509e323f3d268e14a0a4a17ecc551c129f81`. El intento E2E previo abortó con `spawn EPERM` antes de iniciar fixtures/SQL/PHP; el mismo runner se ejecutó con escalación dirigida, conservando el bloqueo de red exterior y la configuración del navegador. No fue un fallo de la aplicación ni una cualificación de Google real.

Pint en modo test y lint PHP pasaron en los archivos PHP del fix. Privacidad: 1600 archivos / 0 hallazgos; contrato MCP estático exit0; scope: 41 archivos, 0 fuera, 0 avisos y 0 exclusiones de usuario; ledger: 0 incoherencias / 11 avisos de Changelog de tareas futuras; diff-check exit0. Recursos del run propio liquidados: puertos de prueba disponibles mediante bind exclusivo y cerrados, procesos propios vivos0, SQL guard aprobado y usuarios/invitaciones propios0; sólo se retiraron sus dos carpetas UUID de cache/sesiones. Detalle privado en `storage/mcp-google-phase2-fix1/handoff.md`; medición sin telemetría atribuible conserva tokens, coste y horas IA reales desconocidos.

## Revisión de dos lentes — intento 2: Fase 2 (T-02, T-03) — B-01 corregido, sin gaps pendientes

La segunda revisión conserva todos los veredictos del intento anterior y evalúa
la corrección B-01 sin reabrir criterios aceptados sin evidencia nueva. La cápsula
inmutable `storage/mcp-google-phase2-review2/manifest.json`, SHA-256
`4c1722bd21f177ba16a7f772977136a735984b97ebd952f715eb3a33e73ff412`,
incluye 41 archivos del incremento y 323 hashes. Cada lente comprobó manifiesto y
323/323 archivos antes y después, sin discrepancias ni escrituras.

Scope independiente exit 0: ningún archivo fuera de alcance, aviso ni exclusión
de usuario. C se activa por autenticación/configuración; D por las migraciones.
A+B+C se ejecutaron en paralelo y D empezó al terminar A, respetando los puestos
disponibles. Se usaron revisores genéricos con contexto nuevo: el rol por nombre
no está disponible y el tier del plugin no acredita ejecución Opus.

| Lente | Critical | Important | Minor | Resultado |
|---|---:|---:|---:|---|
| A — requisitos y alcance | 0 | 0 | 0 | Conforme; B-01 corregido desde requisitos |
| B — corrección | 0 | 0 | 0 | B-01 corregido, sin defectos nuevos |
| C — seguridad | 0 | 0 | 0 | Sin vulnerabilidades introducidas o reabiertas |
| D — rendimiento | 0 | 0 | 0 | Sin degradación sustentada; se conserva D0 |

| Criterio | Veredicto | Evidencia pública |
|---|---|---|
| T-02: unicidad exacta y estabilidad ante cambio de email | ✓ conservado | Migración de identidades:18; `GoogleIdentityResolver.php:76` |
| T-02: sin fusión por email ni sustitución de propietario | ✓ conservado | `app/Services/Auth/GoogleIdentityResolver.php:17`, `:43` |
| T-02: desvínculo autenticado y recuperación viable | ✓ conservado | `app/Services/Auth/GoogleIdentityResolver.php:63`; estado local y recuperación Fortify |
| T-02: transacciones, auditoría y carreras | ✓ conservado | `app/Services/Auth/GoogleIdentityResolver.php:43`, `:86`; evidencia del intento 1 |
| T-03: identidad, state y código inválidos no producen acceso ni altas | ✓ conservado | `app/Services/Auth/GoogleLoginFlow.php:68`, `:94`; negativos existentes preservados |
| T-03: alta explícita e invitación según destinatario | ✓ | `app/Services/Auth/GoogleLoginFlow.php:146`; `tests/Feature/GoogleLoginTest.php:206`; navegador E2E-03 |
| T-03: Workspace firmado y sin roles derivados de Google | ✓ conservado | `tests/Feature/GoogleLoginTest.php:285`, `:358`; evidencia del intento 1 |
| T-03: alternativa local, retorno y protecciones Fortify | ✓ conservado | `app/Services/Auth/GoogleLoginFlow.php:174`, `:204`; controlador y pruebas existentes |
| B-01: pendiente y caducidad originales tras errores corregibles | ✓ | Validación previa al consumo en `GoogleLoginFlow.php:146`; igualdad del pendiente en `GoogleLoginTest.php:251`, `:261` |
| B-01: errores útiles, input permitido y contraseñas vacías | ✓ | `GoogleLoginFlow.php:150`; escape de `resources/views/auth/google-confirm.blade.php:7`; recorrido de `tests/e2e/mcp-google.spec.mjs:131` |
| B-01: corrección sin otro OAuth y sin efectos durante inválidos | ✓ | `GoogleLoginTest.php:257`, `:267`, `:272`, `:282`; E2E-03 con CSRF real |
| B-01: errores genéricos restantes, transacción y consumo único | ✓ | `GoogleLoginFlow.php:147`, `:163`; `GoogleLoginController.php:56`; prueba de repetición tras cerrar sesión |
| Alcance y ejecución declarada | ✓ | Cuatro fuentes corregidas declaradas; 41 archivos dentro de alcance; RED y finales con fuentes verificadas |
| Estados y puertas posteriores | ✓ | T-02/T-03 en progreso; MCP 1/14 y principal 18/20; sin aceptación QA ni Google/cliente real |
| Constitución, interop y OpenAPI modificado | N/A conservado | Sin constitución; no cambia commands/agents/hooks ni una spec OpenAPI |
| Cobertura porcentual | N/A conservado | No se atribuye un porcentaje; lectura de pruebas y evidencia real |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important histórico, resuelto | Error de formulario consumía la confirmación Google y obligaba a repetir OAuth | T-03 | Corregido: validación limitada al formulario antes del consumo; retorno confiable, input permitido, TTL original y alta válida atómica | RED de implementación exit 1; GoogleLoginTest final 14/476 y E2E-03 con corrección real, mismo snapshot final |

Root verificó los recibos, streams y ZIP completos de 289 fuentes del RED y los
finales. El RED `mcp-google-fix1-red-20261025T015410609867Z` conserva el defecto
original. PHP `mcp-google-fix1-login-final-20261025T015745691631Z` pasó **14/476**
y navegador `mcp-google-fix1-e2e-final-unsandboxed-20261025T020402382265Z`
pasó **3 escenarios**, ambos exit 0 y pin de fuentes
`0669a041696f9b1cd28f8c5fa541509e323f3d268e14a0a4a17ecc551c129f81`.
La comparación con el intento anterior sólo cambia Flow, vista de confirmación,
prueba PHP y prueba E2E. Los verdes antiguos no se atribuyen al snapshot nuevo.
Los dos fallos intermedios de aserciones y el fallo de spawn del navegador siguen
conservados; no se convirtieron en verdes ni en intentos formales de QA.

La recuperación recorre exactamente dos campos acotados a 100 caracteres, sin
consultas ni HTTP añadidos. Este dictamen no acredita carga medida. Root verificó
privacidad del árbol actual exit 0 y binds exclusivos de ambos puertos de prueba,
cerrando sus probes sin arrancar app ni enumerar procesos. El implementador cerró
su medidor y liberó escritura, aplicación, SQL y navegador; no quedan handles vivos.

Sin gaps pendientes, GOT-012 se promueve al formato canónico de aceptación por
esta revisión y su índice se actualiza. Esa aceptación valida el conocimiento del
arnés; no acredita QA funcional ni Google real. No hay opt-in Jira/Confluence ni
publicaciones externas. Tokens, coste y horas IA reales siguen desconocidos sin
telemetría atribuible; no se convierten minutos de reloj en horas IA.

T-02/T-03 continúan **en-progreso**, MCP **1/14**, principal **18/20** hasta QA
independiente. El siguiente paso es esa puerta de fase; consentimiento completo,
Apache/Nginx/Docker, Google real, clientes reales, carga y cierre global conservan
T-05/T-07/T-11–T-14 y T-19/T-20. No se reduce ningún escenario del objetivo.

## Cierre técnico de Fase 2 — identidad y acceso Google aceptados, 2026-10-25

Root admite T-02/T-03 después de revisión 2 A+B+C+D sin gaps y QA independiente
de fase verde. La fase 2 pasa a **2/2** y la ampliación a **3/14**; el plan sigue
en-progreso y la plataforma principal conserva **18/20**. No se cierran criterios
globales ni tareas posteriores por este resultado.

La ejecución independiente pasó **3/3 E2E**, cero fallos, flaky, omitidos o retries,
y **20 pruebas PHP / 510 aserciones**. Root volvió a ejecutar el gate oficial
sobre el JSON original y obtuvo exit 0. Los 289 archivos de implementación
actuales coinciden con el pin final
`0669a041696f9b1cd28f8c5fa541509e323f3d268e14a0a4a17ecc551c129f81`.
Los 16 recibos independientes conservan ZIP de 295 archivos: esas fuentes y seis
controles. Se contrastaron hashes, streams, miembros de cada ZIP y ausencia de
cambios durante las ejecuciones. El arranque EPERM mantiene su NO-VERDE; el
checker SQL inicial con tablas ausentes sigue rechazado como prueba de cierre.

La cápsula de QA `storage/mcp-google-phase2-qa1/manifest.json` tiene SHA-256
`260f3caa61ff36df9731d25bc6d3ded6d38be086a109724af1166966e781dbb4`.
Root verificó sus 35 archivos públicos y 203 artefactos privados. La proyección
del reporter sólo cambia once valores de rutas de configuración; estructura,
stats, errores, duraciones y retries permanecen idénticos. Las cinco capturas
públicas coinciden byte a byte con sus originales privados.

El [informe](testing/fase-2/google/report.md) y su
[PDF](testing/fase-2/google/report.pdf) conservan la entrega de QA previa a esta
admisión. Root renderizó de nuevo el PDF y revisó sus **seis páginas** y las
**cinco capturas originales**: sin recortes, solapamientos ni datos personales
visibles. La confirmación corregible muestra el error y contraseñas vacías.
Hashes finales: informe `52d28771c5acff60f8fa137e40da5b2486d2157d3a6cd36773bdd973ce376431`; PDF `d591d7bedb20004b9f4b4e5ed0aa1e1be894fd53b8daf741a7994380a669d528`.
Admisión privada de root: `d1bb99f78beb320efa3ffd95f9680d9808aadb0c8ee4aaaf043ee86660a82fec`.

El check del índice detectó CRLF en el Markdown y manifiesto nuevos, conservados
como bytes por los atributos de evidencias. La publicación normaliza sólo esos
dos artefactos a LF y actualiza el hash del informe en el manifiesto. Se guardan
los originales privados y la proyección editorial: contenido, código, resultados,
PDF y capturas permanecen iguales. El fallo inicial del check se conserva; no se
desactiva ninguna regla de whitespace para obtener verde.

El perfil acreditado es Chromium real, PHP cli-server nativo Laragon, SQL y
CSRF reales, con sólo el proveedor HTTP simulado. E2E-01 conserva pendiente su
consentimiento MCP completo; la ruta fixture de retorno no lo sustituye. Google
real, Apache/Nginx/Docker, TLS y logs de despliegue, clientes reales, M-01–04 y
E2E-04–06 conservan las tareas T-05/T-07/T-11–T-14. No se atribuye al navegador
lo que sólo prueba PHP ni se declara aprobación humana.

El propietario liberó escritura, aplicación, SQL y navegador, con handles vivos
vacíos. El launcher carece de settlement automático, y esa ausencia se conserva.
El recibo explícito acredita SQL `emailing_testing` guardada y tablas eliminadas
por `DatabaseMigrations`: no quedan filas posibles, sin atribuir ese resultado a
DELETE selectivo. Root comprobó bind exclusivo de ambos puertos y cerró sus
probes, sin inspeccionar ni señalar procesos. No se recreó esquema para obtener
un verde ni se tocó la base operativa.

El cierre sincroniza la guía Google y los índices de documentación, RAG y roadmap.
T-03 declara esos índices como alcance editorial; no cambia código, dependencias
ni contratos después del QA. La pasada documenter del plan completo sigue al final
de la iniciativa. Las verificaciones de privacidad se refieren al árbol actual;
el saneamiento del historial publicado mantiene su procedimiento y aprobación
final separados. SMTP externo continúa deshabilitado: sólo Mailpit/log.

Siguiente trabajo: T-04, contexto delegado y autorización vigente, seguido por
OAuth, transporte y consentimiento de la fase 3. Permanecen íntegros las once
tareas MCP restantes y T-19/T-20, incluidas carga cruzada y recuperación.
Horas, tokens y coste reales siguen desconocidos sin telemetría atribuible;
las duraciones de pruebas no se convierten en tiempo IA.
