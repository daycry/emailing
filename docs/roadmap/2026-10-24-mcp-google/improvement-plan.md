---
design: n/a
test-plan: test-plan.md
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
---

# 2026-10-24-mcp-google

> Acceso Google para la web y autorización delegada MCP para usar la plataforma desde agentes.

| | |
|---|---|
| **Fecha** | 2026-10-24 |
| **Estado** | en-progreso |
| **Tipo** | Nueva funcionalidad |
| **Prioridad** | Alta |
| **Solicitante** | Usuario del proyecto |
| **Responsable** | Equipo del proyecto y workflow custom-agents |
| **Spec** | [`spec.md`](spec.md), aprobada técnicamente por el orquestador dentro del objetivo ya autorizado |
| **Evaluación** | [`evaluation.md`](evaluation.md), completado; implementación en-progreso e interoperabilidad real pendiente |
| **Diseño** | n/a; sin opt-in design.md. Esta propuesta técnica se concreta en T-01 antes de implementar |

## Cuadro de mando

| Métrica | Estimado | Real | Confianza |
|---|---|---|---|
| Tiempo humano equivalente | **201,6 h** (168 h base +20 %) | Desconocido | Media-baja |
| Tiempo IA (ejecución) | **5,76 h** (4,8 base) + **1,44 h** supervisión | Desconocido | Baja; ratio no calibrado |
| Coste humano equivalente con margen | **10.080 € + 1,44 p_in + 0,288 p_out** | Desconocido | Tarifa supuesta; tokens por verificar |
| Tokens IA | **1.728.000** (1.440.000 in / 288.000 out), base 1.440.000 | Desconocido | Baja |
| Multiplicador teórico | **×28** | — | No es plazo ni rendimiento comprometido |
| Tareas | **14** | 7 aceptadas técnicamente; 7 pendientes, con verificaciones humanas/externas conservadas | — |

El presupuesto de evaluación se conserva íntegro. Ninguna partida se descuenta por leer la referencia. Los importes no representan gasto real ni facturación.

## Estimación por fase

Horas, tokens e importes de fases son base. El margen aparece sólo en la fila de total con margen.

| Fase | Tareas | Estimado h base | Tokens in / out base | Coste humano equivalente base |
|---|---|---|---|---|
| 1 — Contrato y decisiones | T-01 | 8 | 80000 / 20000 | 400 € + tokens |
| 2 — Identidad Google | T-02, T-03 | 20 | 120000 / 20000 | 1000 € + tokens |
| 3 — Autorización delegada y MCP | T-04, T-05, T-06, T-07 | 48 | 340000 / 60000 | 2400 € + tokens |
| 4 — Catálogo de negocio | T-08, T-09, T-10 | 32 | 200000 / 40000 | 1600 € + tokens |
| 5 — Clientes y operación | T-11 | 16 | 100000 / 20000 | 800 € + tokens |
| 6 — QA, revisión y cierre | T-12, T-13, T-14 | 44 | 360000 / 80000 | 2200 € + tokens |
| **Total base** | **14** | **168** | **1200000 / 240000** | **8.400 € + tokens** |
| **Total con margen +20 %** | **14** | **201,6** | **1440000 / 288000** | **10.080 € + tokens** |

### Herencia por característica

| Partida | Tareas | h base | Tokens in / out base |
|---|---|---|---|
| C-01 Google e identidades | T-02, T-03 | 20 | 120000 / 20000 |
| C-02 OAuth y MCP | T-05, T-06 | 32 | 240000 / 40000 |
| C-03 Cuenta y permisos | T-04, T-07 | 16 | 100000 / 20000 |
| C-04 Herramientas | T-08, T-09, T-10 | 32 | 200000 / 40000 |
| C-05 Clientes y despliegue | T-11 | 16 | 100000 / 20000 |
| C-06 QA | T-12, T-14 | 24 | 160000 / 40000 |
| P Proceso y decisiones | T-01 | 8 | 80000 / 20000 |
| R Revisión y correcciones | T-13, distribuida entre incrementos | 20 | 200000 / 40000 |
| **Total** | | **168** | **1200000 / 240000** |

