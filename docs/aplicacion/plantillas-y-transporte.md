# Cómo editar plantillas y probar el transporte de una cuenta

**Última actualización: 2026-10-31.** T-07/T-08 implementados y probados en [QA3 de fase 2](../roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md). El modo por defecto sigue siendo log. La ampliación de [proveedores](proveedores-email.md) incorpora Brevo y SendGrid por SMTP/API, con salida externa deshabilitada por defecto y cualificación real pendiente.

## Qué permisos necesita cada acción

Plantillas, remitentes, configuración y mensajes pertenecen a la cuenta activa. Los miembros autorizados comparten esos recursos; los identificadores de otra cuenta devuelven 404.

| Acción | Scopes actuales |
|---|---|
| Abrir, buscar y previsualizar plantillas | `templates.read` |
| Crear, guardar o eliminar plantillas | `templates.read` y `templates.write` |
| Probar una plantilla guardada | `templates.read` y `mail.send` |
| Consultar transporte y remitentes | `settings.read` |
| Modificar configuración o remitentes | `settings.read` y `settings.write` |
| Probar desde Transporte y remitentes | `settings.read` y `mail.send` |
| Consultar registros de mensajes y descargar `.eml` de la cuenta | `activity.read`; abrir la página de ajustes requiere además `settings.read`. |

El preset Developer puede consultar y probar una plantilla guardada sin `contacts.read` ni `settings.read`. Los datos de muestra se escriben en el formulario y no consultan contactos. Los permisos actuales se comprueban de nuevo en cada acción.

Fuentes: [TemplateService](../../app/Services/TemplateService.php), [MailSettingsService](../../app/Services/MailSettingsService.php), [Permissions](../../app/Services/Permissions.php) y [rutas](../../routes/web.php).

## Cómo guardar una plantilla

1. Abre **Plantillas** (`/templates`) en la cuenta correcta y pulsa **Nueva plantilla**.
2. Indica un nombre, asunto y al menos uno de los cuerpos HTML o texto.
3. Completa **Datos de muestra** y pulsa **Actualizar preview**.
4. Corrige los avisos y pulsa **Guardar plantilla** para compartirla con la cuenta.

La búsqueda por nombre admite 150 caracteres y pagina 10 resultados. **Eliminar** retira la plantilla y conserva los mensajes creados anteriormente.

| Campo o resultado | Límite verificado en código |
|---|---|
| Nombre | Obligatorio, hasta 150 caracteres. |
| Asunto | Obligatorio, hasta 200 caracteres antes y después de sustituir; sin CR/LF. |
| HTML y texto fuente | UTF-8, hasta 100.000 bytes por campo; al menos uno con contenido. |
| Expresiones | Hasta 200 apariciones de variables por campo. |
| Valores del renderizador | Textos de hasta 512 caracteres; el formulario acota nombre a 150 y email a 254. |
| Resultado de sustitución | Hasta 200.000 bytes por campo. |

Si el cuerpo de texto está vacío, el servicio lo deriva del HTML quitando etiquetas y decodificando entidades. Si sólo hay texto, no inventa una versión HTML. Rechaza ambos cuerpos vacíos.

Fuentes: [Templates](../../app/Livewire/Templates.php), [vista de plantillas](../../resources/views/livewire/templates.blade.php), [TemplateService](../../app/Services/TemplateService.php) y [TemplateRenderer](../../app/Support/TemplateRenderer.php).

## Qué variables admite y dónde se pueden colocar

| Expresión literal | Valor |
|---|---|
| `{{ contact.name }}` | Nombre de muestra escrito en el formulario. |
| `{{ contact.email }}` | Email de muestra escrito en el formulario. |
| `{{ account.name }}` | Nombre de la cuenta activa. |

Ejemplo ficticio válido de cuerpo HTML:

```html
<h1>Hola {{ contact.name }}</h1>
<p>Este mensaje de {{ account.name }} usa el dato {{ contact.email }}.</p>
```

Usa estas mismas expresiones en asunto y texto. Los valores ausentes se sustituyen por vacío. Variables desconocidas y delimitadores incompletos producen error; no se ejecuta Blade, PHP ni un lenguaje de plantillas general.

En HTML las expresiones sólo pueden aparecer en nodos de texto del cuerpo. No se admiten en atributos, comentarios, CSS, scripts, cabecera o contextos como `textarea`. Por ejemplo, `<a href="{{ contact.email }}">Contacto</a>` se rechaza.

La sustitución HTML escapa los valores mediante `htmlspecialchars`. En texto conserva caracteres como `&`. Realiza una sola pasada: un valor que contenga otra expresión no provoca una segunda sustitución.

## Qué muestra la preview y por qué puede diferir del email

La preview permite revisar el formulario actual sin guardarlo. Cambiar contenido o datos de muestra oculta el resultado anterior hasta pulsar **Actualizar preview** otra vez.

[TemplatePreview](../../app/Support/TemplatePreview.php) construye un documento reducido y lo muestra en un `iframe` con `sandbox=""`, `referrerpolicy="no-referrer"` y CSP restrictiva. Conserva texto, tablas y estilos inline permitidos. Elimina scripts, formularios, contenido activo, navegación y recursos externos.

