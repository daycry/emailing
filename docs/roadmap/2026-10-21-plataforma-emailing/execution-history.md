# Historial de ejecución — plataforma-emailing

## Archivado 2026-11-03 — Revisión de dos lentes — intento 1: Fase 1 (T-01, T-02, T-03) — correcciones necesarias (bytes sin cambios)

SHA-256 del bloque: `666aca68535d3c1d6fcf6088e042ae27c65bb797cc69861d3f1be4517fc2da23`.

## Revisión de dos lentes — intento 1: Fase 1 (T-01, T-02, T-03) — correcciones necesarias

Lentes A+B+C+D mediante subagentes genéricos con rol reviewer y contexto fresco;
la herramienta no ofrece selector de tipo de agente. El tier procede del
frontmatter, sin override. A/B/C en paralelo, D al liberar una plaza; las dos
reanudaciones por capacidad del modelo continuaron el mismo intento. Base
`master...HEAD` más copia de trabajo; 120 archivos recorridos por bloques.
Puerta de alcance: exit 0, sin avisos, exclusiones del usuario ni archivos fuera.
C activada por autenticación, permisos, secretos e IaC; D por colas, caché y
migraciones. No hay configuración de cobertura mínima ni constitución.

| Criterio de aceptación | Veredicto fusionado | Evidencia |
|---|---|---|
| T-01.1 base exclusiva y preservación | ✓ | TestingDatabaseGuard:7, tests/TestCase:11, RuntimeIntegrationTest:13/21/28; lectura lab_smoke registrada arriba |
| T-01.2 perfiles y runtime aislado | ✗ | HTTP de ambos perfiles y RabbitMQ verificados; C2 rompe el aislamiento de PHP-FPM, B-01 impide consumir la cola configurada |
| T-01.3 locks y protocolo real | ✓ | composer.lock adaptador 15.0.2, RuntimeSmoke:19 y evidencia de publish/reentrega/ack |
| T-02.1 alta atómica y configurable | ✓ | CreateNewUser:20/27, AccountAuthenticationTest:24/41/49; B-02 exige además rechazar tipos inválidos |
| T-02.2 autenticación, CSRF y contexto | ✓ | routes/web:13/31, AccountContext:10, AccountAuthenticationTest:65/83 |
| T-02.3 recuperación por correo segura y única | ✗ | Token válido/único probado; C1 permite cambiar su origen por Host |
| T-03.1 invitaciones y concurrencia | ✓ | TeamService:100/110, TeammateTest:46/72 y TeammateConcurrencyTest:18 |
| T-03.2 roles y owner | ✓ | Permissions:34, TeamService:128/141/154 y TeammateTest:93/109/122 |
| T-03.3 revocación y auditoría | ✓ | TeamService:46/128/141, TeammateTest:139/159 |

| # | Grado | Gap | Tarea | Corrección / veredicto | Evidencia |
|---|---|---|---|---|---|
| C1 | Critical | Host de la petición determina enlaces de recuperación e invitación | T-02 | corregido por implementer; pendiente revisión 2 | Evidencia roja original conservada: notificaciones con origen attacker.example, CWE-346/640. TrustedUrl + AppServiceProvider:24 + TeamInvitation:24; cuatro regresiones de notificaciones reales por HTTP con Host/esquema manipulados PASS, suite 30/230 |
| C2 | Critical | FastCGI accesible a otras aplicaciones de la red común | T-01 | corregido por implementer; pendiente revisión 2 | Evidencia original: docker.conf listen=9000 sin allowed_clients, PHP conectado a lab/app, CWE-668/306. compose:25/42 y zzz-emailing-fpm.conf:2; socket privado no root, TCP 9000 inaccesible desde lab; viejo despliegue detenido; MySQL/RabbitMQ/SMTP PASS |
| B-01 | Important | Worker consume emailing.jobs aunque el productor usa RABBITMQ_QUEUE | T-01 | corregido por implementer; pendiente revisión 2 | Se conserva causa original compose fijo frente config/queue.php. compose:33 sin --queue; RuntimeIntegrationTest:43 demuestra productor/worker reales con cola alternativa sin argumento explícito, PASS; QA configura su cola independiente |
| B-02 | Important | Email no escalar causa TypeError antes de validación en alta/login | T-02 | corregido por implementer; pendiente revisión 2 | Evidencia roja original: email array causa TypeError. ValidateAuthEmail + bootstrap:16 validan antes de Fortify/throttle; CreateNewUser valida tipo; cuatro datasets HTTP array/vacío/número/null PASS, perfiles 8082/8083 rechazan array con 422 |
| C3 | Critical | Sesiones previas conservan acceso después del reset; origen coordinador, ver adenda | T-02 | corregido por implementer; pendiente revisión 2 | HTTP rojo con tres cookiejars conservando acceso; bootstrap:17 AuthenticateSession estándar. PasswordResetSessionTest:23 rojo 200 y verde tras fix; tres cookies antiguas rechazadas y nuevo login válido en 8082/8083 |
| A1 | Minor | Documentos de integración e índice describen runtime terminado como pendiente | T-04 | pendiente: documenter actualiza tras QA; plan refleja progreso desde ledger | docs/RAG-INDEX.md:48, docs/infraestructura/integracion-aplicaciones.md:49–54, improvement-plan.md:51 |

Aprobados con evidencia: alcance semántico, pruebas significativas y ejecución
de verificaciones declaradas. Interop/OpenAPI no aplican en este diff. La
transferencia a una membresía promovida a Admin en la misma transacción respeta
el estado final del plan, bloqueo y reautenticación; no se exige rol previo.
ADR-003 conserva estado propuesta para la fase de entrega. No se promueven ADR
con Critical/Important abiertos. Jira no está habilitado; no se publica nada.

T-01/T-02 se reabren y el progreso vuelve a 1/20; no se ocultan los defectos
mediante el verde de la suite anterior. La duración de revisión se registra
aparte de implementación, con tokens_reales/horas_ia/eur null por falta de
telemetría compatible. El rechazo de permisos del test Python de índice de D
es ambiental: esa regresión pasó con permisos adecuados en el checkpoint; no
se atribuye un fallo de rendimiento ni se borra su temporal ignorado.

### Adenda del coordinador al intento 1 — C3: sesiones anteriores al reset

Origen: comprobación adicional del coordinador, posterior a los informes A/B/C/D; no se atribuye a sus autores ni supone su aprobación. Grado **Critical**, T-02: una sesión autenticada con el email de otra persona conservaba acceso después de que el destinatario recuperase la contraseña; podría heredar sus futuras membresías compartidas. La ausencia de verificación previa del email permite esa preparación en el registro interno habilitado.

Reproducción HTTP real en el perfil nativo 8082 con `tests/support/password-reset-sessions.php`: `FAIL: old registration before first navigation retained access`, `FAIL: old login before first navigation retained access`, `FAIL: old login after navigation retained access`; el login con contraseña nueva sí funciona. Tres cookiejars antiguos y un cliente de recuperación separados; fixture UUID con limpieza exclusiva, guard de base/usuario `emailing_testing`, sin migraciones ni transacción que oculte datos al servidor.

Causa: `ResetUserPassword` cambia el hash y Fortify rota remember_token, pero no se habilitó `AuthenticateSession`. El `SessionGuard::login` instalado ya guarda `password_hash_web` al autenticar, incluyendo registro/login antes de primera navegación; basta activar la comprobación estándar central. Estado de la corrección: en-progreso dentro de T-02-fix1; regresión Feature adicional con guards y estados de sesión separados roja antes del cambio. La revisión 2 debe reevaluar C3 junto con C1/C2/B-01/B-02.

**Resultado posterior de C3 (implementer)**: `PasswordResetSessionTest` produjo antes del fix `Expected response status code [201, 301, 302, 303, 307, 308] but received 200` en la sesión antigua. Se activa únicamente `authenticateSessions()` del framework en todas las rutas web; no se añade epoch, columna ni migración. La regresión pasa con **24 aserciones**; la suite final completa tiene 30 pruebas/230 aserciones. Los clientes HTTP reales de 8082 y 8083 producen `PASS` para sesiones antiguas de registro antes de navegar, login antes de navegar y login después de navegar, y para el login nuevo con contraseña nueva. La evidencia roja previa queda histórica; corrección completada dentro de T-02-fix1, pendiente de revisión 2.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 3: corrección del harness (bytes sin cambios)

SHA-256 del bloque: `bcbb368a82e7ab7fbb050cc585799cbdbac2e31ecd2e6191da00bc8b784d63ac`.

## Revisión de dos lentes — intento 3: corrección del harness

Revisión incremental del diff inmutable aplicado a E2E-01/E2E-04. Lentes A y
B con contexto fresco y sólo lectura; C/D no aplican según el selector. No se
reabren los criterios de producción aprobados en el intento 2.

| Criterio | Lente A | Lente B | Evidencia |
|---|---|---|---|
| Registro deshabilitado rechaza alta y conserva ausencia SQL | ✓ | ✓ | E2E-01:27, config/fortify.php:9 y diagnóstico con CSRF/404/conteos cero |
| Formulario visible antes de la segunda confirmación | ✓ | ✓ | E2E-04:37–40 y diagnóstico oculto/abierto con propietario intacto |
| Reautenticación, roles y auditoría mantienen sus asserts | ✓ | ✓ | E2E-04:35–43; no se fuerza relleno ni se ocultan fallos |
| Alcance y datos sintéticos | ✓ | ✓ | Sólo dos MJS; UUID@example.test, sin cambios en producción |
| Estados y pasos pendientes se declaran fielmente | ✓ | ✓ | T-04 abierta, QA1 roja conservada; transferencia válida y timeout pendientes de QA2 |

Resultado: 0 Critical, 0 Important y 0 Minor nuevos. A1 documental anterior
continúa pendiente para documenter tras QA. Constitución, interop y OpenAPI
no aplican. Comprobación inversa del patch y sintaxis sin errores; la sintaxis
de Node 22 se ejecutó por implementer, y B identifica su comprobación adicional
con el Node 23 de su sesión. Ninguna lente ejecutó navegador o migraciones.

Medición revisión 3: 2026-10-22T17:15:33Z–2026-10-22T17:20:11Z, cerrada antes de QA2.
Fuente estimado, tokens_reales/horas_ia/eur null; no se imputa reloj como IA.
QA2 abre su primer intento el 2026-10-22T17:20:12Z y conserva un máximo de
tres intentos, sin segundo diagnóstico automático si agotase ese ciclo.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 4: incremento FB-01 de fase 1 (bytes sin cambios)

SHA-256 del bloque: `d82dda0a1afec42ecb6823dca1bba64e2329c76726f81e44ec240899f0e82f1a`.

## Revisión de dos lentes — intento 4: incremento FB-01 de fase 1

Primera revisión del incremento surgido de la inspección visual de QA2; no
reinicia el contador de QA ni reabre la revisión productiva anterior. Lentes
A/B nuevas, genéricas con rol reviewer, runtime heredado; C/D false según
selector. Ambas verifican el patch completo de siete archivos, SHA-256
`71ceb21d19f12a29dd7845b1e4a72aa8abeaca2490188384e1cbe655205e42a0`.

| Criterio | A | B | Evidencia |
|---|---|---|---|
| Errores se limpian sólo después del éxito | ✓ | ✓ | Team.php:42/71/88; excepciones mantienen feedback |
| Reintento sobre la misma instancia y efectos persistidos | ✓ | ✓ | TeammateTest:123/140/154/168; RED 3 fallos, GREEN 4/48, Feature 33/262 |
| Reautenticación, propietario, roles y auditoría conservados | ✓ | ✓ | Servicio sin cambios; rechazo antes del éxito y asserts persistidos |
| E2E detecta el error residual y capturas parten del inicio | ✓ | ✓ | E2E-04:41 y helpers:18; nueva ejecución pendiente |
| PHP de QA sólo cambia formato | ✓ | ✓ | Lectura íntegra de fixture, server-off y registration-count |
| Alcance y estados fieles | ✓ | ✓ | T-03/T-04 declaran los archivos; T-04 sigue abierta |

FB-01: corrección aprobada por ambas lentes, pendiente de QA2 intento 2 de 3.
Cero Critical/Important/Minor nuevos. A ejecuta comprobación inversa del patch
y sintaxis de cinco PHP/dos MJS, exit 0; B lee fuentes y stdout sin ejecutar
suites. Ninguno usa navegador, DB o migraciones. Constitución ausente;
interop/OpenAPI no aplican; Jira deshabilitado. A1 documental permanece
pendiente. No se promueven ADR de entrega futura.

Medición de la revisión del incremento: 2026-10-22T17:49:55Z–17:53:06Z,
cerrada antes de abrir QA2-2 a las 17:53:08Z. Fuente estimado, consumo real
desconocido y campos de tokens/horas IA/coste null. Datos públicos del estado
revisado: 340 archivos, cero hallazgos; no es una comprobación del historial.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 1: Fase 2 (T-05, T-06, T-07, T-08) — correcciones necesarias (bytes sin cambios)

SHA-256 del bloque: `7447e3ba9fd9fa9a273b09dc388272ccec24100924e4d3549869950da24d7078`.

## Revisión de dos lentes — intento 1: Fase 2 (T-05, T-06, T-07, T-08) — correcciones necesarias

A+B+C+D sobre snapshot54archivos, hash46b233731c5e7d2fff50d34d4dedf20159485a6f83bab92bf58f10026172403f.
**Correcciones requeridas: 3 Important abiertos, 0 Critical.** B-01: HTML-only
rechazado por text requerido (T-07); B-02: conteos no aseguran ubicación de
tokens (T-07); D-01: conteos por lista sin índice de prefijo utilizable (T-05).
[Tabla, escenarios y artefacto inmutable](testing/fase-2/review/attempt-1.md).
C no confirma vulnerabilidades; B-02 procede de su observación fuera de lente,
confirmada después por B como corrección. A inicial verde no invalida esa
evidencia nueva. T-05 y T-07 se reabren; T-06/T-08 siguen implementadas.
T-09 permanece en progreso; QA de navegador todavía no se ha ejecutado.

Verificación/checkpoint: ventana T-09-verify1 19:57:56Z a20:09:52Z cerrada.
Revisión: 20:10:24Z a20:31:32Z cerrada (2026-10-22); cifras reales null.
Lentes por subagentes genéricos/reviewer; D al liberarse slot trasA/B/C.
El checkpointdb18d4a está confirmado en remoto, push normal sin reescritura.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 3: Fase 2 (T-09) — corregir teardown del harness (bytes sin cambios)

SHA-256 del bloque: `186896afd148cbbd0675c335ee319686f11d724d2ec85721c1b345910a7a8d34`.

## Revisión de dos lentes — intento 3: Fase 2 (T-09) — corregir teardown del harness

A+B sobre31fuentes/125.761bytes/1.369líneas, hash
`1bc528a8bf60bfc6cfb6469afc6a0ea60c0234ea462b6c74cf02e6e852af869f`.
Integridad31/31 y lectura completa; A además reconstruye el patch y comprueba
29/29 hashes del handoffQA. Scopeexit0sinfuera/avisos/exclusiones; C/Dfalse.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| QA-01 | Important | Overflow móvil | T-09 | Navwrap móvil y nowrapdesktop; focal aprobada | Rects390/768/1440 y seis enlaces completos |
| B-03 | Important | Finalizador busca temporales en raíz equivocada y diagnóstico sinUUID no atribuible | T-09 | Pendiente, QA2 no arranca | config/livewire.php7–8,filesystems.php43,finalize-extras.py27/35,diagnostic.mjs18 |

A no encuentra gaps nuevos. B confirmaB-03; las aprobaciones anteriores se
conservan y readinessE06 sigue provisional. Ver [dictamen y decisión del
orquestador](testing/fase-2/review/attempt-3.md). Tras el tercer intento el
orquestador decide continuar con reparación específica deB-03 dentro del alcance
autorizado, sin aceptar deuda ni relajar pruebas. No se atribuye la limpieza de
temporales como completa: se verificó una ruta distinta de la real. Originales
QA1 conservados; T-09 permanece en progreso.

**Publicación parcial validada:** `9109d87`, publicado y comprobado en la rama
`feature/plataforma-emailing`, contiene únicamente la corrección de navegación
móvil y sus cuatro evidencias focales. El cambio de producto coincide con el
snapshot aprobado de revisión 3. El control de datos públicos del índice examinó
421 archivos sin hallazgos; autor y committer usan la identidad pública del
proyecto. Esta publicación no cierra T-09 ni sustituye la segunda pasada completa
de QA, pendiente de resolver B-03.

La captura incremental incluye `.gitattributes`: Git normalizaba los finales de
línea de fuentes QA y alteraba los bytes cuyos hashes constan en los manifiestos.
Se preservan los bytes del árbol de pruebas de fase 2 al publicarlo. La regla no
desactiva las comprobaciones de espacios de su código; las excepciones anteriores
para patches y stdout originales siguen acotadas a esas evidencias.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 4: Fase 2 (T-09) — aislar errores del teardown (bytes sin cambios)

SHA-256 del bloque: `4b6bd742875ca9908424b8c3ae034a11e89bdb590e8ce7ba034643fbddab8579`.

## Revisión de dos lentes — intento 4: Fase 2 (T-09) — aislar errores del teardown

Primer intento acotado del incremento B-03 tras la decisión del orquestador del
intento 3. Lentes A+B frescas; C/D false. Scope exit 0, 145 cambios incluidas
evidencias, cero fuera de alcance/avisos/exclusiones de usuario. Diff completo
16 archivos/54.636 bytes/572 líneas, SHA256
`c801a6a284ed0b9cea3e2dce639daeecf7441ceb5f8e3de1200417789ce3132e`.
Ambas lentes comprueban 42/42 fuentes congeladas; A verifica 36/36 hashes de
handoff y publicación exacta de bytes para 38/38 fuentes QA.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-03 | Important anterior | Raíz equivocada y uploads sin registro | T-09 | Objetivo focal aprobado | RED/GREEN4 y cleanup tras SQL eliminado en ambos perfiles |
| B-04 | Important nuevo | Fallo de cleanup temporal impide limpiezas independientes | T-09 | Pendiente | specs/helpers.mjs:17; prueba pura calls=[temporary] |

A no encuentra gaps nuevos; B encuentra B-04, verificado por el orquestador en
la función congelada. Ver [dictamen completo](testing/fase-2/review/attempt-4.md).
Se conserva lo aprobado, incluida la navegación y las correcciones anteriores.
La siguiente revisión será el intento 2 de 3 del incremento específico. QA2 no
arranca. QA1 permanece roja 8/12 y T-09 en progreso. Medición review4:
2026-10-22T22:35:48Z–22:42:31Z, reales desconocidos/null.

La verificación focal B-03 final acredita ocho registros propios, cero archivos
propios restantes y cero fixtures. Se preservan seis archivos nativos y cuatro
Docker no atribuibles; no se afirma cero global ni se reescribe la evidencia de
QA1. Ventana harness-fix2 cerrada: 2026-10-22T22:01:14Z–22:35:26Z, reales null.
El archivo privado adicional de 320 salidas originales, verificado por hash,
protege los nombres reutilizables del runner antes de QA2.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 5: Fase 2 (T-09) — B-04 aprobado (bytes sin cambios)

SHA-256 del bloque: `4f5f618ad89e0bdbf8445847458a01e603ba3413d3ac7e4f32462542f75310d2`.

## Revisión de dos lentes — intento 5: Fase 2 (T-09) — B-04 aprobado

Segundo intento de tres del incremento específico, sin reiniciar el contador.
Lentes A+B frescas: cero Critical/Important/Minor. Scope exit 0, 159 cambios
incluidas evidencias y cero fuera de alcance/avisos/exclusiones de usuario;
C/D false. Diff completo tres archivos/10.549 bytes/148 líneas, hash
`0b197a8aa7cf2214137b65de21554d20a3309136607618898b9719177166d279`.
Ambas lentes verifican 43/43 fuentes y preimágenes; A comprueba el manifiesto
B-04 37/37. Se conserva lo aprobado en intentos anteriores.

| ID | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-04 | Important anterior | Excepción temporal bloqueaba otras limpiezas | T-09 | Aprobada por A+B | Siete escenarios sobre el módulo real; RED 5/7 fallan y GREEN 7/7 pasan, mismo test |
| Nuevos | — | Ninguno | T-09 | Sin hallazgos | Integridad y lectura completa del incremento |

La función intenta las cuatro operaciones independientes y conserva causas
originales de contextos y etapas en AggregateError. B repite RED/GREEN en
memoria con resultados idénticos. Ver [dictamen](testing/fase-2/review/attempt-5.md)
y [evidencia focal](testing/fase-2/raw/b04-fix/report.md). Sin DB, servicios ni
fixtures durante esta corrección. Ventana harness-fix3:
2026-10-22T22:43:38Z–22:48:26Z; review5:
2026-10-22T22:48:45Z–22:53:03Z; reales desconocidos/null.

La revisión permite QA2 completa; no acredita su resultado. T-09 sigue abierta,
con 8/20 tareas implementadas, QA1 roja preservada y M-01 humano pendiente.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 7: Fase 3 (T-10) — B-01 menor (bytes sin cambios)

SHA-256 del bloque: `163b6eac817a48ea8cb7f42e73ca00f3105728505b283cdd03bbfb9103e17ec7`.

## Revisión de dos lentes — intento 7: Fase 3 (T-10) — B-01 menor

Primer intento de tres del incremento. A+B+D frescas: **0 Critical, 0 Important,
1 Minor**. C false; D por migración. Scope exit 0, cero fuera de alcance, avisos
y exclusiones de usuario. Integridad 94 fuentes/25 evidencias/10 preimágenes,
sin diferencias; patch completo 21 archivos, SHA256
`026ec95c3821715aac9a5bbce5368591af7458186253b6ec86f1b966605e33e1`.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Minor | Página vacía al retirar la última supresión de página 2 | T-10 | Verificado; pendiente de corrección, sin aceptar deuda | [Lente B](testing/fase-3/review/attempt-7-lens-b.md), `app/Livewire/Suppressions.php:35` |
| A/D | — | Sin hallazgos nuevos | T-10 | Aprobaciones del componente conservadas | [Dictamen completo](testing/fase-3/review/attempt-7.md) |