P cubre esta cadena de documentos y la decisión/contrato inicial. No se presenta como ocho horas futuras medidas además de la planificación ya hecha. R cubre puertas incrementales y consolidación, sin repetir veinte horas por incremento. Cada tarea tendrá revisión y QA exigidos por el workflow.

## Presupuesto económico

**Coste equivalente humano = horas × tarifa + tokens.** El escenario IA utiliza sólo supervisión humana más tokens; no suma de nuevo todo el desarrollo humano.

### Supuestos (ajustables)

| Parámetro | Valor | Nota |
|---|---|---|
| Tarifa | 50 €/h | Heredada; no confirmada como tarifa real |
| Modelo IA | Heredado del entorno | Tier planner sonnet/medium es metadato del plugin, sin override de modelo |
| Precio input/output | p_in / p_out €/millón; **verificar** | No hay precio numérico vigente confirmado |
| Cambio de moneda | No aplicado | Sin conversión inventada |
| Supervisión | 25 % IA | Supuesto |
| Ratio IA | 300000 tokens/h | Default no calibrado; no existe CALIBRATION.md local |
| Margen | +20 % horas humanas, IA y tokens | Una sola aplicación |
| FTE | 160 h/mes | Referencia matemática |

### Desglose

| Concepto | Cálculo | Importe |
|---|---|---|
| Humano equivalente base | 168 × 50 | 8.400 € |
| Contingencia humana | 33,6 × 50 | 1.680 € |
| Tokens base | 1,2 p_in + 0,24 p_out | Por verificar |
| Tokens con margen | 1,44 p_in + 0,288 p_out | Por verificar |
| **Escenario humano con margen** | 201,6 h × 50 + tokens | **10.080 € + 1,44 p_in + 0,288 p_out** |
| **Escenario IA + supervisión** | 1,44 h supervisión × 50 + tokens | **72 € + 1,44 p_in + 0,288 p_out** |

Los escenarios son alternativos. Se conserva el rango de evaluación: 156–297,6 h y 7.800–14.880 € equivalentes con margen, más tokens. No hay deadline ni consumo real inferidos del ratio.

## Previsión de tokens (por fase)

| Fase | Input tok base | Output tok base | Total base | Coste tokens € |
|---|---|---|---|---|
| 1 — Contrato y decisiones | 80000 | 20000 | 100000 | 0.08 p_in + 0.02 p_out |
| 2 — Identidad Google | 120000 | 20000 | 140000 | 0.12 p_in + 0.02 p_out |
| 3 — Autorización delegada y MCP | 340000 | 60000 | 400000 | 0.34 p_in + 0.06 p_out |
| 4 — Catálogo de negocio | 200000 | 40000 | 240000 | 0.2 p_in + 0.04 p_out |
| 5 — Clientes y operación | 100000 | 20000 | 120000 | 0.1 p_in + 0.02 p_out |
| 6 — QA, revisión y cierre | 360000 | 80000 | 440000 | 0.36 p_in + 0.08 p_out |
| **Total base** | **1200000** | **240000** | **1440000** | **1,2 p_in + 0,24 p_out** |
| **Con margen** | **1440000** | **288000** | **1728000** | **1,44 p_in + 0,288 p_out** |

**Método de estimación:** reparto de la evaluación por adaptadores, identidad, autorización, clientes y pruebas; 37 operaciones iniciales con lógica de negocio compartida. No se reestiman sus seis características. T-01 valida el contrato y registra cualquier diferencia futura mediante cambio explícito del plan.

## Productividad IA (humano vs. IA)

| KPI | Con margen |
|---|---|
| Horas humanas equivalentes | 201,6 h (168 base) |
| IA estimada por tokens | 5,76 h (4,8 base) |
| Supervisión | 1,44 h (1,2 base) |
| **IA + supervisión** | **7,2 h** |
| Diferencia matemática | 194,4 h |
| Ahorro teórico | 96,43 % |
| Multiplicador teórico | ×28 |
| FTE teórico | 1,215 meses equivalentes |

Estas cifras reproducen defaults no calibrados. No predicen ejecución, esperas de proveedor ni sesiones manuales. La generación del plan tiene una ventana privada independiente de T-16; su reloj no se convierte en horas IA ni tokens.

