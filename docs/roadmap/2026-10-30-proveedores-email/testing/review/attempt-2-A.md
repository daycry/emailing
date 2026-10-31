# Revisión adversarial — Lente A, intento 2 de 3

**Veredicto: sin gaps pendientes en la corrección revisada.** Critical: 0; Important: 0; Minor: 0. A1-01/C1-01, A1-02/B1-01 y A1-03 quedan corregidos con el alcance y los límites descritos abajo. Este veredicto permite continuar las puertas posteriores; no acredita regresión global, QA de navegador ni cualificación privada real.

Base `74970e0af4c5e68bd3b8e139a0988a119208493e`; HEAD comunicado `effd48ada42155686f7bc8e95c379cbdf566782b` más worktree y nuevos públicos. Revisor independiente de contexto fresco, subagente genérico con modelo heredado real por ausencia de `reviewer` nativo. Se leyeron y aplicaron SKILL.md y el prompt literal A de custom-agents 2.3.0, incluidos contrato OpenAPI y puertas relajadas. Esta salida pertenece exclusivamente a A; B/C/D tienen revisión propia.

## Traspaso y alcance

Leídos los cuatro informes públicos `testing/review/attempt-1-{A,B,C,D}.md` y la fusión de `tasks.md:322`. Se conserva su juicio sobre lo aprobado y rebatido: se reevalúan únicamente las correcciones, sus llamantes y las pruebas afectadas. No se han encontrado evidencias nuevas que obliguen a reabrir otros criterios. El estado general sigue `en-progreso`; sólo T-01/T-02 están completadas. T-03–T-06 y sus casillas pendientes no se presentan como realizadas.

La evidencia `storage/providers-email-20261030/scope-review2.json` declara 116 rutas, ninguna fuera de alcance, ningún criterio alterado, `avisos: []`, `info: []` y ninguna exclusión de usuario. La exclusión estándar registrada es `.claude/.progress-last.lock`. Se contrastaron los archivos de las correcciones con `Archivos`: transporte/soporte SMTP en T-03, política API en T-04 y UI/actividad/contratos/guías/fixtures en T-02/T-06. Todos corresponden al objetivo de los respectivos criterios.

## Tabla por tarea y criterio afectado

✓ significa conformidad de la corrección examinada; no cierre de toda la tarea. ✗ en la última fila identifica una puerta expresamente pendiente, no una verificación inventada ni un gap nuevo de implementación.