El siguiente intento conservará la tabla completa y revisará lo corregido sin
reabrir aprobaciones sin nueva evidencia. T-10/E2E-08/CA-10/14 integrales siguen
abiertos; 9/20 sin cambiar, Pint global exit 1 conservado y ADR-003 propuesta.
Una interrupción por capacidad de B se reanudó sobre el mismo snapshot y no es
un nuevo intento de revisión. Roles genéricos, runtime heredado; no se atribuye
ejecución Claude. Ventana 2026-10-23T02:17:58Z–02:26:38Z, nueve minutos de
reloj; tokens/euros/horas IA reales null. Jira desactivado/no configurado.



## Archivado 2026-11-03 — Revisión de dos lentes — intento 8: Fase 3 (T-10) — B-01 aprobado (bytes sin cambios)

SHA-256 del bloque: `53f993c63f5ad41394ad7cc2e230b2aec56e0bda85e19159862ebc975e1131a7`.

## Revisión de dos lentes — intento 8: Fase 3 (T-10) — B-01 aprobado

Segundo intento de tres del mismo incremento. Lentes A+B+D frescas:
**0 Critical, 0 Important, 0 Minor pendientes**. B-01 corregido y aprobado;
ningún criterio reabierto sin evidencia nueva. Scope acumulado exit 0,
51 archivos, cero fuera de alcance/avisos/exclusiones de usuario. C false;
D por migración. Incremento 33 archivos, solo dos PHP funcionales modificados;
124 fuentes, 30 evidencias y tres preimágenes con SHA verificados sin diferencias.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Minor anterior | Página vacía tras última retirada de página 2 | T-10 | Aprobado A+B+D | `resetPage()` tras servicio autorizado; [regresión RED/GREEN](testing/fase-3/review/b01-fix.md) |
| Nuevos | — | Ninguno | T-10 | 0 Critical/Important/Minor | [Dictamen completo](testing/fase-3/review/attempt-8.md) |

Patch SHA256 `7762bfa5d4f9d67a1114e94474fa21e12ef85143a9ce85ba9f2d5fb2cc643c67`.
Root verificó integridad antes de añadir esta traza. La tabla anterior completa
y todas sus aprobaciones/pendientes se conservan. Regresión 1/6 RED→1/12 GREEN,
suite supresiones 16/165 y Pint de dos PHP exit 0. Feature 107/1147, build y
frontera corresponden al snapshot anterior; Pint global sigue exit 1 histórico.

Se satisface la dependencia técnica para desarrollar T-11, conservando T-10
abierta para el recorrido durable pendiente de T-12/T-13/T-16. No se rebaja el
alcance ni se cambian dependencias. T-10/E2E-08/CA-10/14 integrales pendientes,
9/20 sin cambiar; ADR-003 propuesta y M-01 humana pendiente. Esto no concede
QA de fase 3 ni feedback real de proveedor.

Ventana review8 2026-10-23T02:42:41Z–02:49:49Z, siete minutos de reloj;
tokens/euros/horas IA reales null. Runtime genérico heredado, sin atribuir
ejecución Claude; Jira no configurado. Siguiente paso: commit/push normal del
checkpoint revisado y desarrollo de campañas.



## Archivado 2026-11-03 — Revisión de dos lentes — intento 9: Fase 3 (T-11) — recuperar selecciones desaparecidas (bytes sin cambios)

SHA-256 del bloque: `b0a26ebcf2e92b0a9722b1e4eb21d3315211ddf91cc9295c40544858ceee46e5`.

## Revisión de dos lentes — intento 9: Fase 3 (T-11) — recuperar selecciones desaparecidas

Primer intento de tres del incremento T-11. Lentes A+B+D frescas:
**0 Critical, 1 Important (B-01), 0 Minor**. C false; D por migración.
Scope exit 0 sobre 18 archivos, sin fuera/avisos/exclusiones de usuario.
Patch SHA256 `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`.
Integridad: 107 fuentes, cinco preimágenes, 89 evidencias y patch (202 rutas
únicas), sin discrepancias. Las tablas A/B/D completas se conservan en el informe.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | Lista/contacto borrado conserva ID invisible y bloquea el borrador | T-11 | Confirmado; pendiente fix1 con retirada explícita de selección | [Revisión y tablas completas](testing/fase-3/review/attempt-9.md) |

La solución debe permitir recuperar la edición desde la UI, sin poda silenciosa
de audiencia ni relajación de cuenta/scopes/versiones/confirmación. Se mantiene
T-11 en-progreso. T-10/CA-05/E2E-08/M-01/QA de fase 3 permanecen pendientes;
9/20 y ADR-003 propuesta. Pruebas previas 25/247, 20/185 y Feature 133/1404,
Pint acotado/build verdes; Pint global anterior mantiene exit 1 separado.

A se reanudó tras una interrupción de capacidad sobre el mismo snapshot y
modelo, sin reiniciar intento. Runtime genérico heredado; sin Jira opt-in.
Ventana de revisión 2026-10-23T08:31:32Z–2026-10-23T08:48:37Z; reloj
17m; tokens/euros/horas IA reales NULL. Siguiente paso:
corrección medida T-11-fix1 y segunda revisión con traspaso completo.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 10: Fase 3 (T-11) — componente aprobado, integración pendiente (bytes sin cambios)

SHA-256 del bloque: `b4ea5b09dbfed5f88d2c0b4ea946697971a2242c69458bb000c3e7758e9af794`.

## Revisión de dos lentes — intento 10: Fase 3 (T-11) — componente aprobado, integración pendiente

Segundo intento de tres del mismo incremento. Lentes A+B+D frescas,
**0 Critical / 0 Important / 0 Minor pendientes**. C false; D por migración
acumulada sin modificar en el fix. Scope exit 0, 89/89 en alcance; sin
exclusiones, avisos ni información oculta. Las tablas completas anteriores
se traspasaron; solo se reevaluó la corrección con continuidad explícita.

| # | Grado previo | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| B-01 | Important | IDs eliminados bloqueaban el borrador | T-11 | Corregido: retirada explícita, nueva selección y revisión de la misma campaña; scopes/versión/confirmación conservados | [Informe y tablas A/B/D](testing/fase-3/review/attempt-10.md) |

Root verificó 178 fuentes, cuatro preimágenes, 41 evidencias y patch,
224 rutas únicas sin discrepancias. Patch SHA256 `6331c8711dc88d90935e5f17da780d14fd2f57d530ffbd31fd86e016556a62db`.
75 archivos incrementales; tres de código/pruebas. Se mantienen las fuentes
del dominio/renderer/migración y los resultados anteriores con su snapshot.
La errata de B («cinco preimágenes») se aclara en la fusión sin alterar su
dictamen original. Los cinco ajustes de LF final del patch tampoco cambian
los bytes originales de evidencia.

Se marcan únicamente scopes/recursos, UI y fechas/concurrencia de T-11.
Campaign final 28/301, cuatro casos MySQL concurrentes, Pint acotado/build
exit 0. Feature 133/1404 y plantillas 20/185 corresponden al snapshot previo.
Pint global anterior exit 1 se conserva, pendiente T-20. Ninguna prueba
de navegador/worker/M-01/QA integral se infiere de esta revisión.

Ventana revisión 2026-10-23T09:19:21Z–2026-10-23T09:48:46Z; reloj
29m; tokens/euros/horas IA reales NULL. Revisores
genéricos con runtime heredado; tier frontmatter, sin Jira opt-in.
T-10/T-11 continúan abiertas; 9/20, ADR-003 propuesta, envío externo
deshabilitado. Siguiente paso: commit/push del checkpoint y T-12.


### Preparación del checkpoint T-11 — bytes de evidencia y formato pendiente

La comprobación exacta del índice detectó que Git normalizaba los CRLF de
`raw/t11-b01-method-verification.json`. Se añade a `.gitattributes` la regla
`docs/roadmap/**/testing/**/raw/*.json -text`, como las reglas existentes
de streams/patches, para conservar los bytes originales de evidencia.
El cambio se declara en Archivos de T-11; no cambia runtime ni resultados.

El control adicional `git diff --cached --check` detectó espacios al final
de 29 líneas de la vista de campañas, incluidas 24 líneas vacías y cinco
líneas con HTML/texto. El control queda rojo y se incorpora al formato
global pendiente T-20; no se altera la vista congelada ni se afirma una
verificación global de formato verde. Los snapshots, manifests y streams
originales permanecen intactos. No es un defecto funcional de revisión ni
un cierre de QA; debe corregirse y verificarse antes del cierre global.



## Archivado 2026-11-03 — Revisión de dos lentes — intento 11: Fase 3 (T-12) — corregir selección global (bytes sin cambios)

SHA-256 del bloque: `7b06d4f887839baf9397e5fd1ba4c7776be7d759cf18a7d59f19046594f73a89`.

## Revisión de dos lentes — intento 11: Fase 3 (T-12) — corregir selección global

Primer intento de tres del incremento. A+B+D frescas: **0 Critical,
1 Important (D-01), 0 Minor**. C false; D por migración. Scope exit 0,
18/18 en alcance, sin exclusiones/avisos/info. Patch
`2406d504e18ce1697e24a9780ea67937b1f78812c63d9bb99376c0e72dc05d5a`. Root contrastó 218 fuentes + 570 evidencias +
dos preimágenes + patch: 791 rutas únicas, cero discrepancias.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | Claim global requiere resolver orden entre estados sin índice compatible por evento | T-12 | Pendiente de contraste SQL y fix1 | [Informe y tablas completas](testing/fase-3/review/attempt-11.md) |

Estimación D: 995.050 candidatos para 100 claims de 10.000 pendientes y
50.005.000 para drenarlos; no es EXPLAIN ni latencia medida. Se contrastará
en emailing_testing con fixtures propios antes de corregir/rebatir. Bulk
47 SQL/3,2206176 s sí aprobado como componente; no se reabre el histórico214s.
Focal17/173 y full158/1.663 pertenecen al snapshot revisado y no validan fix1.

La fusión corrige una errata de cobertura A sin modificar su dictamen:
el full154 tiene129 fuentes presentes en sources/ y25 resources leíbles en
ZIP; no138/16. Metadata y los tres informes completos se preservan. Evidencia
pública seleccionada59artefactos sin secretos; original sensible y ZIPs privados.

Revisión 2026-10-23T11:49:08Z–2026-10-23T12:03:29Z; reloj 14m,
tokens/euros/horas IA reales NULL. Sin Jira opt-in. GOT-003 y ADR-003 quedan
propuestas; T-10/T-11/T-12 siguen abiertas, total9/20, sin QA/E2E/M-01 concedidos.
Siguiente: implementación medida T-12-fix1 y segunda revisión, con traspaso de
las tablas completas y sin reabrir aprobados salvo evidencia nueva.



## Archivado 2026-11-03 — Revisión de dos lentes — intento 12: Fase 3 (T-12) — D-01 corregido, componente aprobado (bytes sin cambios)

SHA-256 del bloque: `18c0822758047954cdb18f4026941bfc3d740dede9fe5ed41e714724a1027842`.

## Revisión de dos lentes — intento 12: Fase 3 (T-12) — D-01 corregido, componente aprobado

Segundo intento de tres del mismo T-12, sin reiniciar contador. A+B+D frescas:
**0 Critical / 0 Important / 0 Minor actuales**. C false, D por migración;
scope exit 0, 83/83 en alcance, sin exclusiones, avisos ni info.

| # | Grado original | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | Selección global con scan+sort repetido | T-12 | Corregido; aprobado por A+B+D | [Fusión y tres tablas completas](testing/fase-3/review/attempt-12.md) |

El predicado y orden eligible_at,id usan un índice compatible; la fecha deriva
del estado/available_at/lease. Orden de elegibilidad explícito, sin promesa FIFO
global. 100 claims sobre 10.000 pasan de 1.000.500 a 300 lecturas Handler;
historial publicado/futuro requiere una lectura vacía. Plan real range sin Sort.
Regresión significativa RED→GREEN con método LF idéntico, fechas/leases y
SKIP LOCKED real. No se confunden contadores con consultas o capacidad SMTP.

Fuentes finales verificadas: focal20/293 y full161/1.783, exit 0; regresión3/120,
Pint acotado0. Full154/0runtime, comparación129directas+25resources ZIP. No se
repite la errata138/16 anterior. Materializador47SQL/6,9351635s para10.000 es
la última muestra;3,2206176s queda histórico. Sin comparación temporal controlada.
Paquete inmutable1.135archivos comprobados antes/después; patch
`93c5525c37f1c49b1bd54376009fd0dc4f684959dfc24b02febc165f5f596e3a`. Se conservan íntegros A/B/D, patch y metadata, más
66 evidencias públicas verificadas sin secretos. Limitación del helper audit
sin streams y captura inicial tardía de helpers expresas, sin recibos fabricados.

Revisión 2026-10-23T12:49:52Z–2026-10-23T13:04:24Z; reloj 15m.
Tokens/euros/horas IA reales NULL. GOT-003 aceptada para su método de barreras;
ADR-003 sigue propuesta, pues faltan worker/SMTP/cuotas/retries/scheduler.
Ningún pendiente anterior se reabre sin evidencia ni se convierte en aprobado.
T-10/T-11/T-12 permanecen abiertas, total9/20, E2E08/09/QA/M01 pendientes.
Siguiente: publicar checkpoint revisado y T-13 completo con Mailpit/log.



## Archivado 2026-11-03 — Revisión de dos lentes — intento 14: Fase 3 (T-14) — una corrección requerida (bytes sin cambios)

SHA-256 del bloque: `ffbcb94de7cfe6fce791370107c34cb9a2960c3132f905c6c7f86458f4c7d67a`.

## Revisión de dos lentes — intento 14: Fase 3 (T-14) — una corrección requerida

Primera pasada acotada **1/3** para el incremento T-14. Lentes frescas **A+B+D**
en paralelo, mediante fallback genérico y runtime heredado. El tier declarado
por frontmatter es opus/high; no se afirma ejecución de Claude/Opus. Scope previo:
21/21 ficheros, sin fuera de alcance, avisos ni exclusiones efectivas. Selector
automático: C=false, D=true por la migración, sin forzar configuración.

La cápsula `storage/phase3-t14-review1` conserva 2766 entradas, 782 fuentes de
contexto y 1958 evidencias; patch
`238dd30a77e00290d55df57abfaaca143d377a23191cedde3e599c46d60716e9`,
manifiesto `f430fbc70590076bca6d86b885e9d65126430cd8746f59bc22231d1c8e63b460`.
Se contrasta su integridad también al recibir los informes, sin cambios. Los
hashes no equivalen a lectura semántica de todo el contexto: cada informe
declara el diff completo leído y su evidencia/contexto pertinente.

| Área/criterio | A | B | D | Fusión raíz |
|---|---|---|---|---|
| Tres criterios literales de aceptación T-14 | ✓ | ✓ | rendimiento pertinente ✓ | Cumplen los escenarios contrastados |
| Cuenta/scopes, filtros, CSV, descarga, procedencia nueva, migración y métricas | ✓ salvo A-01 | Sin defecto confirmado | Sin defecto de rendimiento confirmado | Se conservan aprobaciones con sus límites |
| Explicación de intentos sin asociación histórica recuperable | ✗ A-01 | Sin hallazgo | No es hallazgo de rendimiento | ✗ Important, confirmado por raíz |
| Navegador, QA de fase, Cronicle, API y carga posteriores | Pendientes | Pendientes | Pendientes | No se cierran T-15–T-20 ni ADR-003 |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A-01 | Important | El vacío de intentos afirma que el transporte no comenzó, aunque una entrega pueda conservar inicios/aceptación sin asociaciones reconstruibles | T-14 | Pendiente: presentar solo la ausencia de registros de la selección y añadir una regresión con comienzos registrados y cero asociaciones | `activity.blade.php:83`, `ActivityService.php:154–162`, esquema nullable de `000010`; [informe A](testing/fase-3/review-14-A.md) |

**Veredicto fusionado: 0 Critical, 1 Important, 0 Minor; T-14 no aprobada.**
Root confirma el defecto mediante la rama incondicional del `@empty`, la consulta
por `delivery_id` y el esquema que permite el histórico sin puntero válido.
No hay nuevo reenvío automático ni fuga; no se eleva a Critical. Los seis gates
verdes anteriores quedan como evidencia de la versión revisada, sin cubrir el
caso omitido. El defecto no se descarta por aparecer solo en un contrato de apoyo:
la spec exige presentar hechos demostrables, también en el detalle histórico.

Matrices completas y límites: [A](testing/fase-3/review-14-A.md),
[B](testing/fase-3/review-14-B.md), [D](testing/fase-3/review-14-D.md).
Las copias mantienen bytes originales y pasan los patrones de datos públicos y
valores locales configurados; no se afirma detección completa de PII. Jira está
desactivado, sin escritura externa. No se promocionan entradas de conocimiento.

El marcador de revisión se cierra a `2026-10-23T23:20:52Z`: **CLOCK 18m**,
tokens/horas IA/euros reales desconocidos/null por falta de transcripciones.
Se conserva la primera implementación y esta revisión; la corrección usará una
ventana independiente `T-14-fix1` y después una nueva pasada **2/3** con todos los
veredictos anteriores. Contador formal **13/20**, T-14 sigue en-progreso.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 1: Fase 3 (T-15) — recorrido histórico por corregir (bytes sin cambios)

SHA-256 del bloque: `b51f2ca052fa985a430ed50ce5f7b12ac19e38df20f6b9199367ca48861e4960`.

## Revisión de dos lentes — intento 1: Fase 3 (T-15) — recorrido histórico por corregir

Revisión global **16**, intento acotado **1/3** de este incremento. Lentes reales
**A+B+C+D**: A/B/C comenzaron en paralelo y D comenzó al liberarse una plaza; el
límite del entorno es cuatro agentes incluido root. Se usaron revisores genéricos
por no existir el tipo dedicado, con runtime heredado. `opus/high` era el tier del
frontmatter del plugin, no una afirmación sobre el motor ejecutado.

La puerta de alcance dio salida 0: 25 archivos, cero fuera de alcance, sin avisos,
información oculta ni exclusiones de usuario. Ambas lentes condicionales estaban
activadas. Cada revisor verificó los 6.306 hashes de la cápsula y distinguió esa
integridad de la lectura semántica: diff nuevo completo, 24 archivos completos y
los tramos nuevos/criterios pertinentes del ledger; no se releyó íntegramente su
historial anterior ya publicado. D declaró compresión de algunas salidas. Root
contrastó directamente el selector y el plan crítico antes de confirmar D-01.

| Lente | Critical | Important | Minor | Resultado |
|---|---:|---:|---:|---|
| A — requisitos | 0 | 0 | 0 | Sin gaps del incremento leído |
| B — corrección | 0 | 0 | 0 | Sin defectos identificados |
| C — seguridad introducida | 0 | 0 | 0 | Sin hallazgos identificados |
| D — rendimiento | 0 | 1 | 0 | D-01 pendiente |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| D-01 | Important | La selección de `SchedulerActions.php:55–66` vuelve a recorrer destinatarios de revisiones completas que conservan estado autorizado/programado y fecha vencida. | T-15 | Confirmado; pendiente de regresión poblada y corrección que evite el recorrido histórico repetido sin cambiar la semántica de envío. | [Lente D](testing/fase-3/review-16-D.md); selector actual; plan archivado con subconsulta dependiente/no cacheable y probes indexados por destinatario. |

El escenario de D es una campaña diaria de 200 destinatarios: 73.000 al año y
aproximadamente 105,1 millones de comprobaciones diarias si se dispara cada minuto.
Es una deducción del algoritmo, **no** una medición de latencia/CPU ni 73.000
consultas SQL separadas. El EXPLAIN de una campaña no prueba el tiempo de ese
histórico. La corrección empezará obteniendo contadores/regresión poblada sobre
la foto anterior; no se presentará el full verde como refutación de ese coste.

Informes íntegros: [A](testing/fase-3/review-16-A.md),
[B](testing/fase-3/review-16-B.md), [C](testing/fase-3/review-16-C.md) y
[D](testing/fase-3/review-16-D.md). Root volvió a verificar los 6.306 archivos de
la cápsula antes de copiar los informes y su control de datos públicos pasó.
La foto anterior y sus pruebas permanecen con sus identidades reales; cualquier
corrección recibirá nuevo manifiesto y verificación pertinente. GOT-005 continúa
propuesta. ADR-003, QA integral y M-01 siguen pendientes. No hay configuración
Jira activa ni issues vinculados que publicar. T-15 sigue **en-progreso** y el
roadmap conserva **14/20** tareas cerradas.

#### Inicio de T-15-fix1 — selección sin recorrido histórico repetido

La ventana de revisión se cerró entre 03:59:50 y 04:22:17 UTC: 22 min 27 s de
reloj, con consumo/coste/horas efectivas desconocidos. La implementación de la
corrección se mide aparte como `2026-10-21-plataforma-emailing/T-15-fix1`.

Se añade `database/migrations/**` al alcance de T-15 porque la corrección de
D-01 puede requerir un checkpoint durable e indexado de materialización. Esto no
es un estado de SMTP ni una afirmación de entrega. La solución debe conservar
revisión vigente, atomicidad, replay, concurrencia y datos anteriores mediante
migración incremental; su regresión demostrará el coste poblado antes/después.
El brief privado exige evidencia de todos esos invariantes y una nueva revisión
con la tabla completa de este intento. Root conserva ledger, medidores y Git;
el implementador tendrá propiedad exclusiva de fuentes/runtime/base de pruebas
hasta su siguiente entrega terminal y liberación expresa.