## Resumen ejecutivo

La web identificará a la persona mediante Google y conservará sus cuentas y permisos locales. Los agentes usarán un servidor MCP con consentimiento por cuenta. La aplicación emitirá sus propios tokens delegados y revalidará Teammates en cada operación. Las herramientas compartirán servicios, outbox, idempotencia, supresiones y Mailpit/log con web y API.

### Objetivos

- Cubrir los dieciséis criterios de la spec con evidencia observable y 37 operaciones iniciales de todos sus módulos.
- Conectar Codex, Claude Code y OpenCode en versiones registradas mediante OAuth, sin tokens Google como credencial MCP.
- Conservar portabilidad Laragon/Docker, registro/invitaciones existentes y envío externo deshabilitado.

## Datos necesarios para un informe completo

- [x] Solicitud explícita de MCP/Google, referencia social-scheduler y workflow completo existentes.
- [x] Spec, evaluación, dieciséis criterios y módulos trazados a tareas.
- [x] Contexto técnico muestreado y dependencias externas mapeadas; no se ejecutó aplicación.
- [x] Restricciones: laboratorio, multi-cuenta/Teammates, repo público, Mailpit/log, sin alta disponibilidad.
- [ ] Política final Google: Gmail/Workspace autorizados con filtro opcional es propuesta configurable, no confirmación nueva del usuario.
- [ ] Credenciales/callbacks Google privados y sesiones de clientes reales, necesarios para T-14; no para empezar contrato o desarrollo aislado.
- [ ] Producto/versión exactos de clientes y red del servidor, por registrar en T-01/T-11 y acreditar en T-14.
- [ ] Tarifa real y precio IA; no bloquean plan, importes siguen parametrizados.

## Análisis de impacto

Recon de lectura: `AccountContext` usa sesión y `auth()`. ContactService, TemplateService, ActivityService y ContactImportService dependen de ese contexto. CampaignService y SuppressionService aceptan cuenta/actor explícitos. `Permissions` reúne scopes Teammates. `CreateNewUser` crea una cuenta propia sólo con registro habilitado.

No se copiará el aislamiento por user_id de social-scheduler. El [análisis de referencia](reference-analysis.md) es estático y no acredita interoperabilidad. Se respetan ADR-001/002/004/005 vigentes; ADR-003 sigue propuesta, sin promoverla por este plan.

## Cambios arquitectónicos

### Propuesta elegida y punto de revisión

Se propone **Socialite + Laravel MCP + Sanctum con OAuth propio acotado**, adaptando la referencia fija `7f542a5`. Passport queda descartado para esta implementación propuesta; no se instala como segunda alternativa en paralelo. T-01 valida compatibilidad de paquetes y contrato antes de editar aplicación. Si una garantía no puede cumplirse, la evidencia provoca una revisión concreta del ADR y plan; no una reducción del alcance.

Esta decisión afecta identidad, persistencia, middleware, consentimiento y herramientas. Cruza el umbral ADR. [ADR-006](../../knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md) nace como **propuesta**, con promoción sólo tras revisión. Su escritura e índice se coordinaron con el orquestador: el owner de T-16 confirmó que estos documentos quedan fuera de su snapshot activo. No existe opt-in de design.md ni aprobación humana nueva.

### Identidad web y admisión

Google se solicita sólo para identidad; no Gmail SMTP, Drive ni envío desde Google. La identidad usa proveedor/emisor y `sub` validado en servidor. Coincidir en email no autoenlaza. Vincular exige control autenticado de la cuenta local y no puede reemplazar otra identidad silenciosamente. Desvincular conserva recuperación local viable.

Gmail y Workspace quedan sujetos a admisión existente, con filtro opcional de Workspace. El flujo de registro/invitación explícito sigue habilitado según configuración. Login Google no crea membresías o roles por sí solo. Callback y retorno vuelven al origen confiable, respetando ADR-004; no se confía en Host. Credenciales reales permanecen privadas.

### Contrato OAuth propuesto

