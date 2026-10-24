# Ejemplos del contrato revisado

Estos ejemplos describen T-17; los endpoints se implementan y prueban en T-18.
Todos los datos son sintéticos, los dominios son reservados y los UUID son
identificadores públicos ficticios. El cliente añade un bearer real desde su
configuración privada; aquí no se publica ningún secreto ni comando con una
credencial activa. En log/Mailpit no se envía correo externo.

Una petición inline a `POST /api/v1/mail/send` añade
`Content-Type: application/json` e `Idempotency-Key: lab-request-00000001`:

```json
{
  "sender_id": 1,
  "recipients": [{"email": "recipient@example.test", "name": "Example"}],
  "content": {
    "type": "inline",
    "subject": "Mensaje de laboratorio",
    "text": "Mensaje de prueba."
  }
}
```

Su éxito contractual es `202`, `Cache-Control: no-store`,
`Idempotency-Replayed: false` y
`Location: /api/v1/mail/requests/00000000-0000-4000-8000-000000000001`:

```json
{
  "request_id": "00000000-0000-4000-8000-000000000001",
  "delivery_ids": ["00000000-0000-4000-8000-000000000002"],
  "accepted_at": "2026-10-24T12:00:00Z",
  "state": "accepted"
}
```

Repetir con la misma entrada normalizada conserva el cuerpo y `Location`, con
`Idempotency-Replayed: true`. Cambiar, por ejemplo, el asunto manteniendo esa
clave devuelve `409`, sin otro trabajo:

```json
{"error":{"code":"idempotency_conflict","message":"La clave ya identifica otra petición."}}
```

Enviar una plantilla almacenada requiere `mail.send` y `templates.read`, tanto
en aceptación como en replay. El servidor resuelve ambos IDs dentro de la cuenta
de la credencial y guarda el contenido aceptado:

```json
{
  "sender_id": 1,
  "recipients": [{"email": "recipient@example.test"}],
  "content": {"type": "template", "template_id": 2}
}
```

No se añade `account_id`, ni ID de contacto, transporte o bypass de supresiones.
Un remitente/plantilla inexistente o ajeno recibe `404`; la ineligibilidad de un
contacto conocido o una supresión devuelve `422` antes de crear el lote.
Ante una caída del broker, SQL/outbox puede seguir confirmando `202`.

`GET /api/v1/mail/requests/{requestId}` necesita `activity.read` incluso para
una petición creada con la misma llave. Los estados y contadores distinguen
`pending`, `logged`, `smtp_accepted` y `ambiguous`; el campo
`delivery_feedback: "not_available"` indica que el laboratorio no acredita
entrega, apertura ni clics. El ejemplo completo figura en OpenAPI.

Crear una llave usa `POST /api/v1/keys`, `application/json` y autoridad vigente
de concesión. Omitir la caducidad hereda el límite del padre cuando existe:

```json
{"name":"Integracion de laboratorio","scopes":["mail.send","activity.read"]}
```

La respuesta `201` contiene sus metadatos y el secreto una sola vez; no se
incluye aquí un valor ficticio que pueda confundirse con una credencial útil.
`GET /api/v1/keys?limit=20` devuelve metadatos, nunca secretos ni hashes. El
cursor de la página siguiente se trata como opaco y no puede cambiar de cuenta.
`DELETE /api/v1/keys/00000000-0000-4000-8000-000000000003` necesita
`api-keys.write`; la revocación correcta devuelve `204` sin body. Si la llave
revocada era la propia credencial, su siguiente uso falla con `401`.

Una cookie administradora sin bearer también devuelve `401`. La misma cookie
con un bearer válido que carezca del scope requerido devuelve `403`. Los
errores conservan `Cache-Control: no-store`; el `401` añade el desafío Bearer y
el `429` añade `Retry-After`. Las cuotas HTTP y SMTP son distintas: un replay
consume admisión HTTP sin crear una nueva entrega.