#### T-15-fix1 — regresión poblada y validación todavía abierta

La misma prueba privada se ejecutó antes y después de la corrección, con el mismo
SHA de código de prueba. En MySQL preparó 0, 10, 50 y 365 campañas completas,
con 200 destinatarios por campaña, y midió tres disparos vacíos por etapa en la
misma sesión. El rojo terminó con código 1 y 399 aserciones: con 73.000 parejas
de entrega y outbox, cada disparo registró 73.366 `Handler_read_key` y 73.000
`Handler_read_next`. El verde terminó con código 0 y 399 aserciones: conservó
esas 73.000 parejas y registró 2 lecturas de clave y 0 lecturas siguientes por
disparo. `EXPLAIN ANALYZE` observó 73.000 iteraciones en el recorrido anterior y
una iteración sin filas en la selección mediante el checkpoint indexado.

Root verificó los recibos terminales, los archivos de fuentes, la identidad de
la prueba privada y las cuatro etapas de ambos resultados. Son contadores de
operaciones de sesión, no consultas SQL independientes ni una promesa de
latencia o capacidad. El verde pertenece a su instantánea `42e71e8f`; no se
reetiqueta como evidencia de una instantánea posterior. La comparación con
`1aaf105c` conserva 216 de 217 fuentes: sólo cambió el archivo público
`SchedulerTriggerTest.php`, que requiere sus propias pruebas nuevas.

Una prueba de migración falló inicialmente al comparar identidades de objetos
en lugar de sus valores y dejó una fixture incompleta. La revisión automática
rechazó el primer intento de recuperación porque faltaba atribuir las filas.
Una observación posterior vinculó las filas sintéticas al intervalo y fuentes
de esa prueba, sin filas ajenas; con esa evidencia se autorizó la recuperación.
Los recibos archivados prueban restauración del esquema registrado, retirada
de esas filas y base de pruebas vacía. La prueba corregida añadió restauración
en `finally` y pasó con 26 aserciones. Este asentamiento es histórico; no
describe el estado de una ejecución posterior todavía activa.

La tanda posterior de `SchedulerTriggerTest` sobre `1aaf105c` terminó con
16 pruebas correctas y 2 fallidas, según la entrega del implementador: dos
observadores aún buscaban la consulta retirada. No se consideran verificadas
las aserciones posteriores que esos casos no alcanzaron. Su adaptación y las
comprobaciones completas siguen abiertas. T-15 continúa en progreso, con
14 de 20 tareas cerradas y sin aprobación, commit ni push de esta tarea.

La adaptación posterior de los observadores pasó sus dos casos focales con
16 aserciones. Root comparó ambas instantáneas: sólo cambió el archivo de
prueba, con cinco líneas modificadas en los observadores y ninguna aserción
modificada. La tanda literal posterior
`php artisan test --filter=SchedulerTriggerTest` pasó sus 18 casos y 247 aserciones sobre
`c074241d`, con recibo terminal de código 0 y fuentes estables. Esto resuelve
aquellos dos fallos; siguen pendientes las restantes comprobaciones finales,
el daemon actualizado y la segunda revisión independiente.

Sobre la misma instantánea `c074241d`, las comprobaciones finales de componentes
pasaron: actividad 20/237, outbox 20/299, componentes PHP 11/80 y cliente Node
7 casos tanto nativo como en Linux. Pint verificó los 17 archivos PHP
seleccionados. Root correlacionó los recibos estables, las salidas y los dos
procesos del cliente Linux, incluida la ausencia del contenedor en su sonda
propia. Esto no es un censo posterior de todos los recursos.

El nuevo daemon real de esa instantánea pasó en ambos perfiles: 15 trabajos
(12 positivos y 3 errores esperados), con 31 recibos de procesos correlacionados.
Cada perfil acreditó los hashes de sus 16 archivos de migración y las 16
migraciones aplicadas. El checkpoint coincide con la revisión vigente y
`materialization_pending` queda a cero, conservando el estado `authorized`.
El perfil nativo publicó 24 eventos y Docker 23; ambos registraron cero
`MailMessage`, sin afirmar entrega SMTP ni recepción en bandeja. La pérdida
de respuesta posterior al commit conservó una nueva materialización durable
y su repetición creó cero entregas adicionales. Los asentamientos de cada
perfil son históricos; el censo final y la revisión siguen pendientes.

La suite general literal se inició después de terminar ese daemon y sigue
abierta. Su progreso parcial no se considera un resultado completo ni una
aceptación de T-15.

La ejecución general terminó con código 0: **232 pruebas y 2.793 aserciones**
en 1.908,09 segundos de ejecución del runner. El recibo del productor registra
1.914,047043 segundos, 217 fuentes `c074241d` sin cambios y stderr vacío.
Root verificó el archivo de fuentes y las salidas, las 22 clases correctas
presentes también en la suite anterior y el incremento de cinco casos y
92 aserciones. Los archivos observados por el productor incluyen preparación
concurrente de root; su presencia en el inventario no atribuye causalmente
todos esos archivos a las pruebas de aplicación.

El censo final pasó y root correlacionó sus 53 recibos de procesos con sus
peticiones, PIDs y hashes de salidas: 31 ejecuciones de productor, 1.352
archivos atribuidos, 161 números de PID conocidos y cero procesos propios
presentes. Los tres PIDs protegidos se excluyeron antes de recoger datos.
Los 51 contenedores atribuidos están ausentes; las 100 sondas de cola/exchange
de los 50 nombres propios devolvieron 404. La identidad SQL exacta de pruebas
devuelve sólo `migrations: 0`. La comprobación posterior de Cronicle no deja
los dos eventos propios ni trabajos activos y conserva los tres hashes de
trabajos anteriores. No consultó ni purgó el historial de trabajos completados;
no se presenta como evidencia de retención de ese historial.

La entrega terminal de la corrección liberó explícitamente fuentes, runtime,
base de pruebas e integración Cronicle. Root comprobó el recibo final de código
0 y la coincidencia actual de las 217 fuentes; también verificó las 70 fuentes
del índice privado. Se cerró el medidor `T-15-fix1` el 2026-10-24 a las 06:06:44
UTC, iniciado a las 04:24:36 UTC: 1 h 42 min 8 s de reloj. Tokens, coste y horas
efectivas de IA siguen desconocidos por falta de telemetría. La revisión 17,
intento 2 de 3 de T-15, es una ventana separada; la tarea permanece abierta.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 18: Fase 3 (T-16) — sin gaps pendientes (bytes sin cambios)

SHA-256 del bloque: `d8d893a3f1b1004a212a2b591ee29f9388e799612f24d9f2c3fe7186dd113b34`.

## Revisión de dos lentes — intento 18: Fase 3 (T-16) — sin gaps pendientes

Fecha: 24-10-2026. T-16, intento **1 de 3**. Se ejecutaron **A+B+C+D** sobre
el incremento completo de 42 archivos; las cuatro lentes confirmaron su lectura
íntegra. Alcance exit 0 sin exclusiones, avisos ni archivos fuera del plan.
C se activó por `.dockerignore`; D, por SchedulerTriggerTest y WorkerRecoveryTest.
Se usaron revisores genéricos de contexto fresco, por ausencia del Agent por
nombre; runtime heredado, sin atribuirle el modelo del frontmatter del plugin.

| # | Grado | Gap | Tarea | Corrección | Evidencia |
|---|---|---|---|---|---|
| A | — | Sin gaps nuevos de requisitos | T-16 | No necesaria | [Conformidad y criterios](testing/fase-3/review/attempt-18.md) |
| B | — | Sin defectos de corrección confirmados | T-16 | No necesaria | [Lectura de los 42 archivos](testing/fase-3/review/attempt-18.md) |
| C | — | Sin vulnerabilidades introducidas identificadas | T-16 | No necesaria | [Seguridad del incremento](testing/fase-3/review/attempt-18.md) |
| D | — | Sin degradación de rendimiento demostrada | T-16 | No necesaria | [Límites de fixtures y producto](testing/fase-3/review/attempt-18.md) |

La revisión contrastó el grupo literal final **137/1972**, sus recibos/salidas
y las 232 fuentes archivadas; no reejecutó aplicación ni integración. Cinco
documentos se contrastaron por separado. El sello de la captura es
`e7b63c3cdaff4e0e7235da266f0661fccb328203d7bc698ad38bfc08cf5fdcc1`.
GOT-006 se promueve a aceptada por este intento; ADR-006 permanece propuesta
de la ampliación MCP, sin implementación o compatibilidad atribuida.

T-16 continúa **en-progreso**: revisión terminada, **E2E-08/E2E-09/M-01
pendientes** y ningún cierre de QA concedido por los revisores. Los perfiles y
diagnósticos anteriores conservan su identidad; no se afirma capacidad medida,
entrega externa ni exactly-once SMTP. Jira no está activado: no hay publicación
ni worklog externo. El siguiente paso es QA controlado con navegador y daemon
Cronicle real, conservando la revisión humana sobre pantallas concretas.

Durante la preparación de QA se amplió únicamente el patrón de `.gitignore`
para que `testing/**/raw/artifacts/` cubra también los informes anidados. Estas
trazas pueden conservar el POST de login sintético y no deben publicarse. Cuatro
comprobaciones `git check-ignore` verificaron que se excluyen los artefactos de
raíz y anidados, pero no `results.json` ni las capturas. Esta corrección de una
línea es posterior a la captura de revisión; no se atribuye a las cuatro lentes
ni cambia los 232 inputs ejecutados del producto y sus pruebas.


## Archivado 2026-11-03 — Revisión de dos lentes — intento 22: Fase 4 (T-18) — tres correcciones necesarias (bytes sin cambios)

SHA-256 del bloque: `e09d7a32af523e7310684091296f04c39ce97fd57973a4e18ce0c64107ac4f5d`.

## Revisión de dos lentes — intento 22: Fase 4 (T-18) — tres correcciones necesarias

Revisión global22, primer intento de tres para T-18. Lentes **A+B+C+D** con
contexto fresco y fallback genérico heredado: Agent(reviewer) no disponible;
el frontmatter no acredita que se haya ejecutado un modelo específico.
Selector determinista activó C y D. Alcance: exit0, 48 entradas de estado
(47 cambios reales y README de contenido normalizado idéntico a HEAD), sin
exclusiones, fuera de alcance ni avisos. Ledger previo: cero incoherencias,
cuatro avisos históricos conservados. Base `dab4b84c7750f79d859762f991b79587f16a3d91`.

Cápsula inmutable `storage/phase4-t18-review22`, SHA-256 del manifiesto
`335178271115c5c70a6055c615ba9b731ca1e7c71118c5a22d0ae62bd7000546`: 345 hashes y 276 fuentes de contexto verificadas por raíz al cerrar.
Cada lente verificó su prompt y todos los hashes; leyó el diff completo y
tests afectados. No ejecutaron aplicación, SQL, broker, Docker ni navegador.
Las 164 pruebas/3071 aserciones de delivery24 siguen siendo evidencia del
incremento anterior a estas correcciones; no prueban que los gaps estén resueltos.

| Tarea/criterio | Veredicto | Evidencia |
| --- | --- | --- |
| T-18: repetición concurrente crea un trabajo; credencial inválida/revocada/sin scope rechazada | ✓ | `app/Services/SendingApiService.php:23`, `app/Services/AccountKeyService.php:36`; carreras en `tests/Concerns/ExercisesSendingApiProcesses.php:201`; bearer en `tests/Concerns/ExercisesSendingApi.php:275`. |
| T-18: Read-only/Restricted no elevan permisos; cuenta derivada de credencial | ✓ | `app/Services/AccountKeyService.php:106`, `:163`; negativos en `tests/Concerns/ExercisesSendingApi.php:176`, `tests/Feature/SendingApiTest.php:157`. |
| T-18: endpoints, middleware, políticas y UI | ✓ | `routes/api.php:7`, `app/Http/Middleware/AccountBearer.php:15`, `app/Services/ApiRecipientPolicy.php:14`, `resources/views/api-keys.blade.php:1`. |
| T-18: contrato y documentación con ejemplos | ✗ | `docs/api/implementacion.md:1`; rechazo de propiedad desconocida NUL incumplido, véase AB-01. |
| CA-01: autenticación y CSRF | ✓ | `app/Services/AccountKeyService.php:24`, `resources/views/api-keys.blade.php:19`, test CSRF real en `tests/Concerns/ExercisesSendingApi.php:176`. |
| CA-12: secretos, errores y autorización de datos | ✓ | `app/Services/AccountKeyService.php:100`, `app/Support/ApiError.php:15`, `tests/Feature/SendingApiTest.php:138`. |
| CA-13: cuenta y scopes | ✓ | `app/Services/SendingApiService.php:82`, negativos status/revocación/remitente/cursor/procedencia SQL en `tests/Concerns/ExercisesSendingApi.php:124`. |
| CA-19: idempotencia/scopes/revocación | ✓ | `app/Services/SendingApiService.php:23`; carreras y revocación en `tests/Concerns/ExercisesSendingApiProcesses.php:201`, `:235`. |
| CA-20: concesión/revocación sin borrar trabajo | ✓ | `app/Services/AccountKeyService.php:106`, `:133`; `tests/Feature/SendingApiTest.php:157`, `tests/Concerns/ExercisesSendingApiProcesses.php:235`. |
| Alcance del plan | ✓ | `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md:833`; scope exit0 sin exclusiones/fuera de alcance. |
| Tests/evidencia | ✓ | Ledger:920 y delivery24 receipt:10; focal23/717, delivery164/3071, exit0, 260 fuentes estables. Aserciones reales verificadas; el verde no resuelve AB-01. |
| Constitución | N/A | No existe docs/CONSTITUTION.md. |
| E2/E3 generados/interoperabilidad | N/A | Sin cambios commands/agents/hooks. |
| Porcentaje de cobertura | N/A | Sin dev.json/gate configurado; no se inventa porcentaje. |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
| --- | --- | --- | --- | --- | --- |
| AB-01 | Important | Una clave JSON válida iniciada por NUL produce Error al asignarse a stdClass y se convierte en503 en vez del422 contractual | T-18 | Pendiente; deduplicado entre A y B, sin rebate ni deuda aceptada | `app/Support/StrictApiJson.php:56`, `app/Http/Controllers/SendingApiController.php:24`, OpenAPI:1104; reproducción PHP aislada exit0 confirma Error, sin bootstrap/DB |
| D-01 | Important | Status carga cuerpos completos para usar solo email | T-18 | Pendiente; proyectar solo datos necesarios y acreditar el coste | `app/Services/SendingApiService.php:88`, :95; con100 destinatarios y dos cuerpos de200000 caracteres:40MB ASCII o160MB UTF8 de4 bytes por consulta bajo mutex; magnitud estática, no OOM/latencia medidos |
| D-02 | Important | Elegibilidad busca contactos históricos por email sin índice, dentro del bucle de destinatarios | T-18 | Pendiente; verificar plan SQL y eliminar el recorrido multiplicativo conservando consentimiento/historia | `app/Services/ApiRecipientPolicy.php:17`, `SendingApiService.php:46`; índices existentes solo(account_id,id)/(account_id,active_email);100 destinatarios ×10000 contactos~1000000 filas por escaneos, estimación estática, no EXPLAIN ejecutado |

**Recepción:** raíz verificó código, límites100/200000 y mutex, así como los
índices declarados en todas las migraciones. El probe PHP aislado confirmó
AB-01; el intento inline anterior falló por quoting de shell y no se cuenta
como fallo de aplicación. Los tres gaps sobreviven a recepción. C: cero
hallazgos; A/B: un Important compartido; D: dos Important. Total: **0 Critical,
3 Important únicos, 0 Minor**. No hay deuda aceptada ni gaps rebatidos.

T-18 sigue en-progreso; GOT-008/009/010/011 siguen propuestas. No se aprueba
E2E-10, QA ni el objetivo global. Principal17/20 y Google/MCP0/14 conservan
todo su alcance. Jira no está activado: no se publica nada externo.

Worklog `[revisión]`: medidor review22 cerrado antes del arreglo; sin
transcripciones ni telemetría verificable, tokens/coste/horas IA reales siguen
desconocidos. No se convierten minutos de reloj en horas IA. Recibos privados
de puertas, prompts, veredictos, reproducción y cierre en
`storage/phase4-t18-review-control/`; estos archivos están ignorados por Git.

Siguiente paso: implementer T-18-fix1 verifica y corrige los tres gaps con
pruebas causales. Revisión global23/intento local2/3 recibirá la tabla completa
y gaps anteriores; reevalúa SOLO lo corregido y no reabre lo aprobado o
rebatido salvo evidencia nueva. QA formal va después de revisión limpia.



## Archivado 2026-11-03 — narrativa cerrada: Corrección posterior a QA2 — diagnóstico y T-06 reabierta

SHA-256 del bloque LF: `67489dbe62d409b60a55022beb84f891826b6cb792cd43ba99e9dcd2117efcd0`.

## Corrección posterior a QA2 — diagnóstico y T-06 reabierta

La reproducción HTTP con referencias firmadas reales confirmó en ambos
perfiles que `_finishUpload` produce metadata `fil` y que la importación por
otro actor devuelve **403**, sin diferencias SQL ni de los archivos exactos.
El payload anterior dejaba `file` como string sin esa metadata y devolvía 200
por validación. En esta reproducción el snapshot activo coincidió con el
atributo DOM: la hipótesis de snapshot obsoleto no se acredita como causa.
Las tres pruebas puras iniciales expresaban un contrato alternativo propuesto;
no se presentan como regresiones de un defecto observado del helper.

Una barrera sobre la respuesta real de `create` reprodujo la pérdida de los
valores rellenados antes del reset del formulario, en ambos perfiles. Las
comprobaciones de validez y rechazo de cuerpos vacíos se conservan; se esperará
la finalización de esa llamada antes de rellenar.

Defecto de producto adicional confirmado: `ContactImports::render()` valida
`listSearch` mediante la validación global del componente y borra errores de
importación antes de mostrarlos. **RED: dos pruebas Feature de importación sin
archivo o con valor inválido fallan al buscar el aviso visible (8 aserciones,
12,99 s).** T-06 vuelve a en-progreso mientras se corrige y verifica este
comportamiento; resumen actual 7/20. La corrección mínima mantendrá la
validación del buscador sin borrar los errores de otros campos. T-09 y QA3
siguen pendientes. Ventana harness-fix4 abierta desde 2026-10-22T23:39:09Z.

Corrección mínima de T-06 verificada: la búsqueda usa `validateOnly` y conserva
los errores de archivo. Las mismas dos regresiones definitivas fallan sobre el
producto anterior; con el arreglo, `php artisan test
tests/Feature/ContactImportTest.php --no-ansi` termina **exit 0, 15 passed,
184 assertions, 47,69 s**. Root comprobó el recibo y stdout originales de
`import-errors-green-2`; el primer intento de pruebas con precondiciones
incorrectas se conserva y no sustituye este par RED2/GREEN2. B-04 mantiene sus
siete escenarios aprobados sin cambios. T-06 vuelve a completado: 8/20 tareas
implementadas; T-09 continúa en progreso a la espera de revisión y QA3.

La primera tanda focal posterior a QA2 terminó completa: **6 aprobados y 2
fallidos**, sin reintentos; no se presenta como QA3. E2E-05 (CRUD y binding) y
E2E-06 pasan completos en ambos perfiles. E2E-07 alcanza log, Mailpit, descargas
ajenas y scopes; se detiene después en la línea 37, que espera 422 al rechazar
SMTP externo y observa 200. El original se conserva. Se está comprobando el
contrato de validación de Livewire y la ausencia de mutaciones antes de cambiar
la expectativa; no se atribuye envío externo habilitado a ese estado aislado.

### Cierre de la corrección posterior a QA2

El diagnóstico de E2E-07 confirma en ambos perfiles HTTP 200 con aviso concreto
en `effects.html` y sin cambios SQL; el mapa `memo.errors` no acredita por sí
solo el rechazo de un campo que no se hidrata como propiedad. La prueba exige
el mensaje del modo no permitido, igualdad de los recursos y un fingerprint
de las filas de configuración ordenadas y limitadas a las cuentas A/B del
fixture. No publica valores crudos ni secretos cifrados.

La ronda siguiente de E2E-07 conserva **1 aprobado / 1 fallido**: nativo se
detuvo en el primer guardado con estado de carga. La reproducción nativa
instrumentada posterior pasa sin cambiar esa fuente; **la causa exacta de la
observación anterior no se demostró**. No se atribuye a lentitud del backend
ni se convierte ese resultado original en verde. Correlacionar las respuestas
de los tres guardados mediante `clickCall` refuerza el contrato de la prueba,
con los presupuestos existentes, sin presentarlo como arreglo causal probado
de aquella observación.

Sobre los mismos bytes finales, E2E-07 pasa en **nativo (50,7 s) y Docker
(33,0 s)**. Junto con los seis recorridos completos anteriores, queda cubierta
la matriz focal de ocho casos; no se afirma que la primera ejecución fuera
8/8. Los veinte casos completos, sus límites y sus reintentos permanecen
intactos para QA3. El helper y las fuentes B-03/B-04 conservan sus bytes.

Verificación declarada de T-09 ejecutada una vez sobre el producto final:
`php artisan test --testsuite=Feature` → **exit 0, 90 passed, 977 assertions,
235,91 s**. Root inspeccionó el stdout original. Pint de los dos PHP y el
control de whitespace terminan con exit 0; privacidad: 689 archivos, cero
hallazgos. El formato del test se registra aparte del par RED2/GREEN2.
Integridad final comprobada por root: **37 fuentes QA y 2 de producto, cero
diferencias** frente a sus manifests.

