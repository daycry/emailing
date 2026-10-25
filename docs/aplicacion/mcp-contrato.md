# Contrato MCP e identidad Google

Estado: contrato **0.1.0** aceptado técnicamente en T-01 tras revisión independiente A+B+C y validación estática (2026-10-25). Se reconcilió con T-18 aceptada (`754935e`). [ADR-006](../knowledge/adr/ADR-006-identidad-google-y-oauth-mcp-delegado.md) está aceptada como decisión revisada. El núcleo delegado OAuth/MCP está aceptado técnicamente en laboratorio (T-04–T-07); el catálogo completo y la compatibilidad real permanecen pendientes en sus tareas posteriores.

T-04–T-07 están aceptadas técnicamente tras revisión 2 y QA independiente:
contexto explícito de servicios, autoridad SQL vigente, endpoints OAuth propios,
transporte MCP, consentimiento, conexiones y auditoría. El [cierre de fase](../roadmap/2026-10-24-mcp-google/testing/fase-3/acceptance.md)
enlaza el backend QA1 (77/1808) y navegador QA2 (6/6), sobre las mismas fuentes.
El preregistro local usa `mcp:client-register`; DCR sigue desactivado por defecto
y, al habilitarlo, requiere destinos admitidos por el operador. `mcp:prune`
ejecuta etapas finitas y conserva referencias de procedencia; su programación
no acredita un scheduler operativo. El catálogo completo, Google y clientes
reales, despliegues cualificados y M-01–M-04 siguen pendientes. Las herramientas
`lists_get` y `lists_create` son el alcance adelantado; T-08 conserva la
verificación de sus veinte operaciones.

## Identidad y autorización

Google identifica a la persona mediante emisor y `sub` verificados en servidor. La coincidencia de email no fusiona usuarios: vincular exige autenticación local explícita. Login, registro permitido e invitaciones conservan sus reglas. Google no crea membresías ni roles y no se usa para SMTP. El acceso local sigue disponible sin credenciales Google. Desvincular no puede eliminar el último método de recuperación viable.

La identidad externa es del usuario local, no de la cuenta seleccionada en la web. El alta Google explícita conserva Fortify, la política de registro y la comprobación del destinatario de la invitación. Se distingue una contraseña local elegida/controlada de un hash aleatorio usado para satisfacer una columna: ese hash no demuestra recuperación. Una persona que sólo dispone de Google debe establecer y verificar un método local viable antes de desvincular. No se omiten 2FA, recuperación ni invalidación de sesiones existentes. Una identidad ya vinculada a otra persona no se sustituye.

La auditoría de vínculo/desvínculo se ancla al usuario y al proveedor/emisor, con resultado y correlación mínimos. El `AuditEvent` actual requiere cuenta: no se escoge una cuenta arbitraria ni se usa el selector web para satisfacerlo. T-02/T-03 deben añadir una persistencia de auditoría de identidad que admita ese contexto de usuario. La auditoría de negocio MCP conserva, por separado, cuenta, usuario, cliente y grant reales. No se registran códigos, tokens, email completo innecesario ni respuestas del proveedor.

El cliente MCP descubre el recurso y obtiene autorización de la persona para una cuenta. La aplicación emite tokens opacos propios sobre Sanctum mediante Authorization Code + PKCE S256; código 300 s, access token 3600 s, sin refresh inicial. Un guard exclusivo verifica propósito, emisor/recurso, caducidad, revocación, usuario, cuenta y membresía en cada petición. Cookie web, API key de cuenta y bearer Google no autentican MCP. La sesión MCP no sustituye al bearer ni conserva permisos revocados.

Los permisos efectivos son la intersección del techo del token, grant y membresía actual. La cuenta procede del grant, sin argumentos `account_id` de herramienta ni dependencia de sesión web; sólo el consentimiento interactivo permite seleccionar entre membresías propias vigentes. Se vuelve a autorizar bajo el mutex de cuenta antes de mutar y antes de devolver un replay. Un cambio de rol no amplía el consentimiento previo. La revocación impide nuevas llamadas y replays; el trabajo ya aceptado conserva su recibo e historial y continúa sujeto a las reglas vigentes del pipeline, consentimiento y supresiones.