| Tarea | Criterio | ✓/✗ | Evidencia y escenario |
|---|---|---|---|
| T-03 | CA-04 — TLS verificado, perfiles y autenticación SMTP | ✓ | `app/Services/ObservedSmtpTransport.php:70` lanza una excepción propia ante fallo EHLO cuando TLS es obligatorio; `app/Support/SmtpPolicyViolation.php:5` no implementa la interfaz que captura el fallback Symfony. `SmtpConnector.php:27` exige STARTTLS/SMTPS y `:33` conserva verificación de certificado/nombre. La sesión SMTP válida STARTTLS y SMTPS sigue presente en `ProviderSmtpWireTest.php:59`; la prueba exige perfil fijo y política activa en `:41`. |
| T-03 | CA-05 — negativo temporal/permanente por código/etapa | ✓ | `ObservedSmtpTransport.php:82` conserva el negativo de negociación y evita que RSET/QUIT lo borren. `SmtpConnector.php:47` lo convierte en `SmtpRejected`; `ProviderSmtpTransport.php:33` permite retry únicamente por código4xx. `ProviderSmtpWireTest.php:67` prueba AUTH535 permanente, `:69` AUTH454 temporal, y `:70` recuperación de LOGIN535 mediante PLAIN válido. El éxito de negociación limpia el fallo en `ObservedSmtpTransport.php:59`, evitando envenenar un envío recuperado. |
| T-03/T-06 | CA-16 — impedir degradación EHLO→HELO y no exponer errores | ✓ | En el vendor instalado `EsmtpTransport.php:162` sólo captura `TransportExceptionInterface` antes del retorno HELO de`:164`; la excepción propia alcanza `SmtpConnector.php:44` y `ProviderSmtpTransport.php:31`, produciendo rechazo local `smtp_tls_required` sin retry. `ProviderSmtpWireTest.php:37` exige cero AUTH/sobre/DATA/correlación en downgrade y`:46` el diagnóstico. `MailProviderSmtpServer.php:22` rechaza EHLO y`:25` aceptaría HELO: el peer conserva exactamente la vía adversarial que antes permitía DATA. No se usa `SocketStream::isTLS()` como supuesto estado STARTTLS. |
| T-06 | CA-05/08/13 — fallo terminal sin nueva generación durable | ✓ | `tests/Feature/MailProviderDeliveryTest.php:261` inyecta negativo AUTH535 y violación TLS en el transporte real del pipeline. Comprueba estado failed, attempts1, generation1, un solo outbox ready y ausencia de nuevo claim en`:272`–279. Su ejecución figura entre los casos del recibo SQL parcial; el único fallo de esa tanda era el texto legado, no este caso. La combinación del cable real y esta prueba de lifecycle cubre ambos lados de la frontera sin afirmar que el Feature use cable real. |
| T-04/T-06 | CA-16 — certificado HTTPS rechazado con oráculo válido | ✓ | `ProviderApiWireTest.php:39` exige booleano TLS observado por el servidor y`:40` cero request/auth/correlación para CA no confiable;`:44` exige curl60 y ausencia de status HTTP. `mail-provider-http-probe.php:28` verifica `verify=true` y redirects desactivados antes del seam;`:33` obtiene el error estructurado con `TransferStats::getHandlerErrorData()`. No se sustituye la política de producto ni se da por aceptado el certificado por el mero estado del peer TLS1.3. |
| T-04 | CA-07 — Retry-After acotado y sin retry implícito | ✓ | `ProviderApiTransport.php:24` sustituye la constante de fecha deprecada por su formato literal;`:151` mantiene parseo estricto UTC, round-trip y límite1–86400s. La clasificación y una única llamada de la API se conservan. Los tests numéricos/malformados permanecen en `ProviderApiTransportTest.php:160`; no se atribuye a esta tanda una nueva prueba de fecha que no existe. |
| T-06 | CA-14/15 — resultado legado conservado y diagnóstico seguro visible | ✓ | `MailResult.php:28` conserva aviso Mailpit y`:31` restaura `Aceptación SMTP no confirmada.`. `resources/views/livewire/mail-settings.blade.php:22` usa label para la tabla. `ActivityService.php:23` ofrece diagnóstico TLS cerrado. `tests/Feature/MailTransportTest.php:394` mantiene su assert histórico y los asserts de secretos en`:398`–400; `git diff 74970e0 -- tests/Feature/MailTransportTest.php` no tiene cambios. QA integral de UI sigue pendiente. |
| T-02/T-06 | CA-15/16 — contratos/pins coherentes, sin ruptura injustificada | ✓ | `docs/api/mcp-tools.json:818` y`:1483` añaden `smtp_tls_required` a la enumeración cerrada; `docs/api/README.md:62` documenta su semántica local/no retry. El caso de fixture `tools.json:18540` exige su validez. Comprobaciones propias de lint/diff y pins detalladas abajo, todas exit0. La autorización booleana del remitente ya fue aprobada y versionada en A1; no se reabre. |
| T-06 | CA-18 — guía actualizada y cualificación real separada | ✓ | `docs/aplicacion/plantillas-y-transporte.md:97` distingue preparación genérica de perfiles fijos;`:99` reconoce proveedores/feedback/supresiones/cuotas implementados y mantiene M-01 privada pendiente. Resuelve la contradicción A1-03 sin afirmar DNS ni entrega real. |
| T-06 | CA-17 y parte global de CA-14 — regresión final y QA local independiente | ✗ Pendiente | `tasks.md:297`,`:304` y`:342` conservan regresión proporcional y QA de navegador como puertas posteriores. La tanda final de16 casos fue comunicada en marcha al despachar esta lente, sin resultado final consultado. No se transforma el verde focal en verde global. `test-plan.md:140` y`:150` mantienen M-01/M-02 pendientes. |

## Resolución de gaps anteriores

| ID anterior | Grado anterior | Escenario | Veredicto y evidencia actual |
|---|---|---|---|
| A1-01 / C1-01 | Critical | EHLO500/HELO250 evitaba STARTTLS y permitía DATA en claro | **Corregido**. `ObservedSmtpTransport.php:70`–75 y excepción propia impiden alcanzar el fallback; cable downgrade exige cero AUTH/sobre/DATA y diagnóstico no reintentable (`ProviderSmtpWireTest.php:37`,`:45`,`:68`). |
| A1-02 / B1-01 | Important | AUTH535 acababa como preterminador reintentable | **Corregido**. Negativo preservado en `ObservedSmtpTransport.php:82`; clasificación `SmtpConnector.php:47` y `ProviderSmtpTransport.php:33`; AUTH535/454/fallback válido por cable (`ProviderSmtpWireTest.php:67`–70) y resultado durable terminal (`MailProviderDeliveryTest.php:261`). |
| A1-03 | Minor | La guía decía que las funciones del proveedor seguían pendientes | **Corregido**. `docs/aplicacion/plantillas-y-transporte.md:99` describe implementación y reserva cualificación privada M-01. |

No quedan gaps nuevos ni reabiertos. No se cuentan como defectos de esta revisión las puertas posteriores declaradas pendientes.

## Ejecuciones acreditadas y límites de verificación

Se leyeron directamente `test-summary.json` y `command-receipt.public.json` de las tandas siguientes; las cifras no se suman entre tandas solapadas:

