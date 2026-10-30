# Lente B — T-10, intento 1

Base: `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`. Revisión de `git diff <base>` (commits y árbol de trabajo), más los dos archivos nuevos no seguidos indicados en el encargo. Lente por subagente genérico, con el prompt literal B y la persona backend del shared de custom-agents 2.0.1.

Resultado: **0 Critical, 1 Important, 0 Minor**. Hallazgo pendiente; no es aceptación de T-10 ni QA.

## B-01 — Important — El borrado del actor durante el transporte impide guardar un resultado conocido

**Ubicación introducida:** `app/Models/MailMessage.php:19` (guard nuevo de `saving`, líneas 16–20).

**Escenario concreto:** un miembro que no es propietario acepta trabajo MCP. El worker crea el `MailMessage`, confirma el inicio durable y conserva esa instancia con `actor_id=N`. Mientras el worker realiza IO, otro proceso elimina ese usuario. Las FK ponen a NULL `mail_messages.actor_id` y `mcp_grants.user_id`, conservando la solicitud y su procedencia, tal como contempla la migración nueva. El transporte devuelve `smtp_accepted` antes de vencer la lease. Al persistirlo, el guard compara el `actor_id=N` de la instancia antigua con el `user_id=NULL` del grant leído ahora y lanza `LogicException`.

**Cadena local comprobada:**

