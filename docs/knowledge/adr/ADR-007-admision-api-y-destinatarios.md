---
id: ADR-007
titulo: Admisión API durable y destinatarios con controles vigentes
estado: "aceptada (validada: revisión de dos lentes, 2026-10-24, intento 21)"
fecha: 2026-10-24
iniciativa: 2026-10-21-plataforma-emailing
---

# ADR-007: Admisión API durable y destinatarios con controles vigentes

## Contexto

El [alcance C-07 y CA-19/20](../../roadmap/2026-10-21-plataforma-emailing/spec.md)
requiere envío bearer con llaves de cuenta e idempotencia, reutilizando el
pipeline existente. El envío de campañas exige identidad/consentimiento vigente,
pero la API admite destinatarios explícitos sin que exista una campaña. Había
que concretar esa autorización sin fabricar contactos o retirar las supresiones,
y distinguir la credencial de cuenta de una sesión personal o delegación MCP.

## Decisión

La API admite un origen inmutable propio bajo una credencial de cuenta vigente,
sin crear Campaign ni Contact; aplica supresiones y elegibilidad de contactos
conocidos en admisión y antes del inicio común de transporte. La procedencia de
una dirección sin contacto acredita autorización de la petición por la cuenta,
sin afirmar consentimiento verificado del destinatario. Cualquier coincidencia
no elegible, incluida la eliminada lógicamente, rechaza el lote inicial. Un
contacto vinculado nunca se convierte en autorización directa tras una baja o
cambio de identidad. Un contacto no elegible que aparece después también impide
iniciar el transporte.

La aceptación fija el contenido, destinatarios, remitente y procedencia junto
con entregas/outbox en SQL. La idempotencia pertenece a la cuenta entre llaves,
sin TTL que permita reutilización, y compara el input normalizado antes de
resolver recursos mutables. El replay mantiene el recibo original y revalida
credencial, scopes y admisión HTTP, sin crear trabajo. La clave de cuenta no
depende de la membresía posterior de su creador; la revocación explícita se
serializa con la admisión bajo el mismo mutex. Las delegaciones MCP conservan
su identidad y condición de membresía, conforme a la propuesta separada
[ADR-006](ADR-006-identidad-google-y-oauth-mcp-delegado.md).

## Alternativas descartadas

- **Exigir siempre Campaign/Contact**: recorta el envío directo aprobado y
  obliga a crear recursos que no representan esa petición.
- **Crear contactos con consentimiento implícito**: registra como hecho una
  autorización del destinatario que la credencial no acredita.
- **Omitir controles conocidos para destinatarios directos**: permitiría eludir
  bajas/supresiones y contactos eliminados utilizando otro canal de entrada.
- **Usar el envío síncrono o un worker SMTP API separado**: evita las garantías
  actuales de outbox, fencing, cuotas y recuperación de aceptación ambigua.
- **Idempotencia por llave con caducidad automática**: permite duplicar trabajo
  al rotar credenciales o repetir tarde una petición de la misma cuenta.
- **Aceptar una sesión como fallback bearer**: una cookie administradora podría
  ampliar la autoridad de una credencial limitada o ausente.

## Consecuencias

T-18 añadirá una rama de origen API con referencias y restricciones coherentes
por cuenta sin degradar las de campañas. El transporte y la actividad resolverán
la procedencia real de cada intento mediante servicios comunes. Esta decisión
no certifica consentimiento externo ni entregabilidad y conserva log/Mailpit.
La protección durable de replay exige preservar recibos y titularidad incluso
al archivar. El contrato y las [reglas detalladas](../../api/decisiones.md)
especifican límites, concesión, errores y carreras que deberá probar T-18.

## Estado

Aceptada técnicamente tras la revisión 21 y la validación documental de T-17. Las
reglas de comportamiento deberán verificarse en T-18; el lint de OpenAPI no
las demuestra. Fuentes: [T-17/T-18 del ledger](../../roadmap/2026-10-21-plataforma-emailing/tasks.md),
[contrato canónico revisado](../../api/openapi.yaml) y servicios actuales
`DeliveryLifecycle`, `AccountMailTransport`, `Permissions` y `AccountContext`.
No promueve ADR-003 ni ADR-006 y no declara CA-19/20 o E2E-10 completados.