| Elemento | Elección a verificar en T-01 e implementar |
|---|---|
| Recurso | URL canónica configurada `/mcp`, ligada al token y publicada mediante metadatos de recurso protegido |
| Descubrimiento | Metadatos del recurso y servidor; challenge de autorización coherente con ambos |
| Grant | Authorization Code + PKCE S256; no password ni client_credentials |
| Registro | Preregistro y registro dinámico controlado de clientes públicos para los perfiles objetivo; cuotas y política de redirects obligatorias |
| Redirects | Exactos por cliente; loopback local con reglas de puerto explícitas sólo para clientes nativos admitidos; nunca wildcard de host/ruta |
| Metadatos remotos de cliente | No fetch de URLs arbitrarias; no se añaden por comodidad sin contrato y control SSRF |
| Código | Aleatorio, hash, 300 s, ligado a cliente/redirect/challenge/recurso/grant; consumo atómico de un solo uso |
| Access token | Sanctum opaco, hash, propósito MCP, emisor/recurso y grant registrados; TTL 3600 s |
| Refresh | No se emite inicialmente, como en la referencia; grant_type refresh_token se rechaza y el cliente reautoriza al expirar |
| Cliente confidencial | Sólo si un perfil objetivo lo necesita; autenticación y secreto hash en contrato, sin reutilizar client_secret de Google |
| Sesión MCP | No prolonga ni sustituye token/grant; autorización por llamada y por mutación |
| Revocación | Grant personal y tokens; refresh no se emite y su grant_type se rechaza. Quitar membresía/reducir permisos afecta inmediatamente nuevas operaciones |

Duraciones son valores propuestos de configuración, no aprobaciones del usuario. T-01 fijará el alcance protocolario/versión y comprobará los perfiles reales. Las pruebas incluyen expiración, reautorización, rechazo de refresh, replay, concurrencia de canje y revocación con conexión ya abierta.

No hay evidencia de cliente real que exija refresh. Se elige reautorización para conservar el patrón de referencia y evitar añadir una familia de credenciales persistentes sin necesidad demostrada. T-14 debe probar expiración y reautorización en cada cliente objetivo. Si un cliente requerido demuestra necesitar refresh, el orquestador reabre ADR/contrato con ese caso, actualiza pruebas y hace explícita cualquier variación de C-02; no rebaja la compatibilidad exigida ni declara éxito por omitir ese recorrido.

### Cuenta y permisos

Un grant identifica **usuario, cliente, cuenta, recurso y scopes consentidos**. El guard dedicado resuelve exclusivamente credenciales MCP y no utiliza la preferencia de sesión de `auth:sanctum`. Google bearer, cookie web, API key de cuenta o token de propósito/recurso ajeno se rechazan antes del servicio.

Permisos efectivos son la intersección de grant, token, membresía vigente y operación. La selección de cuenta ocurre en consentimiento; cambiar cuenta requiere otro grant. Las mutaciones revalidan en transacción con orden de bloqueo documentado. El punto de revocación y el punto de mutación quedan probados con barreras; no se promete cancelar una acción ya confirmada antes de revocar.

Los servicios usan un contexto explícito compartido con T-18, con adaptadores web/API/MCP. No se cambia el usuario global para simular una sesión. API keys de cuenta conservan su ciclo de vida; un grant delegado pierde acceso si su persona deja esa cuenta.

La experiencia es conectar cliente → login Google/local → consentimiento → retorno a la solicitud validada → canje automático de código por el cliente. La persona no crea ni pega API keys en cada cliente. Una identidad Google nueva puede continuar el registro explícito habilitado o aceptar la invitación que le corresponda, sin fusionar por correo ni recibir roles ajenos.

### Catálogo inicial: 37 operaciones

Nombres y esquemas se congelan en T-01. Todos los grupos son alcance, no alternativas optativas. Las anotaciones MCP describen efectos; el servidor siempre autoriza invocaciones directas.

