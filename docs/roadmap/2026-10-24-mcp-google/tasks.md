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

Los IDs locales pertenecen a `2026-10-24-mcp-google`. Los IDs de la iniciativa principal llevan su slug completo. Estado actual: **9/14** aceptadas técnicamente; contratos, identidad/acceso Google, núcleo delegado OAuth/MCP, veinte herramientas de datos y catorce de campañas/actividad/supresiones completados en laboratorio tras revisión y QA independiente. El catálogo registrado tiene37 operaciones en T-10 en-progreso; las34 de T08/T09 conservan su aceptación. Envío transaccional MCP está en pruebas; perfiles de despliegue y conexiones reales siguen pendientes. M-01–M-04 conservan su aceptación humana/externa.

Las horas y tokens de tareas/fases son **base** y heredan la evaluación; el total con margen es 201,6 h y 1.728.000 tokens. No se suma dos veces el +20 %. Tiempo/coste reales siguen desconocidos; el contador de progreso no mide consumo real.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---|---|---|---|---|---|---|
| Fase 1 — Contrato y decisiones | 1 | 1 | 100% | — / 8h | — / 0.333333h | — / 0.083333h | — / 100000 |
| Fase 2 — Identidad Google | 2 | 2 | 100% | — / 20h | — / 0.466667h | — / 0.116667h | — / 140000 |
| Fase 3 — Autorización delegada y MCP | 4 | 4 | 100% | — / 48h | — / 1.333333h | — / 0.333333h | — / 400000 |
| Fase 4 — Catálogo de negocio | 2 | 3 | 67% | — / 32h | — / 0.8h | — / 0.2h | — / 240000 |
| Fase 5 — Clientes y operación | 0 | 1 | 0% | — / 16h | — / 0.4h | — / 0.1h | — / 120000 |
| Fase 6 — QA, revisión y cierre | 0 | 3 | 0% | — / 44h | — / 1.466667h | — / 0.366667h | — / 440000 |
| **TOTAL** | **9** | **14** | **64%** | **— / 168h** | **— / 4.8h** | **— / 1.2h** | **— / 1440000** |

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

**Estado**: completado · **Estimado**: 48h base · **Real**: — · **Coste est.**: 2400 € equivalentes + tokens por verificar · **Tokens est.**: 400000

### T-04 — Introducir contexto delegado y autorización vigente

- **Descripción**: Extender el contexto común con actor/cuenta/grant explícitos y conservar el adaptador web. Compartir la autorización de servicios sin suplantar la sesión global.
- **Partida**: C-03 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Cerrada**: 2026-10-25
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01; 2026-10-21-plataforma-emailing/T-18 aceptada para reutilizar su contexto API y servicios
- **Tipo**: backend
- **Archivos**: `app/Services/AccountContext.php`, `app/Services/Permissions.php`, `app/Services/TeamService.php`, `app/Services/ContactService.php`, `app/Services/ContactImportService.php`, `app/Services/TemplateService.php`, `app/Services/ActivityService.php`, `app/Services/CampaignService.php`, `app/Services/SuppressionService.php`, `app/Support/ExecutionContext.php`, `app/Services/Mcp/DelegatedContext.php`, `tests/Feature/McpAuthorizationTest.php`, `tests/Feature/McpRevocationConcurrencyTest.php`, `docs/aplicacion/arquitectura.md`, `docs/aplicacion/mcp-contrato.md`, `tests/Feature/McpMembershipRemovalRegressionTest.php`, `tests/support/mcp-membership-race.php`
- **Cubre (tests)**: McpAuthorizationTest, McpRevocationConcurrencyTest, McpMembershipRemovalRegressionTest, TeammateTest; E2E-05, E2E-06
- **Verificación**: `php artisan test --filter="McpAuthorizationTest|McpRevocationConcurrencyTest|TeammateTest|McpMembershipRemovalRegressionTest|McpOAuthTest|McpOAuthConcurrencyTest|McpOAuthRegistrationRegressionTest|McpPruneCapacityRegressionTest|McpTransportTest|McpProtocolMetadataRegressionTest|McpConsentToolsTest|GoogleLoginTest"` → lote QA1 de fase: 77 passed / 1808 assertions, exit0, sobre las mismas 352 fuentes revisadas; no es un resultado independiente por tarea. QA2 ejecutó E2E-01–06: 6/6 passed al primer intento, exit0, sin flaky ni omitidos. Revisión 2 A+B+C+D sin defectos pendientes. [Cierre técnico y procedencia](testing/fase-3/acceptance.md), [backend original QA1](testing/fase-3/qa-1/report.md), [QA2](testing/fase-3/qa-2/report.md). M-01–M-04 y la aceptación global T-14 permanecen pendientes; no se suman ejecuciones históricas.

**Criterios de aceptación**

- [x] Permiso efectivo = scopes del grant ∩ scopes del token ∩ permisos actuales de membresía ∩ permisos de la operación.
- [x] La cuenta procede del grant verificado; account_id y IDs del cliente nunca seleccionan otra cuenta.
- [x] Cada mutación revalida grant y membresía en su transacción con orden de bloqueo definido; una revocación confirmada impide nuevas mutaciones.
- [x] La revocación de membresía afecta al grant personal; el ciclo de vida de API keys de cuenta de T-18 se conserva separado.

**Subtareas**

- [x] Reutilizar el contexto adoptado en T-18; añadir sólo el adaptador delegado y los cambios de servicio necesarios.
- [x] Eliminar dependencias de auth()/session() de las rutas de negocio usadas por MCP mediante parámetros/adaptadores explícitos.
- [x] Probar sesión ausente, multi-cuenta, role read-only/restricted/admin y carreras revocación-mutación.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.


- Prerrequisito compartido adelantado de T-05: modelos/tabla SQL de cliente, grant y token Sanctum separado para verificar autoridad real y carreras en T-04; `app/Services/Mcp/GrantService.php` aporta la revocación básica compartida de T-07. No son DTOs de autoridad ni API keys artificiales. ADR-008 queda propuesta e indexada. Medidor arrancado después de la primera escritura del contexto/persistencia; esa preparación queda fuera de su ventana, sin consumo real atribuible.

- Implementación de T-04 preparada, **en-progreso pendiente de revisión y QA**: no sube el contador 3/14. `ExecutionContext`/`DelegatedContext` releen SQL y los servicios conservan adaptador web. La cuenta proviene del token/grant contrastados; ningún account_id del cliente selecciona autoridad. Se revalida bajo cuenta → membresía → cliente → grant → token → origen. Retirada de membresía revoca terminalmente sus grants sin afectar las API keys de cuenta.
- Evidencia privada T-04: `storage/mcp-google-phase3/runs/t04-authority-20261025T030735632648Z` (recibo SHA-256 `32a50ac89a550e9bdf49ef9c975c07f91e8f0863846c068e2d8aae128d0843b9`) y `t04-web-api-regression-20261025T031034803577Z` (`3159ce70282f6e3e766ec27a5beb76767e4c85f51a6960425b4dc2657fc00122`): procesos SQL independientes, ZIP/fuentes/streams originales. Los tres órdenes probados son mutación→revocación, revocación→mutación y retirada de membresía→mutación; sin efectos posteriores a revocación confirmada.
- Pint inicial detectó formato (exit1, salida de herramienta conservada en conversación; ese primer comando no usó el capturador privado). La mutación de formato tiene stdout/stderr/recibo en `storage/mcp-google-phase3/format-20261025T031300215108Z`. Pint final exit0. Tras ella se añadió el rechazo de roles desconocidos incluso sin scopes: focal `t04-unknown-role-focal-20261025T031426979416Z`, recibo `3bb8547f8811372a87161a102f460736d5ab388a447ec65be53a17a462a49ae5`. Los verdes previos pertenecen a sus hashes, no al posterior. El trait se ubica en `tests/Concerns/` para respetar PSR-4 con case-sensitive Linux; no se presenta ejecución Linux por comprobar su ruta.
- Scope sobre base `7cce40bd09997e079013f01e96ccf6a734b0187d`: 23 archivos, exit0, fuera de alcance0/avisos0/exclusiones de usuario0; ledger exit0, incoherencias0/avisos11 de Changelog futuro. El scope de ADR/índice y revocación se declara expresamente. No se requiere editar Permissions/ActivityService: ya consultan AccountContext o reglas existentes que el adaptador conserva.
- No AGENTS/CONSTITUTION/CLAUDE/dev.json aplicables, ni opt-in Jira/Confluence; defaults TDD/worktree/subagentes false. Medidor T-04 cerrado sin transcripciones: tokens, coste, horas IA/humanas/supervisión reales null; no se convierten 10 minutos de reloj en horas IA. Ajuste posterior de ubicación del trait sin telemetría atribuible. T-05 empieza únicamente después del cierre de esta ventana.
- **Changelog**: Las conexiones delegadas aplican cuenta y permisos vigentes sin depender de la sesión web; revocar acceso impide nuevas mutaciones y conserva las llaves de cuenta.

- Focal de ubicación PSR-4 final: `t04-fixture-portability-focal-20261025T031535568848Z` → 1 passed (11 assertions), 21.65s, exit0, recibo `6d3d6f626e2edc2c71d9b7188d2b6e22f37ccef8908082c30a63d58c12cd1e95`, fuentes estables. Pint final previo al mero cambio de namespace/ubicación: recibo `eb3d80e9d022b90afec74ae33e9bfa02eea0efb28ffe0fb41f5ed707a44cbf85`; revisión final de fase verificará también ese movimiento. Todos los procesos propios T-04 terminaron; sin app/browser/broker iniciados. DatabaseMigrations desmontó su esquema de testing: no se reconstruye para afirmar limpieza.

### T-05 — Implementar servidor OAuth, códigos y reautorización

- **Descripción**: Adaptar el patrón OAuth de referencia al recurso y grants de Emailing. Emitir credenciales propias, opacas y separadas de Google y de las API keys.
- **Partida**: C-02 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Cerrada**: 2026-10-25
- **Tiempo humano**: est. 20h · real —
- **Tiempo IA (ejec.)**: est. 0.583333h (estimado por tokens) · real —
- **Supervisión**: est. 0.145833h (25 % IA) · real —
- **Previsión IA**: 150000 in / 25000 out tok · coste 0.15 p_in + 0.025 p_out €; tarifa por verificar
- **Coste estimado**: 1000 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01, T-04
- **Tipo**: backend
- **Archivos**: `app/Models/McpClient.php`, `app/Models/McpGrant.php`, `app/Models/McpAuthorizationCode.php`, `app/Models/McpAuthorizationRequest.php`, `app/Models/PersonalAccessToken.php`, `app/Services/Mcp/OAuth/*`, `app/Services/Mcp/Admission.php`, `app/Services/Mcp/DelegatedContext.php`, `app/Http/Controllers/Mcp/OAuth/*`, `app/Http/Middleware/McpOAuthBoundary.php`, `app/Console/Commands/McpRegisterClient.php`, `app/Console/Commands/McpPrune.php`, `database/migrations/*_create_mcp_authorization.php`, `database/migrations/*_create_mcp_oauth.php`, `config/mcp.php`, `.env.example`, `bootstrap/app.php`, `routes/mcp-oauth.php`, `routes/console.php`, `resources/views/mcp/consent.blade.php`, `tests/Concerns/McpFixtures.php`, `tests/Feature/McpOAuthTest.php`, `tests/Feature/McpOAuthConcurrencyTest.php`, `tests/Feature/McpOAuthRegistrationRegressionTest.php`, `tests/Feature/McpPruneCapacityRegressionTest.php`, `tests/support/mcp-oauth-action.php`, `docs/api/mcp-oauth.openapi.json`, `docs/aplicacion/mcp-contrato.md`
- **Cubre (tests)**: McpOAuthTest, McpOAuthConcurrencyTest, McpOAuthRegistrationRegressionTest, McpPruneCapacityRegressionTest; E2E-01, E2E-04, E2E-06
- **Verificación**: `php artisan test --filter="McpAuthorizationTest|McpRevocationConcurrencyTest|TeammateTest|McpMembershipRemovalRegressionTest|McpOAuthTest|McpOAuthConcurrencyTest|McpOAuthRegistrationRegressionTest|McpPruneCapacityRegressionTest|McpTransportTest|McpProtocolMetadataRegressionTest|McpConsentToolsTest|GoogleLoginTest"` → lote QA1 de fase: 77 passed / 1808 assertions, exit0, sobre las mismas 352 fuentes revisadas; no es un resultado independiente por tarea. QA2 ejecutó E2E-01–06: 6/6 passed al primer intento, exit0, sin flaky ni omitidos. Revisión 2 A+B+C+D sin defectos pendientes. [Cierre técnico y procedencia](testing/fase-3/acceptance.md), [backend original QA1](testing/fase-3/qa-1/report.md), [QA2](testing/fase-3/qa-2/report.md). M-01–M-04 y la aceptación global T-14 permanecen pendientes; no se suman ejecuciones históricas.

**Criterios de aceptación**

- [x] Metadatos de recurso/servidor y desafío apuntan al origen configurado y a los endpoints implementados; no se derivan de Host.
- [x] Código con hash, TTL 300 s y consumo atómico se liga a cliente, redirect exacto, S256, recurso y grant consentido.
- [x] Access token Sanctum opaco: propósito MCP, emisor/recurso, grant y TTL 3600 s; sin refresh inicial, con reautorización explícita al expirar.
- [x] Grant_type refresh_token se rechaza; reautorizar vuelve a comprobar membresía y scopes sin recuperar acceso revocado.
- [x] Preregistro y DCR controlado de clientes públicos siguen política de redirects y límites; errores previos a validar redirect nunca redirigen a destino no confiable.

**Subtareas**

- [x] Crear persistencia e índices/constraints para códigos, grants, tokens y cliente; secretos de clientes confidenciales sólo hash si se habilitan.
- [x] Implementar descubrimiento, autorización, canje y revocación con respuesta sin caché y errores protocolarios.
- [x] Aplicar límites de registro y token; no recuperar metadatos de URLs arbitrarias ni habilitar grants password/client_credentials.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

- Prerrequisitos funcionales declarados: la solicitud OAuth validada y ligada al navegador necesita su modelo y migración propia; `bootstrap/app.php` carga las rutas y la frontera segura de errores. La vista mínima de consentimiento POST+CSRF se adelanta desde T-07 para que authorize sea funcional en T-05; conexiones, auditoría transversal y E2E siguen pendientes de T-07. El cliente queda ligado también al emisor configurado. Configuración pública y comando local de preregistro no contienen secretos.
- Primera ejecución real `t05-oauth-initial-20261025T032258366814Z`: 6 failed (6 assertions), 79.90s, recibo `c402c86b33f7a7ea3e4cd8f416836927e7c8249a7ae6395d0edfdfff449474d4`. MySQL rechazó el alias reservado current_time; se sustituye por window_now. El pipeline interior de Laravel convierte excepciones en respuestas antes del catch externo: se adapta OAuthError al contrato Responsable existente y se sanea también la respuesta renderizada, incluido APP_DEBUG, sin reflejar parámetros ni SQL.
- Correcciones intermedias conservadas: `t05-oauth-fix1-20261025T032504548796Z`, recibo `4f3f53ef1aa5274b0cd8b2a7cabf49568f3b01e3db69f1f97915984c4f9d6136` → 5 failed / 1 passed (33 assertions), 92.40s; `t05-oauth-fix2-20261025T032652202290Z`, `0b813f753cb7638df77d984198d4fe1122750324c5f0a8df94252be9ae514008` → 1 failed / 5 passed (120 assertions), 85.28s. El rojo restante comparaba float de Carbon con int para TTL; se corrige la aserción, no la caducidad. Todas conservan fuentes ZIP, manifests y streams sin cambios durante cada ejecución; no son QA formal ni se convierten en verdes de hashes posteriores.

