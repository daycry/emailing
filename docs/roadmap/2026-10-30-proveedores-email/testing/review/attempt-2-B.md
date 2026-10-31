# Revisión B — intento 2 de 3

**Veredicto: B1-01 corregido; sin defectos nuevos de corrección demostrables en los fixes.** Critical: 0; Important: 0; Minor: 0 pendientes de esta lente.

Base `74970e0af4c5e68bd3b8e139a0988a119208493e` hasta el worktree congelado, incluidos nuevos públicos. Scope-check recibido: exit 0, 116 rutas, sin fuera de alcance ni alteración de criterios. Revisor independiente de contexto fresco; subagente genérico con modelo real heredado por ausencia del reviewer nativo. Método: `custom-agents` 2.3.0, `adversarial-review`, prompt literal B completo y persona backend compartida (sin persona local). Sólo corrección, límites, carreras y regresiones; sin estilo ni refactors.

Se han leído la fusión completa de `tasks.md` y los cuatro informes `attempt-1-{A,B,C,D}.md`. Se conserva lo aprobado/rebatido: esta segunda lectura reevalúa lo corregido y sus llamantes, sin reabrir asuntos previos sin evidencia nueva. La conformidad general A, seguridad C y rendimiento D siguen perteneciendo a sus lentes.

## Revalidación estructurada

| ID / comprobación | Resultado | Evidencia y escenario |
|---|---|---|
| B1-01: AUTH535 definitivo no se reprograma | ✓ Corregido | `app/Services/ObservedSmtpTransport.php:82` conserva el último negativo de negociación; RSET/QUIT no lo sustituyen, y el HELO exterior de Symfony no pisa el AUTH retenido. `app/Services/SmtpConnector.php:47` lo toma si no existe fallo de mensaje y `:54` lanza `SmtpRejected`. `ProviderSmtpTransport.php:33`–35 produce `retryable=false` para 535. |
| AUTH454 temporal y recuperación de autenticador | ✓ Sin regresión observada | El mismo código conserva 454 como temporal; una respuesta válida limpia el negativo en `ObservedSmtpTransport.php:59`, y llegar a MAIL lo limpia en `:43`. El fallback LOGIN fallido→PLAIN válido termina aceptado, no arrastra el 535 anterior. Wire datasets `auth-535`, `auth-454` y `auth-fallback`, `tests/Unit/MailProviders/ProviderSmtpWireTest.php:67`–70. |
| Resultado durable de B1-01 | ✓ Corregido | Seguidos `MailProviderTransport::send` → `AccountMailTransport.php:192` → `:161` → `DeliveryLifecycle.php:187`/`:203`. La clasificación del adaptador externo vuelve como resultado, por lo que no llega al catch retryable del Mailpit. Sin retryable, el lifecycle finaliza failed y no incrementa generación. Test nuevo `tests/Feature/MailProviderDeliveryTest.php:261` exige failed, un intento, generación1, un único delivery.ready y claim posterior nulo para AUTH535 y política TLS. |
| Guarda ESMTP/TLS y compatibilidad STARTTLS | ✓ Sin defecto de corrección nuevo | `ObservedSmtpTransport.php:70` lanza `SmtpPolicyViolation` ante EHLO fallido con TLS requerido. Su herencia RuntimeException (`app/Support/SmtpPolicyViolation.php:5`) evita el catch TransportExceptionInterface del fallback Symfony. No usa isTLS como señal de STARTTLS real. `SmtpConnector.php:44` conserva la excepción y `ProviderSmtpTransport.php:31` la convierte en rechazo terminal `smtp_tls_required`. El modo plain conserva el fallback de laboratorio. La revalidación de seguridad completa de C1-01 corresponde a C2. |
| Fecha HTTP y diagnóstico público nuevo | ✓ Sin regresión observada | `ProviderApiTransport.php:24`/`:151` sustituye el formato obsoleto por el mismo formato literal GMT con zona UTC y comparación de ida/vuelta. Se mantienen límites y fallback de Retry-After. Se leyeron diagnóstico en Activity, enumeraciones REST/MCP, fixture599 y pin de ContractArguments; no se observó un consumidor que rechace el diagnóstico nuevo. |
| Aviso legacy y etiqueta de tabla | ✓ Sin regresión observada | `app/Support/MailResult.php:9`/`:24` separa label/notice. `app/Livewire/MailSettings.php:123` conserva el aviso SMTP incierto legacy; la tabla usa label (`resources/views/livewire/mail-settings.blade.php:22`). `Templates.php:121` conserva su aviso particular de log. El diff de `tests/Feature/MailTransportTest.php` está vacío: no se alteró el assert para ocultar el fallo. |
| Oráculo HTTPS y expectativas de mocks | ✓ No se identifica debilitamiento que oculte el fallo | `ProviderApiWireTest.php:37`–46 permite el booleano TLS del servidor, pero exige curl60 del cliente, ausencia de respuesta HTTP y request/auth/correlación falsos con CA ajena. `mail-provider-http-probe.php` obtiene el error de on_stats del CurlHandler real y mantiene verify=true. Un servidor TLS1.3 puede terminar su mitad antes de recibir la alerta; su booleano no era prueba de confianza del cliente. Los mocks SMTP incorporan expects(once), que refuerza la cardinalidad. |