`admin` puede solicitar los scopes declarados del catálogo; `read-only` sólo los terminados en `.read`; `restricted` sólo los explícitos de su membresía vigente. Ningún rol supera el techo consentido. No se exponen administración de Teammates, API keys o transporte a través de estas 37 operaciones. El orden común de bloqueo es cuenta → membresía → cliente → grant → token → origen de operación. Los cambios/revocaciones de membresía y credenciales deben usar esa frontera; T-04/T-07 verifican con barreras SQL el orden de commit, según el cierre técnico de fase. Los envíos de negocio conservan su integración posterior T-10. La revocación no cancela retroactivamente un intercambio SMTP ya iniciado.

## Contratos y catálogo

[mcp-tools.json](../api/mcp-tools.json) publica las 37 operaciones, esquemas de entrada/salida cerrados, scopes mínimos/condicionales, errores y límites. [mcp-oauth.openapi.json](../api/mcp-oauth.openapi.json) describe descubrimiento, autorización/decisión, canje, revocación y registro controlado. Ninguno sustituye las validaciones y políticas reales.

El catálogo conserva todos los módulos: conexión, contactos, listas, importaciones, plantillas, campañas, envío transaccional, actividad y supresiones. Descubrimiento y ejecución se filtran con permisos actuales. Las proyecciones opcionales de campañas exigen sus scopes de contactos/plantillas/remitente; solicitarlas sin permiso se rechaza. La revisión crea una revisión congelada; continuar destinatarios utiliza campaigns_get y el cursor de esa revisión, sin volver a revisar. Autorizar envío sigue siendo un paso explícito posterior a inspeccionar audiencia, contenido, remitente y fecha. Un cursor obsoleto por cambio de revisión produce conflicto.

Crear/actualizar campañas con sender_id explícito no nulo exige mail.send cuando así lo exige el servicio común. Leer remitentes desde connection_info también exige mail.send. mail_send con plantilla exige templates.read. Los presets read/send/manage son techos solicitados, nunca permisos concedidos automáticamente. Una conexión sin scopes sólo puede consultar connection_info; la prueba real de cliente debe alcanzar una operación útil autorizada y una denegada, además de revocación y reautorización al expirar.

## Procedencia e idempotencia comunes

El envío MCP no se representa creando una API key, contacto o campaña artificial. La fuente transaccional común conserva account_id, clave idempotente/fingerprint y recibo únicos por cuenta, además del tipo de origen y su procedencia real. Para API mantiene su credencial de cuenta y actor humano nulo; para MCP identifica grant/cliente y usuario que autorizó. Las FK compuestas y ramas exclusivas de SQL deben impedir mezclar cuenta, fuente, grant o destinatario. El usuario puede desaparecer: la atribución histórica no inventa otro usuario y las referencias del trabajo aceptado se conservan mediante tombstones, sin borrar recibos por revocar una conexión.

La tabla interna api_mail_requests y sus columnas heredadas pueden mantenerse durante una migración aditiva; su nombre no justifica origin=api para MCP. El servicio de aceptación común recibirá un principal validado explícito, conservará payload/fingerprint de T-17 y usará el mismo mutex e índice único(account_id,idempotency_hash). Peticiones API/MCP concurrentes con misma cuenta, clave y payload crean una sola población de trabajo y devuelven el mismo recibo; otro payload produce conflicto. El origen del primer commit es inmutable. El replay se audita con su invocante actual sin reatribuir el trabajo. No se cambia el contrato REST T-17 para lograr esta integración.

Actividad y CSV distinguen población mcp de api/campaign/test/unknown. El DTO MCP usa request_id para la fuente transaccional compartida y credential.kind=api_key o mcp_grant, con referencias públicas y nombres seguros, sin tokens. La actividad de una campaña sigue siendo campaña, conservando en su auditoría la delegación que la autorizó. Un grant revocado elimina acceso delegado y no elimina su procedencia. Las métricas cuentan una sola entrega por origen, sin duplicar replay ni intentos.

## Límites, errores y paginación

Antes del SDK se limita el JSON-RPC crudo a 4 MiB y profundidad 32, se rechazan miembros duplicados y se conservan lexemas numéricos exactos. Después se aplica el esquema cerrado y la validación de dominio. Los límites semánticos y de bytes del mensaje de T-17 continúan siendo adicionales: no se justifican por el tamaño de una recodificación JSON. IDs/versiones/limits enteros no se aceptan por redondeo de float.

