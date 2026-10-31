# Cómo reducir el riesgo de bloqueos y proteger la reputación de envío

**Última actualización: 2026-10-31.** Campañas, supresiones, cuotas y feedback
autenticado Brevo/SendGrid están implementados y probados en laboratorio. La
cualificación privada de los cuatro canales, dominios y callbacks reales sigue
pendiente. El [manual](../manual/guia-completa.md#transporte-y-proveedores-externos)
explica su activación y los límites de esa evidencia.

Log y Mailpit permiten trabajar sin salida a Internet. Brevo/SendGrid son los
proveedores implementados, pero estas pruebas no han configurado una cuenta real,
validado un dominio privado ni medido una IP emisora pública. No se ha consultado
una lista de bloqueo para una IP concreta como parte de esta documentación.

Guardar credenciales o autorizar un remitente de laboratorio no habilita Internet.
Los canales externos exigen flag, perfil y autorización externa independiente.
Los estados `logged`, `smtp_accepted`, `api_accepted` e incertidumbre conservan
su significado; consulta [plantillas](../aplicacion/plantillas-y-transporte.md).
Las pruebas TLS controladas no acreditan una integración externa privada operativa.

## Qué podemos controlar y qué no se puede garantizar

Podemos reducir el riesgo mediante autenticación de dominio, destinatarios que consientan el envío, límites, supresiones y reacción ante incidencias. No podemos garantizar que una IP nunca aparezca en una blacklist ni que todos los mensajes lleguen a la bandeja de entrada.

Los receptores valoran varios factores: IP, dominio, autenticación, contenido y comportamiento de los destinatarios. Que una consulta a una lista pública no encuentre una IP no equivale a que todos los proveedores acepten sus mensajes.

La aplicación aplica consentimiento, supresiones, cuotas y pausas. No implementa
un monitor universal de reputación ni alertas automáticas por umbral de quejas.
No se contempla rotar IPs para eludir bloqueos.

## Qué IP envía realmente el email

Los adaptadores usan relay/API gestionados Brevo/SendGrid. El operador elige
cuenta, plan y modalidad de IP con el proveedor. El código no contrata un plan
ni decide por sí mismo si una IP dedicada conviene al volumen previsto.

```mermaid
flowchart LR
    subgraph A[Responsabilidad de la aplicación]
        W[Workers de emailing]
        Q[Cuotas, permisos y supresiones]
        W --> Q
    end
    subgraph R[Responsabilidad del proveedor de envío]
        S[Relay SMTP o API autenticados]
        IP[IP o pool de salida del relay]
        S --> IP
    end
    Q -->|SMTP o HTTPS| S
    IP -->|SMTP hacia el receptor| MX[Servidor MX del destinatario]
    S -. eventos autenticados si los ofrece .-> F[Feedback de aplicación]
```

En este patrón, el servidor destinatario recibe la conexión del relay. La IP pública del servidor que aloja la web o los workers no es necesariamente la IP emisora de ese último salto SMTP.

Si se configurase entrega directa a los MX, el servidor sí actuaría como emisor. Ese MTA propio no forma parte del alcance actual y exigiría otro diseño de operación y reputación.

El relay no elimina la responsabilidad sobre el contenido, dominio y destinatarios. Tampoco impide que otros elementos del mensaje, como sus enlaces, influyan en el tratamiento del correo.

## Quién responde de cada control

| Control | Aplicación / equipo propietario del dominio | Proveedor de envío |
|---|---|---|
| Autorizar un dominio remitente | Acreditar control del dominio y permitir solo identidades aprobadas. | Proporcionar el procedimiento de verificación y los registros necesarios. |
| SPF, DKIM y DMARC | Publicar DNS, comprobar alineación y mantenerlo actualizado. | Firmar y utilizar las identidades configuradas según su servicio. |
| IP de salida y PTR | Verificar qué modalidad se contrata y que existe una configuración válida. | Administrar las IP y DNS inverso que estén bajo su control. |
| TLS hacia el relay | Exigir conexión segura y validar el certificado en producción. | Ofrecer el canal autenticado y gestionar su entrega posterior. |
| Listas de destinatarios | Registrar procedencia/consentimiento y aplicar bajas y supresiones. | Aplicar también sus propias políticas de abuso y supresión. |
| Ritmo de envío | Cuotas por cuenta y límite agregado por relay; pausas y reintentos acotados. | Informar límites, respuestas y restricciones del servicio. |
| Rebotes y quejas | Procesar señales autenticadas, correlacionarlas y detener futuros envíos afectados. | Exponer eventos, feedback o mecanismo equivalente cuando esté disponible. |
| Incidencia de reputación | Pausar, investigar causa y corregir campañas o listas. | Gestionar su infraestructura y las solicitudes que le correspondan. |

Estas responsabilidades necesitan verificarse al seleccionar el proveedor. Un servidor SMTP genérico puede aceptar mensajes sin proporcionar webhooks ni datos completos de quejas o rebotes posteriores.

## Cómo autenticar el dominio antes del envío real

El operador debe verificar SPF, DKIM y DMARC alineados antes de habilitar el
dominio. La casilla interna de remitente autorizado no valida esos registros;
escribir una dirección en `From` no acredita su propiedad.

| Mecanismo | Comprobación en el destino |
|---|---|
| SPF | El dominio utilizado para el envelope sender autoriza a los emisores configurados. |
| DKIM | El proveedor firma con el dominio/selector acordados y la firma se valida al recibir. |
| DMARC | El dominio visible de `From` alinea con SPF o DKIM válidos; se revisan los informes y la política. |
| DNS directo/inverso | La IP emisora real y sus registros son coherentes con la configuración del relay. |
| Remitente autorizado | La cuenta puede usar ese dominio y esa identidad; la API y los workers aplican la misma regla. |

Las [directrices de Gmail](https://support.google.com/mail/answer/81126) distinguen
requisitos según remitente y volumen. Revisa sus condiciones vigentes y las del
proveedor antes de cualificar el destino. Este manual no ha vuelto a consultar
esas reglas externas ni convierte una cifra de volumen en garantía de aceptación.

Adoptar los tres desde el diseño es una elección de este proyecto, no la afirmación de que nuestro volumen inicial active todos los requisitos de remitente masivo. Se revisarán las reglas del receptor y proveedor antes de habilitar producción.

Las campañas incluyen un enlace de baja visible con confirmación. No se presenta
ese recorrido como implementación automática de todas las cabeceras/requisitos
de baja de un clic de cada receptor. Cualifica lo exigido por el tipo de mensaje
y proveedor antes del uso real.

## IP compartida o dedicada

| Modalidad | Ventaja | Compromiso |
|---|---|---|
| Pool compartido gestionado | El proveedor mantiene infraestructura y un pool ya operativo. | La reputación puede verse afectada por otros emisores del pool. |
| IP dedicada | Mayor control sobre el tráfico que usa esa IP. | Exige mantener reputación, ritmo adecuado y calentamiento; no garantiza mejor entrega. |

SendGrid explica estas diferencias en su [documentación de IP dedicada](https://www.twilio.com/docs/sendgrid/ui/account-and-settings/dedicated-ip-addresses). También describe el [calentamiento gradual](https://www.twilio.com/docs/sendgrid/concepts/reputation/warm-up-ip-addresses) de IPs nuevas y aclara que no garantiza una reputación perfecta.

Para 100–200 mensajes diarios no asumimos que comprar una IP dedicada sea la mejor opción. La elección dependerá de constancia del tráfico, presupuesto, separación disponible, soporte y política del proveedor. Se validará antes del envío externo.

## Qué debe impedir la aplicación

Los controles actuales comparten pipeline y cuenta. Sus detalles están en
[entrega](../aplicacion/entrega-y-recuperacion.md),
[actividad](../aplicacion/actividad-y-metricas.md) y
[proveedores](../aplicacion/proveedores-email.md).

| Situación | Comportamiento actual o límite |
|---|---|
| Cuenta sin remitente autorizado | Bloqueo interno de externo; dominio/DNS se verifican con el proveedor. |
| Destinatario dado de baja | Excluirlo de las comunicaciones afectadas, incluso si se vuelve a importar. |
| Rebote permanente confirmado o queja | Registrar evidencia y suprimir futuros envíos según alcance y política definidos. |
| Negativo SMTP transitorio demostrado | Reintento seguro acotado; un aplazamiento por webhook no crea por sí solo otro envío. |
| Respuesta de límite del relay | Reducir ritmo o pausar, respetando su semántica y política. |
| Aumento anómalo de rechazos o quejas | Operador investiga y pausa; no hay umbral automático universal implementado. |
| Varias cuentas usando el mismo relay | Aplicar cuotas individuales y un límite agregado; no multiplicar la cuota global por worker. |
| Envío interrumpido con resultado ambiguo | Conservar el estado y reconciliar; no reenviar a ciegas. |

Una cuota por usuario sin coordinación no controla el agregado. El mecanismo
actual comparte estado SQL por cuenta y pool de relay elegido por el servidor.

Las cuentas internas comparten infraestructura física. Las campañas y las notificaciones de acceso o recuperación de contraseña podrán usar transportes o dominios separados si el proveedor lo permite. Esa separación lógica no demuestra aislamiento de IPs o reputación; debe verificarse con el servicio elegido.

## Cómo recibir rebotes, quejas y confirmaciones

Un `250` del relay confirma que ese relay aceptó el mensaje. No demuestra llegada al servidor final, presencia en bandeja de entrada ni lectura.

La aplicación diferencia aceptación SMTP/API y feedback posterior de entrega,
rechazo, aplazamiento, queja y baja. No llama entregado a un mensaje del que solo
conoce aceptación inicial; sin eventos el resultado externo sigue desconocido.

Algunos proveedores ofrecen eventos de entrega y de quejas. La [referencia de eventos de SendGrid](https://www.twilio.com/docs/sendgrid/for-developers/tracking-events/event) ilustra esa separación y exige proteger el receptor de eventos mediante su mecanismo de autenticación.

La integración verifica firma P-256 en SendGrid y Bearer privado en Brevo,
correlaciona con referencia opaca y destinatario e impone dedupe. Ordena callbacks
con inicios durables bajo mutex de cuenta. No confía en una cuenta elegida por
el payload. Los eventos adversos no se revierten con uno benigno posterior.

Si el relay solo ofrece SMTP, habrá que acordar un mecanismo viable para los fallos posteriores: por ejemplo, DSN en un buzón controlado si lo soporta. Una dirección de retorno por sí sola no implementa su lectura, validación ni correlación. Sin feedback suficiente no se podrán presentar tasas completas de entrega o quejas.

## Cómo monitorizar sin inventar garantías

Primero se identificará el relay y las IPs o pools que usa realmente. En pools compartidos las IPs pueden variar, por lo que monitorizar una única IP observada no cubre todas las salidas.

El seguimiento combinará señales del proveedor, rechazos SMTP, dominios receptores y, cuando existan datos suficientes, sus herramientas de reputación. Una consulta DNSBL aislada no resume la reputación del dominio ni los filtros privados de cada receptor.

No hay consultas masivas DNSBL ni credenciales de monitorización de reputación
configuradas por estas pruebas. Si se integran, respeta condiciones de uso,
límites y significado de cada respuesta. Los perfiles proveedor del producto
no equivalen a una cuenta real ya configurada por el operador.

Con muestras pequeñas, una sola queja puede alterar mucho un porcentaje. El panel deberá mostrar conteos, intervalo y cobertura de datos. Usará estados como «sin datos» cuando el relay no informe; no mostrará un 0 % ficticio.

Los umbrales de alerta y pausa siguen pendientes de concretar con el proveedor. No se fijará un número universal como garantía de que no habrá bloqueos.

## Qué comprobar antes de permitir correo externo

1. Seleccionar relay, modalidad de IP y límites; documentar condiciones de reputación y feedback disponibles.
2. Verificar dominio, remitente, SPF, DKIM, DMARC y configuración DNS/TLS del relay.
3. Verificar en el destino bajas, supresiones, cuotas, pausas y rotación de credenciales ya implementadas.
4. Validar autenticación, deduplicación y correlación del mecanismo de feedback elegido.
5. Hacer una prueba limitada hacia buzones controlados, inspeccionar cabeceras y revisar el resultado real.
6. Registrar qué señales se pueden medir y qué permanece desconocido antes de aumentar volumen.

Estas comprobaciones mantienen la cualificación privada M-01 y revisión humana
M-02. No cambian los verdes locales ni habilitan salida por sí mismas.
