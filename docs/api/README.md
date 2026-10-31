# Contrato de la API de Emailing

**Última actualización: 2026-10-31.**

El [contrato OpenAPI 3.0.3](openapi.yaml) define la API REST `/api/v1`,
implementada en T-18. T-17 está aprobada técnicamente tras la
[revisión 21](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/review/attempt-21.md)
y la validación documental. T-18 está aprobada tras la [revisión 23](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/review/attempt-23.md) y el [QA de API](../roadmap/2026-10-21-plataforma-emailing/testing/fase-4/t18-api/report.md), con dos recorridos completos en Laragon/Docker. CA-19/20 y E2E-10 están satisfechos técnicamente; no se acredita migración/despliegue operativo.
La [guía de implementación y uso](implementacion.md) explica la primera llave,
los límites compartidos, el pipeline y la preservación de los recibos.

La ampliación de [proveedores](../roadmap/2026-10-30-proveedores-email/tasks.md)
publica REST 1.1.0 y catálogo MCP 0.2.0, con adaptadores integrados y pruebas
locales. Añade
`api_accepted` como estado y contador propio, sin reinterpretar `smtp_accepted`;
los contadores y datos de feedback nuevos son opcionales en el esquema.
`provider_feedback` distingue eventos posteriores de la aceptación del transporte
y `delivery_feedback=provider_events` indica que hay evidencia de proveedor en
alguna entrega de esa respuesta, sin afirmar la entrega de todas. Los consumidores
que enumeran estados exhaustivamente deben incorporar el nuevo valor.
Las 37 herramientas y sus permisos se conservan; los fixtures 0.1.0 mantienen sus
bytes y 0.2.0 dispone de su propio manifiesto y pin de integridad. El
[contrato de webhooks](mail-provider-webhooks.openapi.json) define autenticación
Brevo/SendGrid, cuerpo acotado y respuestas de confirmación. Estos contratos no
acreditan por sí solos una conexión real con los proveedores.

| Operación | Scope obligatorio | Resultado de éxito |
|---|---|---|
| `POST /api/v1/mail/send` | `mail.send`; también `templates.read` para plantilla almacenada, incluso en replay | `202`, recibo SQL/outbox inmutable |
| `GET /api/v1/mail/requests/{requestId}` | `activity.read` | `200`, estados observados de la cuenta |
| `GET /api/v1/keys` | `api-keys.read` | `200`, metadatos paginados |
| `POST /api/v1/keys` | `api-keys.write` y techo de concesión vigente | `201`, secreto una sola vez |
| `DELETE /api/v1/keys/{keyId}` | `api-keys.write` | `204`, revocación de una llave de la cuenta |

La cuenta procede exclusivamente de un bearer válido. La cookie de la web,
Google y un selector de cuenta no sustituyen esa credencial ni amplían sus
scopes. Las llaves de automatización pertenecen a la cuenta; los grants
delegados MCP son otro tipo de autorización, con sus propias condiciones de
membresía. Google identifica a una persona, sin conceder por sí mismo permisos.

Consulta las [decisiones y reglas de integración](decisiones.md), los
[ejemplos sintéticos](ejemplos.md) y la
[decisión revisada ADR-007](../knowledge/adr/ADR-007-admision-api-y-destinatarios.md).
El contrato contiene los límites y errores exactos; las extensiones `x-*`
expresan reglas que el esquema por sí solo no puede validar, como la cuenta
vigente, los scopes condicionales, la unicidad normalizada y la idempotencia.

Log y Mailpit siguen disponibles. Los cuatro canales Brevo/SendGrid requieren
habilitación externa, perfil preparado y remitente autorizado; consulta las
[recetas y límites](../aplicacion/proveedores-email.md). El `202` de admisión de
esta API no demuestra publicación AMQP, aceptación SMTP/API del proveedor ni
entrega al destinatario. No se promete compatibilidad íntegra con SendGrid ni un
caudal no medido. El QA local no acredita la cualificación privada de proveedores.

La verificación estructural se reproduce con el plugin custom-agents instalado:

```powershell
python "$env:CUSTOM_AGENTS_PLUGIN_ROOT/skills/api-contract/scripts/openapi-lint.py" docs/api/openapi.yaml
```

El linter comprueba su subconjunto estructural; no prueba el comportamiento de
los endpoints. Antes de modificar un contrato ya publicado, compara la versión
base con la propuesta mediante `openapi-lint.py --diff <base> <propuesta>` y
revisa también los cambios semánticos que ese comparador no detecta.

MCP 0.2.0: `connection_info.senders` enumera los remitentes autorizados para el transporte activo. `lab_authorized` pasa de literal `true` a booleano real y se añade `external_authorized` opcional. Los clientes que validen el literal anterior deben adoptar el contrato 0.2.0 antes de usar canales externos; el campo no se falsea para compatibilidad. Los fixtures 0.1.0 permanecen intactos.

El diagnóstico aditivo `smtp_tls_required` indica rechazo local de la negociación
ESMTP requerida antes de AUTH/sobre/cuerpo y bloquea el fallback que eludiría TLS.
No es un código SMTP del servidor ni permite retry. Su implementación y las
pruebas de transporte constan en la [guía de proveedores](../aplicacion/proveedores-email.md).
