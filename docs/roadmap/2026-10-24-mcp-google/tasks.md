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
| **Estado** | borrador |
| **Fecha** | 2026-10-24 |
| **Plan** | [`improvement-plan.md`](./improvement-plan.md) |
| **Diseño** | n/a; decisiones técnicas propuestas en el plan y T-01 |

> **⚠️ Ledger canónico de progreso.** Este fichero es la **fuente única de verdad** del avance del plan. **Cualquier** implementador —el agente `implementer`, el chat principal, o un orquestador SDD externo— **debe** marcar aquí cada tarea (checkbox + estado) al completarla y actualizar el resumen. Los ledgers propios de otras herramientas son **espejo**, no fuente.

Los IDs locales pertenecen a `2026-10-24-mcp-google`. Los IDs de la iniciativa principal llevan su slug completo. Estado inicial: **0/14**, sin implementación ni aprobación humana nueva.

Las horas y tokens de tareas/fases son **base** y heredan la evaluación; el total con margen es 201,6 h y 1.728.000 tokens. No se suma dos veces el +20 %. Tiempo/coste reales siguen desconocidos; cero tareas ejecutadas no equivale a cero consumo medido.

## Resumen de progreso

| Fase | Completadas | Total | Progreso | H. humanas (real/est) | H. IA ejec. (real/est) | Supervisión (real/est) | Tokens (real/est) |
|---|---|---|---|---|---|---|---|
| Fase 1 — Contrato y decisiones | 0 | 1 | 0% | — / 8h | — / 0.333333h | — / 0.083333h | — / 100000 |
| Fase 2 — Identidad Google | 0 | 2 | 0% | — / 20h | — / 0.466667h | — / 0.116667h | — / 140000 |
| Fase 3 — Autorización delegada y MCP | 0 | 4 | 0% | — / 48h | — / 1.333333h | — / 0.333333h | — / 400000 |
| Fase 4 — Catálogo de negocio | 0 | 3 | 0% | — / 32h | — / 0.8h | — / 0.2h | — / 240000 |
| Fase 5 — Clientes y operación | 0 | 1 | 0% | — / 16h | — / 0.4h | — / 0.1h | — / 120000 |
| Fase 6 — QA, revisión y cierre | 0 | 3 | 0% | — / 44h | — / 1.466667h | — / 0.366667h | — / 440000 |
| **TOTAL** | **0** | **14** | **0%** | **— / 168h** | **— / 4.8h** | **— / 1.2h** | **— / 1440000** |

> **Horas → Jira.** Si se activa el opt-in, el worklog usa IA + supervisión de cada tarea. Esta planificación no crea issues ni publica documentos externos.

## Fase 1 — Contrato y decisiones

**Estado**: borrador · **Estimado**: 8h base · **Real**: — · **Coste est.**: 400 € equivalentes + tokens por verificar · **Tokens est.**: 100000

### T-01 — Fijar autorización, contratos y dependencias

- **Descripción**: Convertir esta propuesta en un contrato técnico verificable antes de instalar paquetes. Conservar los dieciséis criterios y todos los módulos de la spec.
- **Partida**: P de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.333333h (estimado por tokens) · real —
- **Supervisión**: est. 0.083333h (25 % IA) · real —
- **Previsión IA**: 80000 in / 20000 out tok · coste 0.08 p_in + 0.02 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Baja, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: 2026-10-21-plataforma-emailing/T-16 y /T-17 terminadas; sin dependencia inversa de T-18 hacia esta iniciativa
- **Archivos**: `docs/aplicacion/mcp-contrato.md`, `docs/api/mcp-tools.json`, `docs/api/mcp-oauth.openapi.json`, `docs/knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md`, `docs/knowledge/README.md`, `docs/roadmap/2026-10-24-mcp-google/*.md`, `docs/roadmap/README.md`
- **Cubre (tests)**: —
- **Verificación**: `python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/skills/api-contract/scripts/openapi-lint.py" docs/api/mcp-oauth.openapi.json` → exit 0; lectura: contrato MCP con 37 operaciones, matriz de permisos, registro, audiencia, caducidades y dependencias sin ciclos. Resolver primero CUSTOM_AGENTS_PLUGIN_ROOT a la instalación real, como en el ledger principal; no usar un nombre de variable sin configurar ni una ruta supuesta.

**Criterios de aceptación**

- [ ] Documentar decisión de OAuth acotado sobre Sanctum, con alternativas y condiciones de reversión; ADR sigue propuesta hasta su puerta de revisión.
- [ ] Fijar PKCE S256, código de un uso, tokens de propósito MCP, reautorización sin refresh inicial, límites y políticas de redirección/registro descritos en el plan.
- [ ] Alinear envío, consentimiento de destinatarios e idempotencia con el contrato T-17 sin inventar consentimiento ni sustituirlo por mail.send.
- [ ] Verificar compatibilidad Composer de Laravel MCP y Socialite con el lock real; registrar versiones candidatas sin instalarlas durante esta tarea.

**Subtareas**

- [ ] Registrar producto/versión objetivo y capacidades de cliente requeridas con fuentes oficiales; compatibilidad ejecutada continúa pendiente.
- [ ] Definir esquemas de argumentos/resultados, errores, paginación y efectos; versionar el contrato y sus fixtures negativos.
- [ ] Revisar nombres/rutas propuestos contra el T-16/T-18 aceptado antes de editar aplicación.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

