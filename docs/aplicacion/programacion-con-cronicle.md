# Programar acciones con Cronicle

**Última actualización: 2026-10-31.** T15 y el QA integral T16 están aceptados
técnicamente en laboratorio. Configurar un calendario operativo del destino
requiere sus eventos y secretos propios. El [manual](../manual/guia-completa.md#colas-workers-y-calendarios)
explica responsabilidades y recorrido completo sin conocimientos de colas.

La receta mantiene un calendario deshabilitado durante la validación y las
pruebas destructivas del esquema QA. La aceptación de integración requiere
resultados del daemon real, autenticación y estado SQL, además de los tests
del cliente; un test de componentes no habilita un calendario operativo.

Cronicle inicia llamadas HTTP finitas. La aplicación conserva las entregas y su
outbox en MySQL, publica en RabbitMQ y ejecuta el transporte en sus propios
workers. Un job correcto de Cronicle no afirma que los emails hayan terminado
de enviarse ni que el destinatario los haya recibido.

## Preparar el destino y la credencial

El endpoint es `POST /internal/cronicle/trigger`. Acepta exclusivamente uno de
estos objetos JSON, sin identificadores de cuenta, límites ni comandos:

El cuerpo admite hasta 1024 bytes y profundidad JSON 4.

```json
{"action":"materialize"}
```

```json
{"action":"dispatch"}
```

La autenticación utiliza `Authorization: Bearer <secreto>`. Genera 32 bytes
aleatorios y representa el resultado como 64 caracteres hexadecimales en
minúscula. Configura el mismo valor en `CRONICLE_TRIGGER_SECRET` del entorno
real de la aplicación y en el archivo privado descrito a continuación. No
reutilices APP_KEY, la contraseña de Cronicle, su clave de servidor o un
secreto SMTP. Sin una configuración válida el endpoint responde 503.

El endpoint no usa el inicio de sesión de un Teammate, cookies ni tokens en
query string o cuerpo. Es una credencial de infraestructura con acceso a la
programación de todas las cuentas del despliegue elegido.

Crea `infra/.runtime/emailing-cronicle.json`, fuera de Git. El ejemplo contiene
un marcador que debe reemplazarse localmente por la credencial; no es un
archivo funcional listo para copiar:

```json
{
  "endpoint": "http://emailing-app-web/internal/cronicle/trigger",
  "token": "<secreto dedicado de 64 caracteres hexadecimales>"
}
```

Usa el alias único del proyecto de aplicación en la red `emailing-lab`. Para
un proyecto `emailing-qa`, el alias es `emailing-qa-web`; sus datos y credencial
deben corresponder al perfil de pruebas. Evita el alias compartido `web`.
El cliente rechaza URL con usuario/contraseña, query, fragmento u otra ruta.
No sigue redirecciones ni desactiva la verificación TLS.

Para PHP nativo en Laragon, usa un perfil QA separado con base y usuario
`emailing_testing`, Mailpit/log y su propio secreto. Desde Cronicle,
`127.0.0.1` identifica el contenedor de Cronicle. En Docker Desktop puede usarse
`host.docker.internal:<puerto-qa>` si el listener nativo es alcanzable por esa
interfaz y el host coincide con el origen configurado del perfil. Comprueba
desde el contenedor tanto `/up` como el rechazo 401 del trigger sin Bearer y
la llamada autenticada; no presupongas que el alias, loopback o un virtualhost
de Apache alcanza el proceso correcto. El perfil nativo probado debe
identificar listener, origen y PHP efectivos; no es un despliegue operativo.

En Linux, el usuario `node` del contenedor utiliza UID/GID 1000. El archivo
debe ser legible por esa identidad. Por ejemplo, un operador con los permisos
adecuados puede asignar propietario `1000:1000` y modo `0400` al archivo; su
directorio privado y las copias deben conservar acceso restringido. En
Windows, restringe su ACL al operador y a Docker Desktop. El montaje dentro
del contenedor es siempre de lectura.

Si cambias la variable de entorno, actualiza también cualquier configuración
cacheada de Laravel y recrea el servicio PHP que reciba variables mediante
Compose. La rotación requiere pausar ambos jobs, cambiar los dos extremos,
verificar una llamada manual y reactivar sus calendarios.

## Montar el cliente en el Cronicle existente

El [perfil adicional](../../infra/compose.emailing-cronicle.yaml) añade sólo
dos montajes al servicio Cronicle de infraestructura:

| Archivo del host | Destino en Cronicle |
|---|---|
| `scripts/cronicle-trigger.cjs` | `/opt/cronicle/emailing-trigger.cjs` |
| `infra/.runtime/emailing-cronicle.json` | `/run/secrets/emailing-cronicle.json` |

Los dos montajes son de lectura y rechazan un origen inexistente. No se monta
el repositorio completo ni el socket Docker. Se conservan los volúmenes de
datos, logs y cola de Cronicle, así como RabbitMQ, MySQL y Mailpit existentes.

Desde la raíz del repositorio, comprueba primero la configuración:

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml -f infra/compose.emailing-cronicle.yaml config --quiet
```

Con el archivo privado preparado y sin jobs activos, aplica el perfil sólo
al servicio Cronicle:

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml -f infra/compose.emailing-cronicle.yaml up -d --no-deps --wait --wait-timeout 240 cronicle
```

No ejecutes `down -v`. Las siguientes operaciones sobre Cronicle deben seguir
incluyendo el perfil adicional para mantener sus montajes.

## Registrar los dos jobs

Usa el plugin existente **Shell Script**, con `json=0` y `annotate=0`. El
cliente HTTP incluido en Cronicle 0.9.137 registra cabeceras sin depurar;
por ello esta receta no introduce la credencial en sus parámetros ni logs.

Crea primero un evento deshabilitado para `materialize` con este script fijo:

```sh
#!/bin/sh
printf '%s\n' '{"params":{"action":"materialize"}}' | node /opt/cronicle/emailing-trigger.cjs /run/secrets/emailing-cronicle.json
```

El segundo evento usa exactamente el mismo script con `dispatch` en lugar
de `materialize`. No añadas la credencial al script: el programa la lee del
archivo privado. Mantén concurrencia máxima 1 por evento, timeout 45 segundos,
catch-up desactivado y multiplex desactivado. El resultado JSON del cliente
se escribe como texto seguro en el log; el código de salida determina el
resultado del job de Shell Script.

Ejecuta ambos manualmente antes de habilitarlos. Registra sus IDs en la
documentación privada de operación y reutiliza esos mismos eventos al
actualizar la configuración. Después de la validación, un calendario de una
ejecución por minuto para cada acción permite procesar trabajo nuevo.
Cronicle debe ser el único propietario de esos calendarios; no dupliques
estas llamadas en Laravel Scheduler, otro cron o eventos adicionales.

## Materialización y revisiones conservadas

La migración incremental `2026_10_24_000012_add_materialization_checkpoint`
añade un checkpoint de revisión e índice de pendientes. La aplicación la aplica
con `php artisan migrate --force`, sobre el esquema existente: no requiere
`migrate:fresh`. Clasifica en una pasada las revisiones actuales completas y
conserva datos e identidades de entregas y outbox. Los runtimes nativo y Docker
deben cargar el mismo conjunto de migraciones y fuentes antes de ejecutar jobs.

Una revisión materializada conserva su estado de campaña. Los siguientes
disparos la excluyen mediante el índice, sin recorrer otra vez todos sus
destinatarios históricos. Una nueva revisión vuelve a estar pendiente por su
identidad; pausa y reanudación conservan el checkpoint. Un snapshot vacío
coherente se certifica sin crear entregas y permite avanzar al siguiente trabajo.

El checkpoint solo acredita pares SQL de entrega y outbox inicial. No acredita
publicación, SMTP ni finalización de emails. Un par histórico dañado sin outbox
permanece pendiente y produce un fallo de backend depurado; requiere revisar su
evidencia antes de cualquier reparación, sin republicación automática que pudiera
duplicar un envío. El dominio no borra esos pares certificados por su cuenta.
La decisión conserva su estado vigente de [ADR-005 aceptada](../knowledge/adr/ADR-005-checkpoint-de-materializacion-por-revision.md).

## Interpretar el resultado

`materialize` selecciona como máximo una campaña vencida con destinatarios
pendientes de materializar. La revisión existente limita la campaña a 10.000
contactos; su materialización mantiene la transacción de entregas/outbox. La
selección omite revisiones completas para poder avanzar más allá de ellas.
Las condiciones actuales vuelven a comprobarse en el dominio y en el worker.

`dispatch` procesa un lote finito con `CRONICLE_DISPATCH_CLAIM_LIMIT=20`
(rango servidor 1–100) y `CRONICLE_DISPATCH_BUDGET_MS=10000` (rango 1–60000).
Los clientes no pueden elegir estos límites. Cada iteración conserva su propia
transacción y la conexión/confirmación del publisher existente. El reloj
monotónico se comprueba entre operaciones; no cancela una espera SQL o AMQP
ya iniciada. La última operación puede superar el presupuesto. Los defaults
siguen el lote de 20 del CLI y requieren observación del perfil elegido;
no constituyen un SLA, un límite rígido de duración o capacidad medida.

Una reclamación completada por el dispatcher no acredita publicación confirmada
ni aceptación SMTP. Una publicación no confirmada conserva pending/backoff
durable aunque incremente `outbox_claims`. Consulta SQL para el resultado de
publicación; no deduzcas emails enviados desde ese contador.

La respuesta incluye `status: processed`, `action`, `deliveries_created`,
`outbox_claims`, `emails_completed: null`, `stop_reason` y `elapsed_ms`.
`materialize` termina por `empty` o `campaign_limit` (una campaña candidata,
incluido cero creaciones si el dominio revalidó un cambio concurrente).
`dispatch` termina por `empty`, `claim_limit` o `budget`. `empty` describe
ausencia de trabajo reclamable en esa consulta, incluidas reservas ajenas
o backoff pendiente; no es una comprobación global de cola vacía.
`elapsed_ms` mide la acción del servidor con reloj monotónico, no el viaje HTTP.
Cero trabajo es un resultado válido cuando no hay
trabajo elegible en esa llamada; no declara que todas las cuentas estén
procesadas. El cliente sólo acepta HTTP 200 y un JSON con ese contrato.
Rechaza cuerpos de respuesta mayores de 4096 bytes y registra códigos fijos,
sin cabeceras, credenciales, URL, cuerpo crudo o trazas del servidor.

El cliente limita la petición HTTP a 30 segundos. Un timeout no demuestra que
el servidor haya cancelado o revertido una transacción: la operación puede
terminar después. Los límites de trabajo son finitos, sin una garantía de
tiempo de pared para el servidor. La integración debe probar que repetir una
llamada conserva la identidad de las entregas y los eventos, incluso después
de una respuesta perdida.

HTTP 401 indica credencial incorrecta; 503 puede indicar configuración
deshabilitada o backend no disponible. `trigger_unavailable` devuelve
`work_outcome: unknown` y `emails_completed: null`: puede haber commits previos;
no devuelve contadores parciales como si fueran un total definitivo.
El endpoint distingue JSON inválido
(400), cuerpo excesivo (413), media incorrecta (415) y acción/campos inválidos
(422). Consulta el diagnóstico fijo de cada extremo y su estado durable antes
de repetir; no deduzcas envío final del estado verde del job.

El laboratorio utiliza Mailpit/log. Esta programación no verifica reputación,
listas de bloqueo, rebotes, quejas ni entrega externa. Consulta la
[guía de entrega y recuperación](entrega-y-recuperacion.md) y la
[integración de aplicaciones](../infraestructura/integracion-aplicaciones.md).