Limpieza final de 17 UUID propios: cero residuos SQL, siete entradas temporales
registradas sin archivos restantes y cero mensajes propios en Mailpit.
`lab_smoke` conserva cinco filas; un recuento de tablas no establece el estado
de migraciones de producto. Ver [informe focal](testing/fase-2/raw/qa2-fix/report.md),
[handoff](testing/fase-2/raw/qa2-fix/handoff.json) y
[validación](testing/fase-2/raw/qa2-fix/validation.json).

Ventana harness-fix4 cerrada: 2026-10-22T23:39:09Z–2026-10-23T00:27:44Z,
49 minutos de reloj; tokens, euros y horas IA reales desconocidos/null.
T-09 sigue en progreso y la iniciativa conserva 8/20 tareas implementadas.
Siguiente puerta: revisión fresca del incremento y QA3 completa, intento 3/3.




## Archivado 2026-11-03 — narrativa cerrada: T-12: primera verificación focal roja, evidencia conservada

SHA-256 del bloque LF: `7edcf3c3c324a1ca47776ad516f1ab0db7ed20140175db429f3d28c4d9278ac2`.

## T-12: primera verificación focal roja, evidencia conservada

`php artisan test --filter=OutboxIntegrationTest`: **11 failed / 13 assertions**,
112,82 s, exit 2. Ventana 2026-10-23T10:06:49.136993+00:00–2026-10-23T10:08:48.077250+00:00.
Root verificó manifest `9d40416af9d8d0105a44c228258a095d17ff11322bf5923b69dbae916680b219`, 24 fuentes en ZIP y ambos
streams originales contra sus hashes; stdout `595ecf7d04dd4119201f211ee7ae5c9677f2dc8ce2e6723150e0782ce3c6d9fc`.
El fallo al imprimir Unicode del wrapper ocurrió después de guardar el recibo
y no sustituye el exit 2 real de PHPUnit. No se acredita ninguna ventana del
broker ni se presenta esta suite focal como un intento QA/adversarial.

Errores constatados: cast Eloquent de Expression al guardar fechas calculadas
y rollback de índice recipient_provenance aún usado por una FK. El implementer
confirmó la terminación de su handle antes de editar; ajusta fechas mediante
query builder/reloj DB y restaura soporte de índices al deshacer la migración.
Fuentes T-11 publicadas sin cambios. Los resultados del siguiente intento se
registrarán por separado, sin sobrescribir estos fallos ni sus fuentes.
T-12 permanece en-progreso, 9/20; envío externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: segunda verificación focal, recuperación Linux aún pendiente

SHA-256 del bloque LF: `cc1f13e7eb017b26d2d6794b0a37311f7301b9413d217521ad307c4c6f6643a1`.

## T-12: segunda verificación focal, recuperación Linux aún pendiente

Mismo comando declarado: **10 passed / 1 failed / 76 assertions**, 140,14 s,
exit 1. Ventana 2026-10-23T10:10:00.648364+00:00–2026-10-23T10:12:25.117995+00:00.
Root contrastó 24 fuentes del ZIP, manifest y ambos streams contra
el recibo. Manifest `fe549f1dc9ae7dde54735b91dfa639528df8747e498838edd471732594476a4d`; stdout
`da2fb1b943cd12857702f14b451206df33af250934d33508d750e79e018c37be`. La salida original se conserva solo en privado:
el entorno nativo produjo un stack con argumentos potencialmente sensibles.
No se copia a documentación pública ni se cambia su resultado; los próximos
recibos usarán configuración PHP aislada, sin cambiar el php.ini compartido.

El caso Linux falló por permission denied al acceder a la API Docker del
entorno restringido y no creó contenedor; no acredita crash/recuperación.
Los diez casos aprobados son evidencia focal pendiente de la revisión de sus
fuentes y trazas, no QA integral ni aceptación del worker. El implementer
confirmó handle terminal antes de corregir y repetirá con acceso Docker para
recursos propios. T-12 permanece en-progreso, 9/20 y ADR-003 propuesta.




## Archivado 2026-11-03 — narrativa cerrada: T-12: depuración causal de la señal de prueba Linux

SHA-256 del bloque LF: `6fffeddf4ab5c969d09ebdaf1456510f693addd9ff96450305eff2d2ea2d8af1`.

## T-12: depuración causal de la señal de prueba Linux

Tercera suite focal: **13 passed / 1 failed / 108 assertions**, 162,76 s,
exit 1. Root verificó las 24 fuentes en ZIP y streams originales; manifest
`8c49807e98c82c42a4e8c68106c0b11cd4f9e05e68b98ae242da38a45be588b3`. El corte SQL tras ACK y rollback/up repetido
están entre los casos focales aprobados, pendientes de revisión de sus trazas.
El caso Linux aún no acredita la ventana de crash: su limpieza con exit 137
no demuestra que el kill ocurriera después de la barrera requerida.

Se aplica `custom-agents:debug-root-cause` como depuración ordinaria, fuera
del gancho de tercer QA: no ha comenzado QA integral ni revisión de T-12.
Reproducción mínima aislada y marcas seguras descartan bloqueo de STDIN.
El contraste conserva test/expectativas/plazo de 40 s y compara vendor del
bind con vendor interno Linux: ambos fallan (29,22 y 23,00 s). Las dependencias
y fuentes cargadas se contrastan por hash; no se atribuye todo el problema
a rendimiento ni se aumenta el plazo para ocultar el fallo.

La segunda hipótesis se prueba en el instante de lectura: el archivo de
barrera ya existe pero contiene **0 bytes**, SHA vacío
`e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855`, `json_valid=false`; leerlo como JSON falla antes de
que termine la escritura. Hash del recibo de esa observación
`ee7591aaef9e5544f4dc2342581bc196a0242059a52291cff82d76fc8dc565ed`. La lentitud del montaje es condición
secundaria; no fue causa suficiente, pues el caso también falla con vendor
interno y confirmación dentro del plazo.

Fase 4 en curso: regresión determinista con escritura parcial y las mismas
expectativas, publicación mediante temporal/rename y espera acotada de JSON,
identidad y secuencia válidos. Las evidencias RED/GREEN y el caso Linux real
se registrarán por separado. Después deben pasar el comando declarado y la
suite completa; no se concede conocimiento aceptado ni cierre por un fix aún
sin verificar. T-12 sigue en-progreso, 9/20, ADR-003 propuesta.




## Archivado 2026-11-03 — narrativa cerrada: T-12: primer focal completo verde; congelación final y suite global pendientes

SHA-256 del bloque LF: `eb3de50032e922548154d4cac38d0c4484f68081a8d4ad2dec4853e05b6452c5`.

## T-12: primer focal completo verde; congelación final y suite global pendientes

Comando declarado `php artisan test --filter=OutboxIntegrationTest`:
**16 passed / 141 assertions**, 287,95 s, exit 0. Ventana
2026-10-23T10:39:07.308732+00:00–2026-10-23T10:43:59.704898+00:00.
Root contrastó 25 fuentes de ZIP/manifest y ambos streams; manifest
`7d7dcb522939795be482d829ee1708a69f6f9bfc0c8db31afabd1b681705115a`, stdout
`ff9e947aada776f625fdb8cc4dd7a657ae4a3bbf7a1e27db98b11f806ba8bea8`. No hay hallazgos de privacidad
en estos streams; el segundo run sensible conserva su tratamiento privado.

La regresión de marcador parcial tiene exactamente el mismo cuerpo en sus
snapshots RED, GREEN y este focal: SHA `42378233f0bf4555eddfa7f292c7a3aad8924cc09986c869c4682a156e13be0c`.
RED 1 failed / 2 assertions → GREEN 1 passed / 3 assertions. El archivo
completo no es idéntico: se refuerza la lectura de barreras del caso Linux
y se añade cobertura de señal inválida permanente/evento ajeno. El Linux
aislado obtuvo 1 passed / 26 assertions; el focal actual incluye ese camino.
Las fuentes/procedencia no se infieren de un cambio de expectativas.

Después de este snapshot se aplica la misma publicación atómica a las señales
ready/observation del proxy y se prueba el comando CLI finito en el caso feliz.
Se requiere nuevo focal declarado sobre esa fuente y suite completa sin editar
entre ambos. El primer verde no se presenta como validación de esos cambios
posteriores ni como QA E2E, aceptación humana o revisión independiente.
T-12 permanece en-progreso; 9/20, ADR-003 propuesta y correo externo bloqueado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: focal final congelado verde; suite completa en ejecución

SHA-256 del bloque LF: `a483b616e815420cb03cc5a66becdeebb9e521f74c004aa09aa6dbdc9a125eba`.

## T-12: focal final congelado verde; suite completa en ejecución

`php artisan test --filter=OutboxIntegrationTest`: **16 passed / 143 assertions**,
235,98 s, exit 0. Ventana 2026-10-23T10:45:25.788572+00:00–2026-10-23T10:49:26.006462+00:00.
Manifest final focal `23f86d599c48330b394d961c9222a7bc599493e2e5f4522aa36df7aac6de9ce1`; stdout
`d5d6ecbe3662029b1f711e7dfade08f86a419246996ca20af357bb96eae32725`. Root verificó fuentes/ZIP/streams
y ausencia de hallazgos de privacidad en esas salidas. Son 25 fuentes
capturadas: 15 cambios y diez dependencias conservadas, no 25 cambios.

Este snapshot incorpora señales atómicas del proxy y el camino CLI finito.
La regresión de señal parcial mantiene el mismo cuerpo que RED/GREEN:
`42378233f0bf4555eddfa7f292c7a3aad8924cc09986c869c4682a156e13be0c`. La prueba Linux acredita su
barrera de confirmación y kill propio antes del CAS; los casos de pérdida
de confirm y conexión SQL disponen de trazas causales por run. Estos detalles
siguen pendientes de la revisión independiente; no equivalen a SMTP/worker.

El implementer ha iniciado `php artisan test` completo sin filtros, de forma
secuencial, con snapshot ampliado de 189 fuentes. Root contrastó sus 189
entradas en ZIP y la igualdad de los 25 SHA focales. Manifest ampliado
`1389517c2f66f6f8c256acb7bb069760015bc7489de143a31b611d6a3cb6f588`. Se excluyen entornos privados,
storage y caché compilada de bootstrap. No se editan fuentes durante la suite;
emailing_testing permanece reservada por el implementer hasta terminal y
limpieza expresa de sus recursos. No se afirma todavía su resultado.

Control preventivo del árbol público: 932 archivos, cero hallazgos. Debe
repetirse sobre el índice exacto antes de un futuro commit; no es auditoría
de historial. La salida sensible del segundo run sigue ignorada por Git y
fuera del contexto de build Docker. Root conserva originales privados y
publicará únicamente evidencia saneada, sin valores de credenciales.

T-12 sigue en-progreso. Pendientes suite completa, handoff/liberación DB,
cierre del medidor de implementación y revisión A/B/condicionales antes de
commit/push. El diagnóstico ordinario completará su fase 4 y memoria solo
con esas verificaciones, sin reiniciar contadores QA/adversariales.
Total 9/20, ADR-003 propuesta y envío externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: suite completa final verde; rendimiento y revisión pendientes

SHA-256 del bloque LF: `969d55c27d8a13569d6c336f3d5d3583fe0c4a9de63cb6e730e93e61ee30bade`.

## T-12: suite completa final verde; rendimiento y revisión pendientes

`php artisan test`, sin filtros: **157 passed / 1.633 assertions**, 548,03 s,
exit 0. Ventana 2026-10-23T10:50:09.520470+00:00–2026-10-23T10:59:20.460117+00:00.
Root verificó las 189 fuentes del ZIP, manifest y ambos streams originales;
manifest `1389517c2f66f6f8c256acb7bb069760015bc7489de143a31b611d6a3cb6f588`, stdout
`3b38f6038f2afefd7855f10ac5cb944806e03262f87949f6c8eeb6bc431d1846`. Las 25 fuentes del focal final
16/143 coinciden con sus entradas del snapshot ampliado. No hay hallazgos
de privacidad en los streams de esta ejecución; el segundo run sensible
conserva su tratamiento privado y no se publica por este resultado.

La suite completa termina la verificación funcional de la corrección de
barreras de prueba. Se registra GOT-003 como propuesta, con el síntoma,
causa demostrada y regresiones; no se promueve antes de la revisión.
La iniciativa supera la orientación de 0–2 entradas de conocimiento:
debe valorarse ese umbral en la retrospectiva final, sin omitir una causa
que ha consumido varios ciclos de depuración ni duplicar una existente.

El implementer mantiene emailing_testing reservada para medir materialize()
con 100, 200 y 10.000 destinatarios. Preparación y limpieza de fixtures
propios quedan fuera del tiempo medido; se cuentan verbos SQL sin guardar
consultas, bindings ni secretos. No se optimiza ni se cambia el snapshot
durante esa medición. Este benchmark no prueba capacidad SMTP ni un SLA.

Pendientes resultado de rendimiento, limpieza atribuible, handoff y
liberación expresa de DB/fuentes; después se cierra el medidor y se
congela la revisión 11, primer intento del incremento T-12. El verde
funcional no sustituye revisión independiente, E2E, QA de fase 3 ni M-01.
Total 9/20, T-10/T-11/T-12 en-progreso, ADR-003 propuesta y envío externo
deshabilitado. No se cierra todavía ninguna tarea por este checkpoint.




## Archivado 2026-11-03 — narrativa cerrada: T-12: coste medido del materializador antes de revisión 11

SHA-256 del bloque LF: `7bea4d97dcbfccac6366f9c7539da632cad5e46640c3909620076b452daefbc9`.

## T-12: coste medido del materializador antes de revisión 11

Benchmark propio terminal exit 0, una muestra por tamaño, PHP 8.4.18
nativo Windows y MySQL de pruebas. Se mide solo materialize(), incluida
su transacción; preparación por CampaignService, comprobaciones de filas,
repetición idempotente y limpieza quedan fuera. El contador QueryExecuted
cuenta sentencias por verbo, sin SQL/bindings y sin begin/commit de PDO.

| Destinatarios | Tiempo de materialize() | SELECT | INSERT | Total SQL |
|---|---|---|---|---|
| 100 | 1.1661848 s | 7 | 200 | 207 |
| 200 | 3.6606002 s | 7 | 400 | 407 |
| 10000 | 214.0082381 s | 7 | 20000 | 20007 |

Las tres campañas producen exactamente una entrega y un outbox por
destinatario y cero nuevas filas al repetir. Quedan cero cuentas/usuarios
de esos tres fixtures propios. No se usó broker ni SMTP en la medición.
Root verificó ZIP/25 fuentes/receipt/streams y privacidad de estas salidas;
stdout `b2fde8108351fc09d995fa097ca31f5d2f02471427c133445cc65a2ad80f9d00`.

El materializador conserva el mutex de Account durante la transacción:
10.000 destinatarios admitidos generan 20.000 INSERT y lo retienen unos
214 segundos en esta muestra. Es una preocupación de rendimiento medible
para acciones de cuenta que comparten ese mutex. Se entrega a la lente D
sin ocultarla tras el verde funcional ni extrapolar un SLA de esta máquina.
No se ha optimizado ni cambiado el snapshot final para esta observación;
la revisión determinará los gaps, su gravedad y la corrección exigible.
T-12 continúa en-progreso y aún no se cierra su medidor de implementación.




## Archivado 2026-11-03 — narrativa cerrada: T-12: corrección del coste confirmado antes de la primera revisión

SHA-256 del bloque LF: `2d1ecaa2075ddae26c119083cb80f816ee5a0e2d8343df9dc91f67a71891cc7d`.

## T-12: corrección del coste confirmado antes de la primera revisión

El handoff histórico liberó los recursos propios a
2026-10-23T11:15:34.910663+00:00; gates y teardown quedan sellados en su índice
original. Root ha contrastado fuente, consultas y tiempo del benchmark:
el patrón de dos INSERT por destinatario mantiene el mutex de cuenta
unos 214 segundos para 10.000 destinatarios admitidos. Se considera un
defecto de rendimiento reproducido que debe corregirse, sin esperar una
revisión independiente para descubrirlo otra vez.

El implementer retoma en exclusiva emailing_testing y las fuentes de T-12
para una corrección acotada del materializador y sus pruebas: inserciones
en lotes limitados, misma transacción, orden de locks, DB UTC, procedencia,
identidades, generación e idempotencia. Debe conservar la prueba de rollback
tras un INSERT real y añadir regresión de coste SQL, sin umbrales de tiempo
frágiles ni mecanismos de producto cuya única finalidad sea probar.

Los originales release.json, evidence-index.json, handoff y resultados
anteriores permanecen intactos; la corrección conserva evidencias nuevas
separadas. Se repetirán benchmark 100/200/10.000, focal declarado y suite
completa sobre una fuente final idéntica. Los verdes 16/143 y 157/1.633
continúan vinculados al snapshot anterior, no validan cambios posteriores.

La ventana de implementación T-12 sigue abierta; no se ha iniciado revisión
11, QA de fase 3 ni se reinicia un contador. Root mantiene ledger, medidor,
Git y publicaciones; no inicia otro actor SQL. La liberación histórica no
autoriza operaciones simultáneas mientras el implementer tiene de nuevo
la base reservada. T-12 en-progreso, total 9/20 y envío externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: RED de presupuesto SQL comprobado antes de escribir el bulk

SHA-256 del bloque LF: `56faa2d5e9c376c03a40337e93f576fd0f997dd20eef2b3256ec6f4adce3aa6d`.

## T-12: RED de presupuesto SQL comprobado antes de escribir el bulk

Un único test: **1 failed / 3 assertions**, 16,20 s, exit 1. El snapshot RED
contiene exactamente el materializador anterior, comprobado por SHA contra
el focal final previo. La traza registra 100 destinatarios, 207 consultas y
presupuesto 26. Root verificó ZIP/25 fuentes/streams sin hallazgos de
privacidad. Manifest `5217ccb5783aaaecfba75df90635ca734a735bdf178f40318a225d6fee526598`.

Se conserva el cuerpo del test para contrastarlo con GREEN/final, sin
cambiar expectativas para lograr el verde. Esta evidencia aplica TDD a la
corrección concreta, no activa TDD global ni sustituye QA. La base sigue
reservada por el implementer; revisión 11 aún no iniciada y T-12 abierta.




## Archivado 2026-11-03 — narrativa cerrada: T-12: bulk verificado en regresión y benchmark; focal y suite pendientes

SHA-256 del bloque LF: `4328437fa8e0e7b960ff97f9ed71759faac01ba1526e811badd61b8e4e7cca96`.

## T-12: bulk verificado en regresión y benchmark; focal y suite pendientes

Regresión aislada final: **2 passed / 35 assertions**, 16,86 s, exit 0.
El método de presupuesto SQL conserva exactamente los mismos bytes entre
RED y GREEN final: SHA `8acd4ed7b3b83ef454e4d8df7c810ab7fa8802fc7e1c38d94427c734ee29702f`. El primer GREEN tiene CRLF; su cuerpo
es idéntico después de normalizar únicamente CRLF a LF. Se conservan los
originales sin sustituirlos ni afirmar igualdad raw de los tres. Los tamaños dan
100→9 consultas (presupuesto 26), 200→9 (27), 1.000→11 (35), con conteos,
UUIDs, generación e idempotencia. No se relajan expectativas para el verde.

El mecanismo inicial de trigger falló por SQLSTATE HY000 / código 1419
antes de crearlo; el fallo se conserva y no se ampliaron privilegios.
El primer GREEN usó CHECK por account_id y pasó. La fixture final usa type,
un campo no FK, y comprueba previamente outbox vacío para evitar depender
de restricciones de CHECK con acciones referenciales. Verifica un INSERT
de deliveries realmente ejecutado, rechazo SQL 3819 al insertar outbox,
cero filas de ambas tablas tras rollback y recuperación posterior. La
constraint con nombre propio se retira en finally; no es un hook de producto.

El materializador usa lotes de hasta 500 pares de filas, UUIDs previos al
INSERT y timestamps UTC de MySQL. Mantiene la transacción original, locks,
procedencia, generación y claves únicas; no publica ni envía. Manifest final
del GREEN y benchmark `1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`.

Benchmark posterior, una muestra por tamaño en el mismo laboratorio:

| Destinatarios | Tiempo de materialize() | SELECT | INSERT | Total SQL |
|---|---|---|---|---|
| 100 | 0.0643694 s | 7 | 2 | 9 |
| 200 | 0.1311307 s | 7 | 2 | 9 |
| 10000 | 3.2206176 s | 7 | 40 | 47 |

Para 10.000 se pasa de 214,0082381 s y 20.007 consultas a
3.2206176 s y 47 consultas en
estas muestras. Preparación, comprobaciones, repetición y limpieza quedan
fuera del tiempo; no es capacidad SMTP ni SLA. Las filas son correctas,
repetir crea cero y quedan cero cuentas/usuarios propios del benchmark.
Root verificó los ZIP/25 fuentes y streams de ambos gates finales, sin
hallazgos de privacidad; originales previos e índice histórico inmutables.

Focal declarado OutboxIntegrationTest en curso sobre este snapshot; después
debe pasar la suite completa sin editar entre ambos. Los verdes previos
16/143 y 157/1.633 pertenecen a la fuente anterior. Pendientes focal/full,
cleanup/handoff y revisión 11 antes de commit/push. T-12 en-progreso,
total 9/20, medidor de implementación abierto y correo externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: focal final del bulk verde; suite completa en curso

SHA-256 del bloque LF: `6bbdd923f04dbd086bb7595cee32d033bffd21dae79e7ec25f8d576fae3fb1db`.

## T-12: focal final del bulk verde; suite completa en curso

