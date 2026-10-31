# Revisión adversarial — lente C, intento 2 de 3

**Veredicto: C1-01 cerrado, corrección verificada. Sin nuevos hallazgos de seguridad.** Nuevos gaps pendientes: Critical 0; Important 0; Minor 0. Este resultado permite continuar la puerta de revisión; no constituye QA verde ni cualificación privada de proveedor.

## Contrato y traspaso

Lente C independiente de contexto fresco, por subagente genérico con modelo heredado real; reviewer nativo no disponible. Se leyeron `adversarial-review/SKILL.md` y `references/lens-prompts.md` de custom-agents 2.3.0 y se aplicó el prompt literal C: sólo vulnerabilidades introducidas o reabiertas por el diff, con escenario y CWE; sin correcciones ni observaciones de estilo. Activación heredada del intento 1: configuración/secretos y nueva frontera de cifrado/autenticación de proveedores/webhooks.

Base `74970e0af4c5e68bd3b8e139a0988a119208493e` hasta worktree congelado, incluidos nuevos públicos. Scope-check del intento 2 comunicado por el orquestador: exit 0, 116 rutas, 0 fuera de alcance, 0 criterios alterados. Se leyó el traspaso completo `attempt-1-{A,B,C,D}.md` y la tabla de fusión de `tasks.md`. Se reevalúa lo corregido: no se reabren las superficies aprobadas ni se transforma la revisión en auditoría completa.

## C1-01 — cerrado

**Grado anterior:** Critical. **CWE-757 / CWE-319.** El ataque anterior devolvía 500 a EHLO y 250 a HELO para alcanzar MAIL/RCPT/DATA en claro en perfiles externos STARTTLS/587. El escenario está corregido en estas fuentes.

| Comprobación de la remediación | Resultado y evidencia |
|---|---|
| El perfil externo sigue exigiendo TLS antes del envío | ✓ `app/Services/SmtpConnector.php:27` activa STARTTLS para ese modo y `:28` exige TLS para cualquier modo distinto de plain. `app/Services/MailProviders/ProviderCredentials.php:50` fija los hosts de proveedor y 587/465; no habilita plain externo. |
| EHLO fallido no alcanza el retorno HELO inseguro del vendor | ✓ `app/Services/ObservedSmtpTransport.php:70` intercepta el fallo de EHLO con TLS obligatorio y `:75` lanza `SmtpPolicyViolation`. La clase de `app/Support/SmtpPolicyViolation.php:5` deriva de RuntimeException y no implementa TransportExceptionInterface. Por tanto no la captura `vendor/symfony/mailer/Transport/Smtp/EsmtpTransport.php:162`, que anteriormente saltaba a su retorno HELO de `:164`. |
| El camino EHLO positivo atraviesa la comprobación TLS real | ✓ El código propio no trata SocketStream::isTLS como estado STARTTLS. Con EHLO positivo, el vendor mantiene `tlsStarted` local, exige éxito de `startTLS()` en `EsmtpTransport.php:185`, fija el estado en `:189` y aplica la exigencia en `:194`, antes de AUTH en `:198` y del sobre en SmtpTransport. La configuración SMTPS abre primero el stream TLS con verificación de peer/nombre; `SmtpConnector.php:33` conserva esa política. |
| Fallo de política distinguible y saneado, sin aceptación ni retry | ✓ `SmtpConnector.php:44` propaga la excepción propia; `ProviderSmtpTransport.php:31` la convierte en `smtp_rejected / smtp_tls_required`, sin retry. La excepción contiene sólo texto fijo y no encadena el error crudo. `ActivityService.php:23` aporta diagnóstico seguro. |
| Regresión durable cubre el resultado terminal | ✓ Lectura de `tests/Feature/MailProviderDeliveryTest.php:261`: inyecta rechazo AUTH535 y política TLS, exige estado failed, un intento, misma generación, un outbox y ninguna nueva reserva. No se ejecutó SQL por esta lente. |

### Validación propia, estrictamente en memoria

Se ejecutó PHP por stdin con únicamente `vendor/autoload.php`, `ObservedSmtpTransport` real y un AbstractStream sintético en memoria. No se cargó bootstrap de Laravel ni `.env`; no hubo DB, procesos auxiliares o sockets. Todos los datos eran sintéticos y la salida sólo contiene verbos/facts. **Exit 0, siete escenarios correctos.**

| Escenario | Resultado | Comandos observados |
|---|---|---|
| EHLO500; HELO250 disponible si se hubiera intentado | Rechazado, SmtpPolicyViolation | EHLO |
| Desconexión durante EHLO | Rechazado, SmtpPolicyViolation | EHLO |
| EHLO250 sin STARTTLS | Rechazado, TransportException del vendor | EHLO |
| STARTTLS220 con handshake que devuelve false | Rechazado, TransportException del vendor | EHLO, STARTTLS, HANDSHAKE |
| Handshake exitoso modelado, segundo EHLO500 | Rechazado, SmtpPolicyViolation | EHLO, STARTTLS, HANDSHAKE, EHLO |
| STARTTLS exitoso modelado, EHLO válido | Aceptado, control positivo | EHLO, STARTTLS, HANDSHAKE, EHLO, MAIL, RCPT, DATA, BODY, terminador, QUIT |
| Plain autorizado, EHLO500/HELO250 | Aceptado, control del fallback de laboratorio | EHLO, HELO, MAIL, RCPT, DATA, BODY, terminador, QUIT |

