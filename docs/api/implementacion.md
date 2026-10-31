# Usar y mantener la API de cuenta

**Última actualización: 2026-10-31.** El [manual](../manual/guia-completa.md#llaves-y-api-rest)
explica llaves, ejemplo de petición, recuperación idempotente y errores para
quien empieza sin experiencia API.

T-18 incorpora el código de las cinco operaciones del [contrato](openapi.yaml)
y la pantalla **Llaves API**. La [revisión 23](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/review/attempt-23.md) y el [QA de navegador](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/t18-api/report.md) están aprobados, con un recorrido completo en Laragon y otro en Docker. No acredita migración de la base operativa,
despliegue, entrega externa ni compatibilidad Google/MCP.

## Primera llave y permisos

Accede con una membresía vigente, selecciona la cuenta y abre **Llaves API**.
Quien tiene `api-keys.write` puede crear y revocar; `api-keys.read` permite
consultar los metadatos. Read-only no crea llaves. Cada concesión se limita a
los permisos actuales del miembro; no basta con conocer un ID o modificar el
formulario. Los POST/DELETE de la pantalla conservan la protección CSRF web.

Selecciona los permisos mínimos y, opcionalmente, una caducidad futura UTC como
`2027-01-01T00:00:00Z`. Guarda el secreto de la respuesta en tu gestor de
secretos y pulsa **Ya lo he guardado: ocultar secreto**. No se guarda en la
sesión ni en un estado público Livewire. La lista nunca vuelve a mostrarlo.
Si se pierde la respuesta, consulta los metadatos y revoca la llave que no
puedas utilizar. Repetir una creación puede emitir otra llave.

Las llaves pertenecen a la cuenta, aunque desaparezca la membresía o identidad
de su creador. Revocar la concedente no revoca automáticamente sus hijas. Un
padre con caducidad limita la de las llaves que emite: omitirla hereda su fecha;
`null` o una fecha posterior se rechazan. Auto-revocar devuelve `204` y el
siguiente uso falla con `401`.

## Solicitudes

El cliente configura una única cabecera `Authorization: Bearer <secreto>` desde
su almacén privado. No añadas credenciales a URLs, repositorios, capturas o logs.
La API no utiliza cookies ni el selector web. No acepta overrides de cuenta,
usuario, propietario, contacto o transporte. Una sesión administradora no
amplía un bearer limitado, caducado o revocado.

El [ejemplo inline](ejemplos.md) requiere `mail.send`. Una plantilla almacenada
requiere además `templates.read`, incluso al repetir la misma petición. Los IDs
de remitente y plantilla proceden de los recursos de la cuenta seleccionada;
los ajenos o inexistentes devuelven el mismo `404`. El parser conserva el cuerpo
original UTF-8, distingue objetos/listas/números/cadenas y rechaza claves JSON
duplicadas, tipos incorrectos y campos desconocidos.

Usa una `Idempotency-Key` de 16–128 caracteres ASCII admitidos y conserva esa
misma clave al reintentar tras un timeout o `503`. La protección pertenece a la
cuenta, también entre distintas llaves autorizadas. Un input normalizado igual
devuelve el recibo original con `Idempotency-Replayed: true`; uno diferente
devuelve `409`. El orden de destinatarios y todos los caracteres del contenido
son significativos. Cambiar después el remitente, plantilla o contacto no
reinterpreta un replay ni crea otro trabajo. No existe TTL que libere esa clave.

Un `202` fija petición, contenido por destinatario, remitente, entregas y outbox
en SQL. Consulta `Location` con `activity.read`: sus contadores describen la
misma población lógica que la lista, sin sumar reintentos como destinatarios.
`logged`, `smtp_accepted` y `ambiguous` son observaciones diferentes. Ninguna
acredita entrega al buzón; un resultado ambiguo no se reenvía automáticamente.

Los destinatarios directos no crean contactos ni evidencias de consentimiento.
Los contactos conocidos, incluidos los eliminados lógicamente, deben conservar
identidad y consentimiento vigente. Toda coincidencia no elegible y toda
supresión rechazan inicialmente el lote completo con `422`. Una baja posterior,
un contacto vinculado modificado o un nuevo contacto no elegible impiden el
inicio durable de transporte. Un contacto vinculado no pasa a envío directo.

## Operación compartida

La admisión HTTP usa SQL UTC: 60 peticiones por cuenta y 30 por llave en el
minuto fijo, compartidas entre las cinco operaciones y todas las instancias.
Los errores autenticados y replays cuentan; `429` no incrementa los contadores
y devuelve `Retry-After`. Son límites de admisión, distintos de las cuotas de
transporte y de cualquier capacidad medida.

El mutex mantiene el orden `Account → credencial → petición/origen` durante
autenticación vigente, contador y acción. Un savepoint permite deshacer todo
el trabajo parcial de una acción fallida conservando su admisión HTTP. Si SQL
no puede confirmar ese límite o el resultado, la API falla cerrada con `503`;
el cliente conserva la clave idempotente. No se deduce de una conexión perdida
que la aceptación no haya confirmado.

La entrega API utiliza los mismos `DeliveryOutbox`, dispatcher con confirms,
consumer con ack manual, reservas, cuotas e inicio de transporte que campañas.
Los comandos existentes siguen siendo:

```text
php artisan app:dispatch-outbox --limit=20
php artisan deliveries:work --once
```

El segundo comando procesa como máximo el evento disponible de esa invocación;
la operación normal de los workers conserva el arranque documentado del
laboratorio. El HTTP no llama al envío síncrono ni crea otro worker SMTP. Si el
broker no está disponible, SQL/outbox puede aceptar y publicar después.
Log y Mailpit siguen disponibles. Brevo/SendGrid SMTP/API comparten ese pipeline
con flag externo, perfil preparado y remitente autorizado. Consulta las
[recetas](../aplicacion/proveedores-email.md); la cualificación real permanece pendiente.

Actividad distingue **Entregas lógicas de API**, conserva llave/petición por
intento y permite filtrar/exportar esta población. El actor API no se atribuye
al creador de la llave como si fuera una persona usando el navegador. El CSV
incluye referencias de llave y petición, nunca secretos/hashes/cuerpos, y
conserva el escape de fórmulas también para nombres de llaves.

## Esquema y preservación

La migración `2026_10_24_000013_create_sending_api` añade tablas de llaves,
ventanas HTTP y fuentes API. Sanctum almacena/compara hashes SHA-256 en un
modelo de credencial de cuenta; su guard session-first no autentica estas rutas.
Los UUID públicos son distintos de los IDs internos de tokens.

Los CHECK y FKs compuestos SQL impiden mezclar cuentas, ramas de campaña/API,
destinatarios, peticiones, credenciales e intentos. Las FKs de campañas y el
checkpoint de materialización se conservan. SQL no impone `actor_id IS NULL`:
MySQL impide ese CHECK sobre la FK de usuario con `SET NULL`. El constructor
común y el guard del modelo impiden esa atribución en la aplicación. No se
introducen triggers ni se requieren SUPER o cambios de binary logging.

Los modelos de fuentes aceptadas rechazan actualización o borrado individual;
las llaves se revocan. Es una garantía de aplicación además de las referencias
SQL, no una protección contra un administrador que modifica datos directamente.
No borres recibos/llaves para limpiar históricos: perderías procedencia y
protección de replay. Un respaldo/restauración debe preservar conjuntamente
estas tablas, entregas, outbox e intentos. La prueba de carga/restauración global
sigue en T-19; esta implementación no la sustituye.

El downgrade se niega antes de DDL si existen peticiones API. Una retirada debe
detener admisión y preservar recibos/titularidad en su archivo o respaldo; antes
de reactivar la API se restauran también esas protecciones. La prueba de
roundtrip con campañas se ejecuta solo en `emailing_testing`. No uses
`migrate:fresh` sobre `emailing` ni alteres `lab_smoke`.

## Verificación y QA de fase

Los identificadores numéricos se validan desde el lexema JSON exacto: `1.0` y
`1e0` son integrales; una fracción que PHP redondearía a un entero se rechaza.
Esta normalización precede al fingerprint y conserva el texto literal de
asunto/cuerpos. Los errores autenticados de dominio consumen admisión mediante
savepoint; si MySQL aborta una transacción completa, la operación falla y
restaura el límite transaccional. No se afirma que el contador de admisión
sobreviva a una unidad SQL abortada por infraestructura.

```text
php artisan test --filter=SendingApiTest
php artisan test --group=delivery
```

Las pruebas requieren el guard de MySQL exclusivo, sockets, broker del
laboratorio y fixtures locales controlados. Los procesos independientes reciben
bearers por stdin privado, sin incluirlos en argumentos o recibos. La evidencia
privada conserva hashes de fuentes, resultados originales, fallos y liquidación
de recursos. El [ledger T-18](../roadmap/2026-10-21-plataforma-emailing/tasks.md)
conserva resultados y límites: focal API actual 28 pruebas/767 aserciones,
unitario de números 3/244 y focal migración/contactos 19/282. El grupo delivery
anterior al último fix pasó 164/3071; no se presenta como ejecución posterior.

El QA de fase acredita E2E-10 con creación/ocultación/revocación en Laragon y
Docker, Admin/Read-only/Restricted, cuenta activa, ausencia de secretos en
pantallas posteriores y actividad API. Ambos recorridos pasan sin retries.
M-01/M-02 siguen pendientes humanas; T-19 conserva carga y restauración.
Google/MCP usan otra iniciativa y credenciales distintas. Tienen implementación
y QA de laboratorio propios; las muestras negativas de esta API no acreditan
sus clientes/Google reales. MCP conserva 11/14 y T12–T14 pendientes.