`php artisan test --filter=OutboxIntegrationTest`: **17 passed / 173 assertions**,
156,46 s, exit 0. Ventana 2026-10-23T11:31:22.612169+00:00–2026-10-23T11:34:01.208933+00:00.
Root verificó ZIP/25 fuentes/streams y privacidad de estas salidas; manifest
`1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`, stdout
`39f8dfb4900a769fa40ccb0d3fd8588668df810ed684a6d1d352315f35074d68`. Incluye regresión del bulk,
rollback SQL real y los casos de protocolo/concurrencia/recuperación previos.

La suite completa `php artisan test` arranca después, sin filtros ni cambios
de fuentes entre ambos. Root contrastó 193 entradas de ZIP/manifest:
154 fuentes y 39 JSON de ejecución en tests/.runtime. Las 189 entradas del
snapshot previo ya incluían 35 JSON de ejecución; la diferencia de inventario
son cuatro JSON TLS nuevos, no cuatro fuentes nuevas. Se aclara aquí que los
recuentos históricos llamados «189 fuentes» contaban entradas de captura.
Las 154 fuentes actuales y los 25 SHA focales coinciden. Los JSON de ejecución
se conservan privados, se distinguen del código y no se exige su igualdad
live ni se borran sin atribución demostrada. Manifest ampliado
`3d4f1ad799f39ab5d1239a47dbad3b125813440201558cf604eba9109664b1b3`. El cuerpo de la regresión de coste conserva
el SHA del RED/final; no se cambia su presupuesto para lograr el verde.

El implementer conserva el handle de la suite completa y la reserva exclusiva
de emailing_testing hasta terminal y cleanup. Este registro no afirma todavía
su resultado. Root no inicia pruebas/SQL simultáneos. Los 354 artefactos del
índice histórico anterior siguen intactos; la corrección tiene recibos nuevos.

Últimos controles administrativos: ledger-lint exit 0, cero incoherencias y
once avisos de Changelog de tareas todavía abiertas; 933 archivos públicos
sin hallazgos; tres documentos modificados con 60 destinos relativos y cero
enlaces rotos. No son auditoría de historial ni verificación del índice Git
futuro. El stream sensible original sigue privado y no se copia a docs.

Pendientes terminal full, cleanup/handoff final, cierre de medidor y revisión
11 antes de commit/push. No existe aún snapshot de revisión T-12, no se
reinician contadores y no se concede QA integral/M-01. Total 9/20, T-12
en-progreso, GOT-003/ADR-003 propuestas y correo externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12: implementación final verificada y liberada para revisión 11

SHA-256 del bloque LF: `b22309497788cdf022b2f4bcb68e091643a429dbbd4ea22e5a3d04923f3969d3`.

## T-12: implementación final verificada y liberada para revisión 11

`php artisan test`, sin filtros: **158 passed / 1.663 assertions**, 529,06 s,
exit 0. Ventana 2026-10-23T11:34:43.100166+00:00–2026-10-23T11:43:34.219695+00:00.
Focal final: **17 passed / 173 assertions**, 156,46 s, exit 0; mismo manifest
de 25 fuentes que GREEN final y benchmark:
`1e8b97a03062a91f09b28ba787cd18749d2eacdb73387ad5d369b20fa4dd32db`. Full contiene 193 entradas de captura,
154 fuentes y 39 JSON de ejecución; los 25 SHA focales coinciden. Manifest
full `3d4f1ad799f39ab5d1239a47dbad3b125813440201558cf604eba9109664b1b3`, stdout
`63a71648b34d18b7f446604c2a2cb3370381031be2917a4acaf3e14d64c93996`. Las salidas finales verificadas no
presentan hallazgos de privacidad. El stream sensible histórico sigue privado.

El handoff final libera DB/fuentes a 2026-10-23T11:44:23.310708+00:00; cero
contenedores, colas, exchanges, cuentas de benchmark, directorios de fixture,
CHECKs y conexiones de prueba propias pendientes. Todos los handles propios
son terminales. No se atribuye ni elimina el PID histórico ajeno. Root
contrastó 184 artefactos nuevos, 354 históricos intactos y 90 recibos
con sus streams. Índice final SHA `7e5f2286d2a7e068b3037a053077929047ac9cb61924b4a8e5ad99d66271833f`. Las 154 fuentes
actuales coinciden; no se exige igualdad live de los JSON de ejecución.

Pint --test acotado posterior pasa sobre materializador y test cambiados;
las otras fuentes PHP conservan el snapshot previamente formateado. No se
presenta como Pint global: formato pendiente T-20 sigue registrado. No hay
cambios de assets ni build nuevo. Benchmark 10.000: 47 consultas / 3,2206176 s,
una muestra de laboratorio, sin extrapolar capacidad SMTP ni SLA.

Ventana de implementación 2026-10-23T09:55:42Z–2026-10-23T11:47:10Z; reloj
1h 51m, fuente estimado, tokens/euros/horas IA reales
NULL por falta de telemetría compatible. No se convierten horas de reloj en
consumo ni se reinician contadores. Queda preparada la primera revisión del
incremento T-12 (global 11), con A+B+D y fallback genérico/runtime heredado.
GOT-003 permanece propuesta y ADR-003 conserva la integración SMTP pendiente.

Pendientes revisión independiente, publicación del checkpoint y T-13/T-16
según sus criterios literales. Full y focal no equivalen a QA de fase 3,
E2E completo ni aceptación humana M-01. T-10/T-11/T-12 siguen en-progreso,
total 9/20 y correo externo deshabilitado. No se concede cierre anticipado.




## Archivado 2026-11-03 — narrativa cerrada: T-12-fix1: D-01 confirmado por MySQL y regresión RED

SHA-256 del bloque LF: `ca04b230986ce892456e290705a9e258cfd316099cd0096b356c5e563bec0339`.

## T-12-fix1: D-01 confirmado por MySQL y regresión RED

Triage real con fixture propio de 10.000, sin broker/SMTP y mismo PDO:
1/10/100 claims globales requieren 10.005/100.050/1.000.500 lecturas Handler.
EXPLAIN ANALYZE muestra Table scan con actual rows=10000 y Sort available_at,id
después de 100 resultados SQL completados. También se relee historial publicado;
las estimaciones de candidatos elegibles de D no se presentan como mediciones.
Las transiciones de completion y preparación quedan fuera de la medición.
Cero cuenta/usuario propios pendientes tras el triage; MySQL 8.4.11.

Regresión aislada RED: 1 failed / 86 assertions, 32,50 s, exit 1. Diez claims
con 100 pendientes requieren 1.050 Handler; con 10.000 más historial previo,
101.050, superando el presupuesto de crecimiento 4.700. No se mide solo número
de consultas ni se usa un tiempo frágil. Root verificó los ZIP/25 fuentes,
streams y trazas sin hallazgos de privacidad; dispatcher y migración del RED
son exactamente los de revisión 11. No había fix productivo antes del rojo.

Root aprueba corrección acotada: eligible_at generado será available_at para
pending, GREATEST(available_at,lease_expires_at) para publishing y NULL para
otros estados; índice eligible_at,id y selección hasta UTC SQL. Conserva el
conjunto elegible, valores nulos, fechas futuras y fencing sin sincronización
manual. El orden pasa explícitamente al instante de elegibilidad: un pendiente
antiguo puede preceder a una reserva recién vencida con available_at anterior.
Spec/diseño no prometen FIFO global. Se probarán leases/available futuros,
historial published, coste y SKIP LOCKED/concurrencia real. No se altera SMTP.

El implementer conserva DB/handles; revisión siguiente será global12, intento
2/3 del mismo incremento. Originales y revisión11 inmutables; verdes17/173 y
158/1.663 todavía solo validan la fuente previa. T-12 sigue abierta, total9/20.




## Archivado 2026-11-03 — narrativa cerrada: T-12-fix1: selección indexada y regresiones verdes; gates completos pendientes

SHA-256 del bloque LF: `31389399aeb307329185b7110ba931cbc569258440b68045479d28f2e5d0e0ce`.

## T-12-fix1: selección indexada y regresiones verdes; gates completos pendientes

GREEN final post-Pint: **3 passed / 120 assertions**, 44,16 s, exit 0.
Manifest25 `ebffe25a95fe99ca6848a26484ea13a2733bc21ee494a91a883d3b6af4e30e95`. El cuerpo del test de trabajo es
idéntico RED/primer GREEN/GREEN final después de normalizar solo CRLF→LF;
se conservan hashes raw separados y no se afirma igualdad del archivo entero.
Presupuestos y expectativas no se relajan. Diez claims: 100 pendientes→132
Handler; 10.000 con historial→30, frente a RED1.050/101.050.

Benchmark propio de selección global: 1/10/100 claims requieren3/30/300
Handler frente a10.005/100.050/1.000.500 anteriores. EXPLAIN ANALYZE muestra
range sobre eligible_at y actual1fila, sin Sort ni Table scan. Con10.000
published, no hay claim y solo1Handler; con9.999published y un pendingfuturo,
también1; reactivar un evento debido requiere3. Son contadores internos y
plan real, no número de consultas, capacidad SMTP ni un SLA. Preparación y
transiciones SQL de completion son fixtures sin broker, fuera de medición.

Las tres regresiones cubren fechas/leases/nulos/historial y dos procesos
globales que completan reclamaciones distintas mientras el padre retiene
el primer candidato con lock SQL. Al liberar ese lock se reclama el evento
restante. Esto acredita selección/concurrencia del outbox, no dos workersSMTP.
Root verificó ZIP/25fuentes/streams y trazas sin hallazgos de privacidad;
cleanup del benchmark cuenta/usuario cero. CAS y publicación conservan su código.

La fecha generada aplica la política documentada de elegibilidad y evita
sincronización manual. La migración/downup y todos los protocolos previos
se verificarán de nuevo en focal completo, seguido de suite global sin editar.
Esas ejecuciones y la segunda revisión12 siguen pendientes. D-01 se considera
corregido a nivel de componente, no cerrado por revisión todavía. T-12 abierta,
total9/20, medidorT-12-fix1 abierto y correo externo deshabilitado.




## Archivado 2026-11-03 — narrativa cerrada: T-12-fix1: suite completa verificada y liberación para revisión 12

SHA-256 del bloque LF: `a754987fb5150c498bb9d643f5e1a1092674c06ff4cc811145863efb66da9695`.

## T-12-fix1: suite completa verificada y liberación para revisión 12

`php artisan test`, sin filtros: **161 passed / 1.783 assertions**, 980,81 s,
exit 0. Focal final: **20 passed / 293 assertions**, 267,55 s. Ambas salidas,
recibos y ZIP fueron contrastados por root. Las 154 fuentes actuales coinciden
con el full final; los 25 SHA focales coinciden con regresión final, benchmarks,
focal, full y Pint. El nuevo full excluye tests/.runtime: 154 fuentes y cero
JSON efímeros. Los históricos 189/193 conservan sus capturas sin modificarse.
Manifest focal `ebffe25a95fe99ca6848a26484ea13a2733bc21ee494a91a883d3b6af4e30e95`; full
`3061d2703af95cc1037f866d0a86c7ed64c98e3312c52d7495019cfbf778ce9a`. No hay hallazgos de privacidad en las
salidas actuales verificadas; el stdout sensible histórico permanece privado.

Pint --test sobre las cuatro fuentes corregidas pasa; no equivale a Pint global.
Materializador actual: 9/9/47 consultas para 100/200/10.000; última muestra
0,278640/0,3224535/6,9351635 s. Las muestras anteriores conservan su fecha y
fuente; no son una comparación controlada de latencia ni capacidad SMTP.
Root contrastó 245 artefactos nuevos y 538 históricos intactos. Índice nuevo
SHA `769614068b6898574dbbec1cb1272cd0c9e1089d33ab62000ed6fa6362be4ea9`: 14 runs y 22 hijos. El primer helper de auditoría
falló antes del sellado al asumir un esquema de índice antiguo; no se guardaron
sus streams originales. La limitación está explícita, sin recibo reconstruido;
el helper corregido verifica ambos esquemas. No fue una suite ni un intento QA.

DB y fuentes liberadas a 2026-10-23T12:45:53.166976+00:00; cero recursos propios
pendientes y todos los handles propios terminales. Se conserva el PID histórico
ajeno sin afirmar su estado actual. Los gates de limpieza verifican contenedores,
colas, exchanges, fixtures, cuentas, CHECKs y conexiones de prueba propias cero.
Control público del worktree: 998 archivos y cero hallazgos; no acredita historial
Git ni el futuro índice. No se realizaron operaciones SQL por root.

Ventana de implementación 2026-10-23T12:05:50Z–2026-10-23T12:47:58Z, reloj
42m; fuente estimado, tokens/euros/horas IA reales
NULL por falta de telemetría. Siguiente paso: revisión global 12, intento acotado
2/3 del mismo T-12, con tablas completas del intento 11 y sin reiniciar contador.
D-01 corregido, pendiente de dictamen independiente. GOT-003 y ADR-003 siguen
propuestas. T-10/T-11/T-12 permanecen abiertas, total 9/20; full backend no cierra
E2E-08/09, QA de fase 3, workers, M-01 ni validación operativa. Correo externo
deshabilitado: Mailpit/log solamente.




## Archivado 2026-11-03 — narrativa cerrada: T-13: primer diagnóstico verificado; ampliación en ejecución

SHA-256 del bloque LF: `1cfa6f1eaa597e12c9e318066a6d5895a83595ac43b4837dcf5308bc7ca7325d`.

## T-13: primer diagnóstico verificado; ampliación en ejecución

Tras la confirmación terminal de su propietario, root verifica recibos, hashes
de ambos streams y las 180 entradas de cada ZIP contra su manifiesto externo.
Los dos diagnósticos usan el manifiesto SHA256
`95d7b3b4b053abe2b24389016bddde8d5c699ea45e2a1317b283c0ee3d406039`:

- `worker-diagnostic-20261023T133439857988Z`: exit 2, **5 failed, 1 passed
  (15 assertions)**, 66,19 s. Se conserva el intento limitado por permisos de
  Docker; no demuestra un fallo de los escenarios Linux que no llegaron a arrancar.
- `worker-diagnostic-docker-20261023T133623588306Z`: exit 0, **6 passed
  (92 assertions)**, 125,94 s. Recursos propios terminados según el owner.

Los streams verificados no producen etiquetas del comprobador de datos privados.
El verificador privado de T-13 se adapta al esquema real del ZIP: contiene
exactamente las entradas del manifiesto externo, sin manifiesto embebido;
rechaza duplicados y verifica todos los hashes. Sus dos primeras ejecuciones
fallaron por asumir el esquema anterior y no generaron un resultado de producto.
Las comprobaciones correctas son históricas: no afirman igualdad con las fuentes
actuales ni sustituyen la suite completa o la revisión independiente.

La ampliación `worker-expanded-20261023T134358019318Z` sigue a cargo del mismo
owner, con fuentes congeladas y acceso exclusivo a `emailing_testing`. Incluye
más ventanas de fallo SMTP/SQL/ack, pausas, cuotas y políticas vigentes. No hay
resultado global terminal ni liberación de la base en este checkpoint. Quedan
además la prueba CLI/consumo continuo y la relación entre timeout SMTP y lease.
La revisión estática señala también que una espera por locks de cuota podría
cruzar una ventana: se debe comprobar la época después de adquirir los locks.
No se modifica runtime durante una ejecución ni se cierra T-13. Total **9/20**.

### Resultado terminal del diagnóstico ampliado

El propietario confirma el final del mismo proceso, sin reiniciarlo:
`worker-expanded-20261023T134358019318Z`, exit 1, **16 passed, 1 failed
(357 assertions)**, 542,98 s. Root verifica los hashes del recibo, ambos streams
y las **182 entradas** del ZIP; manifiesto SHA256
`7df22374557d7eee1b7cc80dcc1f6d628c3bcdaa9f1893755d57170e875fe51a`.
Los streams no producen etiquetas del comprobador de datos privados. El fallo
se localiza en la comparación de fecha del test de sesión SQL no UTC: la fecha
persistida incluye precisión de microsegundos. Se conserva el rojo y se corregirá
la expectativa antes de repetir; este diagnóstico no es el gate final.

El owner informa de teardown de sus workers/relay/proxy y colas propios, pero
mantiene la exclusividad de la base y fuentes para completar los casos pendientes.
La inspección del ZIP también detecta que `fn () => $stop` captura el valor inicial
en el comando del worker e impide observar el cambio del handler de SIGTERM.
Se requiere cierre con estado actualizado y prueba Linux de parada real. Quedan
la revalidación de la ventana tras locks y los límites SMTP/lease; no se toma el
verde de los otros escenarios como aprobación global. T-13 sigue en progreso.

### Diagnóstico CLI y reloj en verde; congelación final pendiente

`cli-clock-diagnostic-20261023T135747336425Z` termina exit 0: **5 passed
(138 assertions)**, 251,37 s. Root verifica recibo, streams y **183 entradas**
del ZIP, manifiesto SHA256
`285f3e15c77b2e2e3f9a286d6d3a8a4757a5a1da791ce60686cd6bed9f4d0a69`.
Sin etiquetas del comprobador de datos privados. El owner confirma procesos
propios terminales y limpieza de sus colas/relay/locker; conserva la base para
los gates finales y todavía no libera fuentes.

Los casos incorporan consumo continuo con dos workers y prefetch 1, CLI Linux
con SIGTERM, límite de timeout SMTP, sesión SQL +05:30 y un proceso que mantiene
un lock de pool hasta cruzar el minuto UTC. La cuota revalida la época tras los
locks y no carga la ventana obsoleta. El arranque SQL añade CAS de token,
generación, estado y lease en el propio UPDATE: si la espera venció la reserva,
revierte cuota, capability y mensaje antes de reconciliar. Estos cambios todavía
requieren el conjunto completo con las fuentes finales.

El alcance de T-13 explicita `.env.example` para reproducir la configuración
pública y `docs/aplicacion/**`, la ubicación real de las guías. Root añade
[entrega y recuperación](../../aplicacion/entrega-y-recuperacion.md), marcada como
implementación en validación: outbox, estados, ack, reintentos, cuotas y límites
de SMTP. Sus cuatro enlaces relativos existen y no aparecen etiquetas privadas;
el índice de docs tiene 24 enlaces relativos válidos. La revisión formal incluirá
esta documentación. No se promociona ADR-003 ni se cierra QA de fase 3. Total 9/20.

### Inicio del gate completo del consumidor

El formatter acotado `scoped-format-20261023T140332428447Z` termina exit 0.
Root comprueba recibo/streams y sus 184 entradas de snapshot; no se atribuye a
todo el repositorio un formato global limpio. El owner congela runtime/config/
tests e inicia `worker-final-frozen-20261023T140435704223Z` con el comando exacto
`php artisan test --filter=WorkerRecoveryTest`. Todavía no hay resultado terminal.

Durante esa ejecución, la lectura estática identifica tres clases legacy que
usan `RefreshDatabase` alrededor de SMTP real: MailTransportTest, TemplateTest y
SuppressionTest. Su transacción exterior es incompatible con el nuevo guard.
Tras el terminal se adaptará únicamente su aislamiento a `DatabaseMigrations`,
manteniendo el guard de `emailing_testing`; no se debilitará la barrera de commit.
La suite completa posterior usará el nuevo snapshot y repetirá el consumidor.
No se atribuirá retrospectivamente el recibo actual a un manifiesto posterior.

Las dos subtareas técnicas de T-12 se marcan comprobadas conforme a su revisión
12 y checkpoint ya publicado: estado/dispatcher y cortes/confirms/routing.
Sus criterios integrales siguen abiertos hasta reconciliar la evidencia del
consumidor. Las integraciones restantes de T-10/T-11/T-12 se evaluarán por su
texto literal, sin atribuirles un E2E o aceptación humana todavía no ejecutados.
T-13 continúa en progreso; revisión 13 todavía no iniciada, medidor abierto.

### Gate exacto del consumidor: verde, suite global en curso

`worker-final-causal-frozen-20261023T144915041057Z` termina exit 0 con el
comando exacto `php artisan test --filter=WorkerRecoveryTest`: **20 passed
(473 assertions)** en 1254,12 s. Root verifica el recibo terminal, los hashes
de stdout/stderr y las 185 entradas del ZIP contra el manifiesto SHA256
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`.
Stderr vacío y cero etiquetas del comprobador de privacidad en ambos streams.
Este recibo acredita esa ejecución y ese snapshot; conserva los rojos anteriores
como diagnósticos y no los transforma en resultados aprobados.

El owner inicia después `full-final-frozen-20261023T151051959342Z` con
`php artisan test`, de forma secuencial y sin modificar las fuentes. Ambos
requests declaran el mismo manifiesto. Al escribir este checkpoint el owner
confirma mediante polling que la suite sigue activa; todavía no hay resultado
terminal global. La base de pruebas y los procesos continúan bajo su propiedad
exclusiva hasta la comprobación nativa, el censo de recursos y su liberación
explícita. Root no ejecuta SQL ni otra suite en paralelo.

La revisión independiente 13, el QA de fase 3 y la publicación de T-13 siguen
pendientes. Los criterios integrales de T-10/T-11/T-12 se reconciliarán con su
texto literal durante esa revisión. No cambia el total formal: **9/20**.

### Suite global de T-13: cuatro rojos conservados para diagnóstico causal

`full-final-frozen-20261023T151051959342Z` termina con recibo exit 2:
**177 passed, 4 failed (2250 assertions)** en 3200,07 s. Root comprueba el recibo,
ambos streams y las 185 entradas del snapshot contra el mismo manifiesto
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`.
Stderr vacío y cero etiquetas del comprobador de privacidad. Este resultado
contradice un verde global; el gate exacto anterior no lo sustituye.

Los cuatro síntomas observados son:

- `OutboxIntegrationTest::test_incremental_migration_down_up_is_repeatable_and_keeps_campaign_snapshots`:
  MySQL 1091 al eliminar el índice `delivery_recovery`.
- `WorkerRecoveryTest::test_legacy_test_send_and_worker_share_atomic_account_and_pool_quota`:
  diagnóstico `null` donde se esperaba `quota_wait`.
