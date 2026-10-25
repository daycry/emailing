# Revisión de la fase 3 — intento 1

La revisión requiere correcciones: **0 Critical, 4 Important y 2 Minor**. Se corregirán los seis hallazgos; no se acepta deuda. QA independiente continúa pendiente.

Fecha: 2026-10-25 12:16:22 UTC. Se revisaron los 98 cambios desde `7cce40bd09997e079013f01e96ccf6a734b0187d`: patch tracked completo y 69 instantáneas nuevas. La cápsula conserva 628 archivos, con manifiesto SHA-256 `3e264c548008978898b489a38a44c222e72412ad7e377472a6e74edaa7daaf16`. Root comprobó 364 fuentes finales y 96 ejecuciones originales con sus archivos y salidas.

Corrieron las lentes A, B, C y D de custom-agents 2.0.0 con contexto fresco. Se usó el respaldo genérico con modelo heredado porque el revisor nominal no está disponible. D arrancó al liberarse una de las tres plazas de revisión. La revisión fue estática; no ejecutó aplicación, SQL, navegador ni envíos.

## Criterios y resultado consolidado

Los ✓ siguientes conservan el veredicto funcional de A. **B-01 demuestra por lectura un escenario adicional de revocación que impide aprobar T-04; A-01 deja incompleta la verificación de T-06.** Un ✓ de lectura no constituye QA ni aceptación final.

| Criterio | Lente A | Implementación | Test |
|---|---|---|---|
| T-04-CA-01 — Permiso efectivo = scopes del grant ∩ scopes del token ∩ permisos actuales de membresía ∩ permisos de la operación. | ✓ | `app/Services/Mcp/DelegatedContext.php:43` | `tests/Feature/McpAuthorizationTest.php:67` |
| T-04-CA-02 — La cuenta procede del grant verificado; account_id y IDs del cliente nunca seleccionan otra cuenta. | ✓ | `app/Services/Mcp/DelegatedContext.php:48` | `tests/Feature/McpAuthorizationTest.php:38` |
| T-04-CA-03 — Cada mutación revalida grant y membresía en su transacción con orden de bloqueo definido; una revocación confirmada impide nuevas mutaciones. | ✓ | `app/Services/Mcp/DelegatedContext.php:47` | `tests/Feature/McpRevocationConcurrencyTest.php:20` |
| T-04-CA-04 — La revocación de membresía afecta al grant personal; el ciclo de vida de API keys de cuenta de T-18 se conserva separado. | ✓ | `app/Services/TeamService.php:154` | `tests/Feature/McpAuthorizationTest.php:109` |
| T-05-CA-01 — Metadatos de recurso/servidor y desafío apuntan al origen configurado y a los endpoints implementados; no se derivan de Host. | ✓ | `app/Services/Mcp/OAuth/Metadata.php:9` | `tests/Feature/McpOAuthTest.php:51` |
| T-05-CA-02 — Código con hash, TTL 300 s y consumo atómico se liga a cliente, redirect exacto, S256, recurso y grant consentido. | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:177` | `tests/Feature/McpOAuthConcurrencyTest.php:20` |
| T-05-CA-03 — Access token Sanctum opaco: propósito MCP, emisor/recurso, grant y TTL 3600 s; sin refresh inicial, con reautorización explícita al expirar. | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:240` | `tests/Feature/McpOAuthTest.php:63` |
| T-05-CA-04 — Grant_type refresh_token se rechaza; reautorizar vuelve a comprobar membresía y scopes sin recuperar acceso revocado. | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:199` | `tests/e2e/mcp-google.spec.mjs:367` |
| T-05-CA-05 — Preregistro y DCR controlado de clientes públicos siguen política de redirects y límites; errores previos a validar redirect nunca redirigen a destino no confiable. | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:33` | `tests/Feature/McpOAuthTest.php:124` |
| T-06-CA-01 — Guard dedicado acepta sólo el token MCP válido ligado al recurso, grant, cliente y usuario; no usa fallback stateful de Sanctum. | ✓ | `app/Services/Mcp/TokenResolver.php:11` | `tests/Feature/McpTransportTest.php:58` |
| T-06-CA-02 — Cookie web sin bearer, token Google, API key de cuenta, token otro recurso/propósito, expirado o revocado no ejecutan servicios. | ✓ | `app/Http/Controllers/McpTransportController.php:36` | `tests/Feature/McpTransportTest.php:58` |
| T-06-CA-03 — Sesiones persistentes vuelven a validar cada llamada; lista y llamadas directas aplican capacidades vigentes. | ✓ | `app/Http/Controllers/McpTransportController.php:36` | `tests/Feature/McpTransportTest.php:344` |
| T-06-CA-04 — Límites de cuerpo, tamaño de resultado, paginación y concurrencia rechazan excesos de forma definida; no activan otro transporte sin revisión. | ✓ | `app/Http/Middleware/McpRawBody.php:27` | `tests/Feature/McpTransportTest.php:100` |
| T-07-CA-01 — Pantalla presenta cliente, cuenta y capacidades concretas; sólo ofrece membresías y permisos actuales, con POST+CSRF para aprobar/denegar. El canje devuelve el token al cliente automáticamente: no exige crear/copiar una API key en cada cliente. | ✓ | `resources/views/mcp/consent.blade.php:4` | `tests/e2e/mcp-google.spec.mjs:289` |
| T-07-CA-02 — Cambiar de cuenta exige un grant nuevo; no se modifica el account_id de una conexión emitida. | ✓ | `app/Models/McpGrant.php:20` | `tests/Feature/McpConsentToolsTest.php:47` |
| T-07-CA-03 — La persona sólo gestiona sus conexiones; revocar corta tokens y sesiones de esa conexión sin tocar otra cuenta. Refresh no se emite y su grant_type se rechaza. | ✓ | `app/Services/Mcp/GrantService.php:18` | `tests/Feature/McpConsentToolsTest.php:90` |
| T-07-CA-04 — Auditoría de éxito y denegación identifica actor, cuenta, cliente, operación y correlación sin tokens, secretos o contenido completo innecesario. | ✓ | `app/Services/Mcp/Audit.php:10` | `tests/Feature/McpConsentToolsTest.php:134` |