| Grupo | Operaciones propuestas | Número | Permisos |
|---|---|---|---|
| Conexión | `connection_info` | 1 | Membresía vigente y grant |
| Contactos | `contacts_list`, `contacts_get`, `contacts_create`, `contacts_update`, `contacts_delete` | 5 | contacts.read/write |
| Listas | `lists_list`, `lists_get`, `lists_create`, `lists_update`, `lists_delete`, `lists_attach_contact`, `lists_detach_contact` | 7 | contacts.read/write |
| Importaciones | `imports_list`, `imports_get` | 2 | contacts.read; sólo consultar reportes |
| Plantillas | `templates_list`, `templates_get`, `templates_preview`, `templates_create`, `templates_update`, `templates_delete` | 6 | templates.read/write |
| Campañas | `campaigns_list`, `campaigns_get`, `campaigns_create`, `campaigns_update`, `campaigns_review`, `campaigns_authorize`, `campaigns_pause`, `campaigns_resume` | 8 | campaigns.read/write; mail.send al autorizar |
| Envío | `mail_send`, `mail_request_get` | 2 | mail.send; templates.read si usa plantilla; estado según T-17 |
| Actividad | `activity_list`, `activity_attempts`, `activity_metrics` | 3 | activity.read |
| Supresiones | `suppressions_list`, `suppressions_add`, `suppressions_remove` | 3 | suppressions.read/write |

Revisión/versiones, confirmación de operación, límites, destinatarios y prueba de consentimiento siguen el contrato común. El permiso mail.send no demuestra consentimiento del destinatario. Envíos MCP/API usan el mismo namespace de clave idempotente por cuenta y payload; no crean campañas/contactos ficticios ni otro camino SMTP.

Lecturas filtran cuenta antes de paginar. No se devuelven secretos, tokens, rutas internas ni cuerpos completos sin necesidad. Límites iniciales propuestos: 100 resultados/página, cuerpo MCP 1 MiB y salida serializada 256 KiB, sujetos al contrato de cada operación; se documentará cualquier excepción antes de implementarla. No se fijan throughput ni SLA sin carga medida.

## Archivos a crear/modificar

Rutas nuevas son destinos **propuestos**, no archivos ya implementados. `tasks.md` contiene el alcance completo por incremento y sus documentos descriptivos. No se modifican agentes/commands/hooks del plugin ni se genera interop.

| Archivo o zona | Acción | Propósito |
|---|---|---|
| `composer.json`, `composer.lock` | Modificar | Resolver MCP/Socialite estables compatibles; Sanctum existente |
| `app/Models/ExternalIdentity.php`, `app/Services/Auth/*`, migración nueva | Crear | Identidad Google y vínculos |
| `app/Http/Controllers/Auth/GoogleLoginController.php`, rutas/config/vistas auth | Crear/modificar | Login, registro/invitación y perfil |
| `app/Support/ExecutionContext.php`, `app/Services/AccountContext.php` y servicios de negocio | Crear/adaptar | Contexto común web/API/MCP, reutilizando T-18 |
| `app/Models/Mcp*.php`, `app/Services/Mcp/*`, migraciones nuevas | Crear | Clientes, códigos, grants, tokens y autorización |
| `routes/ai.php`, `routes/mcp-oauth.php`, `app/Http/Middleware/McpBearer.php` | Crear | Transporte y endpoints OAuth sin fallback de sesión |
| `app/Mcp/Servers/*`, `app/Mcp/Tools/*` | Crear | Catálogo de 37 operaciones y adaptadores |
| Vistas de consentimiento y conexiones, `app/Livewire/McpConnections.php` | Crear | Cuenta, scopes, denegación y revocación |
| `docs/api/mcp-tools.json`, `docs/api/mcp-oauth.openapi.json` | Crear | Contratos versionados de herramientas y endpoints |
| `tests/Feature/*Mcp*Test.php`, `Google*Test.php`, `tests/e2e/mcp-google.spec.mjs` | Crear | Protocolos, autorización, UI y regresión |
| `scripts/mcp-profile-evidence.py`, configuración Playwright | Crear | Evidencia reproducible de ambos perfiles |
| `.env.example`, `compose.yaml`, `docker/nginx.conf` | Adaptar si requiere | Configuración portable sin duplicar infraestructura |
| `docs/aplicacion/acceso-google.md`, `conexiones-mcp.md`, `mcp-clientes.md`, `mcp-contrato.md` | Crear | Operación y contratos; índices/docs existentes se actualizan |
| `docs/knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md` | Crear como propuesta al liberar fuentes | Decisión transversal reversible |