- `WorkerRecoveryTest::test_quota_windows_and_wait_are_utc_even_with_non_utc_mysql_session`:
  el identificador de ventana observado difiere del esperado en un minuto.
- `WorkerRecoveryTest::test_real_pool_lock_crossing_utc_minute_defers_without_charging_stale_windows`:
  la barrera del locker no proporciona bytes válidos antes de finalizar la espera.

Se aplica `custom-agents:debug-root-cause` fuera del gancho de tres intentos QA:
reproducción mínima, aislamiento con descartes, hipótesis probada y corrección
con regresión. Todavía no se declaran probadas las causas de esos síntomas.
Antes de cualquier ejecución o cambio se censan y resuelven los hijos y recursos
atribuibles al rojo. No se amplían umbrales ni se reinicia la suite a ciegas.
Tras las correcciones verificadas se ejecutará otra suite completa sobre sus
fuentes finales. La revisión 13 sigue sin comenzar; T-13 continúa en progreso.

### Diagnóstico causal: enrutamiento reproducido y orden de migraciones aislado

La observación de catálogo posterior al rollback
`diagnostic-state-after-red-20261023T161526056580Z` termina exit 0 y acredita,
en ese momento, sólo la tabla `migrations`, sin migraciones registradas ni
tablas de negocio. La consulta del processlist devuelve únicamente la propia
consulta del diagnóstico; no se extrapola esa selección a otros usuarios.
Es una observación histórica de la base de pruebas, no un gate de producto.

`routing-collision-baseline-20261023T171136264002Z` termina exit 0 y reproduce
el candidato con broker real y topología propia. Un binding durable de fallos
ya existente recibe un payload `delivery.ready` cuando la clave normal se
configura igual que la de fallos. Se observan ack y confirm, outbox `published`,
cola normal vacía y entrega todavía `pending`, generación 1, cero intentos y
cero `MailMessage`. No se ejecuta transporte. La hipótesis queda probada:
mandatory/confirm acredita enrutamiento a alguna cola, sin distinguir su rol.
La corrección propuesta rechaza la colisión de ruta o binding normal con la
clave de fallos antes del IO; la publicación debe conservar pendiente/backoff
con un diagnóstico tipado y el consumidor debe fallar antes de bind. Aún faltan
su regresión y los gates sobre el código corregido.

`migration-minimal-red-20261023T171308956852Z` ejecuta únicamente la prueba
incremental de OutboxIntegrationTest y termina exit 2: **1 failed
(9 assertions)**, 29,74 s, con el mismo MySQL 1091. Su secuencia baja/recrea
la migración 000009 directamente mientras 000010 continúa registrada; al
restaurar sólo la tabla de la migración anterior faltan las columnas e índice
que la migración dependiente espera retirar en el teardown. Se debe respetar
el orden de dependencias de la prueba, sin debilitar la migración de producto.

Root comprueba recibos, streams y snapshots de las tres ejecuciones:
185 entradas, manifiesto
`357d94d511c161e7c71bacd11563a1b370abaf84a24a523e46c5d6b1de794a6e`,
stderr vacío y cero etiquetas del comprobador de privacidad. Se conservan las
observaciones y el rojo mínimo; no se les atribuye un resultado de regresión
verde. En ese punto los casos temporales continuaban en diagnóstico. Total
formal: **9/20**.

### Hipótesis temporales probadas; regresiones corregidas pendientes de verde

`boundary-causal-diagnosis-20261023T171809481664Z` termina exit 0 sobre el
snapshot anterior de 185 fuentes. Es diagnóstico controlado, no gate de
producto. Su protocolo confirma que reconstruir 000009 mientras 000010 sigue
registrada elimina el índice dependiente y reproduce MySQL 1091. Con sesión
MySQL `+05:30`, una llamada posterior al cambio real de minuto utiliza la
ventana UTC comprendida entre sus consultas de inicio y fin; la expectativa
tomada antes de la frontera pertenece al minuto anterior. Dos envíos log en
minutos consecutivos consumen una unidad cada uno en ventanas distintas:
el segundo envío puede continuar legítimamente. Root verifica la coherencia
de esos valores, el hash del helper capturado y la referencia al protocolo en
el stdout cuyo hash consta en el recibo padre. El recibo auxiliar carece de timestamp
independiente de fin y hashes originales de sus observaciones; se conserva
esa limitación y su snapshot, sin atribuirle un verde de regresión.

`routing-regression-red-20261023T172253884378Z` incorpora primero la prueba
de colisión y termina exit 1: **1 failed (3 assertions)**, 19,05 s. Antes de
la corrección intenta conexión y devuelve `publish_uncertain`, en lugar de
rechazar la configuración con `routing_key_collision`. Se conserva su
manifiesto de 185 fuentes
`997fcc6c324984f62f47efbdfde577d27b596a6d7074a4663c2913a983c1907b`;
no se atribuye este rojo a las fuentes anteriores ni a las corregidas.

El implementer comunica aplicados el guard previo al IO del publisher y
consumidor, el orden dependiente correcto en la prueba de migraciones y
las correcciones de sincronización temporal. No cambia la migración de
producto ni DeliveryQuota. Faltan los focales verdes, el gate exacto, la
suite global final, el smoke nativo y la liberación explícita de recursos
antes de la revisión independiente. T-13 sigue en progreso; **9/20**.

### Cinco focales causales verdes sobre una nueva foto de fuentes

`causal-five-focal-20261023T173841307602Z` termina exit 0: **5 passed,
116 assertions**, 149,09 s. Root verifica recibo, hashes de ambos streams y
185 entradas del ZIP contra el manifiesto
`595805bea9db7398973b45528a11cc57f58bd86026e5f24a49becc9ab98f9029`;
stderr vacío y cero etiquetas del comprobador de privacidad. Incluye las
regresiones de migraciones, cuota compartida, sesión MySQL no UTC, colisión
de claves y lock real que atraviesa la frontera de minuto.

La prueba de colisión comprueba ruta y binding por separado, cero conexiones
al listener y ningún paso de publicación. El outbox permanece pendiente con
backoff; al restaurar claves válidas publica y produce un único intento log.
En el caso de frontera real, los registros capturados muestran renovaciones
de la misma reserva y una caducidad posterior al desbloqueo: la espera no se
confunde con una reserva vencida. Antes de continuar se observa `quota_wait`,
cero intentos y cero cuota cargada; el siguiente evento puede continuar.
Root inspecciona esos métodos desde el ZIP de la ejecución y las observaciones
temporales, sin consultar SQL ni tocar procesos del implementer.

Son focales sobre fuentes nuevas, no un verde global. El comprobador Pint
acotado anterior pasa, y se preparan las suites OutboxIntegrationTest y
WorkerRecoveryTest completas, otra suite global y la comprobación nativa
final antes del handoff. Revisión 13 aún no iniciada; total **9/20**.

### Outbox y worker completos verdes; nueva suite global todavía en ejecución

`outbox-regression-causal-final-20261023T174237942409Z` termina exit 0:
**20 passed, 299 assertions**, 344,99 s.
`worker-exact-causal-final-20261023T174853241748Z` ejecuta el comando literal
`php artisan test --filter=WorkerRecoveryTest` y termina exit 0:
**21 passed, 429 assertions**, 611,58 s. Root verifica ambos recibos, streams
y 185 entradas de cada ZIP contra el mismo manifiesto
`595805bea9db7398973b45528a11cc57f58bd86026e5f24a49becc9ab98f9029`;
stderr vacío y cero etiquetas del comprobador de privacidad. La comparación
estática de métodos conserva los 20 casos anteriores y añade el de colisión;
esto no se presenta como una medida de cobertura de aserciones.

El owner inicia `full-causal-final-frozen-20261023T175935140900Z` sobre esas
fuentes. Un poll autoritativo informa de FAIL parcial en
`ConcurrentCampaignTest::test_authorization_waiting_on_mysql_account_mutex_observes_committed_scope_revocation`.
La suite sigue activa y todavía no tiene detalle final ni recibo terminal.
Se conserva la ejecución y no se atribuye una causa a la duración del caso.
Después del resultado terminal se resolverán recursos propios y se aislará
el fallo antes de corregir o repetir. Estos gates focales no sustituyen un
verde global; revisión 13, cierre de T-13 y publicación siguen pendientes.

Resultado posterior de esa misma ejecución: terminal con **181 pruebas correctas,
1 fallida y 2221 aserciones**, en 2606,89 s, sobre las mismas 185 fuentes.
Root verificó el recibo, los hashes de stdout/stderr y el ZIP del snapshot.
Los 21 casos de recuperación del consumidor pasan. La excepción final es
`Directory not empty` al ejecutar `rmdir` en el `finally` de la prueba de
revocación concurrente; esa excepción podría ocultar una anterior y no demuestra
que sus aserciones funcionales llegaran a ejecutarse. La reproducción aislada
sin cambios pasa con 1 prueba y 6 aserciones; no reemplaza el resultado global.

El aislamiento con archivos propios en Windows reproduce `unlink` correcto
seguido de `rmdir` fallido mientras permanece abierto un handle que permite
eliminar el archivo; tras cerrar ese mismo handle, el directorio se elimina.
Esto demuestra una fragilidad de la limpieza, sin identificar al actor del
fallo histórico. Se prepara una regresión que preserve por separado el error
primario y el de limpieza. Antes de esa nueva reproducción, el censo registró
296 identidades propias sin contenedores presentes ni helpers nativos
coincidentes; es una observación histórica, no una liberación del entorno.
T-13 continúa abierta y requiere corrección causal, suite global final, smoke,
liberación explícita y revisión independiente antes de publicar.

La adaptación posterior modifica únicamente `ConcurrentCampaignTest.php` y su
helper `campaign-action.php`: conserva los plazos anteriores, observa el hilo
MySQL propio ejecutando `accounts ... FOR UPDATE` mientras el padre mantiene el
mutex, y registra por separado error primario y errores de asentamiento. Las
barreras del hijo se publican mediante rename y la limpieza usa el retry acotado
demostrado por la regresión Windows. El focal completo termina con **5 pruebas
y 34 aserciones correctas**; se verifica la secuencia espera SQL → commit de la
revocación → rechazo 403 sin autorización ni auditoría → limpieza sin errores.

Las 185 fuentes finales tienen manifiesto
`32f798edf22b2e2385fa4846953c25814cdf79160aaa4c31d7cece8bdcd3996f`.
El formatter acotado a 26 PHP pasa en modo comprobación. Root confirma que
runtime, configuración, esquema y pruebas Worker/Outbox conservan sus bytes;
los recibos anteriores de esos componentes siguen siendo históricos.
Se inicia `full-campaign-final-frozen-20261023T190743204457Z` con
`php artisan test`, que repetirá también esos componentes sobre las fuentes
finales. Todavía no se declara verde global ni se inicia revisión 13.

Resultado terminal posterior: **183 pruebas y 2227 aserciones correctas**, en
1403,46 s, con stderr vacío y el mismo manifiesto `32f798…3996f`. Root verifica
los 185 miembros del ZIP y los streams; el resultado contiene los 21 casos
WorkerRecovery, 20 OutboxIntegration y 5 ConcurrentCampaign.

El inventario incluye 184 fuentes/contextos del proyecto y el runner privado
que produce los snapshots. Ese productor se conserva como evidencia y no se
incorpora al diff público de la aplicación; los hashes originales no cambian.

La prueba de
revocación registra espera SQL, commit, aserciones completas y asentamiento sin
error primario ni secundario. No se sustituyen los recibos históricos por otros
de fuentes posteriores.

El smoke nativo de Windows completa ocho comandos: dos ejecuciones reales de
`deliveries:work --once` confirman y consumen el original y su replay, conservando
una entrega logged, un intento, generación 1, el mismo MailMessage y el mismo
hash del artefacto; el contador del listener SMTP es cero. Se eliminan su
topología y artefacto propios y quedan diez contadores de negocio y reservas
en cero dentro de `emailing_testing`. Root verifica comandos, recibos, helpers,
ZIP y observaciones; no ejecuta otra tanda SQL para reinterpretar el resultado.

El censo final verifica 342 identidades de contenedores propias ausentes y cero
helpers nativos coincidentes, con 30 ejecuciones atribuidas y tres orígenes TLS.
Es una observación fechada; el proceso histórico excluido no se inspecciona.
El implementer libera explícitamente fuentes y base de pruebas, con todos sus
handles terminales. T-13 sigue abierta hasta revisión independiente. La memoria
de la eliminación pendiente de Windows se registra como GOT-004 propuesta,
complementaria a GOT-002/003; su promoción requiere esa revisión. La retro final
revisará el umbral de memoria porque la iniciativa acumula cuatro gotchas.

El medidor de implementación T-13 queda cerrado antes de la revisión: fuente
estimada, sin tokens, euros ni horas IA reales disponibles. Su intervalo de
reloj no se presenta como esfuerzo IA medido; tampoco permite separar con
exactitud la preparación aislada de T-14/T-15. La revisión tendrá su propia
ventana. La comprobación de documentación cubre también GOT-004 y su índice.

### Documentación de perfiles: separar ambas familias de colas

La inspección del Compose congelado de T-13 muestra cuatro servicios:
`emailing-php`, `emailing-web`, `emailing-worker` (dominio) y `emailing-jobs`
(Laravel). La receta QA anterior solo cambiaba `RABBITMQ_QUEUE`; aplicada al
consumidor nuevo habría conservado referencias de dominio operativas. Root
actualiza la guía de desarrollo y la integración de infraestructura: web y ambos
consumidores reciben la cola de jobs QA, exchange/cola/fallos de entregas QA y
pool QA. El nombre del proyecto Compose por sí solo no separa colas.

La receta YAML se extrae a un fixture privado y se comprueba mediante
`docker compose ... config --format json`, exit 0. La configuración se analiza
en memoria: los cuatro servicios son los esperados, los tres servicios PHP usan
`emailing_testing` y las referencias de colas/pool coinciden con los valores QA.
No se arrancan servicios ni se consulta SQL; la salida completa con credenciales
no se escribe ni publica. Se conserva un reporte privado depurado con hashes.
Esta comprobación de configuración no acredita build, arranque, salud ni E2E del
Compose nuevo. Los recibos de fases anteriores siguen describiendo su versión.

### Gate completo del consumidor: un límite temporal por diagnosticar

El mismo `worker-final-frozen-20261023T140435704223Z` termina exit 1:
**19 passed, 1 failed (477 assertions)**, 1.118,65 s. Root verifica las 184
entradas, recibo y streams contra el manifiesto `4a28f1401efd59c80556dad7be2a91a453e167dbdf88ea3e21ac5a2eccd3b168`;
sin etiquetas del comprobador de privacidad. El owner confirma limpieza de sus
recursos y mantiene propiedad de la base/fuentes para completar el diagnóstico.

La única prueba roja mide desde la señal `sending` hasta el final del proceso:
15,5865 s frente a una expectativa de 12 s. Esa medición incluye SMTP, limpieza,
persistencia y arnés; por sí sola no demuestra que el timeout de operación de
5 s falle. Se requieren tiempos causales por etapa antes de corregir código o
expectativa. Las aserciones posteriores de estado ambiguo, aceptación observada
y liberación del relay no se ejecutaron en ese caso y no se cuentan aprobadas.
Se conserva el rojo, sin aumentar el umbral a ciegas ni debilitar el guard SQL.
La suite global y la revisión independiente siguen pendientes. Total 9/20.

### Diagnóstico por etapas: timeout comprobado, dos regresiones pendientes

`causal-and-legacy-20261023T142913651950Z` termina exit 2: **14 passed,
2 failed (208 assertions)**, 534,20 s. Root verifica recibo/streams y las
185 entradas, manifiesto SHA256
`3513f0112ae7e4a11d26633fc8bc9e3e3b72cd113fa59b0d2e437b9d6f4d8795`;
sin etiquetas del comprobador de privacidad. La instrumentación SMTP nueva es
inerte por defecto; sus llamadas aportan etapas conocidas, códigos numéricos,
flags, timeout y tiempos monotónicos. No incluyen comandos, respuestas, AUTH ni
cuerpos.

La prueba causal del timeout pasa: configuración efectiva de 5 s, espera de
DATA final de 5,004756108 s y limpieza de 5,003676919 s. Comprueba además el
resultado ambiguo y las invariantes de lease/estado antes de liberar el relay.
No se aumenta el umbral total de 12 s: se sustituye aquella medición conjunta
por observaciones de las fases que realmente se pretendía verificar.

El primer rojo confirma un cambio de contrato legacy: el remitente ajeno produce
NotFoundHttpException donde se esperaba ModelNotFoundException. Se restaurará
`findOrFail` en esa rama, conservando el tratamiento de bloqueo del consumidor.
El segundo rojo es una espera de readiness de SuppressionStartTest: el marcador
aparece después del plazo y su factory puede lanzar antes de registrar el handle
en el bloque de limpieza. Se deben atribuir y resolver los hijos afectados,
preservar los recibos ausentes como limitación y corregir el arnés con evidencia
antes de otra ejecución. No se debilitan guards ni se reescriben helpers sellados.

La observación auxiliar SQL del owner se verifica por hashes del helper y de
ambos streams; su esquema PowerShell usa hashes en mayúsculas. El freezer privado
acepta ese esquema explícito normalizando sólo la representación hexadecimal y
lo clasifica como observación de sólo lectura, nunca como un gate de producto.
Los primeros intentos de root con comparación de casing literal no generaron
un informe; la verificación corregida no ejecutó SQL ni imprimió su salida cruda.
La base y las fuentes todavía no están liberadas. Total 9/20.

### Inicio del gate completo del consumidor

El formatter acotado `scoped-format-20261023T140332428447Z` termina exit 0.
Root comprueba recibo/streams y sus 184 entradas de snapshot; no se atribuye a
todo el repositorio un formato global limpio. El owner congela runtime/config/
tests e inicia `worker-final-frozen-20261023T140435704223Z` con el comando exacto
`php artisan test --filter=WorkerRecoveryTest`. Todavía no hay resultado terminal.

Durante esa ejecución, la lectura estática identifica tres clases legacy que
usan `RefreshDatabase` alrededor de SMTP real: MailTransportTest, TemplateTest y
SuppressionTest. Su transacción exterior es incompatible con el nuevo guard.
Tras el terminal se adaptará únicamente su aislamiento a `DatabaseMigrations`,
manteniendo el guard de `emailing_testing`; no se debilitará la barrera de commit.
La suite completa posterior usará el nuevo snapshot y repetirá el consumidor.
No se atribuirá retrospectivamente el recibo actual a un manifiesto posterior.

Las dos subtareas técnicas de T-12 se marcan comprobadas conforme a su revisión
12 y checkpoint ya publicado: estado/dispatcher y cortes/confirms/routing.
Sus criterios integrales siguen abiertos hasta reconciliar la evidencia del
consumidor. Las integraciones restantes de T-10/T-11/T-12 se evaluarán por su
texto literal, sin atribuirles un E2E o aceptación humana todavía no ejecutados.
T-13 continúa en progreso; revisión 13 todavía no iniciada, medidor abierto.




## Archivado 2026-11-03 — narrativa cerrada: T-14-fix1 — focal final y migración verdes; full en ejecución

SHA-256 del bloque LF: `ad194654636cd37585272c60508e26fa1866c907cc1d68ac23781ddaba721f39`.

## T-14-fix1 — focal final y migración verdes; full en ejecución

El comando literal `php artisan test --filter=ActivityTest` termina con hijo 0:
`fix1-activity-exact-20261023T233258528346Z`, **20 pruebas/237 aserciones**,
280.67 s del runner. Root contrasta recibo, hashes de streams/ZIP, manifiesto y
recuento del stdout; las 196 fuentes no cambiaron durante el proceso.

La migración incremental se repite con su filtro exacto de OutboxIntegrationTest:
`fix1-migration-final-20261023T233853373319Z`, hijo 0, **1 prueba/15
aserciones**, 26.54 s del runner. Root verifica la misma integridad y el recuento
literal. Ambos gates, Pint y build conservan manifiesto
`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`.

El propietario arranca después `fix1-full-final-20261023T233955193754Z`, comando
literal `php artisan test`, y confirma handle propio vivo 62398. Solo él lo
observa; el coordinador no ejecuta aplicación/SQL/SMTP ni reinicia la suite por
una espera de observación. Esta anotación conserva una observación, no prueba
la liveness futura ni anticipa el resultado del full.

Se prepara un auditor de censo específico de fix1, solo de lectura sobre
evidencia archivada, con sintaxis validada pero todavía sin ejecutar. Ese censo
deberá declarar su alcance sobre los runs fix1 y excluir de CIM los tres PIDs
no inspeccionables; no se confundirá con una comprobación de todos los recursos
del host ni con el censo histórico de la primera implementación. Permanecen
pendientes full, censo, handoff/liberación, cierre del medidor, revisión15 y
publicación. Contador formal **13/20**; T-14 sigue **en-progreso**.

#### T-14-fix1 — full verde, entrega y liberación verificadas

El full literal `php artisan test` del run
`fix1-full-final-20261023T233955193754Z` terminó con exit0:
**203 pruebas, 2.466 aserciones, 2.710,15 s** de runner, en 18 clases.
Root contrastó recibo, comando, streams y ZIP de las 196 fuentes finales
con SHA `30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`,
sin cambios durante la ejecución; verificó 494 copias atribuidas y 236
orígenes sin copia. El conteo de casos coincide con el total de consola.
Esto completa los gates de código de fix1, sin sustituir revisión ni QA.