| Carpeta bajo `storage/providers-email-20261030/` | Resultado del recibo | Uso de la evidencia |
|---|---|---|
| `fix1-smtp-red-20261030T231542184328Z` | exit1;2 casos/38 aserciones;2 fallos;505 fuentes estables | Conserva el rojo anterior al fix de AUTH535 y downgrade. |
| `fix1-unit-final-20261030T232930242625Z` | exit0;90 casos/1045 aserciones;0 fallos/errores/omitidos;506 fuentes estables | Incluye13 sesiones SMTP y8 HTTPS. Los IDs confirman los cinco casos SMTP nuevos y ambos certificados HTTPS no confiables. No es regresión SQL/UI. |
| `fix1-lab-and-smtp-worker-20261030T232929000982Z` | exit1;12 casos/214 aserciones;1 fallo;identidad real testing;506 fuentes estables | Conserva el fallo de texto de `test_smtp_failures_are_sanitized...`; incluye el Feature terminal535/TLS y los otros11 casos sin fallo. No se llama verde. |
| `fix1-legacy-notice-20261030T233512752157Z` | exit0;1 caso/8 aserciones;0 fallos/errores/omitidos;identidad real testing;506 fuentes estables | Acredita el texto corregido con el test histórico intacto; no reemplaza una repetición completa. |

Las tareas ya cerradas conservan verificación real: recibos de `t01-config-green2-20261030T210717012942Z`20/265, `t02-common-final-20261030T212849379067Z`41/61 y `t02-mysql-schema-green-20261030T212641710653Z`1/13, todos exit0/sin fallos/errores/omitidos y fuentes estables. T-01 y schema acreditan identidad SQL testing real. Esta lente consultó los recibos y no ejecutó sus pruebas para tapar falta de evidencia. Los estados T-03–T-06 siguen abiertos.

## Comprobaciones propias de contratos y puertas relajadas

Se ejecutó el `openapi-lint.py` oficial de api-contract2.3.0 con `--diff` frente a las versiones obtenidas mediante `git show 74970e0:docs/api/<nombre>`. Las copias temporales públicas se eliminaron al terminar:

| Comando propio | Resultado |
|---|---|
| `openapi-lint.py --diff <base-openapi.yaml> docs/api/openapi.yaml --json` | exit0; cambios[];rompedores0 |
| `openapi-lint.py --diff <base-oauth.json> docs/api/mcp-oauth.openapi.json --json` | exit0; cambios[];rompedores0 |
| Lint `docs/api/openapi.yaml` | exit0;errores[];avisos[] |
| Lint `docs/api/mcp-oauth.openapi.json` | exit0;errores[];avisos[] |
| Lint `docs/api/mail-provider-webhooks.openapi.json` | exit0;errores[];avisos[];spec nueva sin base |
| `python scripts/check-mcp-contract.py` | exit0;`MCP contract 0.2.0: 37 operations, 76 embedded schemas, 599 tool / 26 error / 48 OAuth cases; 34 cursor reference cases; 0 failures` |

El linter estructural no sustituye lectura semántica de `$ref`. La comparación contra el candidato público previo confirma que la modificación adicional del catálogo es la enumeración `smtp_tls_required`; la autorización independiente y el caso455→válido ya estaban juzgados y documentados en A1. El nuevo caso de rechazo TLS es aditivo. No cambian fixtures0.1.0. No se actualizó un golden para ocultar el defecto.

No se han eliminado/saltado tests ni bajado umbrales, reglas o asserts para conseguir el verde. La sustitución del oráculo `peer tls=false` por curl60/no HTTP conserva la garantía requerida con una observación del cliente pertinente; exige además que las sesiones confiables sí establezcan TLS. Las expectativas de mocks pasan a exigir llamada, sin ocultar rutas no ejercidas. El texto legado se corrige en producto y su assert permanece intacto.

Sin `docs/CONSTITUTION.md` aplicable. Interop no aplica: las correcciones no tocan `commands/`, `agents/` ni `hooks/` del producto. La regla docs-style de iniciativa mayoritariamente prosa no aplica al diff de código; A1-03 es coherencia funcional documental. No hay porcentaje de cobertura acreditado/configurado que permita inventar una cifra; casos/aserciones no son cobertura porcentual.

No se ejecutaron tests SQL/E2E, DDL, probes de red, proveedores reales, inventarios globales de procesos/contenedores ni operaciones sobre PIDs ajenos. No se leyeron ni calcularon hashes de `.env`/entornos privados, request/owner/process/settlement/fixtures privados, PEM privados, dumps, ZIP ni rutas prohibidas del encargo. La única entrega persistida por esta lente es este informe; no se modifica ledger, código, contrato ni Jira.

Siguiente paso: fusionar A2 con las otras lentes; completar la regresión final y después QA independiente con sus evidencias originales. M-01 y M-02 conservan su estado y su aceptación humana propia.