## Dependencias y prerequisitos

El grafo no cierra en ciclo: **principal T-16 → principal T-17/T-18 → integración MCP → QA MCP → cierre global principal T-19/T-20**. T-01 consume contrato T-17. T-04/T-10 consumen servicios aceptados T-18, que no dependen de cerrar MCP. T-19 puede preparar sus pruebas mientras tanto; su cierre global incorporará esta ampliación.

T-14 se acepta con sus pruebas propias M-01–04, matriz de clientes, escenarios, fixtures y resultados entregados, y requisitos de integración trazados hacia T-19/T-20. No requiere que esas tareas globales ya se hayan ejecutado o cerrado. Después, root debe ejecutar y verificar en T-19 el QA cruzado, carga y restauración con MCP/Google, y en T-20 su documentación y revisión final. La aceptación del componente y el cierre global son puertas distintas; se preservan ambos requisitos.

Las verificaciones del plugin usan `CUSTOM_AGENTS_PLUGIN_ROOT`, igual que el ledger principal. El ejecutor resuelve esa variable a la instalación real y comprueba la existencia del script antes de ejecutarlo. El linter OpenAPI corresponde a `skills/api-contract/scripts/openapi-lint.py`; los comandos documentados no prueban que un contrato futuro ya haya sido validado.

No se edita el ledger principal desde este plan. El orquestador añadirá el enlace de cierre en su propia coordinación. No se declara logrado el objetivo al cerrar veinte tareas originales.

Credenciales Google, callbacks privados, sesiones y conectividad reales sólo condicionan las pruebas externas de T-14. Su ausencia no bloquea contrato, implementación, mocks ni QA local. Ningún secreto se pide en chat ni se publica. Cliente alojado y CLI local tienen rutas de red distintas: la matriz de T-11 las identifica antes de probar.

## Criterios de aceptación (global)

Cada criterio conserva su significado completo en la spec. La siguiente tabla identifica evidencia observable planeada; ninguna fila está ejecutada por este plan.

| CA | Tareas | Evidencia requerida para cerrar |
|---|---|---|
| CA-01 | T-02/03/14 | GoogleIdentityTest/GoogleLoginTest, E2E-01 y M-01 reales: mismo usuario/cuentas |
| CA-02 | T-02/03/12 | Negativos state/código/callback sin efectos; E2E-02 |
| CA-03 | T-02/03/12 | Email coincidente no enlaza, sub estable, conflictos; E2E-02/03 |
| CA-04 | T-03/12/14 | Admisión/registro/invitación y filtro, sin roles implícitos; E2E-03, M-01 |
| CA-05 | T-01/05/06/14 | Metadatos, registro, S256, redirects, código/recurso; McpOAuthTest y M-03 |
| CA-06 | T-06/12 | McpTransportTest demuestra rechazo Google/API key/cookie/otro recurso/expirado/revocado |
| CA-07 | T-04/07/08/09/12 | Dos cuentas, tres roles, scopes e IDs ajenos; McpAuthorizationTest, E2E-04/05 |
| CA-08 | T-04/05/07/12/14 | Barreras de revocación, conexión abierta, rechazo de refresh y reautorización; E2E-06, M-03 |
| CA-09 | T-08/09/10/12 | 37 operaciones positivas/negativas y auditoría cliente/actor/cuenta |
| CA-10 | T-10/12 | MCP/API concurrentes, un trabajo/mensaje, payload distinto conflicto, otra cuenta aislada |
| CA-11 | T-09/10/12 | Supresión/consentimiento/cuota/autorización y worker antes SMTP, Mailpit/log |
| CA-12 | T-11/14 | M-03: clientes reales con versión, consentimiento, permitido/denegado, renovación y revocación |
| CA-13 | T-11/12/14 | Runners nativo/Docker, reinicio/restore, claves/grants/revocación; M-04 |
| CA-14 | T-01/11/13/14 | Contratos y guías revisados, comprobación datos públicos y evidencias sanitizadas |
| CA-15 | T-01/11/14 | Matriz producto/versión/transporte/registro/callback/red; documental ≠ ejecutado |
| CA-16 | T-01/14 y root | T-14 entrega escenarios/fixtures/resultados y root registra requisitos de integración T-19/T-20. La ejecución global posterior debe verificarlos antes del cierre, conservando alcance original y ampliado |