El censo `fix1-owned-settlement-20261024T002545020815Z` y el writer
`fix1-handoff-final-20261024T002630577672Z` finalizaron con exit0.
El alcance del censo son cinco runs de pruebas de fix1: 77 PIDs históricos
atribuidos, ninguno presente; 46 nombres propios de contenedores ausentes
y 84 consultas pasivas de las 42 entidades propias de broker ausentes.
Se excluyeron de la consulta CIM los tres PIDs ajenos documentados.
La identidad SQL comprobada fue la de pruebas, con solo `migrations: 0`.
Root auditó 656 archivos de procedencia y los comandos archivados sin
ejecutar consultas de aplicación, SQL, procesos ni broker. No se afirma
un inventario global del host ni una garantía posterior de liveness.

El implementador liberó explícitamente las 196 fuentes y `emailing_testing`
después del writer terminal. Root contrastó el handoff fix1 SHA
`3697e18af93ed33bed9830d167f80592ca696c12e78ab4070888fa3e2f6df3e4`,
las 30 entradas nuevas de fuentes privadas, las 41 originales intactas,
el handoff original intacto y las fuentes actuales contra el recibo final.
La evidencia privada queda en los informes root de full, conteo, censo y
liberación dentro de `storage/phase3-t14/`; no se publica ese directorio.
Algunos JSON de stdout root conservan BOM UTF-16LE de PowerShell: se
leyeron con detección de encoding sin modificar sus bytes originales.

Medidor `T-14-fix1` cerrado: 2026-10-23 23:26:36Z →
2026-10-24 00:32:06Z, **CLOCK 1h 6m**. Sin transcripciones disponibles,
tokens reales, coste y horas de IA siguen desconocidos; CLOCK no los estima.
Pendiente congelar la cápsula y ejecutar revisión15, segundo intento
acotado de T14, con los veredictos completos previos. El único gap previo
es A-01, todavía pendiente de esa revisión. No hay cierre ni publicación
de T14: contador formal **13/20**. T15–T20 permanecen pendientes.

#### Revisión15 — despacho, segundo intento acotado de T14

Tras la liberación explícita y el cierre de implementación, la congelación
terminó con exit0: 24 archivos cambiados, 785 fuentes de contexto y 3.607
archivos de evidencia. Cápsula privada `storage/phase3-t14-review2`, sello
SHA `a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4`,
patch SHA `0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29`.
La preparación inicial detectó que root exigía el nombre de la base en el
resumen Markdown: se conservó el diagnóstico y se verificó la identidad
explícita en el handoff JSON al que remite ese resumen, sin modificar los
handoffs ni código probado. No fue un intento de revisión ni fallo de app.

Scope exit0 sin exclusiones ni avisos. Selector oficial: A+B+D; C no
activada. Se lanzaron tres revisores genéricos independientes con contexto
fresco y prompts literales, solo lectura, sin override de modelo y con
fallback declarado. Reciben los veredictos previos completos y el diff de
corrección; no deben reabrir criterios aprobados sin evidencia nueva.
Medidor `review-15` iniciado el **2026-10-24 00:34:32Z** después de cerrar
`T-14-fix1`. Resultados y fusión pendientes; no hay aprobación anticipada
ni commit de T14. Contador formal **13/20**.



## Archivado 2026-11-03 — narrativa cerrada: T-15 — primer focal real y diagnóstico separado

SHA-256 del bloque LF: `a85e2b8fe80eb7d991e8031e5fce663409bcd97f2f72665c7f150a8a89b42081`.

## T-15 — primer focal real y diagnóstico separado

El propietario integra el borrador reconciliado y refina el contrato: lote
del servidor20 como precedente del CLI, presupuesto cooperativo propuesto10s
entre claims, stop_reason/elapsed_ms y emails_completed desconocido. Esos
valores siguen provisionales hasta pruebas nativa/Docker; no prometen
cancelación de una operación SQL/AMQP bloqueante, capacidad óptima ni SLA.
Cliente, endpoint y guía deben conservar el mismo contrato cerrado.

Run `scheduler-initial-20261024T011000705360Z`, comando literal
`php artisan test --filter=SchedulerTriggerTest`, termina con hijo2/wrapper1:
**6 aprobadas, 1 fallida, 100 aserciones, 95,26s** del runner. Root verifica
recibo/request, hashes de streams,212 entradas del ZIP y manifiesto
`8f926ddf7757b916eee9888e5f06b45a34596ebb2d220d81685eb984108a3a92`,
sin cambios durante la ejecución y sin comparar con fuentes actuales del
propietario. El recuento independiente coincide con la consola. No es un
gate aprobado y los hashes no acreditan cobertura semántica de requisitos.

La prueba de broker no disponible falla primero preparando el consentimiento;
no se acreditan las aserciones posteriores no ejecutadas. El propietario
instrumenta fechas SQL/PHP y estudia la precisión0 de consented_at; no se
asigna un instante no capturado al fallo original ni se relaja el dominio.
Un diagnóstico focal posterior reporta1/5 y broker inaccesible real con
3claims/backoff/0publicaciones; sigue separado del focal inicial y pendiente
de contraste completo por root. La causa del fallo de fixture aún requiere
evidencia controlada antes de corregirse.

Formatter/import iniciales conservaron las preimágenes y mutaciones previstas
como diagnósticos, sin promocionarse a gates finales. Una observación de infra
falló por una ruta de fuente del plugin supuesta; su repetición de lectura
reporta UID/GID y permisos temporales reales y jobs existentes desactivados.
No acredita creación/ejecución de jobs ni integración Cronicle. No se cambian
Git ni contadores; medidor T15 sigue abierto y el propietario mantiene en
exclusiva fuentes/app/SQL. Estado **en-progreso**, contador **14/20**.

#### T-15 — conversión controlada y focal ampliado

Root contrasta `consent-clock-diagnostic-20261024T011316284388Z`:
1 prueba/5 aserciones,20,79s, SHA
`e8fc3235226fdb4315c49e8d3448083d5592511da65f4f63f5b052d495c2636c`.
No reproduce el fallo inicial; no se convierte su fecha en la del rojo.

`consent-conversion-proof-20261024T011508329992Z` pasa1/4,12,93s,
SHA `a43597ff32fe64687d55613978214b7691e85234270f0ae7723f4b470c7a6fee`.
El caso actualiza contacts con .750000, observa persistido el segundo
siguiente y fija el reloj PHP a .900000: el dominio rechaza audience y no
crea snapshots. Root lee el método del ZIP, contrasta esos hechos con el
protocolo y cuatro orígenes cargados contra las212 fuentes archivadas.
Demuestra una condición controlada de redondeo; la fracción del fallo
original sigue desconocida. Se ajusta únicamente la factory a consentimiento
pasado estable, conservando la validación estricta del dominio.

La observación de infra-v2 sí tiene productor/recibo padre estándar. Root
rectifica su lectura inicial del inventario, sin atribuir otro fallo al
implementador. Verifica helper copiado/hash, tres recibos hijos/streams,
inventario de cuatro servicios, UID/GID1000 con /tmp W+X y tres jobs existentes
desactivados con sus hashes. El helper inicia/cierra una sesión local para
leer el schedule; no modifica ni ejecuta jobs. No es integración de la
aplicación ni inventario vivo posterior. Root solo lee evidencia archivada.

El propietario arranca el focal literal ampliado
`scheduler-expanded-20261024T012128304326Z`:12 casos y213 fuentes congeladas,
handle propio11768 observado vivo. Incluye factory estable/conversión,
auth/input, progreso/replay,23claims20+3, dos cuentas sin sesión, recheck de
candidato, dos procesos de kernel HTTP, revisión10.000/planes, broker
indisponible, presupuesto lento e interrupción tras commit. El alcance
efectivo se contrastará con los resultados; no se anticipa verde ni QA HTTP
de red por una prueba de kernel.

También arranca Node nativo sin SQL
`node-client-native-20261024T012156897025Z`, handle propio90685, y PHP puro,
handle86677, sobre los mismos bytes. Estas anotaciones preservan observaciones
del propietario, no liveness futura. No se editan fuentes mientras sus
handles estén vivos. Focal final, daemon real, full, censo, revisión y
publicación siguen pendientes; medidor T15 abierto y contador **14/20**.

#### T-15 — regresión del propietario de la revisión y evidencias intermedias

El focal ampliado `scheduler-expanded-20261024T012128304326Z` terminó con
**12 pruebas y 149 aserciones**, sin cambios de las 213 fuentes durante la
ejecución (manifiesto `38248ae5273b150202f4a72a756d2d5ebc252ed153c3e2b042e58544af2c327d`).
El cliente Node nativo terminó con **7/7 pruebas** y el conjunto PHP de token,
entrada y controlador con **11 pruebas y 80 aserciones**, sobre ese mismo
manifiesto. Root comprobó los recibos terminales, los hashes de las fuentes
archivadas y los contadores de consola. Son resultados intermedios: no prueban
la integración del daemon ni aprueban el estado posterior de las fuentes.

Antes de probar el daemon se detectó una correlación insuficiente en el selector:
una campaña podía referenciar una revisión de otra campaña de la misma cuenta.
La regresión específica se escribió antes de corregir el selector. Los dos
rojos `revision-owner-red-20261024T012645937844Z` y
`revision-owner-observed-red-20261024T012839621447Z` terminaron con salida 1.
El segundo conserva la observación SQL: HTTP 503, consulta bloqueada sobre la
campaña incorrecta y cero entregas/outbox. No se atribuye este fallo a MySQL ni
al materializador; faltaba correlacionar el propietario de la revisión al elegir
el candidato.

La corrección añade al selector una comprobación de existencia de revisión por
ID, cuenta y campaña. `revision-owner-green-20261024T013011176699Z` terminó con
**1 prueba y 6 aserciones**: ante los mismos datos iniciales responde HTTP 200,
revalida bajo transacción la campaña válida y crea solamente su entrega y su
outbox. La misma prueba comprueba que una revisión inexistente se omite sin
repetir el trabajo. Entre el rojo observado y el verde sólo cambió
`app/Services/SchedulerActions.php`; materializador y publicador permanecieron
idénticos. Root verificó offline los tres recibos, los ZIP/manifiestos y la
correspondencia de los orígenes cargados con las fuentes, además de las
observaciones y aserciones causales. Evidencia privada:
`storage/phase3-t15/root-revision-owner-semantics.json`.

Los verdes del manifiesto anterior quedan como evidencia histórica. El nuevo
focal literal de 13 casos `scheduler-final-candidate-20261024T013359216245Z`
se observó en ejecución; su resultado requiere un recibo terminal. Continúan
pendientes daemon real contra aplicación nativa y Docker, gates finales sobre
las mismas fuentes, revisión independiente y publicación. T-15 sigue
**en-progreso**, con medidor abierto y **14/20** tareas cerradas.

#### T-15 — focal de 13 casos contrastado y primer preflight del daemon

`scheduler-final-candidate-20261024T013359216245Z` terminó con **13 pruebas,
155 aserciones y 178,52 s** de runner. Root verificó recibo terminal, streams,
ZIP de 213 fuentes y manifiesto
`ad77359aca2a5d82b74020fe326364d1b5e3ceb18a350bfe9bcbee9f036fd6b3`, sin
cambios durante la ejecución. También contrastó los 18 protocolos y sus
orígenes cargados: despacho 20+3, 23 mensajes distintos observados/ack,
tres claims conservados en backoff con broker indisponible, presupuesto
cooperativo que se detiene tras un claim, revisión sintética de 10.000
destinatarios, progreso tras prefijo completo, separación de cuentas,
revalidación del candidato y replay tras interrupción. El caso de 10.000
registra un EXPLAIN posterior a la materialización; no es un benchmark de
capacidad ni promete un tiempo máximo de ejecución.

La concurrencia conserva dos launchers con salida 0, dos procesos PHP
declarados y dos conexiones SQL distintas; ambos kernels responden 200 y
convergen a una sola pareja entrega/outbox. Root corrigió una suposición
incorrecta de su auditor: el PID del launcher no coincidía con getmypid de
PHP. Se conservó el auditor fallido y su diagnóstico; la versión 2 comprueba
separadamente ambas identidades, los streams, las fuentes y los resultados.
No se reconstruyó una relación padre/hijo del sistema operativo no capturada.
Estos son kernels HTTP separados, no prueba de un servidor HTTP de red.
Evidencia privada: `root-final-focal-protocol-semantics-v2.json` en
`storage/phase3-t15/`.

El primer intento real del daemon,
`cronicle-real-daemon-20261024T014234435462Z`, terminó con salida 1 sobre el
mismo manifiesto estable. El preflight encontró hashes literales diferentes
en dos scripts del perfil instalado; se conserva como diagnóstico pendiente
de comparar bytes, sin atribuir todavía la causa a finales de línea. El
propietario informa que no llegó a crear jobs ni a ejecutar aplicación/SQL,
y que restauró los montajes y retiró los ficheros propios. Root verificó el
recibo padre y las fuentes, pero la semántica completa de esa restauración
requiere aún contrastar sus recibos hijos. No se acredita integración de
Cronicle por haber arrancado el helper ni por un Shell plugin aislado.

T-15 conserva su alcance y permanece **en-progreso**. Daemon nativo/Docker,
suite final, censo, revisión y publicación siguen pendientes; **14/20**.

#### T-15 — causa del perfil Docker probada y daemon completo

La comparación archivada de los dos scripts instalados confirma equivalencia
LF tras normalizar exclusivamente CRLF; sus hashes literales distintos se
conservan. Root verifica nueve recibos hijos, sus streams y los tres hashes de
jobs existentes sin cambios. El segundo intento del daemon falla en el
lanzador nativo de QA: la prueba mínima con el mismo PHP/router distingue
cwd de proyecto (respuesta sin el contrato Laravel) y cwd público (401 JSON
con no-store). Se corrige el lanzador privado, sin cambiar la aplicación.

El tercer intento, `cronicle-real-daemon-v3-20261024T015119926713Z`, completa
nueve resultados nativos, pero falla al decodificar `docker-init`: su proceso
devuelve realmente 0 con un error SQL 42S02 por `campaigns` ausente. Un exit 0
no acredita éxito de la fixture. Se activa el método de cuatro fases de
`custom-agents:debug-root-cause` dentro de implementación; no se trata de tres
revisiones adversariales ni de un ciclo formal de QA agotado.

La observación atribuida encuentra una cuenta/usuario propios, 23 contactos y
10 migraciones aplicadas, sin filas ajenas. La recuperación
`partial-state-recovery-v3-20261024T020352551038Z` elimina sólo las filas
atribuidas, obtiene salida real 0 del rollback y deja `migrations: 0`.
Root contrasta los dos registros JSON del stream, la identidad SQL y el owner;
su lector inicial había supuesto incorrectamente un único documento JSON,
sin que fallase el verificador del recibo. El stream y el diagnóstico del
lector se conservan. `cleanup.errors=[]` del intento fallido no se usa como
prueba de haber retirado una fixture que falló antes de devolver su estado.

La causa queda probada por
`image-migration-inventory-20261024T020447306371Z`: la imagen reutilizada
contiene 10 migraciones y el ZIP congelado 15; las diez comunes tienen hashes
idénticos. Faltan las cinco posteriores de supresiones, campañas, outbox,
reservas y actividad. El override privado montaba app/config/routes/bootstrap
actuales, omitiendo database. El diagnóstico usa un contenedor propio de sólo
lectura, sin red ni Laravel/SQL; la ausencia posterior conserva inspect exit 1.
Root verifica la correspondencia con el ZIP y con las diez migraciones SQL
aplicadas. Su auditor inicial suponía que cada recibo hijo repetía argv; se
conserva y la versión 2 valida argv en el request y el helper congelado que
lo pasa a Popen, además de los hashes de streams y las salidas reales.

`daemon-v4-preflight-20261024T020722931073Z` obtiene el verde mínimo al añadir
el bind de database: 15 hashes visibles correctos. La fixture privada nueva
también prueba que un error deliberado devuelve 1 con salida depurada. No se
modifica el código de producto para resolver esta mezcla de fuentes de QA.

El daemon completo `cronicle-real-daemon-v4-20261024T020743248472Z` termina 0
sobre las mismas 213 fuentes/ad773, sin cambios. Root valida su recibo padre
y lee los resultados: nativo 24 eventos publicados (23 como 20+3 y uno tras
respuesta perdida con commit durable/replay sin duplicación); Docker 23 como
20+3; ambos con cero MailMessages y entrega a bandeja desconocida. Las pruebas
negativas de credencial y respuesta perdida conservan sus resultados de error
esperados. El propietario declara restauración de montajes, retirada de jobs
propios y liquidación SQL; el contraste completo de sus hijos y el censo final
siguen pendientes. No se transforma la publicación en confirmación SMTP ni
en correo recibido.

Pint de los 13 PHP y los componentes PHP/Node finales reportan verde. Se
conserva un intento Node nativo impedido por spawn EPERM antes de cargar
casos y su repetición local con permisos suficientes, sin alterar umbrales.
La suite literal final `php artisan test`, run
`full-final-frozen-20261024T021459718078Z`, está en ejecución según el poll de
su propietario. No se anticipa su resultado ni se libera app/SQL. T-15 sigue
**en-progreso**, medidor abierto, **14/20**; revisión y commit/push pendientes.

#### T-15 — contraste independiente del daemon y fallo observado en full

Root completa el contraste offline de v4: 29 recibos hijos y sus streams,
15 jobs correlacionados con sus requests de inicio y resultados del daemon
(12 positivos y 3 negativos esperados), UID/GID 1000, SHA del cliente montado,
seis orígenes PHP por snapshot, replay sin nuevas identidades y profundidad
real de broker 24/23 antes de retirar las topologías propias. También verifica
los 15 hashes del preflight corregido y `migrations: 0` tras cada perfil.
Los snapshots de jobs antes/después de la prueba preceden al borrado final:
prueban dos eventos desactivados y los tres hashes ajenos intactos, pero no
su ausencia posterior. Esa ausencia corresponde al censo API final aún
pendiente. Evidencia privada: `root-daemon-v4-semantics.json`.

La suite completa sigue viva según polls del propietario. El bloque Activity
señala un caso fallido en la prueba de planes de consultas; todavía no hay
excepción ni resumen terminal. La lectura estática sugiere un registro con
created_at del día actual y una ventana terminada el 23 de octubre. Es una
hipótesis pendiente de reproducción, no una causa confirmada ni permiso para
modificar las fuentes durante la ejecución. Continúa el mismo handle, sin
reinicios ni otro actor SQL. Los bloques posteriores observados en verde no
convierten el conjunto en aprobado.

Como preparación privada de T-16 se han generado un parche no aplicado que
etiqueta nueve clases relevantes con el grupo delivery y un reporter CLI de
inventario de fuentes visibles, sin arrancar Laravel ni leer env. No se han
ejecutado ni validado esos borradores; sus 132 métodos estáticos no son un
recuento descubierto por PHPUnit. Deben reconciliarse con el T-15 finalmente
aceptado antes de uso. T-16 no se inicia ni se cierra por esta preparación.
Progreso formal **14/20**; app/SQL permanecen bajo el propietario de T-15.

#### T-15 — suite completa terminada con un fallo de búsqueda

El propietario confirmó la salida real **1** de
`full-final-frozen-20261024T021459718078Z`: **226 pruebas aprobadas, una fallida,
2.684 aserciones y 2.385,19 s** de runner. Root contrastó el recibo, los streams,
el ZIP de 213 fuentes y el manifiesto `ad773`, sin cambios durante la ejecución.
El único fallo es `ActivityTest.php:509`: la búsqueda de un registro único devuelve
cero cuando se esperaba uno. Los bloques de Cronicle, supresiones, plantillas y
recuperación de workers terminaron aprobados; no convierten esta suite en verde.

La causa de calendario sigue pendiente de reproducción aislada. Primero se
asientan los recursos de la ejecución fallida y después se observarán las fechas
reales de la fixture y del filtro. No se modifica la ventana ni la validación del
producto para conseguir un verde. Si se confirma la hipótesis, se conservarán el
rojo y su fuente, se corregirá únicamente la fixture y se repetirá la suite
completa sobre el nuevo manifiesto. App/SQL continúan reservados al implementer.

Los componentes finales anteriores sí tienen contraste independiente: Pint de
13 PHP, 11 casos PHP con 80 aserciones y siete casos Node tanto nativos como Linux.
El intento nativo impedido por `spawn EPERM` se conserva separado de la repetición
local aprobada. Todo ello pertenece a la foto `ad773`; una fuente corregida exige
identificar y validar la foto nueva. T-15 permanece **en-progreso**, con revisión y
publicación pendientes; progreso formal **14/20**.

La reproducción `calendar-prototype-observed-red-20261024T025931249552Z` confirmó
la causa: prototipo fechado el 24 de octubre UTC fuera de la ventana fija que
termina el 23. Con la fecha de fixture explícita `2026-10-23 12:00:00`, el verde
`calendar-prototype-observed-green-20261024T030305752317Z` obtiene el registro
esperado y aprueba **un caso y 25 aserciones**. Root contrastó ambas observaciones,
las copias de los probes y seis orígenes cargados. El reemplazo exacto cambia solo
los timestamps de esa fixture; las otras **212 fuentes son idénticas byte a byte**.
El manifiesto nuevo es `d2653a7dc912e0660b858cc02b3abf486b75fa0ff9c5cd308533d20f2d0e8b99`.
Ventana, validación y aserciones del producto permanecen iguales. Este focal no
sustituye las pruebas literales Activity/Scheduler, la suite completa ni el censo
final que siguen pendientes. El daemon conserva su manifiesto histórico `ad773`
con continuidad de fuentes contrastada, sin atribuirle una ejecución sobre `d265`.

#### T-15 — suite corregida completa y memoria propuesta

`full-calendar-final-frozen-20261024T031209954237Z` terminó con salida real 0:
**227 pruebas, 2.701 aserciones y 2.031,14 s** de runner. Root contrastó recibo,
streams, ZIP de 213 fuentes, sus copias/orígenes atribuidos y los 22 bloques de
clases. El manifiesto final es `d265`, sin cambios durante la ejecución. También
contrastó directamente en esa foto Activity 20/237, Scheduler 13/155 y sus 18
protocolos, PHP puro 11/80, Node 7 nativo y 7 Linux, y Pint de 14 PHP.