Una respuesta es un único objeto estructurado más su texto JSON equivalente. El límite de 8 MiB cuenta el envelope realmente serializado, ambas representaciones y metadatos. Preview tiene techo de 1 MiB de documento. Páginas de 25 por defecto y 100 máximo se cortan también por bytes sin saltar/duplicar filas; un elemento que no cabe produce result_too_large y no un cursor inmóvil. Error seguro hasta 16 KiB, mensajes de diccionario sin SQL, stack, contenido enviado o secretos, también con APP_DEBUG habilitado.

Errores de protocolo no reflejan nombres/valores arbitrarios. Fallos de herramienta usan isError con DTO de código, operación, outcome y recuperación. Tras un commit exitoso, un fallo de construcción devuelve outcome=applied con referencia propia si se conoce; si el commit es incierto, unknown. mail_send sólo recupera repitiendo la misma cuenta, clave y payload; las demás mutaciones inciertas requieren consultar la referencia o resolver el trace_id. Aceptación/cola y resultado SMTP son estados distintos.

Cursores autenticados con el cifrado de Laravel y su anillo de claves anteriores, sin serialize/unserialize ni criptografía propia. Payload máximo de 1024 bytes, token base64url canónico máximo de 2048 bytes, TTL 900 s acotado por expiración del bearer y tolerancia de 30 s. Se vinculan a recurso confiable, usuario/grant/cuenta, permisos actuales, filtros normalizados, recurso padre, proyección y orden estable. Se autentica antes de interpretar. Una ventana de actividad por defecto conserva sus fechas a través de medianoche. Cambio de permiso/grant/revisión invalida el cursor. Se validan roundtrip, alteración, rotación y recorrido completo con el framework real antes de declararlo cualificado.

## Admisión y conservación

El JSON fija valores iniciales configurables y medibles para laboratorio. Ventanas SQL atómicas de 60 s aplican todos los límites relevantes: transporte 60/IP; token y grant 120 cada uno; cuenta 600; authorize 30/IP; token 30/IP y 60/cliente; revoke 60/IP. Registro dinámico desactivado por defecto: si se habilita, 5/IP/min, 20/IP/día y 100 clientes activos por emisor. Máximo 5 autorizaciones pendientes por usuario/cliente con TTL 600 s; cinco intentos de verifier por código. Las cuotas de negocio existentes siguen aplicándose y no se amplían por estos límites. La implementación debe demostrar contadores concurrentes y Retry-After, sin guardar IP cruda ni confiar en forwarded headers de proxies no autorizados.

Limpieza finita y acotada: códigos/pendientes expirados 24 h; ventanas 48 h; tokens expirados/revocados 30 d; grants revocados 90 d; auditoría 365 d. Clientes dinámicos caducan tras 30 d inactivos; los preregistrados se revocan explícitamente. Primero se revoca/expira de forma atómica; no se borran grants/tombstones/recibos todavía referenciados por trabajo aceptado. La conservación indefinida de recibos idempotentes de T-17 no se reduce mediante la limpieza OAuth.

## Entornos, paquetes y evidencia

Perfil público inicial: clientes OAuth públicos con método none y PKCE S256. Preregistro local administrado; DCR sólo con switch explícito y admisión. No se anuncia CIMD ni se descarga una URL de cliente sin un diseño SSRF adicional. Refresh y clientes confidenciales no se anuncian hasta tener requisito demostrado y contrato/pruebas correspondientes.

El emisor y recurso HTTPS se configuran explícitamente; ejemplo reservado https://emailing.example.test y /mcp. No se derivan de Host. Los redirects se comparan exactamente salvo la excepción de puerto loopback definida por el contrato; no fragmentos, userinfo ni comodines. Laragon y Docker requieren cualificar TLS/cookies y la confianza real de los clientes. Credenciales/callbacks Google se configuran fuera de Git. No se sustituye una prueba de Google por el proveedor simulado.

Candidatos resueltos aisladamente: Laravel MCP 1.1.1 y Socialite 5.31.0, con firebase/php-jwt 7.2.1 y league/oauth1-client 1.12.0; Laravel13.38.0 y Sanctum4.3.3 existentes. El solver no actualizó ni eliminó dependencias existentes, no instaló vendor ni ejecutó plugins/scripts y no ignoró requisitos de plataforma. Es factibilidad de resolución sobre Windows/PHP 8.4.18, no un lock aplicado ni prueba Linux o funcional. Tareas posteriores fijarán lock y cualificarán SDK/perfiles.