En los cinco negativos hubo cero AUTH/MAIL/RCPT/DATA/BODY/terminador y `accepted=false`. El stream de prueba no realiza criptografía: su control positivo valida el flujo, no la confianza de certificados. La evidencia real de TLS corresponde a los recibos de cable existentes descritos abajo. El caso EHLO500 reproduce el ataque original bajo el mismo punto de extensión y ahora se corta antes de HELO.

## Cambios corregidos sin otros hallazgos nuevos

- `ObservedSmtpTransport.php:59`, `:79`, `:82` y `SmtpConnector.php:47`: el negativo de negociación se conserva durante el wrapper HELO y RSET/QUIT; una negociación recuperada lo limpia antes del sobre. Se siguió el AUTH del vendor y el llamante `ProviderSmtpTransport.php:33`. La clasificación de AUTH535 y su corrección pertenecen al hallazgo B1-01; no se abre un duplicado de seguridad.
- `ProviderApiTransport.php:24`, `:142`: el formato HTTP explícito reemplaza la constante obsoleta y mantiene parseo estricto, límite de longitud y espera acotada. No cambia el endpoint fijo, las cabeceras de autenticación, verify=true, redirects prohibidos ni el estado ambiguo de errores de transporte.
- `MailResult.php:9`, `:24` y el diagnóstico de Activity conservan texto fijo; no incorporan respuestas crudas, credenciales o cuerpos. Las nuevas etiquetas no alteran la autorización o las fronteras de cuenta.
- Se leyeron los negativos SMTP de cable, fixture/probe SMTP, oráculo HTTPS y probe HTTP corregidos. La observación curl de pruebas registra únicamente clase/errno/status/facts; no imprime errores crudos. El caso CA no confiable exige curl60, ninguna respuesta HTTP y cero bytes de request/auth/correlación. Cambiar el booleano del lado servidor TLS1.3 por esos oráculos no desactiva la verificación del cliente.
- Los diagnósticos públicos/pins 0.2 reflejan `smtp_tls_required`; no se identificó una nueva ruta de exposición o autorización derivada de esa ampliación. La contradicción documental anterior corresponde a lente A y no se reabre aquí.

## Evidencia existente consultada y límites

Se leyeron los resúmenes públicos y el recibo de fuentes de las tandas indicadas; no se suman ejecuciones solapadas:

- `fix1-smtp-red-20261030T231542184328Z/test-summary.json`: exit1, 2 casos/38 aserciones/2 fallos antes del fix, AUTH535 y downgrade.
- `fix1-unit-final-20261030T232930242625Z/test-summary.json`: exit0, 90 casos/1045 aserciones, 0 fallos/errores/omitidos. Incluye 13 escenarios SMTP y 8 HTTPS. Su `command-receipt.public.json` declara 506 fuentes estables y terminal_exit0. Se leyeron las pruebas para delimitar lo que acredita ese verde; no se sustituye la revisión con el recibo.
- `fix1-lab-and-smtp-worker-20261030T232929000982Z/test-summary.json`: exit1, 12 casos/214 aserciones, 1 fallo heredado de notice. `fix1-legacy-notice-20261030T233512752157Z/test-summary.json`: focal posterior exit0, 1 caso/8 aserciones. La tanda roja no se convierte en verde global mediante ese focal.

La regresión completa final y QA independiente siguen pendientes según el estado recibido; no se afirma su resultado. M-01/M-02, cualificación con credenciales/proveedores reales y aceptación humana siguen pendientes. Esta lente sólo ejecutó el probe en memoria; no ejecutó suites SQL/E2E ni abrió red o proveedores. No realizó inventarios de procesos/contenedores, no tocó PIDs ajenos y no leyó ni calculó hashes de archivos prohibidos, secretos, PEM privados, dumps SQL o ZIP. Única escritura: este informe; no modifica fuentes, tests, documentación ni ledger.

```json
{"lens":"C","attempt":2,"status":"complete","verdict":"C1-01 cerrado; sin nuevos hallazgos","previous_gap":{"id":"C1-01","severity":"Critical","cwe":["CWE-757","CWE-319"],"state":"corregido y verificado"},"open_counts":{"Critical":0,"Important":0,"Minor":0},"own_validation":{"kind":"autoload-only in-memory stream","scenarios":7,"exit":0,"sockets":0,"sql":false,"e2e":false},"qa":"pendiente","M-01":"pendiente","report":"storage/providers-email-20261030/review-C2.md"}
```