## Hallazgos

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Important | Faltan negativos HTTP de metadatos modernos — pendiente | T-06 | Probar ausencia, parcialidad en ambos sentidos, tipos erróneos y espejos de cabecera; rechazo definido y ningún servicio ejecutado. | `tests/Feature/McpTransportTest.php:84` |
| B-01 | Important | La retirada puede omitir autorizaciones concurrentes — pendiente | T-04 | Reproducir la intercalación real con MySQL REPEATABLE READ y barreras; impedir reactivar tokens tras retirada/reinvitación, conservando el orden de bloqueos. | `app/Services/TeamService.php:156` |
| B-02 | Minor | El callback admite nombres OAuth reservados con valor vacío — pendiente | T-05 | Rechazar claves reservadas vacías y codificadas sin cambiar que los valores vacíos del protocolo OAuth se tratan como omitidos. | `app/Services/Mcp/OAuth/RedirectPolicy.php:15` |
| B-03 | Minor | El nombre Unicode se limita por bytes — pendiente | T-05 | Admitir hasta 150 caracteres Unicode en client_name y rechazar 151; preservar los límites de bytes de los demás campos y del cuerpo. | `app/Services/Mcp/OAuth/Input.php:78` |
| D-01 | Important | La limpieza programada drena menos ventanas de las que se crean — pendiente | T-05 | Mantener pasadas finitas y demostrar que la frecuencia y el presupuesto drenan varios lotes de ventanas caducadas; conservar las retenciones y referencias. | `routes/console.php:11` |
| D-02 | Important | La retirada recorre autorizaciones históricas ya revocadas — pendiente | T-04 | Evitar trabajo proporcional a tombstones ya revocados; probar un histórico de 600 grants y la autoridad viva, con presupuesto de consultas y auditoría correcta. | `app/Services/TeamService.php:156` |

B-01 y D-02 afectan al mismo método, con causas distintas: instantánea obsoleta y trabajo histórico innecesario. Sus pruebas deben cubrir ambos. La ausencia de hallazgos en C no rebate los defectos encontrados por otras lentes.

### A-01: Faltan negativos HTTP de metadatos modernos

Falta la matriz negativa de metadatos modernos que el contrato descriptivo aceptado asigna expresamente a T-06. Con bearer válido y espejos de cabecera correctos, no se envían peticiones sin los metadatos modernos, con sólo uno de protocolVersion/clientCapabilities, ni con tipos erróneos de _meta/clientCapabilities. El helper packet() siempre reconstruye ambos metadatos como objetos y los negativos actuales sólo alteran la versión a future o Mcp-Method; una regresión que elimine la comprobación de capacidades completas podría pasar estos tests.