Se fijan dos **perfiles objetivo**, todavía sin soporte runtime acreditado: MCP `2025-11-25` mediante `initialize` y `notifications/initialized`, y MCP `2026-07-28` con metadatos completos por petición, sin handshake initialize. En el segundo, el servidor implementa `server/discover` y el cliente puede consultarlo o invocar directamente. La [fuente fijada de Laravel MCP 1.1.1](https://github.com/laravel/mcp/blob/e77af70cd009aaa31455626bd463b0e7e39cfcf9/src/Enums/ProtocolVersion.php) separa el descubrimiento nuevo y la negociación legacy; su enum no demuestra ejecución correcta. T-06 probará ambos caminos, metadatos ausentes/parciales/tipos erróneos, versión no admitida y divergencias entre cuerpo y cabeceras. [El transporte 2026](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports) hace autoritativo el cuerpo; no basta añadir una cabecera con el número de versión. La [negociación de versiones](https://modelcontextprotocol.io/specification/2026-07-28/basic/versioning) requiere el error estándar de versión no admitida y no permite degradar metadatos modernos parciales a una llamada legacy permisiva.

La [autorización 2025](https://modelcontextprotocol.io/specification/2025-11-25/basic/authorization) y la [revisión 2026](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) exigen descubrimiento y vinculación del recurso. Se publica recurso protegido en las dos rutas well-known del OpenAPI y servidor de autorización en raíz. `resource` es obligatorio en autorización y canje. Metadatos y respuestas de autorización usan el mismo emisor exacto, incluido `iss` en éxito/error; no se normaliza para comparar. El perfil elige preregistro público con `none`; DCR es opcional y desactivado inicialmente, y CIMD no se anuncia. Esta elección acotada requiere reabrir ADR/contrato si un cliente objetivo no puede usarla, sin rebajar la compatibilidad exigida.

Ayuda CLI, resolución de paquetes y esquemas correctos no acreditan conexión. Google real y sesiones de clientes requieren evidencia directa del operador en T-14. Cada versión y ambos perfiles deberán recorrer descubrimiento, consentimiento Google/local, llamada permitida/denegada, revocación y reautorización tras expirar.

Las catorce tareas MCP y sus dieciséis criterios se conservan. Su entrega precede a la carga/restauración y al QA global de T-19/T-20; no los sustituye. SMTP externo continúa deshabilitado, con Mailpit/log. Datos, ejemplos y evidencias públicas son sintéticos y no contienen secretos ni rutas personales.

## Caducidades, rotación y recuperación

El código dura 300 s y se consume una sola vez junto con la emisión atómica del token. Un replay invalida las credenciales derivadas de ese código. El access token y el grant duran como máximo 3600 s; reautorizar crea un grant nuevo con permisos actuales y consentimiento, sin extender el anterior ni emitir `offline_access` o refresh. Los tokens opacos se guardan mediante hash; no se inventa una firma JWT o JWKS para Sanctum.

El cifrado de cursores usa claves Laravel privadas persistentes. Al rotar, la clave nueva cifra y el anillo anterior sólo descifra; mantener la anterior al menos 900 s + 30 s permite agotar cursores vigentes. Una retirada inmediata invalida los cursores y obliga a reiniciar la consulta. Una clave comprometida o restauración de snapshot antiguo requiere revocar de forma atómica grants/tokens/códigos potencialmente reactivados y retirar claves afectadas antes de volver a autorizar. Los recibos idempotentes y su procedencia sobreviven. La clave idempotente no depende de `APP_KEY`.

## Matriz inicial de clientes

El inventario privado capturó versión/ayuda, sin login ni llamada. Estas son versiones **candidatas de cualificación**, no versiones mínimas ni compatibilidad aceptada. El destino es el recurso HTTPS configurado en Laragon y Docker; el cliente local necesita alcanzar ese origen y confiar en su certificado. No se modifica su configuración durante T-01.

| Producto | Versión candidata | Registro y callback objetivo | Permisos y protocolo | Resultado |
|---|---|---|---|---|
| Codex CLI | 0.163.0 | Cliente público preregistrado; callback mostrado por CLI; `127.0.0.1` y puerto loopback variable sólo donde se admite | `scopes` de servidor; verificar protocolo negociado | Pendiente T-11/T-14 |
| Claude Code CLI | 2.1.304 | `--client-id` sin secreto y puerto fijo opcional; `localhost:puerto/callback` es perfil distinto de IP literal | `oauth.scopes` es string; `--scope` sólo elige almacén de configuración | Pendiente T-11/T-14 |
| OpenCode CLI V2 | 2.0.12 | `mcp.servers`, `oauth.client_id` y callback loopback preregistrado; campos snake_case | `oauth.scope` es string; cualificar legacy y `2026-07-28` explícitamente | Pendiente T-11/T-14 |
| Claude Desktop | Sin fijar | Producto separado, fuera de los tres objetivos iniciales; no se atribuye el resultado de Claude Code | Incorporarlo como objetivo exige fijar versión y ejecutar la misma matriz | Sin prueba ni aprobación |

Fuentes oficiales consultadas el 2026-10-25: [Codex CLI](https://learn.chatgpt.com/docs/extend/mcp?surface=cli) documenta preregistro, callbacks ligados al emisor y scopes; [Claude Code](https://code.claude.com/docs/en/mcp) documenta cliente público, callback localhost y scopes; [OpenCode V2](https://dev.opencode.ai/v2/docs/mcp-servers/) distingue configuración V2, registro y modos de protocolo. Estas páginas no prueban las versiones locales. T-14 debe acreditar reautorización sin refresh en los tres clientes; un fallo obliga a revisar el contrato antes del cierre.

## Reproducción y trazabilidad

Desde la raíz, con Python 3.10+ y `jsonschema` 4.x disponible en un entorno aislado:

```text
python scripts/check-mcp-contract.py
python scripts/check-mcp-contract.py --json
```

El verificador lee exclusivamente contratos/fixtures públicos versionados en `tests/Contracts/fixtures/mcp-v0.1.0`, comprueba sus hashes, 37 operaciones, schemas, ejemplos positivos/negativos y matriz de permisos. El lint OpenAPI del plugin se ejecuta además sobre `docs/api/mcp-oauth.openapi.json`. No instala dependencias ni carga Laravel. Las fixtures antiguas de actividad se proyectaron explícitamente a `request_id` y a contadores MCP vacíos: no representan trabajos MCP ejecutados.

El cuerpo crudo de 4 MiB y resultado completo de 8 MiB son excepciones expresas a los límites propuestos inicialmente (1 MiB/256 KiB): conservan el cuerpo de envío T-17 de 2 MiB más su envelope y las dos representaciones MCP del resultado/preview, sin truncar contenido válido. Son límites de admisión, no capacidad medida. T-06/T-08 probarán el serializador real, límites exactos y páginas por bytes; si un cliente tiene menor capacidad, la prueba debe registrarlo y el contrato adaptarse con evidencia.

| Criterios conservados | Contrato y evidencia posterior obligatoria |
|---|---|
| CA-01–04 | Emisor/sub, vínculo explícito, admisión y recuperación; T-02/03 y Google real T-14 |
| CA-05–06 | OpenAPI de descubrimiento/código/recurso y bearer exclusivo; T-05/06 y negativos protocolarios |
| CA-07–08 | Roles/intersección vigente, contexto y bloqueo/revocación; T-04/07 y barreras con conexión abierta |
| CA-09 | 37 entradas/salidas y efectos; T-08/09/10, auditoría y negativos de cada operación |
| CA-10–11 | Dominio T-17/T-18, idempotencia API/MCP y consentimiento/supresión; concurrencia T-10/12 |
| CA-12–13 | Tres clientes reales y Laragon/Docker; T-11/14, reinicio/restauración y reautorización |
| CA-14–15 | Contratos/fixtures sintéticos, rotación y matriz versionada; revisión/privacidad y evidencia real T-14 |
| CA-16 | T-14 entrega escenarios/fixtures/resultados; root ejecuta después T-19/T-20 con alcance global íntegro |

Dependencias comprobadas sin ciclos: T-01 consume T-16/T-17 aceptadas y reconcilia T-18 aceptada; T-04/T-10 reutilizan sus servicios. T-02–T-14 implementan y cualifican estos contratos. Aceptar esta tarea no cierra un criterio funcional o manual de la spec, ni el QA global.