- Implementación T-05 preparada, **en-progreso pendiente de revisión y QA**, sin elevar 3/14. OAuth real sobre SQL: emisor/recurso configurados, cliente público none y preregistro local; DCR off por defecto y allowlists explícitas. Pendiente del navegador persiste en SQL, se liga a la persona al acceder, caduca a 600 s y admite como máximo cinco filas activas por persona/cliente bajo lock. Código hash 300 s, canje atómico opaco Sanctum hasta la caducidad del grant (máximo 3600 s); sin refresh, grants password ni client_credentials.
- Primer conjunto completo `t05-oauth-concurrency-retention-20261025T033013724012Z`: recibo `56c922535519a16f611f5134d4ae70862cb9b0a4b85f3dd1be3e6e86decc32ac`, 11/245. Final tras reforzar cap5, APP_DEBUG y reloj de cuotas: `t05-final-20261025T033549109278Z`, `8ad30bd54a9a4e1da8c2b11d60d6635d3ec378773448c75d555e9ebb1dac37c9`, 12/258 incluyendo una focal de T-04 por la nueva vinculación de cliente/emisor. Canje concurrente con barrera de lock emite un token; el replay rival revoca ese derivado sin tocar otra conexión. Admisión concurrente prueba dos dimensiones sintéticas limit1 con el servicio productivo real; no acredita carga hasta los límites de producción.
- Focal de fronteras HTTP productivas y DCR: `t05-production-thresholds-20261025T034040785895Z`, recibo `c72efb2b379e0714358b8b0b8ad804105e223b2997cfd7f0fc0214d837397369`, 2/33, 31.94s. Los techos 60/cliente de token, 60/IP de revoke, 5/IP/min y 20/IP/día de DCR se verifican con ventanas SQL primadas; se rechaza el cliente dinámico 101. No es una carga desde cero. La cuota token/IP30 y el cap5 de pendientes sí recorren peticiones HTTP reales desde cero; ventanas UTC y Retry-After se conservan.
- Corrección protocolaria contrastada con [RFC6749 §5.2](https://www.rfc-editor.org/rfc/rfc6749.html#section-5.2) y [RFC7009 §2.2.1](https://www.rfc-editor.org/rfc/rfc7009.html#section-2.2.1): Basic no admitido en token/revoke devuelve 401 invalid_client y WWW-Authenticate de esquema conocido, sin reflejar credenciales; DCR/authorize mantienen 400 y el método admitido sigue none. RED causal `t05-basic-auth-red-20261025T034357717832Z`, `791e88fe9265ccb1b42a10489a0e42defb786fe7a4084eb0f932fa783fe20111`: 1 failed (19 assertions), 23.09s, esperaba401/recibió400. GREEN `t05-basic-auth-green-20261025T034458224455Z`, `d55fd386c161dd167b3c034a89409c475bd046d3f71f82d089cb16b736be006d`: 3 passed (72 assertions), 43.62s, sobre fuentes estables; incluye ambas cabeceras, DCR400, cuotas y rollback seguro con APP_DEBUG. Los formatos posteriores sólo afectan estilo; las cápsulas previas conservan su hash.
- Retención finita: hasta100 filas por etapa por defecto, máximo1000; revoca expiración bajo el orden común antes de podar, respeta 24h/48h/30d/90d y FK de procedencia. La prueba con FK restrictiva real conserva un grant referido y permite podarlo tras retirar esa referencia sintética; fuentes de negocio MCP reales se integrarán en T-10. La tarea queda programada en el scheduler existente; no se ejecutó el scheduler operativo ni SMTP/broker.
- Gates T-05: Pint final exit0 (recibo 2fec2739574f6ccd12521a6de20e5569ed114ff536ab4b02abaedc97917f3d9c); contrato estático 37/76/596/26/48/34, cero fallos; privacidad1667archivos/0hallazgos; scope44archivos, fuera0/avisos0/exclusionesusuario0. Los dos últimos corresponden al snapshot anterior al ajuste de cabecera, conservado en sus recibos; se volverán a comprobar sobre la fase completa. El contrato JSON y REST permanecen intactos. Pint rojo intermedio y mutaciones de formato conservados en storage/mcp-google-phase3.
- Settlement `t05-sql-settlement-20261025T034732423487Z`, recibo `1fb483953c0d7c7ddbb564bba7176483a90cce4b262a56db9eba62d9cb32924e`: guard emailing_testing aprobado, nivel transaccional0, ocho tablas MCP/fixture ausentes tras DatabaseMigrations. No se presenta como DELETE selectivo ni se recrea esquema para dar verde. Todos los procesos propios terminaron, handles vivos[]; sin app/browser/broker iniciados. Medidor sin transcripciones: tokens/coste/horas reales null, sin convertir reloj en horas IA; se cierra antes de T-06.
- **Changelog**: Los clientes pueden autorizar conexiones por cuenta con PKCE y tokens propios temporales; la revocación, los límites y la reautorización conservan los permisos vigentes.

### T-06 — Servir MCP con guard dedicado y sesiones acotadas

- **Descripción**: Instalar Laravel MCP y exponer Streamable HTTP con validación Bearer específica antes de negociar o ejecutar herramientas. Una sesión MCP no sustituye una credencial vigente.
- **Partida**: C-02 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Cerrada**: 2026-10-25
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.35h (estimado por tokens) · real —
- **Supervisión**: est. 0.0875h (25 % IA) · real —
- **Previsión IA**: 90000 in / 15000 out tok · coste 0.09 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-05
- **Tipo**: backend
- **Archivos**: `.dockerignore`, `composer.json`, `composer.lock`, `routes/ai.php`, `bootstrap/app.php`, `app/Http/Controllers/McpTransportController.php`, `app/Http/Middleware/McpRawBody.php`, `app/Services/Mcp/TokenResolver.php`, `app/Services/Mcp/TransportServer.php`, `app/Services/Mcp/ProtocolError.php`, `app/Services/Mcp/Concurrency.php`, `app/Services/Mcp/WireJson.php`, `app/Services/Mcp/RegistryCursor.php`, `app/Services/Mcp/ListTools.php`, `app/Services/Mcp/BoundedHttpTransport.php`, `app/Services/Mcp/ToolError.php`, `app/Support/StrictMcpJson.php`, `app/Support/McpJsonNumber.php`, `app/Console/Commands/McpPrune.php`, `config/mcp.php`, `database/migrations/2026_10_25_000005_create_mcp_sessions.php`, `tests/Feature/McpTransportTest.php`, `tests/Feature/McpProtocolMetadataRegressionTest.php`, `tests/support/mcp-concurrency-action.php`, `docs/knowledge/adr/ADR-009-frontera-cruda-y-transporte-mcp.md`, `docs/knowledge/README.md`
- **Cubre (tests)**: McpTransportTest, McpProtocolMetadataRegressionTest; E2E-05, E2E-06
- **Verificación**: `php artisan test --filter="McpAuthorizationTest|McpRevocationConcurrencyTest|TeammateTest|McpMembershipRemovalRegressionTest|McpOAuthTest|McpOAuthConcurrencyTest|McpOAuthRegistrationRegressionTest|McpPruneCapacityRegressionTest|McpTransportTest|McpProtocolMetadataRegressionTest|McpConsentToolsTest|GoogleLoginTest"` → lote QA1 de fase: 77 passed / 1808 assertions, exit0, sobre las mismas 352 fuentes revisadas; no es un resultado independiente por tarea. QA2 ejecutó E2E-01–06: 6/6 passed al primer intento, exit0, sin flaky ni omitidos. Revisión 2 A+B+C+D sin defectos pendientes. [Cierre técnico y procedencia](testing/fase-3/acceptance.md), [backend original QA1](testing/fase-3/qa-1/report.md), [QA2](testing/fase-3/qa-2/report.md). M-01–M-04 y la aceptación global T-14 permanecen pendientes; no se suman ejecuciones históricas.

**Criterios de aceptación**

- [x] Guard dedicado acepta sólo el token MCP válido ligado al recurso, grant, cliente y usuario; no usa fallback stateful de Sanctum.
- [x] Cookie web sin bearer, token Google, API key de cuenta, token otro recurso/propósito, expirado o revocado no ejecutan servicios.
- [x] Sesiones persistentes vuelven a validar cada llamada; lista y llamadas directas aplican capacidades vigentes.
- [x] Límites de cuerpo, tamaño de resultado, paginación y concurrencia rechazan excesos de forma definida; no activan otro transporte sin revisión.

**Subtareas**

- [x] Instalar MCP estable resuelto compatible con Laravel 13 y conservar lock.
- [x] Integrar protocolo y manejo de errores sin copiar user_id como frontera de cuenta.
- [x] Verificar Origin/transporte, caché de metadatos y expiración en sesiones abiertas.

**Notas**:

- Implementación preparada, **en-progreso pendiente de revisión y QA**; el contador permanece 3/14. TokenResolver relee token/grant/cliente/recurso/membresía y no consulta auth/session. El presupuesto IP se consume antes de leer como máximo 4 MiB+1 y antes de Origin/autenticación; se excluyen sólo de /mcp los normalizadores globales de Laravel. Parser MCP separado, sin tocar semántica REST. Metadatos y cabeceras del perfil moderno son obligatorios y coherentes; el legado usa initialize/notifications/initialized y sesiones SQL ligadas al token, con caducidad y cap local8. Cada llamada vuelve a autorizar; creación/inicialización de sesión usa el mutex común.
- SDK oficial laravel/mcp1.1.1, commit e77af70cd009aaa31455626bd463b0e7e39cfcf9, instalado con una alta y cero actualizaciones/bajas de paquetes existentes. Laravel13.38/Sanctum4.3.3 conservados. Composer --strict inicial dio exit1 por tres pins exactos; el ajuste autorizado a ~7.2.1/~5.31.0/~1.1.1 conservó todos los metadatos/versiones del lock y sólo refrescó content-hash con Composer oficial. Strict final exit0. ADR-009 propuesta explica los adaptadores y límites; contrato JSON/REST aceptados intactos.
- Evidencia histórica: primer verde5/49 `c7a19ddf968d844faf4813d634456bd7e8bf121bfdaa033d903573ca4dc07f4a`; verde9/82 `2e995c09be988e9f32e2a5bd9d8d9d012791aa4e26d3409a573c757fea186fb1`. Los rojos f6ddaea... (cursor400/tamaño413 convertidos en500 por SDK), d455f82... (cinco rechazos cobraban sólo un hit IP) y 20f280... (Errable reflejaba excepción con APP_DEBUG) se preservan con ZIP/streams/recibos. Sus correcciones no se atribuyen a hashes previos.
- Conjunto `t06-final-runtime-20261025T042947933114Z`, recibo3ecc9589986f3075ac182990b8923985ca2f45a875464f58b5fa01b212369881: 14 passed/1 failed,140assertions; el arnés pasaba --limit como int1 y el comando lo rechazó con INVALID2, sin defecto nuevo de producto. La corrección usa la opción CLI textual. Focal `t06-final-boundary-focal-20261025T091158606811Z`, recibob0968706720780b6286d5f5dfe3d40a9f85ee879d8ea68e6011145b2b0273c1f: 4/65,112.20s,exit0, fuente estable. Los originales incluyen el contrato runtime pin aafcf53e... en ZIP y manifest; las nuevas cápsulas capturan también .dockerignore.
- Slots MySQL por cuenta prueban tres procesos propios con barrera real: holder200, rival429 y nueva ejecución200 tras release. Es prueba del servicio de concurrencia, no carga HTTP hasta producción. Catálogo de probes exclusivo de test prueba 25/100, cursor Crypt de registro ligado a autoridad vigente, corte por bytes sin saltos y rechazo de una primera pieza demasiado grande. El SDK real cuenta el sobre completo (TextContent+structuredContent+metadata) y rechaza generadores antes de liberar el slot. Estos probes no acreditan herramientas de negocio; el catálogo productivo sigue pendiente de sus tareas y las dos herramientas mínimas de T-07 se declararán como prerrequisito sin cerrar T-08.
- Fábrica de errores: operation/código/outcome y referencia se proyectan contra el contrato; kind+id ASCII1..100 conserva el identificador completo o descarta la referencia inválida, nunca lo trunca o renombra una operación desconocida. Hay cota explícita16KiB. Backtest5855fb... restaura sólo nuestra fábrica original exacta desde ZIP95671; falló al conservar id101 (10assertions), no en la cota numérica. El verde posterior admite Response real en el probe y exige result_too_large para descartar un TypeError del arnés; confirma texto acotado y outcome applied con referencia SQL propia. Es mecanismo de transporte/fábrica con auditoría sintética, no negocio real ni pipeline de entrega T-10. Errores SDK arbitrarios no reflejan mensajes, paths o stacks en ambos perfiles.
- Docker: se incluye únicamente docs/api/mcp-tools.json y se excluye *.private.env. Build real de la etapa app y probe sin red/puertos/mounts/escritura prueban contrato pin, SDKv1.1.1 y usuario no root. Se eliminó sólo la etiqueta UUID propia tras contrastar image_id y ausencia del contenedor --rm; cachés compartidas intactas. No acredita HTTPMCP/TLS/clientes ni despliegue T-11/T-14. Un readiness directo inicial falló por permiso de namedpipe; retry dirigido capturado salió0, sin inferir daemon caído. Docker receipts099aa5f...,4c9dc784... y6b135103... conservan fuentes, streams e inputs privados de comprobación.
- Medición y operación: hubo discontinuidad de reloj entre recibo terminal04:38:10Z y reactivación solicitada09:06Z; no se atribuye a IA, CPU ni horas humanas. El cache del plugin1.22.0 desapareció; los helpers oficiales2.0.0 conservan formato de estado v2 y sustituyen sólo las utilidades de cierre/ledger/scope, manteniendo el workflow leído y autoridad de sesión. Sin transcripciones disponibles, tokens/coste/horas reales quedan null; no se convierten intervalos de reloj en consumo. No se inició app web, navegador, broker ni SMTP externo. Settlement SQL sólo observa esquema tras DatabaseMigrations, no prueba DELETE ni recrea tablas. Se conserva exclusividad de toda fase3 aunque el runtime propio de T-06 esté liberado.
- **Changelog**: MCP sirve ambos perfiles contratados con tokens propios verificados en cada petición, sesiones acotadas y límites de entrada, respuesta y concurrencia. Los errores y cursores conservan permisos y resultados seguros sin depender de la cookie web.


### T-07 — Añadir consentimiento, conexiones y auditoría

- **Descripción**: Permitir elegir cuenta y permisos, aprobar o denegar la conexión y revocarla desde la web. Registrar cuenta, actor, cliente y correlación en operaciones MCP.
- **Partida**: C-03 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Cerrada**: 2026-10-25
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-03, T-04, T-06
- **Tipo**: frontend
- **Archivos**: `.gitignore`, `app/Http/Controllers/McpConnectionsController.php`, `resources/views/mcp/connections.blade.php`, `resources/views/mcp/consent.blade.php`, `resources/views/components/app-layout.blade.php`, `routes/web.php`, `app/Services/Mcp/GrantService.php`, `app/Services/Mcp/Audit.php`, `app/Models/McpAuditEvent.php`, `database/migrations/2026_10_25_000006_create_mcp_audit_events_table.php`, `app/Services/Mcp/DelegatedContext.php`, `app/Services/Mcp/RegistryCursor.php`, `app/Services/Mcp/ContractArguments.php`, `app/Services/Mcp/CallTool.php`, `app/Services/Mcp/Tools/ContactListTool.php`, `app/Services/Mcp/Tools/GetList.php`, `app/Services/Mcp/Tools/CreateList.php`, `app/Services/Mcp/TransportServer.php`, `app/Http/Controllers/McpTransportController.php`, `app/Services/Mcp/OAuth/AuthorizationServer.php`, `app/Services/Auth/GoogleLoginFlow.php`, `app/Services/TeamService.php`, `app/Console/Commands/McpPrune.php`, `tests/Feature/McpConsentToolsTest.php`, `tests/Feature/McpTransportTest.php`, `tests/Feature/GoogleLoginTest.php`, `tests/e2e/mcp-google.spec.mjs`, `tests/support/google-browser-fixtures.php`, `tests/support/google-browser-server.php`, `tests/support/google-browser-launch.mjs`, `tests/support/google-browser-teardown.mjs`, `playwright.config.mjs`, `docs/aplicacion/conexiones-mcp.md`, `docs/README.md`, `docs/knowledge/adr/ADR-010-consentimiento-y-auditoria-mcp.md`, `docs/knowledge/README.md`, `docs/roadmap/2026-10-24-mcp-google/test-plan.md`, `app/Http/Controllers/Mcp/OAuth/OAuthController.php`, `app/Http/Middleware/McpOAuthBoundary.php`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/report.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/evidence.json`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/google-real-consent.png`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/mcp-limited-consent.png`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/mcp-consent-safe-error.png`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/mcp-connections-and-audit.png`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/implementation/mcp-revoked.png`, `docs/roadmap/README.md`, `docs/roadmap/2026-10-24-mcp-google/improvement-plan.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-3/acceptance.md`
- **Cubre (tests)**: E2E-01, E2E-04, E2E-05, E2E-06; M-02 pendiente de una persona
- **Verificación**: `php artisan test --filter="McpAuthorizationTest|McpRevocationConcurrencyTest|TeammateTest|McpMembershipRemovalRegressionTest|McpOAuthTest|McpOAuthConcurrencyTest|McpOAuthRegistrationRegressionTest|McpPruneCapacityRegressionTest|McpTransportTest|McpProtocolMetadataRegressionTest|McpConsentToolsTest|GoogleLoginTest"` → lote QA1 de fase: 77 passed / 1808 assertions, exit0, sobre las mismas 352 fuentes revisadas; no es un resultado independiente por tarea. QA2 ejecutó E2E-01–06: 6/6 passed al primer intento, exit0, sin flaky ni omitidos. Revisión 2 A+B+C+D sin defectos pendientes. [Cierre técnico y procedencia](testing/fase-3/acceptance.md), [backend original QA1](testing/fase-3/qa-1/report.md), [QA2](testing/fase-3/qa-2/report.md). M-01–M-04 y la aceptación global T-14 permanecen pendientes; no se suman ejecuciones históricas.
- **Changelog**: Consentimiento OAuth real conserva el retorno tras login, ofrece cuenta y capacidades vigentes y permite revocar conexiones propias con auditoría segura. El cliente recibe el token automáticamente y dos herramientas mínimas verifican el aislamiento sin depender de la cuenta activa web.

**Criterios de aceptación**

- [x] Pantalla presenta cliente, cuenta y capacidades concretas; sólo ofrece membresías y permisos actuales, con POST+CSRF para aprobar/denegar. El canje devuelve el token al cliente automáticamente: no exige crear/copiar una API key en cada cliente.
- [x] Cambiar de cuenta exige un grant nuevo; no se modifica el account_id de una conexión emitida.
- [x] La persona sólo gestiona sus conexiones; revocar corta tokens y sesiones de esa conexión sin tocar otra cuenta. Refresh no se emite y su grant_type se rechaza.
- [x] Auditoría de éxito y denegación identifica actor, cuenta, cliente, operación y correlación sin tokens, secretos o contenido completo innecesario.

**Subtareas**

- [x] Añadir vistas de consentimiento y conexiones con estados vacíos, errores y confirmación de revocación.
- [x] Preservar solicitud OAuth validada durante login local/Google, sin aceptar retorno externo arbitrario.
- [x] Documentar diferencia entre iniciar sesión, consentir cliente y autorizar un envío.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

- **Implementación en curso (T07)**: Ventana propia abierta 2026-10-25T09:34:40Z. Se extraen exactamente lists_get y lists_create productivas como prerrequisito de E2E-04/05; T-08 conserva sus 20 operaciones y su verificación, sin doble atribución. Auditoría dedicada nullable antes de elegir cuenta y correlación explícita; ADR-010 propuesta. Se aplican mapas api-contract/backend-practices/delivery-practices/frontend-quality/stack-practices de custom-agents 2.0.0 y referencias shared-boundaries, contracts-data/service-boundaries, delivery-tools, interaction-performance y criterios PHP genéricos de php-codeigniter; la fuente 1.22.0 del despacho ya no está instalada. No se cambian alcance, estimaciones ni puertas aprobadas.
- **Evidencia inicial T07**: McpConsentToolsTest, 5 passed/82 assertions, 115.62s, exit0; recibo privado t07-consent-tools-initial-20261025T095910282085Z sha256 3e34602041b4fd6add15b6e66bc6cee9a0d3076b4d2b1d58d14556864101c8b0, 347 fuentes estables. Prueba DTO/tenant B con selector A, herramientas reales, scopes/IDs directos, revocación propia, auditoría y continuidad de cursor entre dos contextos SQL con correlaciones distintas; esta última todavía no se atribuye a dos HTTP. UI/E2E y regresiones finales pendientes; no es aceptación ni QA formal.


- **Cierre técnico T07**: Implementación terminada, estado en-progreso pendiente de revisión/QA; no aceptación global ni avance del contador3/14. Navegador final y regresiones de desarrollo documentados con fuentes exactas; los rojos anteriores permanecen preservados. M-02 sigue sin marcarse y no se atribuyen clientes/Google reales. Medidor propio y settlement final se relacionan en el handoff de fase, con métricas reales desconocidas.

## Fase 4 — Catálogo de negocio

**Estado**: en-progreso · **Estimado**: 32h base · **Real**: — · **Coste est.**: 1600 € equivalentes + tokens por verificar · **Tokens est.**: 240000

### T-08 — Herramientas de contactos, listas, importaciones y plantillas

- **Descripción**: Implementar veinte operaciones de datos con argumentos/resultados contractuales y servicios comunes. No crear una lógica paralela de validación o permisos.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.3h (estimado por tokens) · real —
- **Supervisión**: est. 0.075h (25 % IA) · real —
- **Previsión IA**: 75000 in / 15000 out tok · coste 0.075 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-06, T-07
- **Tipo**: backend
- **Archivos**: `app/Services/Mcp/Tools/*`, `app/Services/Mcp/ContractArguments.php`, `app/Services/Mcp/DataCursor.php`, `app/Services/Mcp/WireJson.php`, `app/Services/Mcp/CallTool.php`, `app/Services/Mcp/TransportServer.php`, `app/Http/Controllers/McpTransportController.php`, `app/Services/ContactService.php`, `app/Services/ContactImportService.php`, `app/Services/TemplateService.php`, `composer.json`, `composer.lock`, `tests/Feature/McpDataToolsTest.php`, `tests/Unit/McpContractArgumentsTest.php`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`, `app/Services/Mcp/DelegatedContext.php`, `app/Services/Mcp/DataTools.php`, `app/Services/Mcp/DataOperations.php`, `app/Services/Mcp/DataDto.php`, `app/Services/Mcp/DataPage.php`, `app/Services/Mcp/ResponseBudget.php`, `app/Services/Mcp/DataFailure.php`, `tests/Feature/McpConsentToolsTest.php`, `tests/Unit/McpWireInputsTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/roadmap/2026-10-24-mcp-google/test-plan.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t08/implementation/*`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t08/qa1/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t08/review-1/*`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t08/qa2/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t08/acceptance.md`
- **Cubre (tests)**: E2E-07; McpDataToolsTest, McpContractArgumentsTest, McpWireInputsTest y regresión McpConsentToolsTest
- **Verificación**: QA1 backend nativo: 60 casos /1410 aserciones, exit0; protocolo separado: 2/215, exit0; estabilidad separada E2E-07: 3/3. QA2 independiente: navegador E2E-01–07 original, 7/7 al primer intento, sin fallos/flaky/omitidos/interrupciones, qa-gate exit0. Las mismas 380 fuentes fueron revisadas y ejecutadas. [Aceptación técnica y límites](testing/fase-4/t08/acceptance.md), [QA2](testing/fase-4/t08/qa2/report.md), [revisión 1](testing/fase-4/t08/review-1/report.md). QA1 conserva NO-VERDE6/7; causa histórica sin confirmar. No se suman tandas.

**Criterios de aceptación**

- [x] Contactos 5, listas 7, reportes de importación 2 y plantillas 6 implementan las 20 operaciones del catálogo inicial.
- [x] Cada herramienta cruza grant y membresía con contacts.read/write o templates.read/write; IDs ajenos se rechazan sin revelar existencia.
- [x] Resultados paginados y errores siguen contrato; preview usa renderer seguro y no expone credenciales ni rutas internas.
- [x] Mutaciones destructivas exigen confirmación y versión cuando el contrato de dominio la requiere; permisos se verifican también en llamadas directas.

**Subtareas**

- [x] Crear clase base de autorización/auditoría/errores y adaptadores pequeños a servicios.
- [x] Probar cada esquema, resultado, permiso, validación y límites.
- [x] Mantener importación de archivos por su flujo existente; estas dos herramientas consultan reportes, no aceptan paths del host.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

- **Inicio T-08 (2026-10-25)**: Tras aceptación y publicación de T-04–T-07 en 08d51f9, se reconcilian las rutas del plan con app/Services/Mcp y el adaptador de transporte existente. Se conservan las veinte operaciones, criterios y servicios comunes. Investigación previa compara validadores; la prueba privada del renderer/sanitizador real produjo un documento de 834838 bytes con fuente de 100000 bytes y 200 variables. Es una observación de borde, no una cota universal ni QA de herramienta MCP. Medidor abierto sin transcripciones disponibles; no se atribuye tiempo de reloj a tokens u horas reales.

- RED: McpContractArgumentsTest::test_empty_json_object_is_valid_for_optional_contact_list_arguments falló con «Failed asserting that false is true» · 2026-10-25. Recibo original SHA-256 `fa8fcc28f9efab4ecc9355b6efa3bdea1c5c25827de181d9f7f946328d21b3d4`. El validador anterior sólo atendía dos prerrequisitos y perdía tipos JSON; no era implementación del catálogo T-08.
- **Base de esquemas T-08**: Opis JSON Schema 2.6.0 con resolución externa deshabilitada, draft 2020-12, sin defaults ni extensiones mutantes. Se conserva el AST antes de deserializar en el SDK; los límites x-maxUtf8Bytes se integran como formatos locales dentro del evaluador estándar de ramas. Primera tanda unitaria: 24 passed /24 assertions, exit0, recibo `3a07ec02273e64d29f975e606fd44b17a821c03464bd55c290b2e0a6a3b288f5`. No son veinte operaciones MCP ejecutadas ni QA formal. Composer incorporó opis/json-schema 2.6.0, opis/string 2.1.0 y opis/uri 1.1.0 (Apache-2.0); los 138 paquetes existentes permanecen idénticos. El capturador registra la mutación intencionada de composer.json/lock, no una ejecución de pruebas. Las comprobaciones de negocio, aislamiento, paginación y salida de las veinte operaciones siguen pendientes.

- **Regresión de prerrequisitos T-08**: McpConsentToolsTest → 9 passed /146 assertions, 159.23s, exit0; recibo `6aa0f3694c31159799685c83ec7166bb8e2f3dc7807c85207a5745fcc0434ecb`. La posterior tanda de comprobaciones repite las 24 unitarias y pasa Composer strict, Pint, contrato, privacidad (1789/0), ledger (0 incoherencias; siete avisos de Changelog futuro), alcance (7/7, fuera0) y diff; recibo `4989d255363ad0d30535ed5b61fb2a31438940c644649288b09dd3c44ea379ed`. Sus cápsulas contienen 353 fuentes; la prueba de catálogo añadida después contiene 354. No se presenta QA formal ni se suman tandas.
- RED: McpDataToolsTest::test_all_twenty_data_tools_are_discoverable_with_the_pinned_schemas falló con «Failed asserting that two arrays are identical» · 2026-10-25. Esperadas veinte operaciones de datos del contrato; observadas sólo lists_create y lists_get. Recibo `ec74d29664d01a219e7c673242e38985cfc2e195ece9f1e0b464de293bbc867a`, 1 failed /2 assertions, fuentes estables. Este primer oráculo de descubrimiento no sustituye las pruebas positivas/negativas de negocio, paginación, autorización y DTOs de cada operación; T-08 sigue en-progreso y 7/14 permanece sin cambios.

- **Implementación preparada (T-08)**: Veinte adaptadores reales y los siete E2E pasan en desarrollo. [Informe y límites](testing/fase-4/t08/implementation/report.md). El consentimiento ya admite las cuatro capacidades mediante el flujo OAuth existente, sin modificar sus políticas. Revisión independiente y QA formal pendientes; la tarea sigue en-progreso y no cambia 7/14.

- **Revisión 1 y QA1 (2026-10-25)**: [A+B sin hallazgos](testing/fase-4/t08/review-1/report.md), diff completo de 49 archivos. C y D no activadas por el selector oficial. Root admitió la evidencia estática antes del handoff; los revisores no ejecutaron runtime. La [QA1 independiente](testing/fase-4/t08/qa1/report.md) verificó 60 casos/1410 aserciones y una regresión protocolaria separada de 2/215; navegador NO-VERDE 6/7 por ERR_TIMED_OUT en el guardrail HTTP previo de E2E-01, antes del flujo de producto. E2E-07 pasó además estabilidad 3/3 separada. Se conservan el fallo, los reporters originales y los límites de fuente; no se suman tandas ni se cierra la tarea. Informe/PDF verificado de 11 páginas, 13 PNG originales y cuatro manuales pendientes. Root contrastó hashes de los diez runs, sus 380 fuentes, 86 artefactos de la entrega y las capturas. SHA de evidence.json: `520b5021dbaee717a3ce833daae57a72171cc01e9a7329e262b4b6dbdee84959`.
- **Alcance de evidencia**: tras el handoff terminal se añaden qa1/** y review-1/* al campo Archivos; no cambian producto, tests, contratos ni criterios. T-08 sigue en-progreso, MCP7/14 y principal18/20. La causa concreta del timeout está pendiente de diagnóstico; no se propone parche sin hipótesis probada. Una reproducción privada sin aplicación ni SQL obtuvo diez rechazos HTTP502/CONNECT correctos y cierre de sus sockets, pero no reprodujo el fallo y no constituye QA del recorrido de identidad.

- **Cierre técnico T-08 (2026-10-25)**: Root admite [QA2 independiente](testing/fase-4/t08/qa2/report.md) tras contrastar 52 hashes de artefactos, originales, siete runs terminales, 380 fuentes comunes, 14 capturas y nueve páginas PDF. La entrega contiene además evidence.json y el recibo de privacidad generado al final: 54 archivos. Sin cambios de producto desde la revisión A+B, 0 hallazgos. La consulta SQL propia acredita 21 categorías cero/tx0; binds exclusivos de los tres puertos y callback cerrados, sin inventar recibo natural de cleanup ni ausencia de procesos. T-08 completada, fase4 1/3 y ampliación8/14; M-01–M-04 siguen pendientes. [Aceptación y alcance](testing/fase-4/t08/acceptance.md).
- **Changelog**: Veinte herramientas MCP permiten gestionar contactos, listas y plantillas y consultar importaciones mediante los servicios comunes, con permisos vigentes, esquemas tipados, cursores autenticados y preview seguro.

### T-09 — Herramientas de campañas, actividad y supresiones

- **Descripción**: Implementar catorce operaciones del flujo de negocio con revisiones, versiones, autorización de envío y controles actuales de bajas.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: completado
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.3h (estimado por tokens) · real —
- **Supervisión**: est. 0.075h (25 % IA) · real —
- **Previsión IA**: 75000 in / 15000 out tok · coste 0.075 p_in + 0.015 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-08
- **Tipo**: backend
- **Archivos**: `app/Services/Mcp/Tools/*`, `app/Services/Mcp/DataTools.php`, `app/Services/Mcp/DataOperations.php`, `app/Services/Mcp/BusinessOperations.php`, `app/Services/Mcp/BusinessDto.php`, `app/Services/Mcp/DataCursor.php`, `app/Services/Mcp/DataPage.php`, `app/Exceptions/DomainConflict.php`, `app/Services/CampaignService.php`, `app/Services/ActivityService.php`, `app/Services/SuppressionService.php`, `app/Livewire/Suppressions.php`, `app/Livewire/Campaigns.php`, `tests/Feature/McpBusinessToolsTest.php`, `tests/Feature/McpConsentToolsTest.php`, `tests/Feature/CampaignTest.php`, `tests/Feature/ActivityTest.php`, `tests/Feature/SuppressionTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/roadmap/2026-10-24-mcp-google/test-plan.md`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/implementation/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/review1/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/qa1/**`, `tests/Concerns/ReplaysMigrationHistory.php`, `tests/Feature/MigrationReplayTest.php`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix1/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/review2/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/qa2/**`, `tests/Feature/McpTransportTest.php`, `docs/knowledge/candidates/pending/2026-10-26-restaurar-migraciones-ante-fallos-de-evidencia.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix2/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/review3/**`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/qa3/**`, `docs/knowledge/candidates/pending/2026-10-26-aislar-datos-por-repeticion-e2e.md`, `.gitattributes`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/acceptance.md`, `docs/roadmap/README.md`
- **Cubre (tests)**: E2E-08; McpBusinessToolsTest y regresiones de CampaignTest, ActivityTest, SuppressionTest y McpConsentToolsTest
- **Verificación**: Suite completa nativa Unit+Feature412/7967, subconjunto T09151/3175 incluido; QA2 propio8/85 conservado. Revisión3 A+B+D sin gaps; QA3 independiente originales8/8 y estabilidad3/3, retry0 y qa-gates0; SQL propia28 categorías cero/tx0 y tres binds por tanda. [Aceptación técnica y límites](testing/fase-4/t09/acceptance.md). No rerun PHP tras el ajuste exclusivo de datos E2E:398 entradas backend idénticas.
- **Changelog**: Se añaden catorce herramientas MCP de campañas, actividad y supresiones sobre los servicios comunes, con autorización vigente, conflictos tipados y paginación verificable; se conservan las evidencias de migración y se aíslan los datos de las repeticiones E2E.

**Criterios de aceptación**

- [x] Campañas 8, actividad 3 y supresiones 3 cubren el catálogo descrito, con permisos de cada operación.
- [x] Programar/autorizar exige campaigns.write y mail.send, revisión vigente, confirmación e idempotencia según el dominio común.
- [x] Pausa/reanudación y revisiones obsoletas conservan sus conflictos de negocio; no duplican materialización ni checkpoint.
- [x] Gestionar supresiones no inventa una nueva prueba de consentimiento; se conservan los controles de baja y destinatario existentes.

**Subtareas**

- [x] Adaptar servicios explícitos de campaña y las consultas de actividad/supresión.
- [x] Añadir anotaciones de efectos e inputs verificables, con pruebas de invocación directa.
- [x] Probar caída de broker y estado seguro sin envío alternativo.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

- **Inicio T-09 (2026-10-25)**: Dependencia T-08 aceptada y publicada en 0fbc8c7. Se reconcilian las rutas antiguas app/Mcp con app/Services/Mcp y se reutilizan la frontera DataTool, DTOs/paginación y los servicios comunes. El contrato de 37 operaciones permanece fijado; T-09 añade catorce al catálogo de veinte. Los conflictos tipados conservan HTTP409 y sus mensajes para web/API, evitando inferir códigos MCP de texto traducido. El borrador privado de pruebas se integra para obtener el primer rojo de catálogo antes de implementar. Sin dev.json: defaults tdd/worktree/subagentes false, implementación en contexto principal; revisión y QA independientes se mantienen. Medidor abierto sin transcripciones disponibles, sin atribuir consumo o tiempo IA real.

- RED: McpBusinessToolsTest::test_all_fourteen_business_operations_publish_the_pinned_contract falló con «Failed asserting that two arrays are identical» · 2026-10-25. Esperadas catorce operaciones de negocio; observadas cero. PHPUnit nativo: 1 fallo /2 aserciones, exit1, 381 fuentes sin cambios; recibo SHA-256 `f46fa3468e86d702eb4b6db0ce100432559971c036a3f9834c009adf91608148`. Es el oráculo inicial de catálogo, no evidencia de ejecución de negocio.
- **Base común en preparación**: conflictos de campaña tipados conservan HTTP409 y mensajes existentes. Se extraen consultas autorizadas de intentos y supresiones para compartirlas con sus pantallas y MCP; el alcance añade explícitamente app/Livewire/Suppressions.php. Aún no se registran las catorce herramientas ni se acepta T-09; ampliación8/14.

- **Verificación intermedia no aceptable**: la primera regresión de CampaignTest/ActivityTest/SuppressionTest arrancó antes de terminar Pint; ActivityService.php y McpBusinessToolsTest.php cambiaron respecto a la captura inicial. Se conserva como diagnóstico, se espera su terminal natural y se exigirá otra tanda sobre fuentes finales estables. No acredita criterios ni QA y no modifica 8/14.

- **Integración de adaptadores (2026-10-25)**: La primera regresión común terminó con PHPUnit67 casos/746 aserciones, exit0, pero su wrapper rechazó la captura por dos cambios de formato; se conserva sin aceptación. Se integran catorce adaptadores, DTOs explícitos, cursores de revisión y ventana UTC, y corte de página sobre el resultado SDK completo. El alcance añade la pantalla de campañas para reutilizar su consulta autorizada de destinatarios. Ninguna herramienta abre SMTP, materializa entregas ni publica en RabbitMQ. Las cinco poblaciones de métricas incluyen MCP con cero registros mientras T-10 no incorpore su procedencia real. T-09 sigue en-progreso,8/14.

- **Verificación de desarrollo**: El catálogo pasó 1 caso/44 aserciones y la primera tanda funcional pasó 12 casos/688 aserciones, exit0, con 398 fuentes estables. Las tandas son distintas y no se suman. El backend ampliado pasó después **17 casos/955 aserciones**, sin fallos ni omitidos, exit0, 398 fuentes estables de la versión ampliada; recibo `c63647ea91687004df66dd0132e1ebf966d944799292e1d2e9bb1fa99389027f`, JUnit `cd6a571d0ec16606e80ca5e137797cc39987270f2f99d7807309b24ef69c412b`. Comprueba fechas UTC, claves de actividad opacas con búsqueda literal, cursores por padre/grant, límite real de respuesta y rollback antes de commit. E2E-01–08 terminaron en verde8/8 al primer intento; el recorrido nuevo generó tres capturas originales inspeccionadas y la limpieza SQL propia acredita28 categorías cero/tx0. Revisión y QA independientes pendientes; criterios sin aceptar y contador8/14.

- **Implementación preparada, pendiente de puertas independientes**: Los checks marcados describen implementación y verificación de desarrollo, no aceptación técnica. T-09 permanece en-progreso y MCP8/14. [Evidencia de desarrollo](testing/fase-4/t09/implementation/evidence.json). La QA deberá acreditar las regresiones comunes y MCP previas sobre las fuentes finales; la tanda diagnóstica67/746 queda descartada para ese fin.

- **Corrección del fixture de revocación, sin cambio de producto**: Antes de congelar revisión se detectó que una prueba escribía withdrawn directamente en SQL, fuera del enum contractual. Se sustituyó por contacts_update con revoked y una aserción explícita del estado admitido. Focal actual1 caso/25 aserciones, exit0; recibo `8fb246a5909520eb56d73c5a748610cf0d5a92ddf2d1537ebfa43fda9bc55bba`. La cápsula de17/955 y navegador8/8 difiere del árbol actual sólo en este test; producto y E2E son idénticos. Se conservan los originales y la QA independiente ejecutará las17 pruebas finales y regresiones. [Calificación actualizada](testing/fase-4/t09/implementation/report.md). El marcador de seguimiento se abrió después de editar el fixture; no se atribuye medición real ni cobertura temporal completa.

- **Revisión de dos lentes — intento 1 (2026-10-25)**: A requisitos y B corrección sin hallazgos; Critical0/Important0/Minor0. Ambas examinaron37 cambios y verificaron1.991 archivos congelados. Admisión raíz por hashes y cobertura completa; selector C/D=false sin avisos. [Informes originales y límites](testing/fase-4/t09/review1/report.md). No hubo corrección de producto. Se prepara QA independiente de la clase final, regresiones comunes/MCP y ocho E2E más estabilidad separada del nuevo recorrido. La tarea permanece en-progreso y8/14; sin consumo ni tiempo IA medidos.

- **QA1 NO-VERDE y diagnóstico fix1 (2026-10-25 UTC)**: QA independiente144 casos/3092 aserciones, nueve clases,0 fallos/1 error/0 omitidos; los17 casos de negocio finales acumulan959 aserciones sin errores. Error histórico `rename` con acceso denegado Windows/code5. Los ocho E2E y estabilidad3 no se ejecutaron en QA1; no hay qa-gate verde ni aceptación. [Informe original](testing/fase-4/t09/qa1/report.md). La guarda/tx0 y tablas ausentes posteriores acreditan teardown, no restauración histórica. Experimento OS aislado: crear y reemplazar sin handle funcionan; mantener el destino sin share-delete reproduce ErrorException, y publicar un snapshot nuevo funciona con el anterior retenido. Holder original no identificado. Una copia congelada del fixture, instrumentada sólo en sus dos publicaciones, prueba dos fallos de restauración con MySQL real al fallar la evidencia tras down o up: `mcp_audit_events` queda ausente. Se amplía el alcance al fixture y su regresión: snapshots inmutables, error de evidencia separado de DDL y conservación de causa primaria. Corrección, revisión nueva y QA final pendientes; contador8/14 intacto.

- **Compatibilidad del transporte**: El focal real de discovery conserva una expectativa de dos herramientas anterior al catalogo de T-08/T-09; RED nativo1 caso, count25 de la pagina por defecto frente a2 esperado. El alcance incorpora actualizar esa expectativa a34 herramientas con per_page100, conservando initialize legacy y ambas comprobaciones de revocacion. Suite completa y QA final pendientes.

- **Fix1 focal verificado; suite completa en curso**: Regresion causal del fixture RED6 casos/28 aserciones, cuatro fallos y cero errores; GREEN6/48, mismo test SHA y399 fuentes estables. Historico original1/25, veinte pasos completados/restaurados, sin fallo primario ni de evidencia. Discovery antiguo RED1/5 por count2 frente a25 de pagina; GREEN1/12 tras pedir pagina100 y verificar34 herramientas, sin eliminar comprobaciones. [Diagnostico y limites](testing/fase-4/t09/fix1/report.md). Suite Unit+Feature completa nativa en ejecucion, imagen Linux aislada con lockfiles actuales; sin resultado ni aceptacion anticipada. Revision2 y QA2 con8E2E+estabilidad3 pendientes,8/14 intacto.


- **QA2 NO-VERDE; inicio de diagnóstico fix2 (2026-10-26 UTC)**: Backend completo de root admitido independientemente412/7967, subconjunto151/3175 incluido; regresión propia QA2 8/85 verde. Ocho E2E originales8/8, gate verde. Estabilidad válida3 resultados reales:1 pasa y2 fallan contacts_create con HTTP200/result.isError=true, retry0; gate NO-VERDE. La selección privada anterior sin casos se conserva como diagnóstico inválido, no estabilidad. [Entrega QA2](testing/fase-4/t09/qa2/report.md). Root verifica166 hashes públicos, siete runs nativos/ZIP/fuentes399 estables,78 copias nativas y SQL propia28 categorías cero/tx0 en tres tandas; admite la liberación explícita y cierra el medidor. Su inspección visual de PDF/capturas queda pendiente, sin atribuirse la realizada por QA. La fuente deriva el email del run compartido y conserva el contacto como dependencia de campaña; el código cerrado del error no fue observado. Hipótesis de aislamiento pendiente de prueba HTTP real; no se cambia producto ni E2E antes de probarla. Se abre medidor fix2 y se corrige únicamente la errata de ruta declarada de McpTransportTest; no cambia ese test ni criterios. Revisión nueva y QA3 pendientes, contador8/14 intacto.


- **Cierre técnico T-09 (2026-10-26 UTC)**: Revisión3 y QA3 admitidas desde sus originales; ambos gates verdes8/8 y3/3, fuentes399 estables. Se conservan QA1/QA2 NO-VERDE y la desviación declarada del inventariador de ámbito usuario, sin publicar salida/rutas ni inventar ausencia de lecturas privadas. Root inspecciona6 páginas PDF y4 originales; QA3 inspeccionó26 capturas. [Aceptación incremental](testing/fase-4/t09/acceptance.md). T-09 completada, catálogo34/37 y MCP9/14; T10–T14/principalT19/T20/manuales/Curator/retro siguen pendientes. Sólo se prepara commit/push normal, sin publicación remota afirmada antes de verificarla.

### T-10 — Envío transaccional y estado sobre el pipeline común

- **Descripción**: Añadir información de conexión y dos operaciones de envío/estado. Compartir exactamente la autoridad de T-18 para contenido, destinatarios, idempotencia, outbox y supresiones.
- **Partida**: C-04 de evaluation.md; herencia sin reestimación.
- **Estado**: en-progreso
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.2h (estimado por tokens) · real —
- **Supervisión**: est. 0.05h (25 % IA) · real —
- **Previsión IA**: 50000 in / 10000 out tok · coste 0.05 p_in + 0.01 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media-baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-09; 2026-10-21-plataforma-emailing/T-18 terminada y aceptada
- **Tipo**: backend
- **Archivos**: `app/Services/Mcp/Tools/ConnectionInfo.php`, `app/Services/Mcp/Tools/SendMail.php`, `app/Services/Mcp/Tools/GetMailRequest.php`, `app/Services/Mcp/McpSendAdapter.php`, `app/Services/Mcp/DataTools.php`, `app/Services/Mcp/DataOperations.php`, `app/Services/Mcp/Tools/DataTool.php`, `app/Services/Mcp/ContractArguments.php`, `app/Services/Mcp/WireJson.php`, `app/Services/Mcp/BusinessDto.php`, `app/Http/Controllers/McpTransportController.php`, `app/Services/SendingPrincipal.php`, `app/Services/SendingApiService.php`, `app/Services/AccountMailTransport.php`, `app/Services/ActivityService.php`, `app/Models/ApiMailRequest.php`, `app/Models/MailMessage.php`, `database/migrations/2026_10_26_000007_share_transactional_mail_origins.php`, `resources/views/livewire/activity.blade.php`, `tests/Unit/McpMailWireTest.php`, `tests/Unit/McpMailCanonicalInputTest.php`, `tests/Feature/McpConnectionInfoTest.php`, `tests/Feature/McpSendTest.php`, `tests/Feature/McpApiIdempotencyTest.php`, `tests/support/mcp-api-concurrency.php`, `tests/Feature/SendingApiTest.php`, `tests/Feature/McpTransportTest.php`, `tests/Feature/McpDataToolsTest.php`, `tests/Feature/McpConsentToolsTest.php`, `tests/Feature/McpBusinessToolsTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/api/mcp-tools.json`, `docs/aplicacion/mcp-contrato.md`, `docs/roadmap/2026-10-24-mcp-google/test-plan.md`, `docs/roadmap/2026-10-24-mcp-google/improvement-plan.md`, `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/**`, `docs/roadmap/2026-10-24-mcp-google/execution-history.md`, `docs/roadmap/README.md`, `tests/Feature/MigrationReplayTest.php`, `tests/Concerns/ExercisesSendingApi.php`, `tests/Feature/McpPipelineTest.php`, `tests/support/google-browser-fixtures.php`, `tests/Feature/ConcurrentCampaignTest.php`, `tests/support/campaign-action.php`, `tests/Concerns/ExercisesSendingApiProcesses.php`, `tests/support/ApiProcessProtocol.php`, `tests/support/sending-api-action.php`, `tests/support/sending-api-deadlock.php`, `.gitignore`, `tests/Feature/OutboxIntegrationTest.php`, `tests/support/outbox-action.php`, `tests/Feature/WorkerRecoveryTest.php`, `tests/support/delivery-action.php`, `tests/support/delivery-sql-proxy.py`, `tests/support/DeliveryFixtureProtocol.php`, `tests/support/test_delivery_sql_proxy.py`
- **Cubre (tests)**: E2E-09
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


- **Capacidades**: api-contract, backend-practices, delivery-practices, stack-practices, unit-tests
- **Procedencia de capacidades**: selector del bundle custom-agents 2.0.0, rol implementer, áreas api/data/mcp/testing, PHP detectado en composer.json, sin avisos. Laravel conserva sus convenciones; no se aplican patrones CodeIgniter. Inventario codex limitado al proyecto: sin piezas ni conflictos; no se leen configuraciones de usuario.
- **Arranque T-10 (2026-10-26 UTC)**: dependencia T09 aceptada y publicada normalmente en `f38e282`; selección470 archivos y blobs/modos comprobados, índice público sin coincidencias de los patrones revisados. Se reconcilia Archivos con los adaptadores reales de app/Services/Mcp y la migración/servicios/actividad/UI/tests necesarios, sin alterar criterios aprobados ni contratos fijados. Cambio de capacidad nueva: primer oráculo, nuevos tests de contrato/HTTP/SQL/concurrencia sobre fuentes instaladas. Los borradores privados y sus pruebas puras anteriores no acreditan integración ni finalización. Misma autoridad de aceptación API/MCP, origen del primer commit inmutable, sin claves/contactos/campañas sintéticos de producción ni segundo SMTP. Mailpit/log solamente.
- **Disciplina y medida**: no existe dev.json; tdd/worktree/subagentes desactivados por defecto, rama existente conservada. Implementer ejecutado en el contexto principal; revisión y QA independientes posteriores obligatorias. Medidor con almacén privado y directorio de transcripciones explícitamente no disponible: tokens/coste/horas IA desconocidos, nunca cero ni inferidos del reloj.

- **Primer oráculo instalado T-10 (2026-10-26 UTC)**: las dos nuevas clases puras se instalaron antes de corregir producto. RED: 24 casos/14 aserciones, 10 errores de referencia interna y 4 fallos de expectativa, PHPUnit exit2; recibo `9d435465847094b21ca5c0473ebb3fc3e9a15f86082ffd623b2b8e8ce788ead9`. Se conserva JUnit y fuentes401 estables. Corregidos ContractArguments/WireJson para validar fragmentos con la raíz fijada y caché por fragmento/raíz. GREEN ampliado:65 casos/124 aserciones, exit0, cero problemas; recibo `bc408563721657410bffedc2cfed5f9670b3bee914d02ad92eb703b1c6374fd9` y JUnit `43a4e6a069273c91408206ea166076020a38f30c270714fcdad0f4947066be59`. Incluye las41 pruebas puras previas; no se suman tandas. Sin bootstrap de aplicación, DB, transporte ni conexiones. No acredita HTTP/SQL/concurrencia ni acepta T-10.
- **Integración inicial T-10**: instaladas las tres herramientas restantes, adaptador común, principal verificado, autoridad única de aceptación, migración aditiva y pruebas funcionales propuestas. La prueba de actor eliminado exige la credencial MCP de cuatro campos del contrato fijado, corrigiendo el borrador previo que reutilizaba `name` de API keys. Actividad/CSV/UI, roundtrip SQL, concurrencia, transporte y regresión siguen pendientes; catálogo37 registrado todavía sin evidencia funcional ni aceptación.

- **Primera integración MySQL T-10 (2026-10-26 UTC)**: catálogo completo37 con esquemas/anotaciones fijados aprobado1 caso/40 aserciones, recibo `c6f737bc663d170073df08f0133aab7128f100b357c7d8baf6e720f751fb12e3`. Tanda posterior envío/conexión20 casos/460 aserciones,15 pasan y5 fallan, cero errores/skips, recibo `ae0ccc84c8e0554a48a5b9955980be6646e7cb96a44402f54cf8e35def9eb371`;411 fuentes estables. Los cinco diffs originales muestran sólo movimiento de las mismas claves/valores del recibo JSON tras lectura MySQL; root comprueba igualdad de multiconjuntos de líneas añadidas/retiradas. Se corrige la comparación del objeto en esos tests y en la prueba de concurrencia aún no ejecutada mediante ordenación exclusiva de claves superiores y assertSame; valores/tipos y orden de delivery_ids siguen estrictos, sin modificar producto ni criterios. No se convierte el rojo en verde: repetición de los cinco casos afectada pendiente. Alcance incremental contra T09 publicado:27/27, cero fuera/avisos/exclusiones/criterios alterados; el chequeo inicial sin base explícita comparó toda la rama histórica contra master y no califica este incremento. Ledger válido, cinco Changelog pendientes T10–T14.

- **Repetición de recibos T-10 (2026-10-26 UTC)**: cinco casos afectados5/5,100 aserciones, cero fallos/errores/skips, recibo `cfa4d4923b1cb5268f50b30e9ffbd1831d16271a4e11fceb6918ee1f4501098e`, JUnit `c5b342b99237f9dfba9169eea0b96be8b7da66a0693d2512914590519a269550`,411 fuentes estables verificadas en ZIP y streams. La tanda original20/460 con5 fallos se conserva: no se sustituye ni se suman tandas como una suite única. Concurrencia real8 casos preparada para ejecución; migración histórica, HTTP crudo, límites y regresión/revisión/QA siguen pendientes.

- **Concurrencia inicial T-10 y diagnóstico (2026-10-26 UTC)**:8 casos reales,7 pasan/1 falla, recibo `1661431f44ad2bd8816ce514bb9b5820d03145e1ac72aa7d58f2457589c608a7`,411 fuentes estables. Falla la independencia entre cuentas: el hijo inicial termina503 y la segunda cuenta completa tras89,76s. Se retira la hipótesis privada inicial que atribuía todo al waiter; no explica esos resultados y no se aplicó ese supuesto arreglo. Se aplica debug-root-cause: reproducción mínima del caso de dos cuentas y marcadores temporales antes/después de lookup/insert en api_mail_requests, sin bindings, bearer, payload ni configuración. Hipótesis de bloqueo de intervalo InnoDB pendiente de prueba; sin fix de producto todavía. Los contadores de aserciones de los bucles de espera no se presentan como cobertura adicional del dominio. Borradores privados de migración/discovery y cinco casos HTTP/SQL siguen sin instalarse ni ejecutarse.

- **Aislamiento SQL y experimento T-10 (2026-10-26 UTC)**: reproducción mínima1 caso rojo, recibo `b904204b92b1d99e2948c11f62654360c83a6492ee25fd421fe4271e4aeb8a21`, fuentes411 estables. MySQL8.4.11/REPEATABLE-READ: lookup de la segunda cuenta termina22,1ms; su primer insert no termina, se reintenta y el segundo tarda37.881,97ms; la primera operación devuelve503 al vencer su barrera90s. El mutex de la segunda cuenta ya se había adquirido y el lookup completado; la espera está en INSERT, no en adquisición de ese mutex. Se ensaya una única variable: lectura consistente del recibo inmutable bajo el mutex de cuenta ya adquirido, retirando FOR UPDATE del lookup ausente. Se conservan índice único, locks de autoridad, transacción, revocación y todos los asserts de concurrencia. Hipótesis de intervalo InnoDB pendiente del resultado A/B; instrumentación temporal se retirará tras admitir el experimento. Fase4/debug y T-10 siguen abiertas.


- **Hipótesis confirmada y follow-ups T-10 (2026-10-26 UTC)**: experimento de una variable1/1, exit0, recibo `0ab10088b5ffdf9b338ad6fa4d54631ef67937ba75d8d7dc2fdfe8468dc2ac84`, JUnit `9fddf61996461f588de6e47af36fc52f2e8b0738569076ddd66c0dc614db9736`. Con el lookup consistente del recibo inmutable bajo el mutex ya adquirido, primera cuenta202 y segunda200, ambas nuevas/tx0/exit0, segunda completa antes de la primera y su INSERT tarda5.79ms sin reintento; la prueba conserva invisibilidad antes de commit y conteos de una solicitud/trabajo por cuenta. Se retienen recibos rojos y se retira exactamente toda instrumentación temporal del hijo antes de regresión. Sin cambio de aislamiento MySQL, índice único, locks de autoridad ni asserts del waiter. Debug fase4/regresión aún pendiente; no se cierra la causa ni se promueve memoria sin esa evidencia.
- **Ampliación declarada T-10**: MigrationReplayTest verifica la nueva ventana de tres migraciones y restaura el conflicto DDL deliberado tras conservar su fallo; ExercisesSendingApi usa el replay de dependencias real antes del roundtrip heredado. Se mantienen criterios previos. Descubrimiento exige catálogo37 y connection_info con grant válido incluso zero-scope, sin quitar negativos ni tocar probes SDK. Se instalan cinco casos adicionales de HTTP crudo/lotes100 inline y plantilla/ramas SQL y downgrade sin pérdida. Sintaxis de los siete candidatos completos comprobada; la salida php-l del fragmento sin etiqueta PHP no se admite como evidencia. Verificación funcional de estos cambios pendiente.


- **Regresión focal y pipeline T-10 (2026-10-26 UTC)**:45 casos/3.871 aserciones, cero fallos/errores/skips, recibo `623ee1005c202f0cbe1c76ad203e8015b214fb0cdb6c49acded72f1f72918070`, JUnit `910ef97f32eda74d985c11da7ca48dcd0e6e7212926d4e2653a1ae2e2314cb3c`,411 fuentes estables verificadas. Incluye8 carreras API/MCP,22 casos de envío,3 de conexión,6 de replay histórico, roundtrip API previo y5 discovery/negativos afectados. La instrumentación SQL temporal está retirada; suite completa/revisión/QA siguen pendientes, debug fase4 no cerrado. Se declara McpPipelineTest para verificar outbox/broker real, cuotas/supresión/reintentos/resultado incierto con relay controlado y Mailpit real, sólo namespaces/recursos propios y destinatarios sintéticos reservados. Estos dos escenarios todavía son verificaciones planeadas.

- **Pipeline MCP T-10 verificado (2026-10-26 UTC)**: dos casos/172 aserciones, cero fallos/errores/skips, recibo `8493467f7348e292ad3c6c53f2827508749fb8cd3f4d00e0f6d0fdc40593652e`, JUnit `c0f0d9934b92e94462ba025862ad6aa53ae57f62ec03617608a3c1baceabfeb6`,412 fuentes estables verificadas. Outbox y RabbitMQ reales con colas/exchange propios: publicación diferenciada de SMTP, cuotas, supresión tardía, rechazo seguro y reintento, aceptación SMTP, resultado incierto sin duplicación automática y procedencia MCP preservada. El relay controlado conserva2 DATA recibidos,1 respuesta250 y1 respuesta final perdida. Mailpit real captura exactamente un mensaje sintético propio aun con republicación del evento; la consulta MCP sólo acredita aceptación SMTP, no entrega final. Root admite dos recibos de recursos: colas ready/failed propias borradas sin mensajes pendientes, relay propio exit0/runningfalse, único mensaje Mailpit propio validado/borrado y búsqueda propia final0, sin borrado global ni lectura de mensajes ajenos. No se afirma ausencia de procesos de la máquina. E2E-09, suite completa y puertas de revisión/QA siguen pendientes; tarea en-progreso.


- **Preparación E2E-09**: Se declara el fixture antes de adaptarlo: eliminar exclusivamente requests transaccionales de las cuentas del fixture antes de grants/clientes, respetando las nuevas FK restrictivas. Recorrido previsto: consentimiento mail.send/activity.read, remitente autorizado por UI, metadata de conexión, aceptación y replay API/MCP, conflicto, estado pendiente sin atribuir entrega, actividad web/CSV con grant/cliente, aislamiento y revocación. Identificadores y destinatarios propios por ejecución/repetición. Sin workers en navegador: RabbitMQ/SMTP se acreditan en McpPipelineTest.

- **Recorrido E2E-09 de desarrollo**: Primer arranque sin casos por spawn EPERM; no creó fixtures ni servidor. Con permisos se ejecutó el recorrido y llegó a aceptación/replay/actividad/CSV/rechazo cross-account; agotó60s porque switchAccount se invocó desde el callback autorizado, donde el snapshot demuestra que no existe selector. Se añadió únicamente navegación al dashboard, sin aumentar timeout ni quitar aserciones. La repetición corregida pasa1/1, cero fallos/skips/flaky, recibo `f48f3a99dba42833568f145603fba14a7f98e169d5222178de7ac8377dc807bf`,412 fuentes estables. Teardown exit0,30 tablas propias sin filas, callback cerrado y los3 puertos disponibles mediante bind exclusivo; recibo natural del launcher ausente, no se inventa. Pint/sintaxis de31 PHP exit0, recibo `832bd296e4a4cdaab056df65c6f048c7bf90d796af08f901ce7ce2e3838360f8`. Se adapta después el selector del caso nuevo a la cuenta secundaria read-only: E2E-06 retira B deliberadamente, por lo que el recorrido completo debe usar una cuenta que permanezca accesible; el lector cross-account sigue siendo el administrador real de B. Suite navegador completa y full Unit+Feature pendientes. No es aceptación ni QA independiente.

- **Navegador completo de desarrollo T-10**:9/9 recorridos originales más E2E-09 pasan, cero fallos/skips/flaky, en200,4s. Recibo `be8ac384726ec55d00cdf02f67a4b3870564af90e6749d7fc1d8633aaede02eb`,412 fuentes estables; reporter original y recibos privados conservados. Limpieza:30 tablas propias sin filas, teardown exit0, callback cerrado y3 puertos con bind exclusivo; recibo natural del launcher ausente. E2E-09 acredita aceptación/replay compartido REST/MCP, conflicto, estado pendiente/cero intentos, procedencia en actividad/CSV, grant limitado denegado, lector autorizado de otra cuenta not_found, selección web aislada y revocación sin perder el recibo previo. Los8 recorridos anteriores conservan sus negativos. Se documentan las3 operaciones y despliegue/rollback en mcp-contrato.md, sin afirmar Google/clientes reales ni aceptación T10. Gates estáticos: scope37 archivos/0 fuera/0 criterios alterados/0 avisos; ledger coherente con5 avisos de Changelog de tareas abiertas; contrato37 operaciones sin fallos; patrones públicos2430 archivos sin coincidencias, sin leer configuración privada, sin certificar historia ni cuerpos excluidos; sintaxis JS y diff-check0. La imagen Linux de pruebas existente responde ID `8d8d8586653675d4786b40afd8d74ea7712ef22a8d0ba44f2cf06fbb63fa038d`. Suite completa Unit+Feature sobre las fuentes finales y revisión/QA independiente siguen pendientes; no se cierra T10.

- **Suite completa y diagnóstico de preparación T-10 (2026-10-26 UTC)**: Unit+Feature sin filtro termina471 casos/12.534 aserciones,1 fallo,0 errores/omitidos, exit1; recibo `efc28d1ff0b1e132a0fcc0d4caf0fce518cab06c68cdceb56147c204b074d3c5`, JUnit `3c01188f9f656cd77001f582d14f79020b785a5255257f1180a0dc4dbbcc5dfa`,412 fuentes estables y coincidentes con el árbol actual. Falla `ConcurrentCampaignTest::test_two_mysql_processes_authorize_exactly_one_reviewed_version`: los procesos propios no alcanzan la barrera de preparación en15s, sin stderr en la aserción. La reproducción mínima sobre las mismas412 fuentes pasa1 caso/6 aserciones, recibo `804c2cbd33d0f82e1c919effecddcc0888abc80f4ffe5b7cbd451850d137f9e9`; tarda18,30s frente a78,57s del caso completo, tiempos que incluyen su ciclo de fixture y no identifican por sí solos la causa. Una primera invocación del reproductor seleccionó0 casos por un filtro incorrecto y se conserva como error de selección, sin atribuirlo a la aplicación. El verde aislado no sustituye al rojo completo. No se modifican producto, asserts ni límites; causa pendiente de instrumentación y prueba. T-10, cierre debug fase4, revisión fresca y QA independiente siguen pendientes.

- **Instrumentación privada de preparación T-10**: Copias privadas del caso y su helper conservan los asserts y límites15s/30s, añadiendo únicamente etapas loaded/autoloaded/kernel/guard/ready de sus procesos propios. Un caso aislado pasa1/6 y ambos procesos quedan listos en979,8ms. La clase CampaignTest completa sin modificaciones seguida del caso instrumentado pasa27 casos/308 aserciones, cero fallos/errores/omitidos; sus procesos quedan listos en2.808,6/2.837,1ms. Recibos `39edbe530e4d8e7c5d0b448b466b6341b2de9422d6ff225f81697d231391d913` y `f03ce9e5dde3dbf6e8bea9c1cbc6d2b33b6990f78695b6a78da1f60cecd206c5`;412 fuentes públicas de runtime permanecen idénticas. Son diagnósticos con copias instrumentadas, no regresión final ni QA independiente. El rojo original no se sustituye, la causa sigue sin probarse y no se aplica fix. Se prepara la selección exacta de145 predecesores originales para el siguiente contraste, que debe conservar también las variables de metadata y el pin de imagen del runner completo; esa selección todavía no se ha ejecutado.

- **Regresiones de preparación declaradas T-10**: La selección exacta de145 predecesores y el caso instrumentado termina146 casos/1.148 aserciones, sin fallos/errores/omitidos, recibo `aaeec1e351b2a65e78ccb992374f2cc14fa2d5a6746717a6479af2f2096732c8`; ambos hijos quedan listos aproximadamente9,6s después del inicio del padre. El control de retraso16s reproduce el timeout del padre con un hijo listo, otro todavía preparándose y stderr0/0 en ese instante; recibo `e6f023e940a5b6eb7424aae236e54f888b2631f50027eb75c267b5a5f78975dd`,1 caso/1 aserción/1 fallo esperado. El contraste con padre60s pero espera antigua del hijo15s también falla,1 caso/3 aserciones, recibo `acb41384a178faee7ee17ec53ff0b7b3cbd2afeff359ab6d384bbd93d9067ffc`: el hijo que llega antes agota su espera antes del segundo. El prototipo privado con plazo de preparación compartido60s, acciones30s y guardia de proceso90s pasa1 caso/6 aserciones con el mismo retraso y el cuerpo original de aserciones, recibo `8bcd2a3bf699d528a6f3f1b91b5c593340cba55fb9aefb4917594831be187941`. Son mecanismos de la prueba comprobados bajo retraso controlado; la demora exacta del fallo histórico no quedó instrumentada y no se atribuye a SQL, memoria o reloj. Se declaran ConcurrentCampaignTest y campaign-action antes de instalar regresiones para preparación tardía, desfase entre hijos y límite de acciones/limpieza propia. Los límites60/30/90 pertenecen todavía al prototipo de prueba; el árbol público conserva el helper anterior. T-10, regresión pública RED/GREEN, suite completa final, revisión fresca y QA independiente siguen pendientes.

- **RED público de las regresiones T-10**: `ConcurrentCampaignTest` ejecuta únicamente los tres casos nuevos sobre el helper con presupuesto de preparación por defecto15s, espera del hijo15s y procesos30s. Resultado3 casos/7 aserciones/3 fallos,0 errores/omitidos, recibo `59d44093cc64e06475fbf18e4161cc0c2a40e87b2f69b3be0d22b8757406e3dd`;412 fuentes estables y actuales. Los tres mensajes identifican respectivamente preparación tardía, expiración del hijo que llega primero y ausencia del límite de acciones solicitado. Se conservan los cuerpos de las8 pruebas de la clase antes del fix, incluida la comprobación independiente de revocación con su presupuesto15s. Es evidencia RED de implementación, no QA ni aceptación; la corrección pública y el GREEN quedan pendientes.

- **GREEN público de preparación/acciones T-10**: Se instala en los dos archivos declarados un plazo monotónico de preparación compartido por padre e hijos, por defecto60s, separado de acciones30s y una guardia de proceso por la suma de fases. Se validan marcas individuales del helper y se detienen únicamente los Process retenidos; limpieza restringida al directorio UUID propio, con error primario conservado y fallos secundarios separados. Los cuerpos de las8 pruebas permanecen idénticos al RED, también tras Pint/sintaxis. La clase completa termina8 casos/60 aserciones,0 fallos/errores/omitidos y footer OK; recibo `b6aaee6558c07b35b6852c83f5216b92742dd94b476b81feebae75eb4ef72a0b`, JUnit `7a55543f413dea7095db812ad1874f6eea023cd8086c7497566b6850f3f9e136`. Las dos preparaciones tardías conservan una autorización y un409; el caso de acciones bloqueadas conserva campaña revisada, versión y auditorías0, y comprueba parada/limpieza propia. La prueba independiente de revocación mantiene su cuerpo y presupuesto15s: espera SQL observada antes de commit,403 después, campaña revisada y auditorías0. Se admiten sólo cinco evidencias JSON permitidas y sus intentos de limpieza; request/settlement protegidos permanecen sin abrir. Se conserva executor_exit null y no se afirma ausencia global de procesos. La suite completa final474, revisión fresca y QA independiente siguen pendientes; el GREEN8/60 no sustituye el RED completo histórico ni acepta T-10.

- **Ampliación declarada del protocolo de procesos API (2026-10-26 UTC)**: Se incorporan los cuatro helpers de pruebas indicados en Archivos para corregir dos defectos observados en la regresión completa474: captura íntegra de fuentes que puede superar la preparación60s, y limpieza que emite go/release y permite ejecutar una operación tras el fallo del padre. La suite completa conserva su rojo474 casos/11.834 aserciones/0 fallos/1 error; no se sustituye por el verde aislado. El prototipo privado separado pasa3 casos/34 aserciones: carrera original17, cancelación de preparación sin go ni petición ni clave, y vencimiento de acción sin petición ni clave. Siete inventarios completos coinciden con el original; recibo 0d51303d6bdff419285888b3946098a793410613cf989fab872b7ce1a4378829. El experimento previo de retraso65s no aisló por sí solo el fallo inicial, pues la captura ya había superado60s antes del retraso; no se atribuye una causa del sistema operativo. Fuentes públicas412 intactas durante ambos prototipos. A continuación se instalan tres regresiones y seams acotados de prueba para obtener RED sobre el protocolo antiguo antes de aplicar cancelación, plazos monotónicos separados e inventario completo cancelable. No cambian criterios aprobados, autoridad de producto, transporte ni catálogo. T10 sigue abierta; helper externo de deadlock, regresión completa, revisión y QA independientes pendientes.

- **Parada solicitada por el usuario (2026-10-26 UTC)**: Las tres regresiones públicas detectaron el protocolo anterior:3 casos/3 aserciones,1 error de preparación y2 fallos por claves creadas indebidamente tras cancelación; recibo 4f0ed35ebc5c287b3427c314556b7cd6ed422f1228cb4efc1ffca0a061050a97. Se instaló el helper cancelable con preparación180s, plazo de acción separado y cancelación sin go/release; el hijo comprueba además el plazo antes de la petición. Pint aplicado; tokens del test conservados tras normalizar CRLF/LF y excluir formato/comentarios. La tanda completa API31 (28 originales más3 regresiones) fue interrumpida por petición del usuario: no tiene recibo terminal natural ni acredita verde. Se comprobó únicamente la terminación de sus procesos y descendientes identificados, sin inventario global ni cambios de infraestructura. Fuentes413 intactas desde el inicio de esa tanda. Suite completa477 preparada pero no ejecutada. Al retomar se revisarán fixtures de la base exclusiva de pruebas y se lanzará una ejecución nueva; se conservan los rojos completos anteriores. T10, revisión, QA y publicación siguen pendientes; no se han hecho commits ni push de estos cambios.

- **Retoma y privacidad local (2026-10-27 UTC)**: El usuario reactiva el objetivo. Fuentes413 sin cambios respecto al punto de parada. Primer intento de retoma termina antes de PHPUnit o DB porque el motor Docker estaba apagado; recibo 3f66f0ce0ab1f6246e0b7672bbf905cf05c03e2aba39902aeaae0557bd9b0747, sin casos, no fallo de producto. Se recupera Docker Desktop y se verifican los cuatro servicios saludables y la imagen fijada. Preflight readonly verifica identidad MySQL exclusiva y cuenta una cuenta sintética API de la interrupción; se conserva el ciclo original DatabaseMigrations antes de cada caso. Nueva ejecución completa API31 en curso, sin atribuir verde a la tanda de ayer. Se declara .gitignore para excluir únicamente el índice de skills y el journal locales recién generados por custom-agents; los índices SQLite y usage-state ya estaban excluidos. Las configuraciones de proyecto siguen visibles; no se alteran criterios ni fuentes de runtime.

- **GREEN público del protocolo API (2026-10-27 UTC)**: La clase completa SendingApiTest termina31 casos/792 aserciones, cero fallos/errores/omitidos, exit0; recibo a0049023ef2763f030ec4e7907bdf91290dd066c543769f5c7e7d70730698d5e, admisión 7331777aa3f4c089006384035bad3608290e49ff1fa245e0d4b07f444f9549d0. Las28 pruebas anteriores y las3 regresiones conservan su selección exacta. Veinte capturas completas del reporter original coinciden con el inventario previo íntegro; los helpers propios coinciden con el ZIP de413 fuentes, sin modificaciones durante la ejecución. Se verifican dos deadlocks MySQL reales (SQLSTATE40001/1213), rollback y posterior reintento, los17 asserts de permisos/límite HTTP originales, preparación65s sin consumir el plazo de acción y ambas cancelaciones sin petición ni clave. Se conservan el RED público y las suites completas rojas471/474; no se suman tandas ni se admite la tanda interrumpida. A continuación se inicia la nueva Unit+Feature sin filtros, inventario477 =474 anteriores +3 regresiones API. Su verde aún no está probado; T10, cierre debug fase4, informe final, revisión y QA independiente siguen abiertos. Las puertas nuevas de revisión exigen este full477 real y rechazan su ausencia; todavía no se han despachado revisores ni QA.

- **Pausa y reanudación de T-10 (2026-10-27 UTC)**: La primera ejecución full477 se interrumpió por petición expresa del usuario, con305 casos aprobados y sin fallos observados; no terminó ni acredita un verde completo. Al recibir la orden de continuar se verifican las mismas413 fuentes del GREEN focal31/792 y se prepara una ejecución nueva, conservando la anterior. El driver nuevo añade únicamente el preflight de lectura que verifica la identidad real de la base y usuario exclusivos de testing antes del ciclo original de migraciones de cada test. Se conservan los477 casos, las aserciones y los límites. La nueva suite está en ejecución; revisión, QA independiente, aceptación y publicación de T-10 siguen pendientes. La preparación privada de componentes de T-11 no modifica runtime ni acepta esa tarea.

- **Diagnóstico de regresión en curso (2026-10-27 UTC)**: La suite completa reanudada conserva sus413 fuentes congeladas y ha observado un error en el caso Linux de caída tras confirmación del dispatcher. El plazo de40s vence sin marcador y el cleanup termina el contenedor propio con137; la traza alcanza input_received pero no loaded_sources/before_dispatch, con evento aún pending y streams vacíos. Se declara el alcance de OutboxIntegrationTest/outbox-action para investigar el ciclo de preparación. Hipótesis pendiente de experimento: el inventario completo previo consume el plazo de la acción del broker. Se conserva el resultado original, sin reducir inventario, filtrar la regresión completa ni modificar producto o criterios. T-10 sigue en-progreso9/14; no se inicia revisión/QA ni se cierra la fase4 de depuración antes del verde final.


- **Hipótesis de plazos probada, fix aún sin instalar (2026-10-27 UTC)**: Ensayo aislado del mismo hijo Linux y reportero completo, sin bootstrap Laravel, DB, broker ni red, con demora de preparación controlada45s. El consumidor original vence a40,11s, sin marcador y con el hijo vivo; el mismo hijo completa inventario9848 archivos/9566 vendor a152,61s dentro del presupuesto de preparación180s. Recibo `ac5758476d0d746d4fc7a11264ba8907e8cb2a98c30afbb3c6057055b3a2b51f`, admisión `eb6d0d2550ac2eab8f0313177489ab53db8d627acf2167c6c9d17f9439685775`,413 fuentes sin cambios. No explica la latencia física variable del host. Fase3 de debug concluida: separar arranque, captura y confirmación40s, con prepared/go ligados al fixture, cleanup sólo abort y rechazo de acción caducada. El candidato y su regresión RED permanecen privados hasta terminar/admitir la suite original. T-10 y fase4/debug siguen pendientes.


- **Checkpoint de jornada y pausa solicitada (2026-10-27 UTC)**: Se conserva la regresión Unit+Feature sin filtro: 477 casos/13977 aserciones, 473 aprobados/cuatro errores, cero fallos u omitidos; recibo `6944c85e28579aedc01e5548fc6620e4e6d10a8f37f2dde629e207fc343552c5`. El oráculo público Outbox reproduce el defecto con un caso/una aserción/un error antes de instalar el fix. La corrección final pasa 4/4 y 96 aserciones sobre 413 fuentes, recibo `56427277402d78fada4c5e6f22aeb72b53b76ba24aa95d89847548849eb3ed91`, con tres inventarios Linux completos, dos caídas 137/reconciliaciones reales y dos negativos terminales sin envío. Sólo cambian los dos archivos del fixture frente al full477; reportero, producto y contratos iguales. Normalización LF con igualdad de tokens PHP; Pint 40/40. [Informe y pendientes](testing/fase-4/t10/checkpoint-2026-10-27.md), [evidencia](testing/fase-4/t10/checkpoint-2026-10-27.json). Los tres errores de WorkerRecovery conservan diagnóstico pendiente; full480, revisión fresca y QA independiente quedan para la siguiente sesión. T-10 permanece en-progreso y MCP 9/14; no se cierra fase4/debug ni se promueve memoria. Marcador de consumo cerrado: tokens/coste/horas IA reales desconocidos. La caché del plugin cambió de 2.0.0 a 2.0.1; los controles de checkpoint y selección usan el paquete 2.0.1 completo. El usuario solicita commit/push normal y parar después; sin iniciar T-11 o full480 ni reescribir historial.


## Fase 5 — Clientes y operación

**Estado**: borrador · **Estimado**: 16h base · **Real**: — · **Coste est.**: 800 € equivalentes + tokens por verificar · **Tokens est.**: 120000

- **WorkerRecovery 2026-10-28**: objetivo reanudado por el usuario. Diagnostico de los tres errores originales con fuentes coincidentes: proxy SQL cerrado antes de loaded; inventario draft completado despues del timeout del padre; SMTP timeout no llego a medir SMTP por bootstrap agotado. Correccion del harness en curso: preparacion completa cancelable, identidad de fixture/helper/delivery, permiso go antes de consumir y presupuesto propio del proxy con motivo de cierre. Se conservan los tres casos y sus aserciones de dominio; se agregan regresiones de preparacion lenta, cancelacion y permiso caducado. Cuatro pruebas de sockets del proxy pasan; primera ejecucion fallo por ACL de TemporaryDirectory en el sandbox Windows y se conserva como diagnostico, no como rojo funcional. Todavia sin evidencia Docker de los cambios, suite completa ni revision/QA; T-10 sigue en-progreso. Capacidades de testing/delivery revalidadas desde custom-agents 2.0.1, sin avisos, implementacion en contexto principal y sin activacion de TDD/worktrees/subagentes.

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
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, E2E-04, E2E-05, E2E-06, E2E-07, E2E-08, E2E-09
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


## T-04-fix1 — revocación vigente y coste acotado de retirada

- **Estado**: implementación corregida; revisión 2 y QA independiente pendientes. Los seis veredictos de revisión 1 conservan su historia; este bloque trata B-01 y D-02.
- **Retoma**: un filtro automático interrumpió al subagente antes de modificar producto. Su cierre confirmó recursos y handles vacíos y dos pruebas aún incompletas. Root continuó el rol implementer, corrigió referencias de fixture a la API real de llaves de cuenta y conservó los originales.
- **Diagnóstico del arnés**: el primer intento terminó con error antes de la aserción de autoridad; Laravel envolvía el timeout de bloqueo en DeadlockException. El helper reconoce el error de driver en la cadena y devuelve fallo explícito sin imprimir detalles de conexión. No se presenta ese intento como reproducción del defecto.
- **B-01 RED**: 1 failed (10 assertions), 22.08s, exit1. Dos procesos SQL en REPEATABLE READ, conflicto real 1205, instantánea anterior con cero grants y transacciones finales a nivel cero. Se alcanzó el fallo de autoridad reactivada tras retirar y reinvitar. Recibo `4a25db4c9c073841e25eca364bdc98d7168d85c4b2192c8d18f24032ae45f6e7`.
- **D-02 RED**: 1 failed (3 assertions), 39.11s, exit1. 600 grants históricos y 600 tokens históricos, más un grant vivo: **1204 SELECT relevantes**, por encima del presupuesto 8. Recibo `fca7c1fa93cbbbd6655b80127df2bb0b927b697a62534f89662253b780a6815f`. Las aserciones posteriores no se declaran alcanzadas en este rojo.
- **Corrección**: bajo el mutex de cuenta, una lectura primaria nueva sin locks descubre clientes confirmados durante la espera; se une con los cambios propios del llamante. La transacción original relee/bloquea clientes, grants vivos y tokens en orden. La conexión temporal se libera; los tombstones y sus fechas no se reprocesan.
- **GREEN focal**: 3 passed (35 assertions), 55.80s, exit0, fuentes estables. Cubre la carrera, los 600 históricos y los cambios no confirmados de una transacción del llamante, incluida su conexión y rollback. El caso histórico usa **5 SELECT relevantes**; conserva otras cuentas/actores, auditoría única y la llave de cuenta. Recibo `ed19c96a8891234897ae011292c07cb8f667ea40cbd05994c5bebc8e64ea3643`.
- **Verificación proporcional**: `php artisan test --filter='McpAuthorizationTest|McpRevocationConcurrencyTest|TeammateTest|McpMembershipRemovalRegressionTest'` → **20 passed (213 assertions)**, 187.71s, exit0. Mismo manifiesto de 349 fuentes que el GREEN focal: `4c6f39452c7cb0bb97056272a5049defbe7a56366d35c76bdeeaf64a2a880885`; no se suman pruebas solapadas. Recibo `2fa2dcd00337aa915ad6d3c9b8e860e864cd16387b180c7a22e58a3dbcf2caf0`.
- **Formato**: Pint sobre las tres rutas PHP afectadas exit0, sin cambios posteriores. Archivos nuevos de regresión declarados en T-04; criterios aprobados intactos. No se ha aplicado QA independiente ni cambiado el contrato.
- **Conocimiento**: propuesta pendiente de juicio; no se promueve memoria ni se reescribe ADR legado. Se conserva la asociación de la propuesta original a continuación.

```curation-refs-v1
[
  {
    "candidate": "docs/knowledge/candidates/pending/2026-10-25-instantanea-de-grants-en-retirada-de-miembros.md",
    "sha256": "409741e1a82b772d336ac26ed23c3b804445354ca88ea5e9a47551ad1d3ee064"
  },
  {
    "candidate": "docs/knowledge/candidates/pending/2026-10-26-restaurar-migraciones-ante-fallos-de-evidencia.md",
    "sha256": "588f34d61759b10c465cd6c465ad0726e75c46e3a35345d158756f023d4d6264"
  },
  {
    "candidate": "docs/knowledge/candidates/pending/2026-10-26-aislar-datos-por-repeticion-e2e.md",
    "sha256": "09cb914eb0f1dd1bc916d899e35b16aee5a5e5f72a0df67bdfe0a09f6cb55b29"
  }
]
```

## T-05-fix1 — registro OAuth y capacidad de limpieza

- Hallazgos de revisión atendidos: B02 (parámetros reservados vacíos en callback), B03 (límite Unicode de nombre) y D01 (capacidad de retención programada). Los dos nuevos ficheros de regresión se declaran en Archivos de T-05; criterios y contratos aceptados permanecen intactos.
- RED causal: `t05-fix1-registration-and-prune-red-20261025T133908528539Z`, recibo `90b91889b4aae0be429715c0613f58b40a2424e06c30e86e65ab0c793ec7dff7`: 3 failed / 108 aserciones / 63.07 s, fuentes estables. El registro HTTP aceptó `?code` con 201 en vez de 400; rechazó un nombre válido de 100 caracteres multibyte con 400 en vez de 201; veinte pasadas con el lote programado de 100 dejaron 17.200 de 19.200 ventanas expiradas. El primer fallo se alcanza antes del resto de la matriz y no acredita que se recorriera completa en rojo. Un intento anterior de invocación falló en el parseo de PowerShell, sin crear cápsula ni lanzar el runner; no se cuenta como prueba.
- B02: RedirectPolicy conserva valores vacíos al inspeccionar las claves de query mediante el parser estricto común. Se rechazan `code`, `state`, `iss`, `error` y `error_description`, incluso sin valor o con nombre porcentualmente codificado. Formularios OAuth siguen tratando los opcionales vacíos como omitidos; duplicados tras decodificar, escapes mal formados y UTF-8 inválido siguen rechazándose. HTTP y CLI usan la misma validación. El flujo real allow/deny conserva una query no reservada con valor vacío sin duplicar parámetros de respuesta.
- B03: sólo client_name cuenta caracteres UTF-8 válidos, entre 1 y 150, y conserva exactamente el texto. HTTP y el comando real de preregistro aceptan los casos de 100 caracteres acentuados, 150 acentuados, 150 emoji y 150 ASCII; rechazan 151, tipos incorrectos, UTF-8 inválido y controles. No cambia el límite de bytes del cuerpo JSON ni la validación de otros campos.
- D01: el scheduler de la aplicación pasa a `mcp:prune --limit=1000` cada minuto y mantiene withoutOverlapping. El comando manual conserva el lote por defecto de 100 y su máximo de 1.000; no se acortan los plazos 24h/48h/30d/90d ni se eliminan referencias protegidas. La prueba SQL introduce 19.200 ventanas expiradas (diez conexiones, una llamada/minuto durante ocho horas y cuatro dimensiones) y 500 retenidas. Veinte pasadas finitas eliminan exactamente 19.200, con máximo 1.000 por pasada y última de 200, conservando las 500. Se comprueba la frecuencia del evento y su presupuesto de capacidad. Son ejecuciones manuales de prueba y cálculo del calendario: no prueban un daemon operativo, Cronicle, rendimiento sostenido ni un SLA. La activación y recuperación del scheduler pertenecen a T-11/despliegue.
- GREEN focal: `t05-fix1-registration-and-prune-green-20261025T134114145756Z`, recibo `8baff49d94189ad73cbc9ee8855f0aa5c494d0b5d3916e3092664eddc1c61c4e`: 5 passed / 312 aserciones / 96.35 s. Regresión completa: `t05-fix1-oauth-regression-20261025T134316100077Z`, recibo `7b88b1b9b7836bf4449536907f450fe385c15e8789b857b3f3ba028e60278374`: 17 passed / 588 aserciones / 414.99 s. Ambas comparten el manifest de 351 fuentes `80e5a144c699ee991b57304345e034f6400507e09294f3fcf9fda4b147668ca3`, sin cambios durante la ejecución; las cinco focales están incluidas y no se suman como pruebas adicionales.
- Los tres contratos mantienen sus pins. SMTP externo, broker y scheduler operativo no se han ejecutado. T-05 continúa en-progreso: estas correcciones técnicas necesitan la revisión fresca del intento 2 y QA independiente; el contador sigue 3/14. El medidor no proporciona tokens, coste ni horas IA reales sin transcripciones.

## T-06-fix1 — cobertura negativa de metadatos y cabeceras

- Se atiende A01 mediante `tests/Feature/McpProtocolMetadataRegressionTest.php`, declarado en Archivos de T-06. La ayuda de construcción conserva la forma exacta de params y no inyecta metadatos en las negativas. Se usan HTTP real, guard Bearer válido, SQL y la herramienta productiva lists_create, con controles que escriben antes y después de los rechazos.
- Matriz moderna: 18 formas ausentes, parciales o inválidas de _meta, protocolVersion y clientCapabilities; seis cabeceras espejo ausentes/divergentes (versión, método y nombre). Se comprueba 400 y el código protocolario definido, ausencia de result, no-store, ausencia de reflexión de la marca sintética y contador de listas sin mutación. Los controles modernos ejecutan la herramienta y no crean sesiones.
- Perfil legado: initialize + notifications/initialized crean una sesión SQL válida; las escrituras autorizadas funcionan antes y después. Cinco formas de metadatos modernos, incompletas o incompatibles, se rechazan incluso con esa sesión válida y cabecera 2025, sin degradarse al legado ni escribir. En total son 29 peticiones negativas, además de controles válidos.
- Ejecución `t06-fix1-protocol-metadata-regression-20261025T135431100391Z`, recibo `51ee47581eb0242db49d1823db29b95e273b0a1707777b6b7f0b4663309f73b8`: 4 passed / 241 aserciones / 76.51 s, 352 fuentes estables, manifest `276f853d903367ff2150e2f84807dba153743cdfa71bfb1f36940a8da1ade10e`. Incluye las dos nuevas pruebas y dos regresiones existentes de SDK/perfiles/revocación y cabeceras/Origin/errores. No se presenta como ejecución de toda la suite de transporte.
- A01 era una carencia de pruebas: el transporte existente supera la nueva matriz en su primera ejecución. No se inventa un RED ni se modifica producto para satisfacerla. Quedan pendientes revisión fresca del intento 2 y QA independiente; T-06 continúa en-progreso y el contador MCP permanece 3/14. No se inició servidor web, navegador, broker ni SMTP externo.

## Revisión de dos lentes — intento 2: Fase 3 (T-04, T-05, T-06, T-07) — sin gaps pendientes

- Cápsula inmutable `ac48477292f63d6055b30b9d767a2d5a662970629e1d79986871a30e787c70a1`: 13 correcciones desde revisión 1, cuatro lentes frescas A/B/C/D según el selector, 1.347 hashes verificados. A/B/C/D: cero Critical, Important y Minor. No se atribuye ejecución Opus al respaldo genérico de modelo heredado.
- Se cierran técnicamente A01/B01/B02/B03/D01/D02 por el veredicto de su lente responsable; sin rebates. Los 17 criterios conservan conformidad estática y cobertura, con el traspaso completo de todos los veredictos anteriores. [Informe público y tabla completa](testing/fase-3/review-2/report.md).
- Regresiones aportadas y calificadas: autoridad/equipos20/213, OAuth17/588 y protocolo4/241; fuentes explícitas distintas, conjuntos solapados y ninguna suma como suite global actual. Preflight final y Pint de fix1 pasan. Los revisores no ejecutaron runtime.
- QA independiente todavía pendiente. Los cuatro estados continúan en-progreso y el contador MCP3/14 no se eleva; los manuales, Google/clientes reales y despliegue siguen sujetos a sus pruebas propias. El siguiente actor recibe código/runtime liberados y debe producir JSON original de Playwright y qa-gate, no convertir salida list a un verde sintético.

## Aceptación técnica de fase 3 — 2026-10-25

T-04–T-07 se aceptan técnicamente tras revisión 2 A+B+C+D sin defectos pendientes y QA2 VERDE. El [cierre de fase](testing/fase-3/acceptance.md) relaciona las 77 pruebas/1808 aserciones de QA1 y los seis E2E de QA2 con las mismas 352 fuentes finales revisadas, sin repetir ni sumar resultados. El contador pasa de 3/14 a 7/14; la iniciativa sigue en-progreso. Las notas anteriores de implementación y revisión mantienen su fecha y estado históricos. M-01–M-04, catálogo completo de 37 operaciones, Google/clientes reales, perfiles y aceptación global siguen en sus tareas pendientes. No se atribuye aceptación humana ni ejecución del scheduler operativo.


## T-09 fix1 — suite completa verde; entrada QA2 pendiente

- Sesión nativa original terminada, sin reinicio. Unit+Feature sin filtro:412 pruebas/7967 aserciones en41 clases,0 fallos, errores u omitidos; exit0. Recibo, streams, ZIP/manifiesto, helpers y JUnit originales admitidos;399 fuentes estables y coincidentes con el árbol actual. El subconjunto T-09 de151 casos/3175 aserciones en11 clases forma parte del total y no se suma.
- [Evidencia fix1 actualizada](testing/fase-4/t09/fix1/report.md): se conservan los RED, focos GREEN y QA1 NO-VERDE. La [revisión2 A+B+D](testing/fase-4/t09/review2/report.md) ya está cerrada sin gaps; la actualización posterior sólo añade resultados y asociación de memoria, sin modificar criterios o fuentes de runtime.
- QA2 sigue sin activar: debe verificar independientemente la suite completa y su inventario contra las fuentes fijadas, repetir los seis casos de MigrationReplayTest, el histórico original y el focal discovery/legacy/revocación; además conserva los ocho E2E originales, tres repeticiones de estabilidad y todas las puertas de SQL, recursos, privacidad y PDF. No se presenta el backend del implementador como una ejecución del agente QA. Cualquier cambio, fallo o divergencia reabre la verificación que corresponda.
- Debug Phase4: regresión focal e histórica y suite completa verificadas. El servicio knowledge-curation propone una entrada sobre la frontera entre evidencia y DDL de restauración; la asociación exacta está en curation-refs-v1. Es una propuesta pendiente de Curator, sin promoción manual ni cambios en el legado GOT-003/GOT-011.
- Estado técnico: T-09 continúa en-progreso; MCP8/14, aplicación18/20 e infra3/3. Google real, clientes reales, manuales M01-M04, T-10–T-14 y T-19/T-20 permanecen pendientes. No se publica ni se cierra la tarea antes del QA independiente.


## T-09 fix2 — aislamiento de repeticiones probado; revisión3 y QA3 pendientes

- Root admite QA2 NO-VERDE con166 hashes, siete runs nativos/ZIP y78 copias originales; recibe liberación explícita, cierra su medidor e inspecciona siete páginas PDF y24 capturas. No acepta T-09 ni atribuye a QA3 sus resultados.
- Hipótesis probada con HTTP/MySQL reales en testing: dos casos/25 aserciones. Tres llamadas con el mismo email producen una aceptación y dos validation_failed; al separar por índice de repetición pasan las tres. El código cerrado histórico no estaba en el reporter; el observado pertenece al experimento controlado. La regla de unicidad del dominio permanece intacta.
- Cambio mínimo en E2E-08: UUID común más repeatEachIndex para cuatro etiquetas propias. Conserva todas las aserciones, guardas, timeouts y retry0; sin cambios PHP ni de esquema. [Evidencia fix2](testing/fase-4/t09/fix2/report.md).
- Regresión final de desarrollo: ocho E2E completos8/8 y estabilidad separada3/3; reporters y gates nativos verdes, fuentes399 iguales y estables, SQL propia28 categorías cero/tx0 y binds exclusivos tras ambas tandas. Root inspecciona cuatro PNG nuevos, con campañas distintas0/1/2. Sólo cambia el archivo E2E respecto a las399 fuentes del backend412/7967; las398 restantes permanecen idénticas. No se afirma una nueva ejecución del backend completo ni se suman tandas solapadas.
- Debug Phase4 completada en desarrollo. El servicio knowledge-curation propone la entrada de aislamiento por repetición y su ruta/SHA se asocian en curation-refs-v1. Curator pendiente; no se promueve ni modifica memoria aceptada. La recuperación previa fue parcial y no se presenta como búsqueda exhaustiva. Tres propuestas en esta iniciativa amplia se mantienen explícitas para revisión/retro final.
- Revisión3 de fuentes finales y QA3 independiente pendientes. QA1 y QA2 conservan NO-VERDE; no se cambian criterios o checklists. MCP8/14, principal18/20 e infra3/3. Google/clientes reales, M01–M04, T10–14 y principalT19/T20 mantienen sus puertas.

- **Entrada de revisión fix2: representación de evidencia (2026-10-26 UTC)**: La primera puerta estática pasó privacidad, contrato, scope, ledger, trazabilidad y sintaxis; git diff --check detectó retornos CR duplicados introducidos al reescribir el ledger desde bytes en Windows. Se normalizan sólo sus finales a LF, conservando contenido y checklists. Los originales de QA y sus hashes no se reescriben. Se declara .gitattributes en T-09 para conservar bytes exactos del árbol nuevo de evidencia T09 al indexar/clonar y extender a raw anidado la política existente de whitespace de herramientas; los CRLF simples de evidencia se tratan como finales de línea. Código y criterios siguen intactos. La tanda estática fallida se conserva y se repetirá sobre esta metadata corregida antes de lanzar revisores.


## Revisión de dos lentes — intento 3: Fase 4 (T-09) — fix2 sin gaps pendientes

Lentes A+B+D, C no seleccionada; selector D por `tests/Feature/MigrationReplayTest.php`, patrón Migration, cero avisos. Tres subagentes genéricos frescos con modelo heredado, sin override. Cada uno contabilizó298/298 rutas y verificó2.249/2.249 miembros; root admitió la misma cápsula y su correspondencia con el árbol. A:23 criterios,19 conformes y4 no aplicables. Revisión2 conservada; delta217 nuevos/5 modificados/0 retirados. Sin descartados, deuda aceptada ni gaps pendientes.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
| --- | --- | --- | --- | --- | --- |
| A | — | Sin gaps de requisitos | T-09 | No necesaria | [Lente A](testing/fase-4/t09/review3/verdict-a.md) |
| B | — | Sin defectos de corrección | T-09 | No necesaria | [Lente B](testing/fase-4/t09/review3/verdict-b.md) |
| D | — | Sin regresión de rendimiento sustentada | T-09 | No necesaria | [Lente D](testing/fase-4/t09/review3/verdict-d.md) |

[Dictamen y procedencia](testing/fase-4/t09/review3/report.md). Los revisores fueron sólo lectura y devolvieron mensajes; root copió B/D JSON y transcribió los23 criterios A, identificando sus copias sin inventar archivos nativos del agente. Marcadores fix2 y revisión3 cerrados sin solape; consumo/coste/tiempo IA desconocidos. Backend completo412/7967 y QA2 propio8/85 conservan su atribución anterior; el único cambio de esas399 entradas es el namespace E2E. Desarrollo final8/8 y3/3 pasa, pero QA1/QA2 quedan NO-VERDE y QA3 independiente sigue pendiente. T-09 continúa en-progreso, MCP8/14; sin aceptación ni publicación Git anticipada. Curator y manuales permanecen pendientes; sin efectos Jira/Confluence.

## Revisión de dos lentes — intento 2: Fase 4 (T-10) — B-01 corregido, sin gaps pendientes

A+B+D, tres revisores frescos de solo lectura. Se conservan los veredictos de los 55 archivos de R1 y se revisan las tres correcciones completas (102 lineas, SHA256 del patch `ce966ead0a4c5eb991b16b45cd8dc9648e6518acdb134623fb23007c04ab7d09`). Alcance exit0 sin avisos, exclusiones ni criterios alterados; selector Cfalse/Dtrue. Sin gaps nuevos: 0 Critical, 0 Important, 0 Minor.

| # | Grado original | Gap | Tarea | Correccion | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | Resultado conocido rechazado tras borrar al actor durante transporte. | T-10 | Corregido: se valida el actor/grant al crear o cambiar procedencia; Eloquent solo actualiza los atributos dirty del resultado y conserva el tombstone SQL. Se sigue rechazando cambiar el actor por otro usuario. | RED real2/15assert, un LogicException exacto y control de actor correcto; GREEN mismos2/26assert, cero problemas. Unico delta RED/GREEN: app/Models/MailMessage.php. |

RED recibo `bf58f0209ba3e1d179479abfb0ed877e3036cfb4210b88b70f215c5d55a25326`, admision `7626c05aec061ba3e24f440f871f61353e6c180f8151aadb8222a73b2cae8d09`; GREEN recibo `6ff0bb60e38ee0f1d44b04f97716fb9651784cf495d73a3929de436e9da95668`, admision `0af0137d51571eb19fc1ead4fd5bef6520b7fef819e5263fffba08cf6d826c30`. Las regresiones usan MySQL y transporte log, con borrado exactamente en transport_result antes de finish; no acreditan una nueva ejecucion SMTP. 415 fuentes estables.

Se revisa tambien que sql-redelivery adopte la misma preparacion controlada; su repeticion focal real sigue en curso. Los otros dos casos originales de WorkerRecovery y la preparacion lenta pasan en el focal anterior4/187 con un unico error150s conservado del SQL; no se convierte esa tanda en verde. La prueba lenta observa al menos100s antes de go y conserva cero intentos antes del permiso.

Informes privados en `storage/worker-recovery-review-20261027/review-t10-attempt2/`: lens-a.md `7fb8f613fb28b0026a07b2138c98ea2b21bbd32cff9b7ccd9ee0ba6d4788c03c`, lens-b.md `7084c7a3ad754b953ba540ed39fae122fb2901197dcb282a10502cdc0f524ea7`, lens-d.md `49f0a663d02a500d9c4ee31c457e481384338286ea2fb5842cd967945f662c5c`. Medidor cerrado sin lectura de transcripciones; tokens/coste/horas IA desconocidos. Sin Jira ni publicaciones externas.

**Puerta siguiente**: revision cerrada sin gaps; aun faltan el terminal focal SQL, la suite completa Unit+Feature sin filtro (inventario actualizado a485 casos, conservando los480 anteriores mas3 regresiones de preparacion y2 de auditoria), y QA independiente. T-10 permanece en-progreso y no se marca aceptada.

**Actualizacion posterior a revision2, 2026-10-28**: focal SQL original GREEN1 caso/35 aserciones, cero fallos/errores/skips; recibo `40a98bb48cfa63bade6540575ea294189c22f210f2799c994946bcc2ec204279`, admision `bd6b823b112ba40e669e7cf7e1785e9cf8dc2f7128bbd5459e715e5b26c18cb4`. Sus415 fuentes coinciden exactamente con el GREEN de B-01. El padre espera la preparacion de sql-redelivery antes de abrir consumo; se preservan los asserts de corte despues del250, sending, recuperacion ambiguous y un unico correo.

Se inicia ahora Unit+Feature completo sin filtros sobre esas mismas415 fuentes, con guard de identidad real emailing_testing y Mailpit/log solamente. Inventario exigido485 IDs unicos, SHA256 `3e062635d6e2608348d4f692c1b0067a06f75e0dee1cc2515d0c843f9e9fee5c`. Ejecucion en curso: aun sin terminal ni veredicto global, y QA independiente no iniciada. Los485 casos son una exigencia de la tanda, no485 casos aprobados. T-10 sigue en-progreso.


**Trazabilidad de QA, 2026-10-28**: coverage-check detecta E2E-09 sin referencia porque Cubre(tests) de T-10 estaba vacio. Se vincula E2E-09 y se reconcilia T-12 con E2E-01 a09, tal como ya los declara la tabla de cobertura del test-plan aprobado. Son referencias de escenarios existentes; no se cambian criterios ni casos y no se acredita su ejecucion por este ajuste.

**Checkpoint solicitado para parar, 2026-10-28**: se separan los commits de correccion de auditoria y preparacion/cancelacion de workers. [Resumen de correcciones y pruebas](testing/fase-4/t10/recovery-fixes.md). La regresion485 iniciada sigue en curso y mantiene sus415 fuentes; el preflight propio del QA independiente pasa, pero sus escenarios funcionales aun no se ejecutan. T-10 permanece en-progreso, sin aceptar ni elevar9/14. Se finalizara la tanda en curso y se publicara el checkpoint antes de pausar el objetivo por peticion del usuario; no se inicia T-11.

**Interrupcion y pausa solicitadas, 2026-10-28**: el usuario pide parar los tests y seguir otro dia. Se detiene la tanda existente, sin reiniciarla. Quedan320 eventos Passed de321 preparaciones, sobre485 casos previstos, sin fallos/errores/skips observados hasta el corte; no es una suite completa aprobada. El terminal de la sesion confirma parada y la consulta restringida a sus comandos Python/PHP no encuentra procesos coincidentes. Las415 fuentes no cambiaron. No existen recibo final del capturador ni resumen completo y no se acredita el teardown de SQL. [Interrupcion sanitizada](testing/fase-4/t10/interruption-2026-10-28.json). Los fixes estan publicados en8aafc6a y291aede. Al retomar, comprobar el guard de emailing_testing y el estado de fixtures en ventana exclusiva, repetir la suite completa sin filtros y ejecutar despues el QA independiente. T-10 continua en-progreso,9/14; no se inicia T-11 ni se declara aceptacion. Se publica este estado y se pausa el objetivo por peticion expresa del usuario.

**Reanudacion expresa, 2026-10-29**: el usuario reactiva el objetivo. Se verifica la interrupcion anterior y la ausencia de sus comandos de prueba; no se intenta admitir el parcial320 como verde. Se arrancan los contenedores existentes MySQL/RabbitMQ/Mailpit, conservando volumenes, y quedan saludables. El preflight de solo lectura acredita base y usuario reales emailing_testing, DB_URL vacio, cero otras conexiones de prueba y transaccion0, sin mutar filas/esquema; recibo `8858744875457714cc0730242184a85e41e3d0aab56ddde0b0f787e83bbc0c4d`, admision `ba4fb49d8204906337d0034ca28c0d1ad6602f9caaf47356f4c6617db9f8407e`. Se inicia una NUEVA tanda Unit+Feature sin filtros, con485 IDs exigidos y415 fuentes identicas a los focos verdes finales; fuentes congeladas y Mailpit local comprobado antes de la suite. Todavia en curso, sin terminal ni veredicto global. Bundle custom-agents2.1.0: misma seleccion de capacidades y sin dev.json. QA independiente fresco completa18 checks estaticos exit0, con listado nativo9 originales/09x3 sin ejecucion funcional, privacidad2437/0 y manifest59 SHA `c4b1144a53bb4db7a774d9547067a3914ae8763229675070a1df65fccb700399`. Sus escenarios de navegador esperan el terminal completo para evitar escritores simultaneos. T-10 sigue en-progreso,9/14; revision previa sin gaps conservada, QA funcional y aceptacion pendientes. Tokens/coste/horas IA desconocidos; sin efectos Jira/Confluence.

**Parada expresa de tests, 2026-10-29**: el usuario pide detener los tests, hacer commits/push y parar. Se interrumpe la nueva tanda y se confirma su terminal; la consulta restringida a sus comandos Python/PHP encuentra cero coincidencias. Quedan205 eventos Passed de206 preparaciones de485 casos previstos, sin fallos/errores/skips observados hasta el corte; las415 fuentes no cambiaron. El runtime nativo fue PHP8.5.12, mientras los focos verdes y la tanda interrumpida del28 usaron8.4.18: no se mezclan ni suman resultados. No existe resultado completo admitido ni se acredita el teardown SQL. [Registro sanitizado del29](testing/fase-4/t10/interruption-2026-10-29.json). El apunte de reanudacion esta publicado en668de48; se publica tambien este cierre y se pausa el objetivo por peticion expresa. Al retomar: guard real de emailing_testing, ventana exclusiva y estado de fixtures, nueva suite completa sin filtros y despues QA independiente. T-10 sigue en-progreso,9/14; sin nueva implementacion, QA funcional ni aceptacion antes de parar.

**Reanudacion del objetivo activo, 2026-10-30**: se comprueba la rama limpia y sincronizada, sin procesos coincidentes de la tanda anterior. Se arrancan los tres contenedores existentes de laboratorio, conservando volumenes; MySQL, RabbitMQ y Mailpit quedan saludables. El nuevo preflight de solo lectura verifica identidad real de base y usuario de pruebas, DB_URL vacio, cero otras conexiones y transaccion0, sin mutar filas ni esquema. Recibo SHA256 `60b6ca79e4f25e2a1c3f3c2777fa67f15e4a93bb2be6321a35e46966f273d389`; admision `b421a021dd0e2bd8554109fe67c8b93ea83243c371a749def6081075c12a85a6`, con415 fuentes comprobadas. Se inicia una nueva suite Unit+Feature sin filtros y con485 IDs exigidos, fuentes congeladas y Mailpit local verificado por el driver. Sigue en curso: no hay recibo terminal ni resultado global admitido. No se suman los parciales anteriores ni se acepta T-10; QA funcional independiente pendiente tras el terminal. Este apunte solo actualiza la trazabilidad, sin cambiar producto, pruebas o criterios.

## Historial archivado

- 2026-10-30 · «Revisión de dos lentes — intento 1: Fase 2 (T-02, T-03) — corrección necesaria» → [execution-history.md](execution-history.md) · SHA-256 `34d6e7dd33e754adc052af9cd7a18ac1b2f5305b5b021a025e70c4be5db6b0bf`
- 2026-10-30 · «Revisión de dos lentes — intento 1: Fase 3 (T-04, T-05, T-06, T-07) — correcciones requeridas» → [execution-history.md](execution-history.md) · SHA-256 `ddcb2847b030db0474676fb09dde5eef6ecda5d8475a0cd48ee1d060158c2fdb`
- 2026-10-30 · «Revisión de dos lentes — intento 2: Fase 4 (T-09) — fix1 sin gaps pendientes» → [execution-history.md](execution-history.md) · SHA-256 `12a65d553d847c54060cb9cbcd803a65b2ed2f095bea041690a2501563bcad26`
- 2026-10-30 · «Revisión de dos lentes — intento 1: Fase 4 (T-10) — corrección de auditoría pendiente» → [execution-history.md](execution-history.md) · SHA-256 `e2b41dbd406b952b04e3889884d578623797cc17749bbdebde5711c6592c8347`