- `database/migrations/2026_10_25_000003_create_mcp_authorization.php:25`: `mcp_grants.user_id` tiene `nullOnDelete()`; `database/migrations/2026_10_22_000004_create_mail_transport_tables.php:39` hace lo mismo con `mail_messages.actor_id`. La migración del diff `2026_10_26_000007_share_transactional_mail_origins.php:35` declara expresamente esa conservación tras el borrado.
- `app/Services/AccountMailTransport.php:138` retiene el modelo creado antes del IO. Las líneas 148–151 pasan **la misma instancia**, sin refrescarla, a `DeliveryLifecycle::finish` después de obtener el resultado.
- `app/Services/DeliveryLifecycle.php:172` ejecuta `$message->update($outcome)` dentro de la transacción del resultado. El framework instalado (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1180`, `:1413`, `:1422`) hace `fill()->save()` y dispara `saving` antes de actualizar; no refresca los atributos modificados por FK.
- El guard nuevo de `MailMessage.php:17` consulta el grant actual y las líneas 18–20 comparan contra el actor de esa instancia. No existe un manejo superior que preserve el resultado si lanza: `DeliveryConsumer::process` propaga la excepción de `sendDelivery`.
- El rollback deja la entrega en `sending` aunque ya se conozca la respuesta SMTP. `DeliveryLifecycle.php:224` la convierte después en `ambiguous` con `sending_lease_expired`, perdiendo el resultado conocido. Un rechazo SMTP seguro durante la misma carrera también pierde su reintento normal.

**Por qué es un defecto del diff:** antes el guard de API sólo exigía actor nulo para `origin=api`; el guard MCP nuevo vuelve a validar un atributo histórico en memoria durante todas las actualizaciones del resultado. El borrado mediante FK es un cambio admitido, no una suplantación. El mutex de cuenta del inicio/resultado no evita que el borrado ocurra durante el IO, cuando el transporte ya salió de la transacción.

**Cobertura existente:** `tests/Feature/McpSendTest.php:228` completa todo el transporte antes de borrar al actor en `:233`. Verifica correctamente los tombstones, pero no alcanza esta intercalación. Una reproducción de regresión puede detener el worker en `transport_result`, borrar al miembro secundario y reanudar `finish` con una lease vigente; debe conservarse el resultado conocido y actor SQL nulo. No se ejecutó esa reproducción en esta revisión.

Estado: **pendiente**. Severidad Important por pérdida reproducible de un resultado durable y alteración de las reglas de recuperación/reintento en una carrera admitida.

## Cobertura y límites

Se recorrieron los **55 archivos** completos del incremento: 53 seguidos y los dos nuevos no seguidos. Se siguieron también los llamantes, el framework y las FK necesarios para juzgar los cambios de aceptación, persistencia y transporte. No se detectaron otros defectos de corrección confirmados. Las actualizaciones de conteos de descubrimiento corresponden a las tres herramientas añadidas; no se reportan como debilitamiento de asserts. Los presupuestos de preparación de los fixtures se revisaron junto a sus aserciones semánticas, cancelación, inventario y cierre.

Archivos revisados:

```text
.gitignore
app/Http/Controllers/McpTransportController.php
app/Models/ApiMailRequest.php
app/Models/MailMessage.php
app/Services/AccountMailTransport.php
app/Services/ActivityService.php
app/Services/Mcp/BusinessDto.php
app/Services/Mcp/ContractArguments.php
app/Services/Mcp/DataOperations.php
app/Services/Mcp/DataTools.php
app/Services/Mcp/McpSendAdapter.php
app/Services/Mcp/Tools/ConnectionInfo.php
app/Services/Mcp/Tools/DataTool.php
app/Services/Mcp/Tools/GetMailRequest.php
app/Services/Mcp/Tools/SendMail.php
app/Services/Mcp/WireJson.php
app/Services/SendingApiService.php
app/Services/SendingPrincipal.php
database/migrations/2026_10_26_000007_share_transactional_mail_origins.php
docs/aplicacion/mcp-contrato.md
docs/roadmap/2026-10-24-mcp-google/improvement-plan.md
docs/roadmap/2026-10-24-mcp-google/tasks.md
docs/roadmap/2026-10-24-mcp-google/test-plan.md
docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/checkpoint-2026-10-27.json
docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10/checkpoint-2026-10-27.md
docs/roadmap/README.md
resources/views/livewire/activity.blade.php
tests/Concerns/ExercisesSendingApi.php
tests/Concerns/ExercisesSendingApiProcesses.php
tests/Feature/ConcurrentCampaignTest.php
tests/Feature/McpApiIdempotencyTest.php
tests/Feature/McpConnectionInfoTest.php
tests/Feature/McpConsentToolsTest.php
tests/Feature/McpDataToolsTest.php
tests/Feature/McpPipelineTest.php
tests/Feature/McpSendTest.php
tests/Feature/McpTransportTest.php
tests/Feature/MigrationReplayTest.php
tests/Feature/OutboxIntegrationTest.php
tests/Feature/SendingApiTest.php
tests/Feature/WorkerRecoveryTest.php
tests/Unit/McpMailCanonicalInputTest.php
tests/Unit/McpMailWireTest.php
tests/e2e/mcp-google.spec.mjs
tests/support/ApiProcessProtocol.php
tests/support/DeliveryFixtureProtocol.php
tests/support/campaign-action.php
tests/support/delivery-action.php
tests/support/delivery-sql-proxy.py
tests/support/google-browser-fixtures.php
tests/support/mcp-api-concurrency.php
tests/support/outbox-action.php
tests/support/sending-api-action.php
tests/support/sending-api-deadlock.php
tests/support/test_delivery_sql_proxy.py
```

Se consultaron `spec.md`, los criterios T-10 y los controles permitidos `scope-20261028.json` / `lenses-20261028.json`. El alcance declara 55/55 dentro, sin criterios alterados ni avisos; C no seleccionada y D seleccionada. Esta salida corresponde exclusivamente a B.

Revisión estática: no se ejecutaron aplicación, tests, Docker ni red; no se hicieron mutaciones Git. Única escritura: este informe. No se leyeron `.env`, credenciales, configuración global, transcripciones, solicitudes crudas, snapshots SQL ni otros archivos de storage. No se inventariaron procesos globales. Los resultados comunicados de las ejecuciones en curso no se sustituyen ni se promueven a verde; revisión/QA y aceptación de T-10 permanecen pendientes.
