# Revisión de fase 3 — intento 2

**Sin hallazgos pendientes en la revisión estática. QA independiente y aceptación formal pendientes.**

Base `7cce40bd09997e079013f01e96ccf6a734b0187d`. Cápsula inmutable `ac48477292f63d6055b30b9d767a2d5a662970629e1d79986871a30e787c70a1`; cuatro revisores frescos, con traspaso completo del intento 1, 13 correcciones y sus consecuencias. No es una nueva auditoría global ni una ejecución de pruebas por los revisores.

| Lente | Critical | Important | Minor |
|---|---:|---:|---:|
| A — conformidad | 0 | 0 | 0 |
| B — corrección | 0 | 0 | 0 |
| C — seguridad | 0 | 0 | 0 |
| D — rendimiento | 0 | 0 | 0 |

## Hallazgos anteriores

| Hallazgo | Grado anterior | Resolución | Evidencia |
|---|---|---|---|
| A01 | Important | Corregido, lente A | Matriz HTTP de metadatos y cabeceras, 29 negativos y controles mutantes válidos; 4/241 con fuentes explícitas. |
| B01 | Important | Corregido, lente B | Descubrimiento propio y primario fresco bajo mutex, relecturas con locks; carrera RR y reinvitación, rollback y conexión preservados. |
| B02 | Minor | Corregido, lente B | Claves reservadas del callback conservan vacíos para validación; registro HTTP y CLI, duplicados y allow/deny con query inocua. |
| B03 | Minor | Corregido, lente B | Límite 1–150 caracteres UTF-8 sólo para client_name; casos 150/151, acentos, emoji, tipos y UTF-8 inválido. |
| D01 | Important | Corregido, lente D | Calendario de lote1000 cada minuto; veinte pasadas drenan19200 expiradas y preservan500 retenidas; capacidad nominal, no SLA. |
| D02 | Important | Corregido, lente D | 600 tombstones quedan intactos; cinco SELECT relevantes frente a1204, agrupación de autoridad viva y auditoría nueva. |

Sin rebates ni deuda aceptada. El juicio anterior se conserva íntegro; no se reabren aprobaciones sin evidencia nueva.

## Criterios de conformidad

Estos ✓ corresponden a producto y cobertura estática; no sustituyen QA ni aceptación humana.

