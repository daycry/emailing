# Revisión adversarial — lente C, intento 1 de 3

Base: `74970e0af4c5e68bd3b8e139a0988a119208493e`. HEAD: `effd48ada42155686f7bc8e95c379cbdf566782b`, más worktree y nuevos públicos sin seguimiento. Alcance comunicado por scope-check: 110 rutas. El inventario independiente `git diff --name-only <base>` unido a `git ls-files --others --exclude-standard`, excluyendo `.claude/**` y `tests/.runtime/**`, enumeró 109 rutas públicas; se informa la diferencia sin inferir su causa. Fuentes congeladas durante la revisión. Revisor de contexto fresco, subagente genérico con modelo heredado porque `reviewer` nativo no está disponible; no se atribuye otro modelo.

Se aplicaron `adversarial-review` 2.3.0 y el prompt literal C. Activación: `.env.example`, fixtures OAuth/permisos y nueva frontera de cifrado/autenticación de webhooks. Sólo seguridad introducida o reabierta por el diff; no auditoría completa.

## Veredicto

**Un hallazgo Critical pendiente.** Important: 0. Minor: 0. No pasar esta lente a «sin hallazgos» antes de corregir y revalidar la degradación SMTP.

### C1-01 — El nuevo SMTP externo STARTTLS puede enviar el mensaje sin TLS tras fallback EHLO→HELO

- **Grado:** Critical. **CWE-757**, selección forzada de un protocolo menos seguro; efecto **CWE-319**, transmisión de información sensible en claro.
- **Anclaje en código nuevo:** `app/Services/MailProviders/ProviderSmtpTransport.php:27` y `:29`. Usa el conector compartido para SMTP externo y devuelve `smtp_accepted` cuando éste termina. `app/Services/MailProviders/ProviderCredentials.php:50` selecciona los hosts externos y el puerto 587 para `starttls`, que además es el default del perfil.
- **Cadena verificada:** `AccountMailTransport.php:192` → `ProviderSmtpTransport.php:27` → `SmtpConnector.php:25`/`:26` → `ObservedSmtpTransport`, que hereda el handshake de Symfony. En el Symfony instalado, `vendor/symfony/mailer/Transport/Smtp/EsmtpTransport.php:164` retorna directamente el resultado de HELO si EHLO falla; ese retorno precede a la exigencia TLS de `:194`. No hay una guarda propia que compruebe TLS observado antes de MAIL/RCPT/DATA.
- **Escenario de explotación:** una cuenta habilitada usa Brevo o SendGrid SMTP con STARTTLS/587. Un atacante en la ruta TCP devuelve `500` a EHLO y `250` a HELO. El fallback evita STARTTLS y la comprobación `requireTls`, y el atacante acepta MAIL, RCPT y DATA en claro. Puede obtener destinatario, remitente, asunto, cuerpo y, en trabajo durable, el enlace de baja que se añade al mensaje. También puede devolver el `250` final y conseguir que el intento se registre como aceptado sin haber llegado al proveedor. No necesita un certificado válido ni las credenciales del proveedor. El camino observado no envía AUTH; no se afirma extracción de contraseña.
- **Por qué pertenece al diff:** antes, `AccountMailTransport` sólo permitía log/Mailpit. Este diff abre los perfiles SMTP externos sobre el conector y handshake existentes. Los certificados verificados y TLS 1.2/1.3 no protegen una ruta que nunca inicia TLS. SMTPS/465 no tiene este escenario previo al handshake.
- **Validación concreta ejecutada:** un probe PHP por stdin cargó únicamente `vendor/autoload.php`, el `ObservedSmtpTransport` real y un `AbstractStream` en memoria. El stream respondió `220`, `500` a EHLO, `250` a HELO, `250` a MAIL/RCPT, `354` a DATA, `250` al terminador y `221` a QUIT. Con `setAutoTls(true)` y `setRequireTls(true)`, el resultado fue exit 0 y:

```json
{"in_memory_only":true,"require_tls":true,"accepted":true,"commands":["EHLO","HELO","MAIL","RCPT","DATA",".","QUIT"]}
```

El stream no abre sockets ni implementa una sesión TLS; la ausencia de STARTTLS y la aceptación de DATA son el oráculo. Se usaron exclusivamente contenido y valores sintéticos. No se ejecutaron SQL, E2E ni llamadas a proveedor.

