---
spec: mcp-google
descripcion: Acceso delegado desde agentes mediante MCP y autenticación Google para la web y la autorización MCP.
estado: aprobada
creado: 2026-10-24
actualizado: 2026-10-24
evaluacion: evaluation.md
design: n/a
plan: improvement-plan.md
generacion:
  inicio: null
  fin: null
  fuente: estimado
  tokens_reales: null
  eur: null
  horas_ia: null
  duracion: null
  ratio_usado: 300000
  nota: Sin telemetría independiente de generación; no se atribuyen tokens ni tiempo de T-16.
estimacion_generacion:
  tokens: { entrada: 16000, salida: 6000 }
  horas_ia: 0.073333
  duracion: 4m
  metodo: Juicio para este documento; 22000 tokens divididos por el default no calibrado de 300000 tok/h. No es consumo medido ni duración de reloj.
---

# MCP y acceso con Google para la plataforma de emailing

> **Evaluación:** [`evaluation.md`](evaluation.md), completada y revisada técnicamente por el orquestador.
> **Plan de implementación:** [`improvement-plan.md`](improvement-plan.md), borrador; [ledger](tasks.md) de 14 tareas y [plan de pruebas](test-plan.md). La revisión técnica no implica aprobación humana nueva.
> **Terminología:** Google acredita la identidad de la persona. La aplicación autoriza sus cuentas y permisos. MCP permite que un cliente agente invoque capacidades de la aplicación por delegación de esa persona.

## Contexto y objetivo

El orquestador acepta técnicamente este alcance y su planificación dentro de la
autorización vigente de implementar todo el roadmap. Esta transición permite
ejecutarlo cuando se cumplan sus dependencias; no atribuye al usuario una nueva
revisión del presupuesto ni una aceptación manual de la interfaz, Google o los
clientes MCP. Esas verificaciones conservan sus propias casillas pendientes.

El usuario solicita añadir un servidor MCP utilizable desde Claude, Codex y OpenCode, y facilitar el acceso mediante Google tanto en la web como en MCP. La plataforma interna conserva múltiples cuentas y Teammates, con recursos compartidos dentro de cada cuenta y acceso limitado por membresía. Esta ampliación se incorpora al objetivo global y al workflow completo ya elegido; su registro no implica implementación ni compatibilidad comprobada.

La ampliación enlaza con [T-17 y T-18 del ledger principal](../2026-10-21-plataforma-emailing/tasks.md): contratos y servicios de API, idempotencia y envío compartidos. Debe integrarse antes del cierre global de T-19/T-20. Las veinte tareas originales conservan su alcance. T-16 continúa independientemente y sus evidencias no se reinterpretan como pruebas de MCP o Google.

## Decisiones de diseño

| Decisión | Elección propuesta | Motivo |
|---|---|---|
| Identidad web | Inicio con Google y vinculación explícita a usuario local; mantener el acceso local existente | Evitar duplicar usuarios y permitir la operación del laboratorio cuando Google no esté configurado |
| Identidad estable | Identificador del emisor y `sub`; validar las respuestas de Google en servidor | El correo puede cambiar y no debe ser la clave de vinculación automática |
| Autorización | Cuenta seleccionada, membresía vigente y permisos Teammates en cada operación | Google no concede permisos de negocio |
| Transporte MCP | Servidor remoto Streamable HTTP, ruta propuesta `/mcp` | Un punto de acceso común para clientes agentes |
| Tokens MCP | Tokens emitidos por un servidor de autorización de la aplicación y destinados a este recurso MCP | Un token de Google no es una credencial de la plataforma |
| OAuth | Authorization Code con PKCE S256, descubrimiento de recurso/servidor y consentimiento | Flujo delegado interoperable para clientes públicos y confidenciales |
| Base preferida a evaluar | Patrón de `daycry/social-scheduler`: Laravel MCP, Sanctum, Socialite y autorización OAuth propia acotada; Passport queda como alternativa | Reutiliza un patrón del mismo entorno, con adaptación obligatoria a cuentas y Teammates; no es una decisión aprobada ni una instalación |
| Permisos por operación | Intersección de permisos de la membresía y autorización concedida al cliente | El acceso al transporte no equivale a permiso para todas las herramientas |
| Envío | Servicios de aplicación, outbox, cuotas, supresiones y workers existentes | Preservar garantías de envío e idempotencia entre web, API y MCP |
| Entorno | Laragon y Docker; Mailpit/log exclusivamente | Mantener portabilidad y el límite de laboratorio autorizado |