- docs/aplicacion/mcp-contrato.md:67: T-06 probará ambos caminos, metadatos ausentes/parciales/tipos erróneos, versión no admitida y divergencias entre cuerpo y cabeceras.
- tests/Feature/McpTransportTest.php:29: packet() inyecta siempre protocolVersion 2026-07-28 y clientCapabilities objeto.
- tests/Feature/McpTransportTest.php:84: negativos de Origin, Mcp-Method, future, unknown method/name y per_page; no casos de metadatos ausentes/parciales/tipos.
- tests/Feature/McpConsentToolsTest.php:21 y tests/e2e/mcp-google.spec.mjs:94: helpers productivos también construyen ambos metadatos válidos.
- app/Http/Controllers/McpTransportController.php:59: las ramas de detección moderna y tipos se implementan; no se afirma un fallo actual de producto.

Corrección requerida: Probar ausencia, parcialidad en ambos sentidos, tipos erróneos y espejos de cabecera; rechazo definido y ningún servicio ejecutado.

### B-01: La retirada puede omitir autorizaciones concurrentes

En MySQL REPEATABLE READ, removeMember abre la instantánea mediante adminAccount -> authorizeAdmin -> membership antes del lock de cuenta. Si una autorización y su canje confirman grant/token durante esa ventana, la enumeración ordinaria de grants conserva la instantánea antigua y los omite. Tras retirar y luego reinvitar a la misma persona, DelegatedContext encuentra una membresía nueva para la misma cuenta/usuario y acepta el bearer que nunca fue revocado.

- app/Livewire/Team.php:78 llama al flujo productivo.
- app/Services/TeamService.php:40-44 abre lectura consistente antes del lock; :147-170 retira y enumera sin lock.
- app/Services/AccountContext.php:54 y :81 materializan la primera instantánea.
- app/Services/Mcp/OAuth/AuthorizationServer.php:158-179 y :211-244 emiten bajo el mutex de cuenta.
- app/Services/TeamService.php:118-126 permite nueva membresía; DelegatedContext.php:48-61 no liga grant a la fila original de membresía.
- McpRevocationConcurrencyTest.php:20-63 no intercala emisión de grant entre la primera lectura de retirada y su mutex.
- MySQL oficial: https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html

Corrección requerida: Reproducir la intercalación real con MySQL REPEATABLE READ y barreras; impedir reactivar tokens tras retirada/reinvitación, conservando el orden de bloqueos.

### B-02: El callback admite nombres OAuth reservados con valor vacío

Un callback registrado como http://127.0.0.1:18743/callback?code= pasa validate porque Input::parameters elimina valores vacíos. Al aprobar se añade otro code, produciendo una respuesta con parámetros code duplicados; clientes que rechazan duplicados o toman el primer valor no pueden completar el canje. state= o iss= presentan el mismo problema de comprobación.

- Input.php:24 elimina parámetros con valor vacío antes de comprobar los nombres reservados en RedirectPolicy.php:15-16.
- AuthorizationServer.php:43-52 usa la política en preregistro/DCR; :71-73 revalida con la misma política.
- AuthorizationServer.php:186-193 conserva el query original y concatena los parámetros OAuth.
- OAuthController.php:106-114 y McpRegisterClient.php:18 son llamantes productivos, sin filtro superior de ese query.
- tests/e2e/mcp-google.spec.mjs:64-72 consume state/iss/code con URLSearchParams.get; sus redirects no tienen query reservado vacío.

Corrección requerida: Rechazar claves reservadas vacías y codificadas sin cambiar que los valores vacíos del protocolo OAuth se tratan como omitidos.

### B-03: El nombre Unicode se limita por bytes

Con DCR habilitado, un registro válido cuyo client_name contiene 100 caracteres á cabe en el maxLength 150 y en varchar(150), pero ocupa 200 bytes UTF-8 y Input::string lo rechaza con invalid_request/400. El preregistro CLI usa el mismo camino.

- AuthorizationServer.php:37 llama a Input::string con máximo 150 para client_name.
- Input.php:78 mide strlen en lugar de caracteres.
- docs/api/mcp-oauth.openapi.json:1823 declara client_name string con maxLength 150.
- database/migrations/2026_10_25_000003_create_mcp_authorization.php:13 declara varchar(150).
- OAuthController.php:106-114 -> Input::json permite ese UTF-8 y el cuerpo está por debajo de 16KiB; no existe una restricción ASCII superior.
- Las pruebas de registro de McpOAuthTest usan nombres ASCII.

