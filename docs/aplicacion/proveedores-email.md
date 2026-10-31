# Enviar con Brevo y SendGrid

**Última actualización: 2026-10-31.** SMTP y API comparten el pipeline de la
aplicación. La [evidencia de laboratorio](#qué-acredita-el-laboratorio) conserva
la revisión, QA y las comprobaciones privadas aún pendientes.

La cuenta comparte sus perfiles de proveedor con sus teammates. Cada usuario sigue
necesitando permisos vigentes para gestionarlos o iniciar envíos. Otra cuenta no
puede leerlos ni usarlos. Campañas, API, MCP y pruebas explícitas usan el mismo
transporte, cuotas, supresiones y registros de intento.

## Elegir proveedor y canal

| Modo | Destino fijo | Credencial de envío | Aceptación registrada |
|---|---|---|---|
| `brevo_smtp` | `smtp-relay.brevo.com` | Usuario SMTP y clave SMTP de Brevo | `smtp_accepted` |
| `brevo_api` | `https://api.brevo.com/v3/smtp/email` | Clave API de Brevo, cabecera `api-key` | `api_accepted` |
| `sendgrid_smtp` | `smtp.sendgrid.net` | Usuario literal `apikey` y clave API de SendGrid como contraseña | `smtp_accepted` |
| `sendgrid_api` | `https://api.sendgrid.com/v3/mail/send` | Clave API de SendGrid, Bearer | `api_accepted` |

Brevo distingue su clave SMTP de su clave API. Los dos campos de SendGrid se
guardan por separado, aunque el operador decida usar una misma clave con permiso
de envío para ambos canales. No hay un host o URL externo editable ni cambio
automático de proveedor ante un fallo.

SMTP permite STARTTLS obligatorio en el puerto 587 o TLS implícito en 465. Se
verifican certificado y nombre del servidor y se exige TLS 1.2 o posterior. La API
usa HTTPS, verifica certificados y no sigue redirecciones. Se reutilizan Symfony
Mailer y el cliente HTTP de Laravel instalados, sin SDK adicional.

## Preparar el despliegue

1. Inspecciona el esquema y el historial de migraciones del destino y prepara
   copia y recuperación. Las cuatro migraciones `2026_10_30_000001`–`000004`
   amplían perfiles cifrados, autorización externa del remitente,
   correlación/feedback y recibos de eventos. Aplica las pendientes con
   `php artisan migrate --force` dentro del procedimiento de despliegue. Las
   pruebas de esta iniciativa no han migrado la base operativa.
2. Conserva `APP_KEY` de forma privada y estable. Las credenciales se cifran con
   Laravel; perder la clave impide descifrarlas. Una rotación necesita la política
   de claves anteriores de Laravel y la renovación de credenciales del proveedor.
3. Configura `APP_URL` con el origen HTTPS real. El webhook debe ser accesible por
   el proveedor. El receptor rechaza HTTP; configura correctamente TLS y los proxies
   de confianza en el servidor de destino.
4. Mantén inicialmente `EMAIL_EXTERNAL_ENABLED=false`. Puedes preparar los perfiles
   y elegir un modo sin conectarte al proveedor. El valor por defecto sigue siendo
   `log` y Mailpit sigue disponible para el laboratorio.
5. Después de verificar la configuración y sus permisos, habilita
   `EMAIL_EXTERNAL_ENABLED=true` de forma privada y reinicia los procesos de larga
   duración para que carguen la configuración. Si usas caché de configuración,
   vuelve a generarla en el destino.

No se introduce un broker, scheduler o Supervisor por proveedor. Los workers de
la aplicación consumen el outbox y ejecutan el mismo pipeline de entrega existente.

## Configurar cada una de las cuatro combinaciones

Necesitas `settings.read` para consultar y también `settings.write` para cambiar
perfiles, modo o remitentes. Crear una prueba desde ajustes exige `mail.send`.

En **Transporte y remitentes** (`/settings/mail`), guarda primero el perfil de Brevo o SendGrid. Los
campos vacíos conservan su valor; las claves guardadas nunca se vuelven a mostrar.
Un guardado no envía mensajes. El perfil puede prepararse deshabilitado y permite
añadir primero las credenciales y después su autenticación de feedback.

### Brevo por SMTP

1. En el perfil **Brevo**, completa **Usuario SMTP nuevo** y **Clave SMTP nueva**
   con las credenciales SMTP del proveedor. La clave SMTP es distinta de la API.
2. Elige **STARTTLS obligatorio · 587** o **TLS implícito · 465**. El servidor
   `smtp-relay.brevo.com` es fijo.
3. Guarda un **Bearer privado nuevo del webhook**, aleatorio, de al menos
   32 caracteres. Guarda el perfil deshabilitado para obtener su URL HTTPS.
4. Configura en Brevo esa URL, el mismo Bearer y los eventos transaccionales
   reconocidos en esta guía. No incluyas el secreto en la URL.
5. Marca **Habilitar este perfil**, guarda y selecciona `brevo_smtp` en
   **Destino de los mensajes**. Completa la autorización del remitente descrita
   abajo antes de una prueba real.

### Brevo por API

1. En el perfil **Brevo**, introduce **Clave API nueva de Brevo** con permiso de
   envío transaccional. No uses la clave SMTP en este campo.
2. Configura el Bearer y webhook HTTPS de Brevo como en la receta SMTP. El mismo
   perfil comparte su autenticación de feedback entre ambos canales.
3. Habilita el perfil, guarda y selecciona `brevo_api` en **Destino de los
   mensajes**. El adaptador envía al endpoint fijo de la tabla anterior.
4. Tras autorizar el remitente, una prueba aceptada muestra `api_accepted`:
   requiere HTTP `201` y un `messageId` válido. Espera el feedback por separado.

### SendGrid por SMTP

1. En el perfil **SendGrid**, completa **Clave API nueva para autenticar SMTP**
   con una clave que permita enviar. El usuario es el literal fijo `apikey`.
2. Elige STARTTLS obligatorio en 587 o TLS implícito en 465. El servidor fijo es
   `smtp.sendgrid.net`.
3. Activa la firma del **Event Webhook** en SendGrid. Copia su clave pública
   ECDSA P-256, PEM o Base64, en **Clave pública nueva de firma del Event Webhook**.
   La aplicación no necesita una clave privada del proveedor.
4. Guarda el perfil deshabilitado, configura en SendGrid la URL HTTPS mostrada
   y sus eventos, y habilita el perfil cuando canal y firma estén preparados.
5. Selecciona `sendgrid_smtp`, guarda la configuración y autoriza el remitente.

### SendGrid por API

1. Introduce la clave con permiso de envío en **Clave API nueva de SendGrid**,
   dentro de **Canal API**. Es un campo distinto del usado para SMTP.
2. Prepara la clave pública y la URL HTTPS del Event Webhook como en la receta
   SMTP. Habilita y guarda el perfil.
3. Selecciona `sendgrid_api` en **Destino de los mensajes** y guarda.
4. Tras autorizar el remitente, una prueba aceptada muestra `api_accepted`:
   requiere HTTP `202` y una cabecera `X-Message-Id` válida. No confirma entrega.

Autoriza por separado el remitente para Brevo/SendGrid. La autorización para log y
Mailpit no habilita envío externo. Este permiso interno tampoco verifica el dominio:
comprueba con el proveedor el remitente, SPF, DKIM y DMARC antes de enviar. Una
prueba con un proveedor habilitado puede enviar correo real.

En **Remitentes de la cuenta**, edita o crea el remitente y marca **Autorizo este
remitente para Brevo y SendGrid**. Confirma que la salida global esté habilitada
en el destino. En **Probar el transporte**, elige ese remitente y un destinatario
real controlado y autorizado. Consulta el resultado en **Actividad** con
`activity.read`. Nunca uses destinatarios `example.test` para cualificar Internet.

Los eventos reconocidos son `request`, `deferred`, `soft_bounce`, `delivered`,
`hard_bounce`, `spam`, `unsubscribed`, `blocked`, `invalid` y `error` en Brevo.
SendGrid admite `processed`, `deferred`, `delivered`, `bounce`, `dropped`,
`spamreport`, `unsubscribe` y `group_unsubscribe`. Otros eventos se ignoran.

## Autenticar y correlacionar feedback

| Proveedor | Ruta | Comprobación |
|---|---|---|
| Brevo | `/webhooks/mail/brevo/{connection}` | Cabecera `Authorization: Bearer …` comparada con el secreto actual del perfil |
| SendGrid | `/webhooks/mail/sendgrid/{connection}` | Firma ECDSA P-256 SHA-256 de timestamp concatenado con los bytes originales del cuerpo |

El UUID de conexión identifica un perfil local; no sustituye su autenticación. El
payload no elige una cuenta. Cada intento externo conserva antes de abrir la red
una referencia UUID opaca, enviada como `emailing_reference` en `X-Mailin-custom`
de Brevo o en `custom_args`/`unique_args` de SendGrid. Sólo se aplican eventos con
referencia, proveedor, cuenta y destinatario coincidentes y transporte ya iniciado.

SendGrid usa las cabeceras `X-Twilio-Email-Event-Webhook-Signature` y
`X-Twilio-Email-Event-Webhook-Timestamp`. La ventana máxima es de 24 horas, con
cinco minutos de tolerancia futura. Una firma con un byte cambiado, otra clave o
un timestamp fuera de ventana se rechaza. Brevo autentica el cuerpo con su Bearer
por HTTPS; no ofrece aquí la misma garantía temporal criptográfica de SendGrid.

El receptor limita el cuerpo a 1 MiB y los lotes de SendGrid a 1.000 eventos. Brevo
envía un objeto por petición. Hay un límite de entrada de 120 peticiones por minuto
por IP. Un `204` confirma consumo local, deduplicación o un evento ignorado; no
confirma entrega de correo. `401` rechaza autenticación, `413` tamaño excesivo,
`422` forma JSON inválida, `429` limita entrada y `503` permite reintentar tras un
fallo de almacenamiento sin reconocer una transacción no confirmada.

Los recibos guardan sólo hash de identidad, mensaje, proveedor, estado y fechas.
No guardan el payload, asunto, cabeceras, claves ni dirección de email del evento.
SendGrid deduplica mediante `sg_event_id`; el `id` de Brevo identifica el webhook,
por lo que no se usa solo para identificar una ocurrencia. Los cambios se confirman
en una transacción por cuenta y los lotes se procesan con consultas agrupadas.

## Interpretar aceptación, feedback y supresiones

`smtp_accepted` significa aceptación SMTP y `api_accepted` aceptación por la API.
El feedback posterior se muestra aparte. Una entrega comunicada por el proveedor
no modifica el recibo original ni demuestra lectura. No se calculan aperturas,
clics, reputación o presencia en listas de bloqueo.

Rebote permanente, queja y baja aplican respectivamente las supresiones
`provider_bounce`, `provider_complaint` y `provider_unsubscribe` en esa cuenta.
Un rebote temporal o aplazamiento no aplica supresión permanente. Una entrega
benigna recibida después no revierte una queja, baja o rebote permanente. Tampoco
se eliminan supresiones manuales existentes. La retirada explícita de una supresión
sigue necesitando permisos y no concede consentimiento.

En API/MCP, `counts.api_accepted` es independiente de `counts.smtp_accepted`.
`provider_feedback` es opcional por entrega y `delivery_feedback=provider_events`
indica que hay datos del proveedor en esa consulta. Sin eventos, la ausencia de
feedback no equivale a cero incidencias. Las métricas de aceptación combinan las
aceptaciones SMTP y API, manteniendo sus contadores separados.

## Rechazos, reintentos y resultados inciertos

Un negativo SMTP transitorio demostrado puede reprogramarse; un negativo
permanente no. Una caída después del terminador de DATA puede haber sido aceptada
y queda incierta. La aplicación conserva la observación de protocolo y no publica
comandos AUTH, respuestas crudas ni excepciones del conector.

En API, `400/401/403/422` y otros negativos deterministas no se repiten. `429`
permite como máximo una reprogramación, respeta `Retry-After` y limita la espera a
24 horas. Timeout, `408`, `5xx`, redirect o recibo inválido quedan como aceptación
incierta y no producen reintento automático. El cliente no añade retries ocultos.
La respuesta se limita a 64 KiB y la petición a 4 MiB; el timeout HTTP total es como
máximo cinco segundos. No se usa otro proveedor como fallback.

Si llega feedback autenticado de cualquier intento de esa entrega antes de
finalizar una respuesta transitoria, el worker evita otra reprogramación y
registra `provider_feedback_conflict`. También consulta todos los intentos antes
del siguiente inicio durable: una entrega comunicada entre intentos bloquea ese
inicio y deja la entrega `ambiguous`. El mutex de cuenta ordena callbacks e inicio
durable; no cancela IO que ya haya empezado ni garantiza envío externo exactamente
una vez. Las reservas y su
fencing impiden que un worker obsoleto finalice otro intento. Los secretos se
congelan sólo en memoria durante el intento, nunca en un job o mensaje del broker.

Revocar una llave API o concesión MCP bloquea llamadas nuevas y replays. El trabajo
ya aceptado conserva su recibo y procedencia conforme al contrato existente. Las
guardas vigentes de remitente, consentimiento, supresión, perfil y cuotas siguen
aplicándose al iniciar el transporte.

## Rotar, deshabilitar y diagnosticar

Actualiza la clave del proveedor y el campo concreto del perfil. El servicio permite
limpiar campos expresos; la UI también permite eliminar todas las credenciales de
un proveedor y deshabilitarlo, sin tocar las del otro. Un intento ya iniciado usa
su snapshot; el siguiente usa el perfil actual.

La autenticación de callbacks usa sólo la clave actual y revalida la configuración
bajo el mutex de cuenta. Coordina la rotación de la clave de firma/Bearer con el
proveedor: los callbacks firmados con una clave retirada se rechazan. No existe
un período de doble clave implícito. Deshabilitar el perfil bloquea también sus
callbacks; conserva y reconcilia los eventos en vuelo antes de hacerlo si los
necesitas. Deshabilitar únicamente la salida global permite seguir recibiendo
eventos de perfiles activos.

En Actividad consulta por separado estado del transporte, diagnóstico depurado y
feedback. `provider_not_configured` indica perfil incompleto, deshabilitado u origen
HTTPS inválido; `provider_credentials_unavailable` requiere revisar cifrado/clave;
`api_authentication_rejected` indica rechazo de la credencial por el proveedor.
`smtp_tls_required` indica que no pudo mantenerse la negociación ESMTP exigida:
bloquea el fallback EHLO a HELO antes de AUTH, sobre o cuerpo. No es un código
SMTP del servidor y no habilita reintento. La ruta normal comprueba el handshake
TLS real. Un AUTH `535` conserva su rechazo permanente aunque Symfony pruebe
otro mecanismo; si otro mecanismo autentica correctamente, ese intento puede
continuar. Un estado ambiguo necesita conciliación con el proveedor antes de reenviar.

## Qué acredita el laboratorio

Las pruebas locales usan credenciales sintéticas, destinatarios `example.test`,
fixtures SMTP/HTTPS propios y SQL `emailing_testing` con identidad comprobada.
No configuran cuentas de Brevo/SendGrid ni envían correo real. Los escenarios de
UI ejercitan el cliente contra adaptadores controlados; las pruebas de cable
verifican aparte TLS, autenticación, límites y respuestas del transporte.

La revisión 3 A+B+C+D cerró sin gaps. [QA3](../roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/qa-gate.json)
acredita seis escenarios originales aprobados, sin fallos, flaky, omitidos ni
interrumpidos. La [paridad de fuentes](../roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/source-parity.json)
y la [limpieza propia](../roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/cleanup.json)
conservan sus recibos. La [aceptación backend](../roadmap/2026-10-30-proveedores-email/testing/acceptance.md)
detalla las tandas originales; no se suman pruebas solapadas como casos distintos.
Esta evidencia no cierra T19/T20 de la plataforma ni T12–T14 del MCP.

La cualificación privada M-01 queda pendiente: un envío autorizado por cada uno de
los cuatro canales, DNS y remitente verificados, callback HTTPS real y evidencia
privada del evento. M-02 conserva la revisión humana de operación y la retro. Un
proveedor gestiona su infraestructura de salida; usarlo no garantiza por sí solo
que el correo evite spam o listas de bloqueo.

## Dónde está implementado

La selección y las guardas viven en [MailTransportPolicy](../../app/Services/MailProviders/MailTransportPolicy.php)
y [AccountMailTransport](../../app/Services/AccountMailTransport.php). El cifrado,
habilitación y borrado los gestionan [MailProviderConnection](../../app/Models/MailProviderConnection.php)
y [MailSettingsService](../../app/Services/MailSettingsService.php).
[ProviderCredentials](../../app/Services/MailProviders/ProviderCredentials.php)
fija destinos y separa secretos por canal. [ProviderApiTransport](../../app/Services/MailProviders/ProviderApiTransport.php)
acota HTTP y [ProviderSmtpTransport](../../app/Services/MailProviders/ProviderSmtpTransport.php)
clasifica SMTP. [DeliveryLifecycle](../../app/Services/DeliveryLifecycle.php)
decide reprogramación e incertidumbre.

[WebhookAuthentication](../../app/Services/MailProviders/WebhookAuthentication.php),
[ProviderFeedback](../../app/Services/MailProviders/ProviderFeedback.php) y
[ProviderWebhookService](../../app/Services/MailProviders/ProviderWebhookService.php)
autentican, normalizan y aplican eventos. Los contratos publicados están en
[API](../api/README.md); las opciones del despliegue, en
[config/emailing.php](../../config/emailing.php) y [.env.example](../../.env.example).

Fuentes de los contratos: [SMTP Brevo](https://developers.brevo.com/docs/smtp-integration),
[API Brevo](https://developers.brevo.com/reference/send-transac-email),
[webhooks Brevo](https://developers.brevo.com/docs/transactional-webhooks),
[autenticación Brevo](https://developers.brevo.com/docs/secured-webhooks),
[SMTP SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api),
[API SendGrid](https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send) y
[firma SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/getting-started-event-webhook-security-features).