Las imágenes aparecen como `[Imagen omitida en preview]`; los enlaces conservan texto y pierden destino. CSP bloquea scripts, conexiones, imágenes y frames. Esta vista protege el panel; no reproduce todos los clientes de correo.

La reducción de contenido se aplica a la preview. El HTML enviado conserva el original con sus variables resueltas; sus enlaces no se eliminan por el filtro visual. Revisa el contenido guardado antes de generar una prueba.

## Cómo preparar un remitente de laboratorio

Abre **Transporte y remitentes** (`/settings/mail`) con permiso de ajustes. En **Nuevo remitente**, indica un email ficticio como `avisos@example.test`, un nombre y, opcionalmente, **Responder a**.

Marca **Autorizo este remitente para log y Mailpit** y guarda. La autorización interna habilita esas pruebas; no acredita propiedad del dominio ni permite correo externo. El email se normaliza a minúsculas y trim, y no puede repetirse en la misma cuenta.

Email y Reply-To admiten hasta 254 caracteres; nombre, hasta 150. Los campos de cabecera rechazan saltos de línea. Retirar un remitente conserva sus mensajes anteriores.

## Cómo elegir el transporte

En **Destino de los mensajes**, selecciona un modo y pulsa **Guardar configuración**.

| Modo | Resultado actual |
|---|---|
| Log privado | Modo por defecto si no hay configuración. Crea un archivo `.eml` privado y no abre SMTP. |
| Mailpit del laboratorio | Usa el endpoint Mailpit de configuración del runtime; captura SMTP local, sin entrega a Internet. |
| Brevo o SendGrid por SMTP/API | Usa el perfil cifrado del proveedor, el remitente autorizado para externo y la habilitación del despliegue; consulta las [cuatro recetas](proveedores-email.md#configurar-cada-una-de-las-cuatro-combinaciones). |

Desde Windows, SMTP Mailpit utiliza `127.0.0.1:11025`; desde Docker, `mailpit:1025`. Consulta los mensajes en <http://127.0.0.1:18025> (`mailpit:8025` dentro de Docker). **11025 es SMTP, no una URL web.**

El modo Mailpit no toma host ni credenciales de la preparación SMTP externa del formulario. Usa `MAILPIT_SMTP_HOST` y `MAILPIT_SMTP_PORT` de [config/emailing.php](../../config/emailing.php). La autenticación/invitaciones de Fortify mantienen su configuración de correo de aplicación; guardar el transporte de una cuenta no cambia ese correo de acceso.

El bloque **Preparar configuración SMTP externa (sin habilitar envío)** permite guardar host, puerto, seguridad y credenciales para preparación. No conecta al servidor indicado. El selector admite tambien los cuatro modos Brevo/SendGrid. Los datos genericos de este bloque no configuran esos perfiles ni permiten elegir un destino externo arbitrario.

El host admite hasta 253 caracteres y puede vaciarse. El puerto inicial es 587
y debe estar entre 1 y 65535; la seguridad permite STARTTLS o TLS implícito.
Usuario y contraseña nuevos sustituyen el valor cifrado; vacíos conservan el
anterior. **Eliminar credenciales guardadas** borra ambos al pulsar **Guardar
configuración**, sin borrar las tarjetas de Brevo o SendGrid. Los secretos no
se devuelven al formulario. La [explicación completa del formulario](../manual/guia-completa.md#preparación-smtp-genérica-conservada-por-compatibilidad)
incluye su efecto preparatorio y un ejemplo en modo log.

Guardar datos de relay no basta para habilitar Internet. Los perfiles de proveedor, feedback, supresiones y cuotas estan implementados en la ampliacion y el pipeline comun. La validacion real del dominio/remitente, DNS y los cuatro canales sigue pendiente de cualificacion privada M-01 en el [roadmap propio](../roadmap/2026-10-30-proveedores-email/tasks.md).

Para Brevo/SendGrid se usan perfiles cifrados separados por cuenta y proveedor, la autorizacion externa del remitente y el flag del despliegue. Los campos SMTP genericos de preparacion se conservan por compatibilidad y no eligen el destino de esos proveedores. Consulta las [cuatro recetas](proveedores-email.md).

## Cómo se protegen credenciales y archivos

[MailSetting](../../app/Models/MailSetting.php) aplica casts `encrypted` a usuario y contraseña SMTP y los oculta al serializar. Su recuperación depende de conservar `APP_KEY`; véase [respaldos](../infraestructura/backup-migracion.md).

Los secretos se entregan como argumentos de acción, no como propiedades públicas hidratadas de Livewire. La página nunca rellena valores guardados. Tras la respuesta, éxito o error, limpia ambos campos del navegador. Dejarlos vacíos conserva lo almacenado; **Eliminar credenciales guardadas** los retira explícitamente.

Los archivos `.eml` viven en el disco privado `mail-artifacts`, bajo `storage/app/private/mail-artifacts/<account_id>/`. El disco no sirve URLs públicas. La descarga autenticada revalida `activity.read` y cuenta, con `Cache-Control: private, no-store`. No publiques originales, credenciales ni capturas autenticadas completas.

[SmtpConnector](../../app/Services/SmtpConnector.php) verifica certificado y nombre del servidor, rechaza certificados autofirmados y exige TLS para STARTTLS o SMTPS. El timeout configurado es 5 segundos. `plain` se utiliza únicamente en el endpoint Mailpit local. Los errores brutos SMTP se sustituyen por un diagnóstico genérico para evitar revelar autenticación o contenido.

La prueba de TLS inválido corresponde a la evidencia Feature de T-08; QA3 de navegador de esa fase no repitió esa integración. Guardar la configuración SMTP genérica no habilita envío externo. Los perfiles Brevo/SendGrid tienen sus propias guardas y [pruebas de transporte](proveedores-email.md#qué-acredita-el-laboratorio).

## Cómo probar la versión guardada y leer su resultado

En Plantillas, guarda primero los cambios. En **Probar la versión guardada**, elige un remitente autorizado y pulsa **Crear prueba**. Usa un destinatario ficticio para log/Mailpit y uno real controlado y autorizado para un proveedor externo habilitado. Se carga la plantilla persistida, con los datos de muestra actuales. Los cambios del editor sin guardar no se envían.

En Transporte y remitentes también puedes usar **Probar el transporte** para crear un mensaje de texto fijo con el asunto indicado. Quienes dispongan de `activity.read` ven **Últimas pruebas y mensajes de la cuenta**, paginado de 10 en 10.

| Estado técnico | Significado y acción |
|---|---|
| `sending` | Inicio durable del transporte registrado; todavía no demuestra aceptación. |
| `logged` | Archivo privado escrito. Puedes descargar `.eml`; no hubo conexión SMTP. |
| `smtp_accepted` | Mailpit o el relay del proveedor aceptó el mensaje por SMTP; no equivale a entrega al destinatario. |
| `api_accepted` | La API del proveedor devolvió aceptación y recibo válidos; no confirma entrega. |
| `smtp_rejected` / `api_rejected` | Rechazo clasificado por el transporte. Consulta su diagnóstico y la política de recuperación del pipeline. |
| `api_unconfirmed` | La petición pudo aceptarse, pero no hay confirmación válida. No reenvíes automáticamente. |
| `smtp_unconfirmed` | No hay aceptación SMTP confirmada. Conserva la incertidumbre y no reenvíes automáticamente. |
| `local_failed` | Falló la escritura del archivo privado; no hay prueba de SMTP. |
| `blocked` | La política vigente impide iniciar el transporte, por ejemplo por salida externa deshabilitada o perfil incompleto. |
| `suppressed` | La cuenta tiene una supresión vigente para ese destinatario. No ejecuta el transporte. |

[AccountMailTransport](../../app/Services/AccountMailTransport.php) recibe explícitamente la cuenta, remitente y mensaje; no depende de sesión HTTP. Las fachadas UI validan los scopes antes de llamarlo. Su método `sendDelivery` integra el consumidor durable con las guardas actuales de cuenta, destinatario, remitente y transporte. Consulta [entrega y recuperación](entrega-y-recuperacion.md).

## Qué está probado y qué falta para envíos reales

QA3 aprobó E2E-06/E2E-07 en nativo y Docker: cuerpos alternativos, variables y escape, preview hostil sin ejecución ni peticiones, versión guardada, scopes, aislamiento, log privado, Mailpit y protección de secretos. [TemplateTest](../../tests/Feature/TemplateTest.php) y [MailTransportTest](../../tests/Feature/MailTransportTest.php) documentan regresiones backend.

La [ADR-003](../knowledge/adr/ADR-003-publicacion-confirmada-y-estado-smtp.md)
sigue propuesta para T-10–T-16. Outbox/publicación confirmada y consumidor de
dominio/resultados SMTP tienen revisión de componentes T-12/T-13; el worker
ordinario `queue:work` permanece separado. Consulta
[entrega y recuperación](entrega-y-recuperacion.md) para entregas e intentos y
[actividad y métricas](actividad-y-metricas.md) para la pantalla T-14,
aceptada técnicamente en laboratorio. La aceptación histórica de fase se conserva en su ledger. La ampliación de proveedores tiene pruebas y revisión propias en su [iniciativa](../roadmap/2026-10-30-proveedores-email/tasks.md).

Campanas, supresiones y cuotas estan implementadas en el pipeline comun. Brevo/SendGrid incorporan feedback autenticado, separado de la aceptacion. La verificacion real de dominios y canales requiere la cualificacion privada M-01. Mailpit o log no prueban reputación, llegada a inbox ni una IP libre de listas de bloqueo. Consulta [entregabilidad](../infraestructura/entregabilidad.md). La aceptación humana M-01 sigue pendiente.

Los nuevos estados `api_accepted`, `api_rejected` y `api_unconfirmed` distinguen el canal API; no se presentan como SMTP ni entrega. Los mensajes y Actividad muestran el feedback posterior de forma separada.