La lectura del framework confirma el recorrido que cerraba B1-01: `vendor/symfony/mailer/Transport/Smtp/EsmtpTransport.php:243` llama a los autenticadores, `:250` ejecuta RSET y `:269` envuelve el fallo. Su `executeCommand` de `:146` convierte HELO en negociación EHLO/AUTH, por lo que también se verificó la excepción que recorre ese wrapper. El rechazo conservado no depende de que Symfony vuelva a lanzar el UnexpectedResponseException original. También se siguieron los fallos de greeting/HELO directo, la prioridad de firstFailure de mensaje y la aceptación/posible aceptación existentes; los fixes no convierten un resultado posterior al terminador en un reintento.

## Evidencia existente inspeccionada

Se leyeron exclusivamente `test-summary.json` y `command-receipt.public.json` seguros en estas carpetas de `storage/providers-email-20261030/`. Son ejecuciones previas del implementador, no tests propios de esta lente:

| Carpeta | Resultado observado | Límite |
|---|---|---|
| `fix1-smtp-red-20261030T231542184328Z` | exit1; 2 casos/38 asserts/2 fallos; fuentes505 estables | Rojo previo: AUTH535 y downgrade. |
| `fix1-unit-final-20261030T232930242625Z` | exit0; 90 casos/1045 asserts, 0 fallos/errores/omitidos; fuentes506 estables | Incluye13 sesiones SMTP y8 HTTPS. No acredita SQL ni QA global. |
| `fix1-lab-and-smtp-worker-20261030T232929000982Z` | exit1; 12 casos/214 asserts, 1 fallo; fuentes506 estables | El único problem_case es el aviso SMTP legacy. La prueba durable AUTH/TLS figura ejecutada sin fallo. No se etiqueta la tanda como verde. |
| `fix1-legacy-notice-20261030T233512752157Z` | exit0; 1 caso/8 asserts; fuentes506 estables | Focal del aviso restaurado. No sustituye la regresión completa. |

La regresión final de16 casos se recibió como en marcha y no se da por verde. Tampoco se suman tandas solapadas ni se afirma un único snapshot anterior al último ajuste de texto. QA independiente y validación privada de proveedor siguen fuera de este veredicto.

## Cobertura y límites

Se revisaron los fixes SMTP/API/MailResult, los tests wire y durable nuevos/ajustados, sus fixtures/probes, los consumidores de diagnóstico y las rutas framework/llamantes mencionadas. El inventario público se comprobó con git, excluyendo `.claude/**` y `tests/.runtime/**`; el conjunto anterior permanece como revisión ya juzgada, no como nueva auditoría íntegra pretendida en intento2. No se halló evidencia nueva que obligue a reabrir aprobaciones anteriores. A1-03 documental queda a la lente A.

No se ejecutaron suites, SQL/E2E, probes, llamadas externas, inventarios de procesos/contenedores ni cambios de aplicación/tests/docs. No se leyeron/hasharon archivos de secretos, entornos privados, request privados, PEM privados, dumps, ZIP ni las rutas prohibidas. Única escritura: este informe. No se atribuyen porcentajes de cobertura ni verde global.

```json
{"lens":"B","attempt":2,"status":"complete","verdict":"sin defectos nuevos","previous_findings":[{"id":"B1-01","status":"corregido"}],"gaps":[],"counts":{"Critical":0,"Important":0,"Minor":0},"own_tests_executed":[],"global_qa_green":false,"report":"storage/providers-email-20261030/review-B2.md"}
```
