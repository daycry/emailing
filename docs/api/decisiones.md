# Reglas de admisión e integración de la API

**Última actualización: 2026-10-31.** Reglas de la API implementada, con ampliación
REST 1.1.0 para proveedores. El [manual](../manual/guia-completa.md#llaves-y-api-rest)
explica su uso con ejemplos; esta página mantiene la semántica de integración.

Este documento concreta el [contrato revisado](openapi.yaml) de T-17, basado en
CA-19/20 del [alcance aprobado](../roadmap/2026-10-21-plataforma-emailing/spec.md)
y en el pipeline de entrega existente. El contrato pasó la revisión 21 y la
validación documental. No acredita endpoints desplegados ni sustituye
las pruebas de comportamiento de T-18. La decisión transversal se registra como
[ADR-007 revisada](../knowledge/adr/ADR-007-admision-api-y-destinatarios.md).

## Credencial, cuenta y permisos

Una única cabecera `Authorization: Bearer …` autentica cada operación. Una
credencial inválida, revocada, caducada o ausente devuelve `401`, aunque exista
una sesión administradora. La cookie y la cuenta activa de la web no participan
en esta autenticación. Un bearer vigente con permisos insuficientes recibe
`403`; los recursos ausentes y ajenos se presentan como `404` dentro del
contexto de cuenta, sin revelar la identidad de otra cuenta.

Los únicos scopes de estas llaves son `mail.send`, `templates.read`,
`activity.read`, `api-keys.read` y `api-keys.write`. No existe `*`. Enviar
contenido inline requiere `mail.send`; enviar una plantilla almacenada exige
además `templates.read`, incluso al repetir una petición aceptada. Conocer el
ID de una plantilla no concede permiso para leer su contenido indirectamente.

La UI crea la primera llave mediante membresía vigente y CSRF; las concesiones
web y bearer comparten la misma regla de dominio. Ningún actor puede conceder
scopes superiores a los que tiene en ese momento. Un miembro read-only no crea
llaves. Un padre con caducidad no concede una duración mayor: omitirla hereda
su límite; `null` o una fecha posterior se rechazan. Las fechas son UTC con
`Z`, hasta seis decimales y válidas para SQL, comparadas con el reloj SQL UTC.

La llave es un recurso de cuenta, con un ID público distinto de la fila interna
de Sanctum. La baja de su creador no la revoca. Cada llave emitida tiene su propia
vigencia; revocar la concedente tampoco revoca automáticamente sus descendientes.
Un actor autorizado puede revocar cualquier llave de su cuenta, sin borrar los
contactos, campañas ni trabajo ya admitido. La auto-revocación responde `204`;
el siguiente uso de esa credencial responde `401`. Otra llave vigente puede
repetir la revocación y obtener `204`.

La validez, concesión, admisión, replay y revocación se serializan con el orden
de bloqueo `Account → credencial → petición/origen`. La validez y los scopes se
revisan dentro de la transacción de la operación, sin invertir los locks al
actualizar `last_used_at`. Si una revocación confirma antes que la admisión,
esta se deniega; el trabajo que confirmó antes permanece admitido. Se guardan
hashes de secretos con Sanctum; la autenticación session-first por sí sola no
cumple esta frontera. Las credenciales delegadas MCP no se confunden con estas
llaves y usan su propio contexto al llamar a los servicios comunes.

## Destinatarios y consentimiento

La API admite direcciones explícitas sin crear Campaign ni Contact. Guarda la
autorización real de la credencial y de la petición. Esa evidencia no equivale
a consentimiento del destinatario, que permanece no acreditado cuando no hay
contacto. No se inventan fechas, orígenes ni estados `granted`.

La admisión se hace bajo el mutex de cuenta. Una supresión vigente o cualquier
contacto coincidente no elegible, incluido un contacto eliminado lógicamente,
rechaza todo el lote con `422`, sin petición, entregas ni outbox parciales.
Los contactos conocidos deben estar activos, mantener su identidad, tener
consentimiento `granted`, origen no vacío y fecha existente no futura según SQL
UTC. Si hay varias coincidencias históricas, ninguna puede quedar fuera de esa
comprobación para eludir una baja anterior. El caller no selecciona Contact ni
una política alternativa en su payload.

Antes de iniciar el transporte se comprueban otra vez las supresiones y la
elegibilidad vigente. La entrega vinculada a un contacto queda `suppressed`
cuando pierde identidad, se elimina o pierde consentimiento; no se convierte
en envío directo. Un contacto no elegible creado después también impide iniciar
un envío originalmente directo. Una causa detectada antes del inicio durable
deja cero intentos SMTP. Una modificación posterior a ese inicio no puede
cancelar retroactivamente un intercambio SMTP que ya empezó.

## Idempotencia y resultados

`Idempotency-Key` tiene de 16 a 128 caracteres ASCII del patrón publicado, es
sensible a mayúsculas y pertenece a la cuenta, no a la llave emisora. La misma
cuenta con otra llave autorizada puede repetir la petición. En otra cuenta la
misma cadena es independiente. Su titularidad única es durable, sin TTL ni
reutilización automática; archivar conserva la protección y el recibo original.
El identificador no depende de una clave `APP_KEY` que pueda rotar.

Se valida y normaliza el input del cliente antes de consultar contenido mutable.
Se normalizan emails, enteros y defaults; se ordenan recursivamente las claves
de objetos y se conserva el orden de los destinatarios y cada carácter del
contenido. El fingerprint excluye la credencial concreta y los bytes actuales
de la plantilla. Su formato/versionado conserva la equivalencia de recibos
antiguos. La misma entrada normalizada devuelve el primer cuerpo `202`, sus
IDs, fecha y `Location`, junto a `Idempotency-Replayed: true`. Una entrada
distinta devuelve `409`. La autenticación, scopes y límites actuales siguen
aplicándose antes del replay.

Un replay idéntico no reinterpreta una plantilla, remitente o contacto cambiado
ni crea trabajo. La elegibilidad vigente sigue gobernando el trabajo original
que aún no inició transporte. La aceptación nueva crea petición inmutable,
destinatarios, entregas y outbox inicial en una misma transacción. Procesos
concurrentes y llaves diferentes de la cuenta deben obtener un único recibo y
una entrega por destinatario. Ante una respuesta perdida o un resultado SQL
incierto se reintenta con la misma clave, sin deducir del error que no se confirmó.

`202` significa aceptación SQL/outbox. El broker puede estar caído y el dispatcher
publicará después. `logged`, `smtp_accepted` y `api_accepted` son observaciones distintas;
ninguna significa entrega al buzón. El estado `ambiguous` no genera reenvío
automático. La consulta devuelve una población lógica coherente con sus
contadores, sin inflarla por reintentos y sin cuerpos ni diagnósticos SMTP crudos.

## Límites y errores

Los valores iniciales son 100 destinatarios únicos por petición, 2.097.152 bytes
del cuerpo UTF-8, profundidad JSON máxima 32 y 200.000 caracteres por campo
inline HTML/texto. Son límites de admisión, sin evidencia de capacidad. Ambas
instancias web admiten el límite de aplicación; el parser HTTP o proxy puede
rechazar peticiones que no lleguen a ese límite y no promete su envelope JSON.
No se aceptan JSON ambiguo con claves duplicadas, coerciones de tipos, campos
desconocidos, selección ajena de cuenta ni credenciales por URL/body. Solo los
dos POST reciben cuerpo; el listado de llaves admite `limit` y `cursor`.

El minuto fijo UTC comparte 60 peticiones por cuenta y 30 por llave entre las
cinco operaciones e instancias con la misma base. Tras autenticar, cada petición
admitida cuenta, incluidos replay y errores posteriores. Un `429` no consume
contador y devuelve los segundos positivos hasta el siguiente minuto mediante
`Retry-After`. Los workers mantienen cuotas de transporte independientes.
Si no se puede establecer de forma atómica la vigencia o el límite, se responde
`503` sin autorizar trabajo sin contabilizar. La UI conserva su propia frontera
de permisos para la revocación administrativa.

Los errores de aplicación tienen un envelope cerrado `error.code/message/fields`.
Los códigos y estados son `malformed_request`/400, `unauthenticated`/401,
`forbidden`/403, `not_found`/404, `idempotency_conflict`/409,
`payload_too_large`/413, `unsupported_media_type`/415, `validation_failed`/422,
`rate_limited`/429 y `temporarily_unavailable`/503. No se divulgan secretos ni
diagnósticos internos. Las respuestas de éxito y error llevan
`Cache-Control: no-store`. Crear una llave muestra el secreto una sola vez;
repetir ese POST tras perder la respuesta puede crear otra llave. Su creación
no tiene la promesa idempotente del envío.

## Integración de T-18 revisada y probada

| Pieza existente | Obligación de integración |
|---|---|
| `DeliveryMaterializer` y FKs de campaña | Añadir un origen API explícito con referencias coherentes por cuenta y restricciones de rama; conservar las garantías de campaña y su checkpoint de revisión. No fabricar campañas. |
| `DeliveryLifecycle` y `AccountMailTransport` | Resolver el origen inmutable y compartir el único inicio transaccional con fencing, supresión, remitente autorizado/snapshot, modo log/Mailpit y cuotas de cuenta/pool. No llamar al envío síncrono desde HTTP. |
| `DeliveryEnvelope`, dispatcher, publisher y consumer | Conservar referencias mínimas, confirms, recuperación, acks manuales y outbox por generación; SQL determina el origen. No introducir un worker SMTP distinto. |
| Recuperación de entrega | Mantener backoff seguro, límite de intentos, fallos durables y ausencia de reenvío automático ante posible aceptación o lease vencido en `sending`. |
| Actividad, detalle, agregados y CSV | Distinguir origen API, llave y petición reales en cada intento; conservar población lógica, evidencia y escape de fórmulas. No atribuir la llamada al creador como usuario interactivo ni clasificarla como prueba o histórico desconocido. |
| Servicios de permisos y contexto | UI usa membresía; REST usa bearer explícito de cuenta; MCP usa delegación vigente. Comparten dominio sin suplir identidad desde una sesión. |

La implementación y sus resultados se registran en el ledger T-18 y la [guía de uso](implementacion.md). La revisión 23 no deja gaps pendientes y el QA de fase acredita dos recorridos completos de E2E-10 en Laragon/Docker.
Las matrices de preparación tienen 15 casos de negocio y 65 de frontera bearer,
cuyo estado histórico no debe confundirse con los resultados nuevos del ledger. Su revisión no equivale a pruebas aprobadas.
T-18 acredita mediante sus pruebas operaciones y errores contra el contrato, concesión y
revocación concurrentes, dos cuentas, cookie administradora con bearer limitado,
idempotencia entre procesos MySQL, mutación de plantilla, supresión posterior,
broker caído, relay controlado, aceptación ambigua y actividad fiel. E2E-10 y
CA-19/20 se aceptan por implementación, revisión y pruebas; lint y validación del documento por sí solos no los acreditan.