| Criterio | Requisito | Producto | Cobertura | Referencias actuales |
|---|---|:---:|:---:|---|
| T-04-CA-01 | Permiso efectivo = scopes del grant ∩ scopes del token ∩ permisos actuales de membresía ∩ permisos de la operación. | ✓ | ✓ | `app/Services/Mcp/DelegatedContext.php:43`; `tests/Feature/McpAuthorizationTest.php:67` |
| T-04-CA-02 | La cuenta procede del grant verificado; account_id y IDs del cliente nunca seleccionan otra cuenta. | ✓ | ✓ | `app/Services/Mcp/DelegatedContext.php:48`; `tests/Feature/McpAuthorizationTest.php:38` |
| T-04-CA-03 | Cada mutación revalida grant y membresía en su transacción con orden de bloqueo definido; una revocación confirmada impide nuevas mutaciones. | ✓ | ✓ | `app/Services/Mcp/DelegatedContext.php:47; app/Services/TeamService.php:157; app/Services/TeamService.php:181`; `tests/Feature/McpRevocationConcurrencyTest.php:20; tests/Feature/McpMembershipRemovalRegressionTest.php:29` |
| T-04-CA-04 | La revocación de membresía afecta al grant personal; el ciclo de vida de API keys de cuenta de T-18 se conserva separado. | ✓ | ✓ | `app/Services/TeamService.php:154; app/Services/TeamService.php:161`; `tests/Feature/McpAuthorizationTest.php:109; tests/Feature/McpMembershipRemovalRegressionTest.php:87` |
| T-05-CA-01 | Metadatos de recurso/servidor y desafío apuntan al origen configurado y a los endpoints implementados; no se derivan de Host. | ✓ | ✓ | `app/Services/Mcp/OAuth/Metadata.php:9`; `tests/Feature/McpOAuthTest.php:51` |
| T-05-CA-02 | Código con hash, TTL 300 s y consumo atómico se liga a cliente, redirect exacto, S256, recurso y grant consentido. | ✓ | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:177`; `tests/Feature/McpOAuthConcurrencyTest.php:20` |
| T-05-CA-03 | Access token Sanctum opaco: propósito MCP, emisor/recurso, grant y TTL 3600 s; sin refresh inicial, con reautorización explícita al expirar. | ✓ | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:240`; `tests/Feature/McpOAuthTest.php:63` |
| T-05-CA-04 | Grant_type refresh_token se rechaza; reautorizar vuelve a comprobar membresía y scopes sin recuperar acceso revocado. | ✓ | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:199`; `tests/e2e/mcp-google.spec.mjs:367` |
| T-05-CA-05 | Preregistro y DCR controlado de clientes públicos siguen política de redirects y límites; errores previos a validar redirect nunca redirigen a destino no confiable. | ✓ | ✓ | `app/Services/Mcp/OAuth/AuthorizationServer.php:33; app/Services/Mcp/OAuth/Input.php:88; app/Services/Mcp/OAuth/RedirectPolicy.php:15`; `tests/Feature/McpOAuthTest.php:124; tests/Feature/McpOAuthRegistrationRegressionTest.php:42; tests/Feature/McpOAuthRegistrationRegressionTest.php:94` |
| T-06-CA-01 | Guard dedicado acepta sólo el token MCP válido ligado al recurso, grant, cliente y usuario; no usa fallback stateful de Sanctum. | ✓ | ✓ | `app/Services/Mcp/TokenResolver.php:11`; `tests/Feature/McpTransportTest.php:58` |
| T-06-CA-02 | Cookie web sin bearer, token Google, API key de cuenta, token otro recurso/propósito, expirado o revocado no ejecutan servicios. | ✓ | ✓ | `app/Http/Controllers/McpTransportController.php:36`; `tests/Feature/McpTransportTest.php:58` |
| T-06-CA-03 | Sesiones persistentes vuelven a validar cada llamada; lista y llamadas directas aplican capacidades vigentes. | ✓ | ✓ | `app/Http/Controllers/McpTransportController.php:36; app/Http/Controllers/McpTransportController.php:59`; `tests/Feature/McpTransportTest.php:344; tests/Feature/McpProtocolMetadataRegressionTest.php:31; tests/Feature/McpProtocolMetadataRegressionTest.php:91` |
| T-06-CA-04 | Límites de cuerpo, tamaño de resultado, paginación y concurrencia rechazan excesos de forma definida; no activan otro transporte sin revisión. | ✓ | ✓ | `app/Http/Middleware/McpRawBody.php:27; routes/console.php:11; app/Console/Commands/McpPrune.php:68`; `tests/Feature/McpTransportTest.php:100; tests/Feature/McpPruneCapacityRegressionTest.php:15` |
| T-07-CA-01 | Pantalla presenta cliente, cuenta y capacidades concretas; sólo ofrece membresías y permisos actuales, con POST+CSRF para aprobar/denegar. El canje devuelve el token al cliente automáticamente: no exige crear/copiar una API key en cada cliente. | ✓ | ✓ | `resources/views/mcp/consent.blade.php:4`; `tests/e2e/mcp-google.spec.mjs:289` |
| T-07-CA-02 | Cambiar de cuenta exige un grant nuevo; no se modifica el account_id de una conexión emitida. | ✓ | ✓ | `app/Models/McpGrant.php:20`; `tests/Feature/McpConsentToolsTest.php:47` |
| T-07-CA-03 | La persona sólo gestiona sus conexiones; revocar corta tokens y sesiones de esa conexión sin tocar otra cuenta. Refresh no se emite y su grant_type se rechaza. | ✓ | ✓ | `app/Services/Mcp/GrantService.php:18; app/Services/TeamService.php:157`; `tests/Feature/McpConsentToolsTest.php:90; tests/Feature/McpMembershipRemovalRegressionTest.php:87` |
| T-07-CA-04 | Auditoría de éxito y denegación identifica actor, cuenta, cliente, operación y correlación sin tokens, secretos o contenido completo innecesario. | ✓ | ✓ | `app/Services/Mcp/Audit.php:10; app/Services/TeamService.php:173`; `tests/Feature/McpConsentToolsTest.php:134; tests/Feature/McpMembershipRemovalRegressionTest.php:136` |

## Evidencia y límites

- Verificados los 1.347 hashes de cápsula y los cuatro informes originales. El traspaso completo y las decisiones independientes permanecen en el consolidado privado; este informe público contiene la proyección sin datos personales ni secretos.
- T04: focal 3 pruebas/35 aserciones y autoridad/equipos 20/213. Su TeamService y tests coinciden con lo revisado; después cambiaron cuatro archivos de OAuth/scheduler, por lo que no se presentan como suite global de las fuentes actuales.
- T05: focal 5/312 y regresión 17/588 sobre el mismo manifest de351 fuentes; T06: 4/241 sobre352 fuentes. Son conjuntos solapados con atribución propia, no se suman. La nueva matriz de T06 supera su primera ejecución: A01 era falta de prueba, sin RED de producto inventado.
- Preflight final: privacidad, contratos, scope, ledger y diff pasan;106/106 rutas en alcance, cero criterios alterados. Pint de los diez archivos PHP de fix1 pasa. Los tres contratos aceptados conservan sus pins.
- La capacidad de poda es un cálculo del calendario y una prueba de pasadas finitas. No acredita scheduler/Cronicle desplegado, rendimiento sostenido ni SLA. Los plazos de retención y las referencias protegidas se conservan.
- No se ejecutaron aplicación, SQL, pruebas, broker, Docker, navegador ni envíos durante la revisión. No se inspeccionaron procesos, credenciales o solicitudes privadas. Seguridad es la lente incremental de esta revisión, no una garantía universal de ausencia de vulnerabilidades.
- T04–T07 siguen en-progreso; MCP3/14, aplicación18/20 e infraestructura3/3. T08–T14, Google y clientes reales, ambos perfiles de despliegue y M02 humana siguen pendientes. Siguiente puerta: QA independiente con resultados originales, capturas y checklist manual.