- **Corrección requerida:** imponer en la frontera propia la sesión TLS realmente establecida antes de permitir comandos que envían datos del mensaje en modos externos, incluida la salida EHLO→HELO; conservar `plain` sólo para el destino de laboratorio autorizado. No basta con volver a establecer `requireTls` en el vendor. Añadir un negativo de regresión con EHLO rechazado/HELO aceptado y comprobar cero AUTH/MAIL/RCPT/DATA sin TLS, además del camino STARTTLS válido.

## Cobertura y comprobaciones sin otros hallazgos

| Superficie leída | Evidencia y conclusión acotada |
|---|---|
| Configuración, permisos y secretos | `MailSettingsService.php:40` revalida membresía/scopes bajo mutex y `:90` busca el perfil por cuenta/proveedor. Se siguieron Livewire, AccountContext y rutas web. No se observó IDOR nuevo en configuración. |
| Cifrado y serialización | `MailProviderConnection.php:19`/`:23` oculta y cifra todos los campos secretos; se comprobó la implementación instalada de casts Laravel. `ProviderCredentials` mantiene valores privados, redacción de debug y prohibición de serialización. La UI recibe perfiles derivados sin secretos. |
| API de proveedores | Hosts HTTPS fijos en `ProviderCredentials.php:35`; `ProviderApiTransport.php:74`/`:75` desactiva redirects y exige verificación TLS, con límites de request/response. Credenciales y correlación validadas antes de red. Sin SSRF o inyección de cabeceras confirmados. |
| Entrada webhook | Se siguieron `bootstrap/app.php:23`/`:28`, ruta, middleware, controlador y servicio. Cuerpo original limitado a 1 MiB; HTTPS obligatorio; autenticación revalidada bajo mutex antes de interpretar payload. Brevo usa comparación constante y SendGrid firma timestamp+bytes originales con clave P-256 y ventana temporal. |
| Aislamiento, SQL y efectos webhook | `ProviderWebhookService.php:61` limita cuenta/proveedor/inicio/referencia y `:71` compara destinatario. El SQL dinámico de `:105` sólo compone placeholders; IDs, estados y fechas van ligados. Dedupe y supresiones no ofrecen un camino no autenticado confirmado. |
| Pipeline y exposición | Se leyeron los cambios de AccountMailTransport, DeliveryLifecycle, CampaignService, SendingApiService, TemplateService, ActivityService, DTO/contratos MCP y vistas. Referencias y recibos de proveedor están ocultos del modelo; los DTO públicos mantienen filtros de cuenta. No se observó otro bypass nuevo confirmado. |
| Tests, fixtures y contratos | Se leyeron pruebas nuevas Feature/Unit/E2E, migraciones y harnesses de soporte; se compararon semánticamente los contratos JSON/YAML para evitar extrapolar de reformateos. OAuth/permisos nuevos sólo cambian versión frente a los fixtures anteriores. No son secretos productivos. Búsqueda de referencias en app/bootstrap/routes/public: los adaptadores de pruebas no son cargados por bootstrap/rutas de producción; `Tests\\` sólo aparece en autoload-dev de Composer. |

Se revisó íntegro el código runtime nuevo/modificado y sus llamantes relevantes, más tests/harnesses y cambios semánticos de contratos. La lectura documental estuvo centrada en especificación, operación y seguridad; no sustituye la conformidad completa de lente A. No se atribuye cobertura porcentual de código.

## Límites y estado de pruebas

La validación propia fue estática y el probe en memoria descrito. No se ejecutaron suites SQL/E2E, inventarios de procesos/contenedores, cambios de CA, conexiones externas ni llamadas reales a Brevo/SendGrid. No se leyeron los archivos privados/prohibidos, claves PEM privadas, dumps ni ZIP. Única escritura: este informe.

El orquestador comunicó una unidad conjunta de 85 casos/823 asserts con dos fallos del oráculo TLS del servidor; no se usó como verde global ni como prueba para descartar el hallazgo. La revisión/QA local global y la cualificación privada real continúan pendientes. La corrección de C1-01 y su siguiente revisión todavía no han ocurrido sobre estas fuentes.