## Fase 2 — Identidad Google

**Estado**: borrador · **Estimado**: 20h base · **Real**: — · **Coste est.**: 1000 € equivalentes + tokens por verificar · **Tokens est.**: 140000

### T-02 — Persistir identidades externas y resolver vínculos

- **Descripción**: Añadir identidad estable Google vinculada explícitamente al usuario local. Resolver conflictos sin depender de coincidencias de correo ni de su collation.
- **Partida**: C-01 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 8h · real —
- **Tiempo IA (ejec.)**: est. 0.186667h (estimado por tokens) · real —
- **Supervisión**: est. 0.046667h (25 % IA) · real —
- **Previsión IA**: 48000 in / 8000 out tok · coste 0.048 p_in + 0.008 p_out €; tarifa por verificar
- **Coste estimado**: 400 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-01
- **Tipo**: backend
- **Archivos**: `composer.json`, `composer.lock`, `app/Models/User.php`, `app/Models/ExternalIdentity.php`, `app/Services/Auth/GoogleIdentityResolver.php`, `database/migrations/*_create_external_identities.php`, `tests/Feature/GoogleIdentityTest.php`, `docs/aplicacion/acceso-google.md`
- **Cubre (tests)**: —
- **Verificación**: `php artisan test --filter=GoogleIdentityTest` → vínculos, conflictos, cambios de email y desvínculo sin acceso cruzado

**Criterios de aceptación**

- [ ] Unicidad de proveedor/emisor/sub y relación local preservan la identidad si Google cambia el email.
- [ ] Una coincidencia de email no vincula ni fusiona automáticamente; un sub ya vinculado a otra persona rechaza la operación.
- [ ] Desvincular exige sesión autenticada y método de recuperación viable; no se almacenan tokens Google que no se necesitan.

**Subtareas**

- [ ] Instalar Socialite estable resuelto y registrar el lock después de la validación de T-01.
- [ ] Implementar resolución transaccional, vínculo explícito y desvínculo, con auditoría mínima.
- [ ] Preparar fixtures ficticios, pruebas de unicidad concurrente y rollback.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

### T-03 — Integrar Google en acceso, perfil y admisión

- **Descripción**: Ofrecer Google en login y vinculación del perfil sin alterar la política existente de registro e invitaciones. Retomar el consentimiento MCP tras autenticar.
- **Partida**: C-01 de evaluation.md; herencia sin reestimación.
- **Estado**: borrador
- **Tiempo humano**: est. 12h · real —
- **Tiempo IA (ejec.)**: est. 0.28h (estimado por tokens) · real —
- **Supervisión**: est. 0.07h (25 % IA) · real —
- **Previsión IA**: 72000 in / 12000 out tok · coste 0.072 p_in + 0.012 p_out €; tarifa por verificar
- **Coste estimado**: 600 € equivalentes humanos base + coste de tokens de Previsión IA; margen global +20 %, no consumo real.
- **Confianza**: Media, heredada de la evaluación; horas IA por ratio no calibrado.
- **Dependencias**: T-02
- **Tipo**: frontend
- **Archivos**: `app/Http/Controllers/Auth/GoogleLoginController.php`, `app/Services/Auth/GoogleLoginFlow.php`, `app/Actions/Fortify/CreateNewUser.php`, `app/Providers/FortifyServiceProvider.php`, `config/services.php`, `config/emailing.php`, `.env.example`, `routes/web.php`, `resources/views/auth/login.blade.php`, `resources/views/auth/register.blade.php`, `resources/views/auth/identities.blade.php`, `tests/Feature/GoogleLoginTest.php`, `tests/e2e/mcp-google.spec.mjs`, `docs/aplicacion/acceso-google.md`, `docs/README.md`
- **Cubre (tests)**: E2E-01, E2E-02, E2E-03, M-01
- **Verificación**: `php artisan test --filter=GoogleLoginTest` → callback, state, identidad inválida, admisión, invitación y retorno seguros; `npx playwright test tests/e2e/mcp-google.spec.mjs --grep "Google"` → E2E-01–03 con proveedor simulado

**Criterios de aceptación**

- [ ] State, código, emisor/sub y datos de identidad inválidos o reutilizados no crean sesión, usuario ni vínculo.
- [ ] Registro permitido crea cuenta propia sólo tras la confirmación explícita del flujo; invitación conserva la comprobación del destinatario.
- [ ] Filtro Workspace configurable valida identidad de servidor y no sólo el sufijo visible del email; Google no concede rol.
- [ ] Google sin configurar muestra un estado comprensible y conserva acceso local; mensajes no revelan existencia de cuentas ajenas.

**Subtareas**

- [ ] Implementar redirect/callback con retorno local validado, sin open redirect ni confianza en Host.
- [ ] Añadir login, vínculo/desvínculo del perfil y errores visibles, conservando protecciones Fortify existentes.
- [ ] Documentar scopes mínimos de identidad, callbacks y configuración privada por entorno.

**Notas**: Los comandos de aplicación y nuevos tests son verificaciones **planeadas**, no ejecutadas por el planner. El campo Changelog se rellena al cerrar cada tarea.

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