- [ ] CA-01–CA-16 demostrados mediante evidencia vigente correspondiente, incluidos recorridos externos y manuales.
- [ ] Revisión independiente limpia y QA completo sin sustitución de pruebas reales por mocks.
- [ ] Ambos perfiles, catálogo íntegro y Mailpit/log conservados; documentación pública sin datos personales ni secretos.

## Riesgos y mitigaciones

| Riesgo | Probabilidad | Impacto | Mitigación |
|---|---|---|---|
| Copiar user_id o autolink email de la referencia | Alta | Alto | Grant por cuenta y vínculo explícito; pruebas cruzadas |
| OAuth propio insuficiente | Media | Alto | Contrato previo, PKCE, audiencias, replay/refresh/carreras y revisión de seguridad |
| Scope emitido sobrevive a cambios de Teammates | Media | Alto | Intersección vigente en llamada y transacción, sin cache de permisos indefinida |
| Sesión/API key aceptada por MCP | Media | Alto | Guard específico y negativos sin efectos de negocio |
| DCR o redirects abren abuso | Media | Alto | Límites, redirects controlados, sin URL fetch arbitrario ni wildcard |
| Herramienta salta consentimiento o revisión | Media | Alto | Servicios compartidos e idempotencia común; pruebas con bajas/cuotas |
| Compatibilidad sólo documental | Alta | Medio | Matriz real por cliente y versión; T-14 pendiente si no se ejecuta |
| Restauración reactiva tokens revocados | Media | Alto | Invalidar grants al restaurar snapshots antiguos y probarlo |
| Fuentes T-16 cambian durante QA | Media | Alto | Escrituras limitadas y confirmación del owner de que roadmap/knowledge quedan fuera del snapshot |
| Datos privados en repo público | Media | Alto | Fixtures ficticios, no guardar tokens Google, proyección de evidencia y gate de publicación |

## Métricas de éxito

- Dieciséis criterios trazados y cerrados sólo con evidencia vigente; 37 operaciones contractuales y verificadas.
- Cero accesos cruzados, elevaciones, replay de códigos/refresh o ejecución con credenciales no MCP en la matriz negativa.
- Un trabajo para la misma solicitud idempotente MCP/API concurrente; mensajes atribuidos en Mailpit/log.
- Tres familias de cliente verificadas y dos perfiles de despliegue probados; métricas de carga medidas en T-19, sin SLA inventado.

## Changelog del plan

| Fecha | Cambio | Autor |
|---|---|---|
| 2026-10-24 | Plan inicial de 14 tareas; seis características y proceso/revisión heredados, contrato y catálogo concretos | planner custom-agents |
| 2026-10-24 | Corrección previa a implementación: handoff T-14 antes del QA global sin dependencia inversa; OAuth sin refresh y ruta real del linter precisados | planner custom-agents |

## Siguiente paso

Plan revisado técnicamente y aceptado por el orquestador para ejecutarse dentro
del objetivo vigente. Las tareas siguen en borrador hasta que se cumplan sus
dependencias y arranque la implementación. No se atribuye aprobación humana de
las pruebas manuales ni se acepta una fila de compatibilidad sin ejecutarla.

El orquestador revisará el contrato propuesto y coordinará las transiciones a implementación usando la autorización vigente del objetivo completo. Este documento no afirma una aprobación humana nueva. T-01 cierra detalles técnicos y verifica el ADR propuesta antes de tocar aplicación. Después se ejecuta por incrementos con revisión y QA del workflow; tareas/plan permanecen borrador hasta iniciar implementación.

Jira/Confluence siguen sin opt-in; no se envía ni publica nada en servicios externos. El test-plan incluye escenarios E2E y manuales. No añade smoke API-xx ni axe-core por defecto; el protocolo ya se verifica con tests de contrato e integración planeados.