El control preliminar de datos públicos examinó 1.309 archivos de la copia de
trabajo sin hallazgos; whitespace también pasó. No es el control del índice final,
que se ejecutará después de preparar los archivos públicos definitivos.

Tras el full verde se incorpora GOT-005 como **propuesta**, con su entrada en el
índice: documenta la imagen de QA con migraciones antiguas, el contrato del hijo
de fixture y la recuperación de estado parcial atribuible. No se promueve hasta
la revisión independiente. El censo final y la entrega explícita del runtime
siguen pendientes; T-15 permanece **en-progreso**, **14/20** cerradas.


#### T-15 — entrega final y recursos asentados

La entrega del implementador terminó con salida 0 (`finalize-handoff-20261024T035147243099Z`); root verificó sus fuentes contra la copia de trabajo: 213 archivos, manifiesto `d265`, sin cambios. El implementador liberó expresamente las fuentes, el runtime y la base de pruebas; todos sus procesos de ejecución están terminados.

Root contrastó el censo final y sus 107 recibos de hijos con los streams: 61 productores, 347 números de PID, cero procesos propios, 105 contenedores propios ausentes y 100 nombres de broker ausentes en ambos tipos mediante 200 respuestas 404. Se observaron seis PID reutilizados sin firma propia; no se atribuyeron ni se actuó sobre ellos. Las exclusiones se aplicaron antes de recoger procesos. La identidad SQL correspondía a la base de pruebas y quedó únicamente la tabla de migraciones vacía.

La comprobación final de API consultó schedule y trabajos activos: ocho identificadores propios o previstos, cero eventos propios, cero trabajos propios activos y los tres hashes de tareas de base intactos. Las cuatro llamadas autenticadas registraron HTTP 200 y código API 0; se conservan hashes y resultados saneados, no credenciales ni definiciones completas. No se consultó el historial completado: el indicador de historial solo significa que no se emitió un purgado explícito. Estas observaciones corresponden al cierre, no garantizan estado futuro.

El medidor de implementación se cerró de 00:59:32 a 03:57:45 UTC del 24/10/2026: 2 h 58 min 13 s de ventana de reloj. Sin transcripciones disponibles, tokens, coste y horas efectivas de IA permanecen desconocidos; no se convierten en cero ni se estiman como tiempo de ejecución. La revisión independiente comienza a continuación. T-15 continúa en progreso y el roadmap conserva 14/20 tareas cerradas; aún no hay commit ni push de esta entrega.



## Archivado 2026-11-03 — narrativa cerrada: T-16 — reparación autorizada tras diagnóstico (fix1)

SHA-256 del bloque LF: `6fc810695fa0366782bb23a976fcc599023b8e03463e4dd9468c564d409bd109`.

## T-16 — reparación autorizada tras diagnóstico (fix1)

El 24-10-2026 el usuario respondió **«adelante»** a seguir con el fix concreto
P-01/P-02/D-02. Esa decisión autoriza la Fase 4 de `debug-root-cause` y abre
**una sola vez** un ciclo nuevo de revisión/QA con contador de QA cero. Los
tres intentos anteriores y su NO-VERDE se conservan. No se inicia un cuarto
intento del ciclo agotado ni se cierra T-16. Implementer genérico con runtime
heredado como fallback; tier resuelto del frontmatter `sonnet/medium`, sin
afirmar un modelo real. No hay `.claude/dev.json` ni constitución; defaults
de TDD/worktree/subagentes desactivados. Se conserva la propiedad exclusiva
de fuentes, `emailing_testing` y runtime controlado hasta el handoff explícito.

Se admitió la propuesta comparando los SHA-256 de las **14/14** fuentes reales
con sus copias `before`. Primero se aplicaron únicamente las dos regresiones
PHP. **RED:** el filtro de los tres métodos nuevos/ampliados de ActivityTest
y CampaignTest falló con **3 fallos, 11 aserciones**, duración de runner
**19,42 s**, exit 1, el **2026-10-24T13:34:26Z**. Recibo privado:
`storage/qa-delivery/t16-fix1-red-20261024T133359121289Z/receipt.json`.
El batch futuro falló con `confirmed => validation.accepted`; las otras dos
pruebas no encontraron los mensajes españoles. La tanda preserva sus 232
fuentes y cero cambios, sin atribuirle cobertura de `lang/`.

Aplicados los cambios causales: traducciones españolas; marca privada por
request que conserva una confirmación explícita en el mismo batch de fecha;
invalidación ante fecha/momento en un request posterior y ante composición
sin revisar; `lang/` en snapshots, productor esperado, observación, comparador
y ambos montajes Linux. No se cambian permisos, revisión vigente, límites de
SMTP ni leases. **GREEN:** el mismo filtro terminó con **3 pasadas,
45 aserciones**, **23,78 s**, exit 0, el **2026-10-24T13:37:53Z**, con
235 fuentes archivadas y cero cambios. Recibo privado:
`storage/qa-delivery/t16-fix1-green-20261024T133709434666Z/receipt.json`.

Las pruebas de inventario también detectaron el hueco sobre los productores
anteriores: 25 métodos, exit 1, con ausencia de hash de `lang/es/validation.php`,
rechazo de esa raíz por el comparador y aceptación incorrecta del árbol de
idioma vacío. Se conserva `t16-fix1-inventory-red-20261024T133607659740Z`;
sus fallos en cascada no se presentan como defectos independientes. Los
controles nuevos exigen detectar cambios de bytes y ausencia del árbol de
idioma; la fixture Linux incorpora también ese rechazo.

Root ha aplicado D-02 al driver privado fuera de las fuentes de producto.
Su regresión aislada con Chromium y HTTP sintético falló sobre el baseline
(observó A en vez de B) y pasó con el candidato (A→B→A). Recibos privados
`storage/phase3-t16/qa-navigation-proposal/regression-{before,candidate}.json`.
El primer fallo de lanzamiento EPERM queda preservado y no cuenta como RED.
Esto valida aisladamente la espera del cambio de cuenta; **no acredita QA
formal ni comportamiento del runtime de la aplicación**.

Pendientes en este punto: controles finales de inventario, perfiles reales,
discovery/grupo delivery sobre estas fuentes, revisión fresca, QA de navegador
y M-01 humano. El medidor `2026-10-21-plataforma-emailing/T-16-fix1` se inició
a las 13:33:00Z; tokens, coste y horas IA verificables siguen desconocidos
(`null`), sin convertir el reloj de pared en horas IA. T-16 sigue en-progreso,
**15/20** original y **0/14** MCP; T-17–T-20 siguen pendientes.

### Fix1 — controles previos y grupo en curso

La ampliación de ContactListTest comprueba texto visible de consentimiento
futuro y fecha mal formada, ausencia de claves y desaparición de los avisos
al corregir la misma instancia: **1 pasada, 19 aserciones**, exit 0.
Recibo `t16-fix1-consent-green-20261024T133849357888Z`.
`python -m unittest discover -s tests/support -p "test_runtime_*.py" -v`
terminó **25 pruebas, OK**, exit 0; recibo
`t16-fix1-inventory-green-20261024T133849309784Z`. Se comprobó además
la sintaxis de nueve PHP y el AST de seis Python afectados.

La primera preparación de perfiles falló en `Set-Acl` antes de crear
ownership/servicios/fixtures SQL. Se conserva el recibo fallido
`t16-fix1-profiles-20261024T133911851643Z`; su archivo privado se retiró sin
leerlo y el bootstrap-settlement registra esa retirada. La reejecución con
escalación autorizada terminó **exit 0**, sin cambios de fuentes, en
`t16-fix1-profiles-20261024T133949915458Z`. Las 235 entradas incluyen los
tres archivos de `lang/es`. El inventario nativo observó **11.592 archivos**
y cada uno de PHP web, consumidor de dominio y jobs Linux observó **9.229**;
los cuatro comparadores terminaron sin ausencias, extras, cambios ni
diferencias de metadatos. Se contrastaron build/dependencias por perfil,
sin construir el esperado a partir de la observación. Web assets y Nginx
pasaron sus comprobaciones independientes. El settlement registra cero
errores y procesos nativos terminales; la parada intencional de Apache
conserva **exit 1**, no se convierte a cero. Las dos imágenes UUID se
retienen como artefactos para estas pruebas y la revisión.

La fixture Linux con `www-data`, red desactivada y montaje de sólo lectura
pasó **10/10 casos**, incluido árbol de idioma vacío, sin restos de fixture.
El primer acceso al pipe Docker fue rechazado por el sandbox y se conserva
como fallo de entorno; el recibo válido de la ejecución escalada es
`t16-fix1-linux-inventory-20261024T134215467105Z` (exit 0). No se presenta
el rechazo del pipe como un control RED del producto.

El descubrimiento literal `php artisan test --group=delivery --list-tests`
terminó **exit 0** con **139 identificadores únicos, nueve clases** en
`t16-fix1-discovery-20261024T134703488037Z`. Su manifiesto de 235 entradas
coincide byte por byte con el de perfiles y los controles finales. Se lanzó
`php artisan test --group=delivery` en
`t16-fix1-delivery-20261024T134817694040Z`, handle **89620**, con la imagen
del perfil `emailing-t16/php:602edb08c3844cbf9089d86c38a9cb44`.
**Está en curso: todavía no hay veredicto del grupo.** No se reiniciará por
un timeout de observación. Se mantienen congeladas las 235 fuentes durante
la tanda y continúa la propiedad exclusiva de base/runtime.

Se añadió **GOT-007**, estado propuesta, y su fila de índice: la causa raíz
del batch superó el umbral de memoria de `debug-root-cause`. No se duplica
GOT-001 (errores conservados entre operaciones), cuya causa es distinta.
`ledger-lint` devuelve **0 incoherencias, cinco avisos** de Changelog
ausente para T-16–T-20 todavía abiertas; no habilita el cierre de ninguna.

### Fix1 — resultado terminal de desarrollo y auditorías

El mismo handle **89620** terminó con **exit 0**. El comando literal
`php artisan test --group=delivery` ejecutó **139 pruebas aprobadas y
2009 aserciones**, duración de runner **4056,19 s**. Recibo:
`storage/qa-delivery/t16-fix1-delivery-20261024T134817694040Z/receipt.json`,
inicio 13:48:20Z y cierre 14:56:18Z del 24-10-2026. No se reinició la tanda.
Las **235 fuentes** permanecieron congeladas; el manifiesto
`10128ad501b6c3f0266896c23b4b87a76f405b284de2a37d6c6a4ec79f242120`
coincide con discovery y perfiles. Se verificaron ZIP, hashes de streams y
fuentes actuales. El número de casos por cada una de las nueve clases coincide
con los identificadores reales del descubrimiento; no se usó 137 como umbral.
Las duraciones observadas no aíslan una causa de latencia ni son un SLA del
producto. No se cambiaron límites SMTP ni leases.

Auditorías terminales, todas con **exit 0**, 235 fuentes y cero cambios durante
cada ejecución; prefijo común `storage/qa-delivery/`:

| Run | Evidencia real |
|---|---|
| `t16-fix1-admission-corrected-20261024T145821549682Z` | 139 identificadores y casos, 2009 aserciones, igualdad de fuentes, 21 recibos de teardown sin errores y procesos nativos del perfil terminales |
| `t16-fix1-group-inventory-20261024T145714273170Z` | 45 documentos de inventario observados coinciden con esperados independientes de los artefactos de esta tanda; montajes extraídos del código congelado, incluido `lang/` de sólo lectura |
| `t16-fix1-resource-docker-20261024T145722039761Z` | Un proyecto propio y 47 nombres de contenedor UUID comprobados; cero recursos de runtime restantes; dos tags de imagen retenidos como artefactos de revisión |
| `t16-fix1-resource-broker-20261024T145728218035Z` | 71 observaciones pasivas de namespaces worker/perfiles registrados, todos ausentes; sin crear ni borrar topología |
| `t16-fix1-resource-mailpit-20261024T145728937737Z` | Cinco IDs ya atribuidos a fixtures observados con 404; sin leer mensajes ajenos ni borrar mensajes durante la auditoría |
| `t16-fix1-sql-settlement-20261024T145714561507Z` | Identidad protegida real de `emailing_testing`; `migrations: 0`; las otras tablas comprobadas están ausentes y se registran como `null`, no como recuentos medidos de cero |

La comprobación pasiva de broker cubre los UUID de los 21 workers y de los dos
perfiles. Los casos outbox conservan su evidencia de limpieza síncrona en
`finally` dentro del grupo aprobado; no se afirma una observación pasiva
independiente de nombres que esos casos no registraron. La inspección de
procesos se limita a recibos y teardown propios; no hubo inspección global de
PIDs, limpieza global de Docker/broker/Mailpit ni operaciones sobre `emailing`.
La parada intencional de Apache sigue registrada con exit 1.

Se conserva también el primer run de admisión
`t16-fix1-admission-20261024T145714273155Z`, **exit 1**: el lector privado
contó el footer `Duration: 4056.19s` como un caso 22 de WorkerRecovery, frente
a sus 21 identificadores. La diferencia exacta se aisló; se corrigió únicamente
ese lector, que admitió después los mismos archivos y streams. No fue un fallo
del grupo ni se reescribió su resultado. Se conservaron igualmente los fallos
previos de ACL, pipe Docker y observación WMI como fallos del entorno.

El monitor intermedio de directorios incluyó cuatro vacíos históricos porque
el inventario previo sólo enumera archivos. Se corrigió la atribución con la
fecha de creación respecto al inicio del run. Ese monitor no fue fuente de
veredictos ni de las auditorías terminales, que usan los archivos atribuidos por
el productor. La comprobación independiente de raíz admitió además las
regresiones y discovery en `storage/phase3-t16/root-fix1-focused-admission.json`.

Diff revisable sin Git en `storage/phase3-t16/fix1/implementation.patch`, SHA-256
`0cea4b5f588201e10b8735296b1c1d7a063ba2e7da507d0c19fab33ef1e949af`:
15 fuentes de producto/tests, más este ledger, el índice de conocimiento y
GOT-007 propuesta. El inventario de cambios y hashes está en
`storage/phase3-t16/fix1/source-changes.json`. No se alteraron los informes QA
previos. No se hicieron commits ni pushes desde este implementador.

El backend queda listo para el handoff; **no se cierra T-16**. Faltan revisión
fresca del incremento, QA de navegador del ciclo nuevo y M-01 de una persona.
Se mantienen **15/20** y **0/14**; T-17–T-20 siguen pendientes. Tokens, coste
y horas IA verificables de fix1 permanecen **`null`**, sin derivarlos del reloj.

El medidor `2026-10-21-plataforma-emailing/T-16-fix1` se cerró el
**2026-10-24T15:00:03Z**, antes de la revisión fresca. Su salida conserva
`tokens_reales: null`, `eur: null`, `horas_ia: null`, `duracion: null` y el
aviso de transcripciones no disponibles. El reloj de 1h27m no se imputa como
horas IA. Recibo privado: `storage/phase3-t16/fix1/usage-meter-close.json`.

**Handoff y liberación explícita:** el implementador libera a root las 235
fuentes congeladas, `emailing_testing` y el runtime controlado a las
**2026-10-24T15:03:02.608239+00:00**. Todos los handles propios y los seis audits
finales están terminales; no queda ninguna suite o servicio propio pendiente.
El manifiesto se volvió a contrastar sin ejecutar app/SQL/servicios. Root puede
congelar la revisión 19 y, después de su puerta, iniciar el ciclo QA nuevo.
`storage/phase3-t16/fix1/handoff.json` enumera 20 runs (éxitos, diagnósticos y
relación final/histórica por igualdad real de manifiesto), 12 validaciones
finales recomendadas y 25 archivos de evidencia con hashes. El puntero
operacional `state.json` refleja ahora la liberación. T-16 sigue abierta.



## Archivado 2026-11-03 — narrativa cerrada: Implementación T-18-fix1 — 2026-10-25 (sellos UTC)

SHA-256 del bloque LF: `fa67aca6f9664afd7e5a92656fec268248cf99d5b5fadf41900bd355942586ae`.

## Implementación T-18-fix1 — 2026-10-25 (sellos UTC)

Los tres gaps Important de revisión 22 se verificaron y corrigieron; se entregan a revisión 23, pasada local 2/3. **T-18 sigue en-progreso**, principal **17/20** y Google/MCP **0/14**. QA formal/E2E-10 y M-01 humana siguen pendientes. Rol implementer genérico heredado, sin atribuir modelo de frontmatter. Sin `dev.json`: defaults false, sin TDD obligatorio ni porcentaje de cobertura inventado.

- **AB-01, RED→GREEN**: `t18-fix1-red-20261024T213955774947Z`: 3 fallos/16 aserciones. Propiedades NUL producían 503, incluso antes de detectar duplicados o gramática inválida. El scanner acumula propiedades y convierte cada objeto al finalizar, manteniendo tipos y detección de duplicados decodificados. HTTP final: seis 422 y dos 400, cero trabajo parcial; incluye nombres con apariencia protegida/privada PHP. Se conserva el rojo unit intermedio: su oracle exigía roundtrip `json_encode` de NUL, que PHP omite al codificar aunque `get_object_vars` lo conserva; la prueba corregida exige visibilidad para esquema y rechazo HTTP. Claves numéricas, objetos/arrays vacíos y el oráculo Decimal de 142 lexemas siguen verificados.
- **D-01, RED→GREEN**: 100 snapshots con 40.000.000 bytes de cuerpos ASCII admitidos. SQL real `SELECT *` → `SELECT id,email`; strings efectivamente hidratados **40.011.392 → 5.792 bytes**, misma respuesta de **17.115 bytes**, orden/estados/recibos conservados. Presupuesto de regresión: 65.536 bytes. Sin atribuir OOM o latencias no medidas.
- **D-02, RED→GREEN**: 100 destinatarios nuevos/10.000 contactos producían **1.000.301 lecturas Handler**, con 1.000.000 `Handler_read_next`; `EXPLAIN ANALYZE` recorría 10.000 contactos por búsqueda. Solo el índice seguía dando 10.006 lecturas con `email OR id IN (...)` (probe conservado). La nueva migración `000014` añade `(account_id,email)` histórico y la política separa accesos por email e IDs bajo el mismo mutex/transacción. Resultado: **301 lecturas** en los 100 directos y **5–7** por escenario bound. Plan real usa ambos prefijos del índice histórico y PK para IDs. Conserva decisiones por cuenta, consentimiento futuro, borrado/recreación, desaparición del ID y cambio de identidad. **17 migraciones históricas byte-idénticas**; replay nuevo y ventanas antiguas restaurados cronológicamente.

**Verificación declarada y regresiones finales**, con ZIP/hashes/streams nuevos bajo `storage/qa-delivery/`:

```text
t18-fix1-unit-green-20261024T214556818913Z
php artisan test --filter=ApiJsonNumberTest
Tests: 3 passed (244 assertions); exit 0
t18-fix1-sending-20261024T214610253926Z
php artisan test --filter=SendingApiTest
Tests: 28 passed (767 assertions); exit 0
t18-fix1-migration-contacts-20261024T220652198953Z
php artisan test --filter=ContactListTest|incremental_migration_down_up_is_repeatable|migration_backfills_only_unique|historical_starts_without
Tests: 19 passed (282 assertions); exit 0
```

Los tres runs, runtime e inspector comparten **262 fuentes**, manifiesto SHA-256 `6ecda20e3bc2055f7a101ec981d64c08fe4555c3159b95f28edc094ea297b2fc`, sin cambios durante ejecución. `delivery24` permanece evidencia anterior al fix y no acredita el código corregido; no se repitió automáticamente su grupo completo. OpenAPI T-17 conserva SHA-256 `e56b967b1a7796113460ef370c0a4a0a63d50539f9b113e8bc45d5d314836c5f`.

**Runtime y recursos**: el primer runtime abortó antes de ownership/build/DB/Docker por `Set-Acl` denegado. Su archivo privado quedó protegido con owner actual/SYSTEM y sin herencia, sin leer contenido. `t18-fix1-runtime-green-20261024T220047937806Z` termina 0 con imágenes nuevas, perfiles nativo/Docker y **27 fuentes desplegadas en seis inventarios**. Wrapper calificado sin `down -v`; dos volúmenes UUID retirados individualmente tras verificar etiquetas y ausencia de conexiones. `t18-fix1-settlement-green-20261024T221024808967Z` termina 0: **19 settlements API, 19 procesos hijos/relay terminales**, dos colas/un exchange propios ausentes y ningún contenedor del proyecto UUID. Se conserva el primer fallo de inspector por permiso del pipe Docker; inspección autorizada elevada posterior 0, fuentes intactas. SQL protegido observa solo `migrations`, cero migraciones aplicadas; tablas de aplicación ausentes, no recuentos ficticios de filas cero. Dos imágenes nuevas UUID retenidas para handoff; sin cleanup global ni cambios a datos/volúmenes operativos.

**Gobierno y conocimiento**: ADR-007 aplicada; GOT-008/009/010/011 siguen propuestas. Cabeceras heredadas alineadas: fase 3 completada técnicamente T-10–T-16/7 de 7; fase 4 en-progreso. M-01 humana permanece pendiente. Sintaxis, Pint, OpenAPI, guardrails, ledger, scope, diff y privacidad pasan con recibos privados finales; cuatro avisos históricos del ledger conservados. Sin interop, publicaciones, commit o push. Índices de métricas NUL/status/direct/bound/EXPLAIN/replays, fuentes y recursos en `storage/phase4-t18-fix1/`. Medidor `2026-10-21-plataforma-emailing/T-18-fix1` cerrado antes de liberar a revisión; sin telemetría, tokens/coste/horas IA reales desconocidos, sin convertir reloj en consumo.