La documentación de [Laravel MCP](https://laravel.com/framework/docs/13.x/mcp) describe integración OAuth con Passport y una autorización general `mcp:use`, sin scopes personalizados en ese wrapper. No satisface por sí sola la granularidad solicitada: el diseño debe resolver permisos de aplicación y comprobar los metadatos, audiencia y flujos requeridos antes de elegir la integración definitiva.

### Referencia solicitada: social-scheduler

El [análisis de referencia](reference-analysis.md) conserva la muestra, los enlaces y sus límites. Los smokes manuales de clientes de aquel proyecto figuran pendientes; no se hereda compatibilidad ejecutada.

Por indicación posterior del usuario se ha inspeccionado una instantánea de [daycry/social-scheduler, commit `7f542a5`](https://github.com/daycry/social-scheduler/tree/7f542a548613eb535e767fc10081fc21dc5b792a). Su patrón separa tres flujos: login social, conexión de cuentas de redes y autorización de agentes MCP. El login utiliza Socialite e identidades estables; el MCP usa Laravel MCP, tokens Sanctum con abilities granulares y un servidor OAuth propio, sin Passport. Su flujo de autorización implementa registro dinámico, código de un solo uso con hash, PKCE S256, consentimiento y caducidad; no ofrece refresh. Estos hallazgos son lectura estática, no ejecución ni aprobación de seguridad de ese repositorio.

Se propone aprovechar esa separación y los patrones de herramientas que llaman a servicios con actor explícito y dejan auditoría. No se trasladará literalmente su aislamiento por usuario: Emailing requiere cuenta, membresía vigente, consentimiento por cuenta y límites de Teammates. Deben comprobarse además el propósito/audiencia de tokens, el guard de credenciales sin fallback de sesión y las necesidades reales de cada cliente. Refresh queda como decisión de interoperabilidad: se puede renovar la autorización sin refresh si los clientes y la política elegidos lo permiten.

La referencia permite vinculación automática únicamente para Google verificado y una cuenta local también verificada con email exacto normalizado; conserva bloqueos tras desvincular. Ese comportamiento no se adopta automáticamente. La propuesta inicial de Emailing conserva vinculación explícita y deja la opción de autoenlace seguro para una decisión documentada. Tampoco se copia su restricción de no crear usuarios: aquí el registro autorizado existente debe seguir funcionando.

## Configuración / parámetros

Los nombres siguientes son propuestas para el contrato de configuración, no variables implementadas.

| Parámetro | Clave / mecanismo propuesto | Default propuesto | Valor objetivo |
|---|---|---|---|
| Activación Google | Configuración de proveedor y credenciales locales | Desactivado sin credenciales | Web y consentimiento MCP utilizan la misma identidad local |
| Política de cuentas Google | Cuentas autorizadas con filtro opcional de dominio Workspace | Gmail y Workspace sujetos a política interna | Preferencia del usuario pendiente; configuración restrictiva opcional |
| Admisión a la aplicación | Política existente de registro/invitaciones | Conservar política local | Iniciar sesión no crea membresía ni rol automáticamente |
| Callback Google | URI exacta por entorno, registrada en Google | Sin URI operativa inventada | Valores locales configurados fuera de Git |
| Recurso MCP | URL canónica y audiencia verificable | Desactivado hasta configurarse | HTTPS en servidor remoto; excepciones locales sólo según protocolo/cliente |
| Credenciales OAuth | Almacenamiento privado y claves de firma persistentes | Sin secretos publicados | Rotación, revocación y restauración documentadas |
| Vida de tokens | Caducidad explícita y reautorización; refresh sólo si se decide y valida | Duraciones y renovación pendientes de diseño | Valores verificables y compatibles con clientes reales |
| Límites | Por cuenta, cliente y operación | Heredar límites de negocio; añadir límites MCP | Paginación y tamaños máximos documentados antes de carga |

## Arquitectura y componentes

Se reutilizan `User`, `Account`, `Membership`, `Permissions`, `TeamService`, servicios de contactos/listas/plantillas/campañas, `ActivityService` y el pipeline de entrega. Actualmente `AccountContext` obtiene la cuenta desde sesión; varios servicios dependen de ese contexto, mientras `CampaignService` recibe cuenta y actor explícitos. Debe introducirse un contexto de ejecución delegado sin depender de la sesión web ni confiar en un `account_id` arbitrario del cliente, preservando los controles actuales.

Serán nuevos el registro de identidades externas, el flujo Google, la autorización OAuth para MCP, los grants ligados a usuario/cliente/cuenta, el catálogo MCP y la gestión de conexiones autorizadas. La persistencia distinguirá los grants delegados de las API keys de cuenta previstas en T-18: quitar una membresía debe invalidar el acceso delegado de esa persona aunque una API key de cuenta tenga otro ciclo de vida. Los detalles de tablas y paquetes se decidirán en diseño y plan.

La aplicación revalidará membresía y permisos efectivos en cada llamada y en el punto de autorización de mutaciones. Un refresh o cambio de cuenta no podrá ampliar el consentimiento ni recuperar permisos revocados. Los tokens sólo se aceptarán con emisor, audiencia/recurso, expiración y estado de revocación correctos. La sesión MCP no será una prueba de autorización por sí misma.

### Catálogo funcional propuesto

Los nombres finales y esquemas se fijarán en el contrato MCP. Todos los módulos siguientes forman parte de la propuesta; no se limita a una demostración de lectura.

| Módulo | Capacidades | Permisos efectivos mínimos propuestos |
|---|---|---|
| Cuenta y conexión | Mostrar identidad local, cuenta autorizada y capacidades concedidas; elegir otra cuenta mediante autorización válida | Membresía de la cuenta; ninguna elevación implícita |
| Contactos y listas | Buscar, consultar, crear, actualizar y eliminar; asociar/desasociar contactos a listas; consultar importaciones admitidas por los servicios | `contacts.read` / `contacts.write`, según operación |
| Plantillas | Listar, consultar, previsualizar, crear y actualizar; eliminar cuando lo admita el contrato de negocio | `templates.read` / `templates.write` |
| Campañas | Listar, consultar, guardar, revisar, programar/autorizar, pausar y reanudar | `campaigns.read`, `campaigns.write`; `mail.send` además cuando se autoriza el envío |
| Envío transaccional | Solicitar un envío idempotente con contenido o plantilla y consultar su estado | `mail.send`; `templates.read` si se usa una plantilla; lectura de estado según contrato T-17 |
| Actividad | Consultar entregas, intentos, errores seguros y métricas de la cuenta con filtros y paginación | `activity.read` |
| Supresiones | Consultar y gestionar bajas/bloqueos con los controles de negocio existentes | `suppressions.read` / `suppressions.write` |

Las anotaciones de herramientas describirán lecturas, mutaciones y efectos, pero no sustituirán la autorización del servidor. Las mutaciones exigirán argumentos verificables y los identificadores/versiones necesarios; los envíos y programaciones tendrán consentimiento explícito de la operación, idempotencia y revisión cuando el flujo de negocio lo requiera. No se expondrán secretos de transporte o tokens. La administración de Teammates y la emisión de credenciales no se añadirán implícitamente al catálogo: requieren contrato explícito si se solicitan después.

## Flujo (paso a paso)

1. La persona abre la web o conecta el servidor MCP desde su cliente agente.
2. Para MCP, el cliente descubre el recurso y su servidor de autorización, obtiene un identificador de cliente mediante un mecanismo admitido y comienza el flujo con PKCE. El diseño fijará preregistro y/o metadatos de cliente; el registro dinámico sólo se habilitará si se necesita y se controla.
3. La aplicación autentica a la persona mediante Google o un acceso local permitido. El callback valida el intercambio y la identidad; una vinculación a usuario existente exige control comprobado de esa cuenta, sin unión automática por coincidencia de email.
4. La política interna resuelve si puede entrar, registrarse por el flujo habilitado o aceptar una invitación. Elegir Google no crea un rol ni acceso a cuentas ajenas. La creación explícita y autorizada de una cuenta propia conserva las reglas del registro existente.
5. Para MCP, la persona elige una cuenta de la que sea miembro y revisa cliente, cuenta y permisos concretos. El servidor emite un código de un solo uso y después tokens propios para ese recurso y grant.
6. Cada herramienta valida token, grant, cuenta, membresía y permisos actuales; ejecuta los mismos servicios y registra actor, cliente, cuenta y correlación de la operación sin datos sensibles innecesarios.
7. Los envíos se materializan mediante el pipeline común y terminan en Mailpit/log. La consulta devuelve estado seguro, sin prometer entrega al destinatario por una simple aceptación SMTP.
8. La persona puede revocar una conexión desde la web. Quitar su membresía o reducir permisos impide inmediatamente nuevas operaciones afectadas, incluyendo refresh y sesiones MCP ya abiertas.

## Alcance

**Dentro de esta ampliación:**

- C-01: autenticación Google web, vinculación/desvinculación segura y política de admisión configurable.
- C-02: autorización OAuth del recurso MCP, descubrimiento, PKCE, gestión de clientes, tokens y revocación.
- C-03: contexto delegado por cuenta y permisos Teammates, consentimiento y auditoría transversal.
- C-04: catálogo funcional MCP de contactos/listas, plantillas, campañas, envío/estado, actividad y supresiones.
- C-05: configuración documentada para Claude, Codex y OpenCode, con versiones reales y despliegue Laragon/Docker.
- C-06: pruebas de identidad, autorización, aislamiento, idempotencia, revocación y compatibilidad real antes del cierre global.

**Fuera de esta ampliación:** nuevos proveedores SMTP, entrega externa, alta disponibilidad, identidad por cuentas de servicio sin persona delegante y acceso a Gmail/Drive. No se requiere una suscripción API de un modelo para operar el servidor MCP; cada cliente mantiene su propia configuración.

## Manejo de errores

| Caso | Comportamiento esperado |
|---|---|
| Google sin configurar o indisponible | Mensaje de acceso claro y conservación de las alternativas locales autorizadas; sin autenticación incompleta |
| Callback alterado, reutilizado o identidad inválida | Rechazo, sin crear/vincular usuarios ni iniciar sesión |
| Email coincide con usuario no vinculado | Flujo explícito de vinculación tras autenticación local; sin fusión automática |
| Dominio o persona no admitidos | Acceso denegado sin crear membresías |
| Token ausente, expirado, revocado o para otro recurso | Rechazo de transporte OAuth y metadatos/desafío apropiados; sin ejecutar la herramienta |
| Membresía eliminada o scope insuficiente | Operación rechazada; no basta un token previamente emitido |
| Identificador de otro tenant | Rechazo sin revelar el recurso ajeno |
| Mutación concurrente o versión antigua | Conflicto de negocio coherente con web/API; no sobrescritura silenciosa |
| Repetición de envío | Mismo resultado para misma clave y payload; conflicto si el payload cambia |
| Caída del broker o resultado de entrega incierto | Estado/reintentos del pipeline común; sin ruta SMTP alternativa |
| Cliente sin capacidad OAuth requerida | Diagnóstico documentado para su versión; no desactivar autenticación para conectarlo |

## Criterios de aceptación

- [ ] [GWT] CA-01 — Dada una identidad Google válida y previamente vinculada, cuando completa el acceso web, entonces entra al mismo usuario local y sólo ve sus cuentas autorizadas.
- [ ] [GWT] CA-02 — Dado un callback alterado, un `state` inválido o un código reutilizado, cuando se procesa, entonces no crea sesión, usuario ni vínculo.
- [ ] [GWT] CA-03 — Dado un email coincidente sin vínculo Google, cuando se intenta entrar con Google, entonces no se fusionan cuentas sin una vinculación explícita autenticada; una identidad `sub` ya vinculada sigue siendo la misma si cambia su correo.
- [ ] [GWT] CA-04 — Dada una persona fuera de la política de admisión o sin membresía, cuando entra con Google, entonces no obtiene acceso ni rol por el mero inicio de sesión; el registro/invitación permitido sigue su flujo explícito.
- [ ] [GWT] CA-05 — Dado un cliente MCP admitido, cuando descubre y autoriza la conexión, entonces usa PKCE S256, valida redirects/código y recibe un token propio válido sólo para el recurso y grant concedidos.
- [ ] [GWT] CA-06 — Dado un bearer de Google, de audiencia ajena, expirado o revocado, cuando invoca MCP, entonces se rechaza sin ejecutar servicios de negocio.
- [ ] [GWT] CA-07 — Dadas dos cuentas y distintos permisos Teammates, cuando se enumeran o ejecutan herramientas con IDs propios/ajenos y scopes insuficientes, entonces sólo se muestran resultados permitidos y no se produce acceso cruzado ni elevación.
- [ ] [GWT] CA-08 — Dada una conexión ya abierta, cuando se revoca el grant, se elimina la membresía o se reducen permisos, entonces las llamadas y refresh posteriores afectados se rechazan sin reactivar acceso.
- [ ] [GWT] CA-09 — Dadas herramientas de cada módulo del catálogo, cuando un actor autorizado ejecuta lectura y mutación, entonces se aplican las mismas validaciones, revisiones y límites que en web/API y se registra su cuenta, actor y cliente.
- [ ] [GWT] CA-10 — Dadas solicitudes concurrentes MCP/API con la misma clave idempotente, cuando comparten cuenta y payload, entonces generan un solo trabajo; otro payload produce conflicto y otra cuenta queda aislada.
- [ ] [GWT] CA-11 — Dado un destinatario suprimido o una autorización de envío inválida, cuando un agente intenta enviar/programar, entonces el pipeline común lo impide, incluso si el token MCP tiene `mail.send`.
- [ ] [GWT] CA-12 — Dados Claude, Codex y OpenCode en versiones documentadas, cuando completan conexión, consentimiento, llamada permitida, llamada denegada y revocación, entonces se conserva evidencia real por cliente; una configuración leída en documentación no se marca como E2E.
- [ ] [GWT] CA-13 — Dados Laragon y el perfil Docker de aplicación, cuando se configuran los mismos servicios de identidad/MCP y se reinician, entonces persisten vínculos/grants y se preservan revocación y claves según la política documentada, sin activar SMTP externo.
- [ ] CA-14 — El contrato de herramientas, configuración, secretos, rotación y recuperación está documentado; ejemplos y evidencias públicas contienen únicamente datos ficticios.
- [ ] CA-15 — La matriz de compatibilidad registra producto, versión, transporte, registro de cliente, callbacks y límites de conectividad; sólo los recorridos ejecutados constan aprobados.
- [ ] CA-16 — Las dependencias y la ampliación quedan incluidas en el cierre global T-19/T-20 sin declarar completada la nueva iniciativa por acabar las veinte tareas originales.

## Pruebas

El planner trazará CA-01–CA-13 a recorridos reproducibles con sus IDs. Las verificaciones incluirán Google simulado con negativos protocolarios y un recorrido real con credenciales locales; no se confundirá simulación con autorización real de Google. Se probarán vínculos/cambios de email, permisos y revocación concurrentes, contexto sin sesión web, catálogo por módulo, payloads y resultados paginados, cuotas, bajas, idempotencia y aislamiento entre cuentas.

La compatibilidad se acreditará por cliente y versión, distinguiendo Claude Code de otros productos Claude y una conexión local de un cliente alojado que requiera acceso de red al servidor. Las pruebas reales de Google/clientes dependen de credenciales y sesiones del operador; su ausencia se documentará como pendiente, sin publicar secretos ni marcar aprobación. El envío seguirá en Mailpit/log.

## Referencias

- Solicitud del usuario de 2026-10-24: MCP para agentes y Google tanto web como MCP; contexto interno multiusuario con Teammates y workflow completo.
- [Spec principal](../2026-10-21-plataforma-emailing/spec.md) y [ledger principal, T-17–T-20](../2026-10-21-plataforma-emailing/tasks.md).
- `app/Services/AccountContext.php`, `Permissions.php`, `TeamService.php`, `CampaignService.php`; `app/Actions/Fortify/CreateNewUser.php`: muestra de controles y contexto existentes, leída durante evaluación; T-16 mantiene fuentes activas.
- [Laravel MCP, autenticación y autorización](https://laravel.com/framework/docs/13.x/mcp), [Passport](https://laravel.com/framework/docs/passport) y [Socialite](https://laravel.com/framework/docs/socialite): paquetes candidatos, no instalados por esta evaluación.
- [MCP Authorization, versión 2026-07-28](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization): referencia de protocolo; el diseño fijará compatibilidad con las versiones de clientes elegidas.
- [Google OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect): validación y vínculo estable por `sub`; configuración de credenciales y callback.
- [social-scheduler, referencia inmutable](https://github.com/daycry/social-scheduler/tree/7f542a548613eb535e767fc10081fc21dc5b792a): `app/Services/Auth/SocialIdentityResolver.php`, `app/Http/Controllers/Auth/SocialLoginController.php`, `app/Services/Auth/LoginFlow.php`, `app/Services/Mcp/OAuth/AuthorizationServer.php`, `RedirectPolicy.php`, `app/Mcp/Tools/SchedulerTool.php`, `app/Mcp/Abilities.php` y `routes/ai.php`. Muestra estática acotada, sin pruebas ejecutadas.
- [Codex MCP](https://learn.chatgpt.com/docs/extend/mcp?surface=cli), [Claude Code MCP](https://code.claude.com/docs/en/mcp) y [OpenCode MCP](https://docs.opencode.ai/docs/mcp-servers/): documentación de clientes consultada por el orquestador; compatibilidad real pendiente.

## Decisiones confirmadas (revisión del usuario · 2026-10-24)

1. Añadir acceso desde agentes mediante MCP y autenticación Google web/MCP. Solicitud explícita.
2. Mantener aplicación interna multiusuario por cuentas y Teammates; workflow completo de custom-agents, elegido previamente.
3. Conservar Mailpit por el momento y el envío externo deshabilitado, conforme a la instrucción previa vigente.
4. Examinar el repositorio de social-scheduler para entender sus redes sociales y MCP antes de cerrar la propuesta técnica. Examen estático realizado; adaptación aún no implementada.

## Supuestos

- Se proponen Gmail y Google Workspace para personas admitidas por la política interna, con restricción opcional por dominio. La preferencia se preguntó y sigue pendiente; no es una decisión confirmada del usuario.
- Los tiempos de token y la política de registro de clientes quedan para diseño. No se promete que el wrapper OAuth de Laravel cubra scopes granulares, audiencia y todas las variantes de registro sin adaptación.
- Se mantendrá el acceso local existente; si se desea posteriormente Google obligatorio, requerirá definir recuperación y migración antes de cambiar esa política.
- No existe todavía prueba real de Google, MCP ni de las tres integraciones de cliente para esta aplicación.
