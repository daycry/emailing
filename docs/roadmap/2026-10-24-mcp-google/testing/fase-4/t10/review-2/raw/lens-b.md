# Lente B — T-10, intento 2

**Veredicto: B-01 corregido; sin defectos pendientes.** Conteo actual: 0 Critical, 0 Important, 0 Minor. Esto no acepta T-10 ni sustituye la suite completa o QA.

Revisión por subagente genérico de contexto fresco, con el método y prompt B de `custom-agents:adversarial-review` 2.0.1 y perfil backend compartido. Se reevalúa exclusivamente la corrección del intento 1 y los posibles defectos introducidos por ese delta. No se reabren los otros 55/55 archivos ya revisados ni los veredictos aprobados de A/D.

## Alcance y trazabilidad

Se leyó completo `corrections.patch`: tres archivos, 102 líneas, SHA256 comprobado `ce966ead0a4c5eb991b16b45cd8dc9648e6518acdb134623fb23007c04ab7d09`. Se cotejaron sus cambios con las fuentes actuales y sus llamantes/framework. Los controles proporcionados conservan 55 archivos en alcance, cero exclusiones/avisos/criterios alterados; C=false, D=true. Este informe corresponde sólo a B.

| Hallazgo previo | Grado histórico | Estado actual | Evidencia |
|---|---|---|---|
| B-01: borrar al actor durante IO impide cerrar el resultado conocido | Important | Corregido | `app/Models/MailMessage.php:18` limita la validación a creación o cambios de procedencia; `tests/Feature/McpSendTest.php:629` reproduce el tombstone entre resultado y cierre durable. |

## Evidencia de B-01

- `AccountMailTransport.php:138` conserva la instancia del mensaje y `:148`–`:151` pasa el resultado y esa misma instancia al cierre. `DeliveryLifecycle.php:172` sólo aplica el arreglo de resultado; no cambia la procedencia.
- El guard en `MailMessage.php:18` incluye `origin`, `account_id`, `mcp_grant_id`, `mcp_client_id` y `actor_id`. En un modelo existente cuyo actor únicamente cambió por FK en SQL, esos atributos siguen iguales a sus originales en memoria: el guard ya no consulta/compara el grant durante el guardado del resultado.
- Se verificó el comportamiento real instalado de Eloquent: `HasAttributes.php:2261` y `:2314` detectan cambios en cualquiera de los atributos seleccionados; `:2340` compara contra los originales del modelo. `Model.php:1546`–`:1549` actualiza exclusivamente los atributos dirty. Por tanto, guardar el resultado no vuelve a escribir el actor antiguo sobre el NULL SQL.
- La creación sigue validada por `! $message->exists`; una mutación explícita de actor sigue activando la comprobación y la misma excepción. La regresión `McpSendTest.php:669` intenta cambiar el actor aceptado por el propietario y comprueba tanto el rechazo como la atribución persistida original (`:679`–`:685`).
- La regresión causal elimina al usuario en `transport_result`, tras exigir resultado `logged` (`McpSendTest.php:644`–`:649`). Comprueba resultado durable del mensaje y entrega, un intento, lease liberada, actor/grant tombstoned, procedencia y recibo intactos, y una sola fila (`:655`–`:666`). Su helper llama al transporte real en `:722`–`:728`; no sustituye `finish` ni el guard.

Los resúmenes permitidos aportan RED: 2 casos/15 aserciones, 1 error LogicException en esa regresión, sin skips; GREEN: los mismos 2 casos/26 aserciones, exit0 y cero problemas. El único delta de fuentes declarado entre ambas tandas es `app/Models/MailMessage.php`. Se usan como evidencia recibida, sin ejecutar ni acceder a los artefactos crudos.

## Otro cambio del delta

`WorkerRecoveryTest.php:672` adopta `controlled_start` también para `sql-redelivery`. Se siguió el helper `worker()` (`:313`, `:322`–`:336`) y el hijo (`tests/support/delivery-action.php:31`, `:49`–`:56`): conserva inventario completo, identidad de fixture/helper/entrega y exige permiso go antes de la acción. `DeliveryFixtureProtocol.php:149`–`:168` valida ese permiso. Se conservan los asserts de resultado ambiguo y un único envío (`WorkerRecoveryTest.php:673`–`:674`), sin suprimir la ejecución ni debilitar el oráculo. No se identifican defectos nuevos introducidos por el cambio.

## Límites

Revisión estática; no se ejecutaron aplicación, tests, Docker, red, Git ni acciones DB. La única escritura es este informe. No se leyeron `.env`, credenciales, configuración global, transcripciones, requests/raw ni otros archivos de storage fuera de los autorizados. No se inventariaron procesos. La ejecución SQL focal pertenece a otro proceso; su resultado, la suite completa y QA siguen pendientes. T-10 continúa en-progreso.
