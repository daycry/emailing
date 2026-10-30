# Integración de proveedores: decisión técnica

Fecha de consulta: 2026-10-30. Encargo: Brevo y SendGrid por SMTP y API, con
código limpio y robusto. Este documento recoge investigación; las pruebas reales
con cuentas del operador requieren configuración privada y se acreditan aparte.

| Alternativa | Compatibilidad y mantenimiento | Decisión |
| --- | --- | --- |
| Adaptar clientes instalados | Laravel13.38.0, Guzzle8.3.0 y Symfony Mailer/Mime8.1.7, versiones del lock, licencia MIT. Ya existen SMTP observado, cuotas, outbox, vallas y supresiones. | Reutilizar SMTP y HTTP; limitar código propio a contratos de los dos proveedores y clasificación de resultados. |
| Puentes oficiales Symfony | [Brevo](https://github.com/symfony/brevo-mailer/blob/8.1/composer.json) y [SendGrid](https://github.com/symfony/sendgrid-mailer/blob/8.1/composer.json), rama8.1 consultada. Son paquetes adicionales y requieren integrar HTTP Client; no resuelven por sí solos las garantías del lifecycle propio. | No añadirlos: el cambio necesita controlar respuestas ambiguas, correlación y límites en el cliente ya instalado. |
| SDK completos de proveedores | Añaden operaciones de administración ajenas al envío solicitado y una superficie de dependencias que habría que fijar y mantener. | No se adoptan. No se afirma incompatibilidad ni se inventa una versión validada de esos SDK. |

La implementación mínima comparte la política de red y errores; cada adaptador
traduce únicamente su contrato. No se clona un SDK ni se copia código externo.
La comparación utiliza repositorio y documentación oficial; no se usaron
credenciales de proveedores ni se registraron webhooks en servicios reales.

## Contratos y experimentos

| Límite | Fuente primaria | Decisión y comprobación observable |
| --- | --- | --- |
| SMTP Brevo | [Integración SMTP](https://developers.brevo.com/docs/smtp-integration) | Host del proveedor, clave SMTP distinta de API, TLS obligatorio. Fixture controlada verifica autenticación, rechazo y pérdida de respuesta. |
| SMTP SendGrid | [Integración SMTP](https://www.twilio.com/docs/sendgrid/for-developers/sending-email/integrating-with-the-smtp-api) | Usuario `apikey` y clave de envío como contraseña. Mismos controles de TLS y aceptación observada. |
| API Brevo | [Envío transaccional](https://developers.brevo.com/reference/send-transac-email) | POST `/v3/smtp/email`, cabecera `api-key`, respuesta201 con identificador. Fixture basada en el esquema oficial; sin retry implícito. |
| API SendGrid | [Mail Send](https://www.twilio.com/docs/sendgrid/api-reference/mail-send/mail-send) | POST `/v3/mail/send`, Bearer y202; correlación propia y recibo del proveedor. Distinguir aceptación de entrega. |
| Eventos Brevo | [Seguridad](https://developers.brevo.com/docs/secured-webhooks) y [eventos transaccionales](https://developers.brevo.com/docs/transactional-webhooks) | Bearer privado sobre HTTPS. El campo `id` identifica el webhook, no un evento único; deduplicar la identidad normalizada del evento. |
| Eventos SendGrid | [Firmas](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/getting-started-event-webhook-security-features) y [eventos](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/event) | ECDSA/SHA256 sobre timestamp y cuerpo original; `sg_event_id` permite deduplicación. Probar clave equivocada, byte alterado, repetición y orden inverso. |
| HTTP compartido | [Laravel13](https://laravel.com/framework/docs/13.x/http-client) | Reutilizar cliente instalado con timeout, TLS verificado, redirects desactivados y respuesta acotada. Las pruebas impiden peticiones no simuladas. |

Las fixtures son sintéticas y siguen esos contratos publicados; no son respuestas
capturadas de una cuenta real. La calificación con servicios reales queda pendiente
hasta disponer de claves, remitentes verificados y un destino autorizado.

## Fronteras que se conservan

- Configuración cifrada por cuenta y proveedor; los secretos no se hidratan como
  propiedades Livewire ni aparecen en auditoría o serialización.
- Un inicio durable y un snapshot de configuración antes de abrir red; ninguna
  llamada SMTP/HTTP bajo cerrojos SQL.
- Sin failover ni reenvío automático tras aceptación incierta; las negativas
  transitorias inequívocas se reprograman mediante el lifecycle existente.
- Correlación opaca por intento, sin datos de cuenta/persona en metadatos del
  proveedor; recepción autenticada con límites de cuerpo y lote.
- Feedback posterior separado del resultado de aceptación; una queja, baja o
  rebote permanente aplica la supresión de esa cuenta y no se revierte al llegar
  una entrega atrasada.
- Log/Mailpit continúan disponibles; el envío externo exige habilitación explícita
  del despliegue, configuración de cuenta y autorización del remitente.

La fuente única de progreso es [tasks.md](tasks.md). La ampliación no cierra las
puertas restantes de las otras iniciativas.