Corrección requerida: Admitir hasta 150 caracteres Unicode en client_name y rechazar 151; preservar los límites de bytes de los demás campos y del cuerpo.

### D-01: La limpieza programada drena menos ventanas de las que se crean

Una conexion hace 1 llamada MCP valida por minuto durante 8 horas diarias y reautoriza al expirar. Cada minuto crea 4 ventanas: peer, token, grant y cuenta. Produce al menos 1920 filas/dia; el schedule ejecuta una sola pasada diaria con limite por defecto 100. Despues del plazo de 48h, el historico caducado crece al menos 1820 filas/dia, unas 54600/mes o 664300/anio. Diez conexiones de cuentas y peers distintos generan 19200/dia frente a las mismas 100 eliminaciones globales.

- routes/console.php:11 programa mcp:prune daily sin opciones
- app/Console/Commands/McpPrune.php:18 define limite por defecto 100; :68-69 selecciona y borra solo ese lote de ventanas, sin continuacion
- app/Services/Mcp/Admission.php:24-31 construye clave por proposito/sujeto/fin de minuto e inserta la ventana
- app/Http/Middleware/McpRawBody.php crea la dimension transport-ip antes de autenticar
- app/Http/Controllers/McpTransportController.php:38-42 crea transport-token, transport-grant y transport-account
- docs/aplicacion/mcp-contrato.md:57 fija retencion de ventanas de 48h

Corrección requerida: Mantener pasadas finitas y demostrar que la frecuencia y el presupuesto drenan varios lotes de ventanas caducadas; conservar las retenciones y referencias.

### D-02: La retirada recorre autorizaciones históricas ya revocadas

Una persona reautoriza 1 conexion por hora durante 8 horas al dia a lo largo de 75 dias: 600 grants, retenidos por la politica de tombstones de 90 dias. Aunque la poda ya haya revocado todos los expirados, quitar la membresia selecciona los 600 y vuelve a consultar cada grant y sus tokens bajo lock exclusivo de cuenta. Son al menos 1200 SELECT individuales adicionales, mas locks por cliente, hasta 600 DELETE de sesiones y actualizaciones de tokens ya revocados.

- app/Services/TeamService.php:147-148 mantiene una transaccion y obtiene adminAccount
- app/Services/TeamService.php:42 adminAccount adquiere lockForUpdate de la cuenta
- app/Services/TeamService.php:156 carga todos los grants sin excluir revoked_at
- app/Services/TeamService.php:159-165 relee el grant y sus tokens por cada elemento
- app/Services/TeamService.php:161-169 revokedNow solo evita actualizar/auditar el grant; no evita consultas, actualizaciones de tokens ni borrado de sesiones
- app/Services/Mcp/OAuth/AuthorizationServer.php:177 emite grants de una hora; no hay refresh
- app/Console/Commands/McpPrune.php:70 retiene grants revocados durante 90 dias

Corrección requerida: Evitar trabajo proporcional a tombstones ya revocados; probar un histórico de 600 grants y la autoridad viva, con presupuesto de consultas y auditoría correcta.

## Disposición y siguiente puerta

Root contrastó los seis hallazgos con las fuentes fijadas y los acepta para corrección. Para B-01, el comportamiento de las lecturas se apoya en el [manual oficial de MySQL 8.4](https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html); su reproducción con barreras SQL sigue pendiente. Las cifras de D expresan filas y consultas derivadas del código, no tiempos medidos ni SLA.

Las correcciones mantienen contratos, permisos, retenciones, orden de bloqueos y referencias de trabajo aceptado. Se medirá cada bloque de implementación con su clave de fix; no se atribuirán consumos reales sin telemetría.

Pint sobre las 73 rutas PHP del cambio pasó. El Pint global falló por 16 archivos tracked anteriores (14 idénticos en bytes y dos sólo con diferencias CRLF) y nueve archivos históricos ignorados. Se conserva el fallo; no se declara verde global ni se modifica evidencia histórica.

El control actual de privacidad no sustituye la revisión del historial público. Las cinco capturas de implementación se inspeccionaron y sólo muestran datos ficticios. El navegador de desarrollo pasó 6/6 con Google simulado; Google real, clientes reales y aceptación humana M-02 siguen pendientes.

No se incrementan contadores: MCP **3/14**, aplicación **18/20**. T-04–T-07 siguen en progreso. Después de corregir se pasará la revisión 2 de un máximo de 3 con esta tabla completa y, si queda limpia, QA independiente.

