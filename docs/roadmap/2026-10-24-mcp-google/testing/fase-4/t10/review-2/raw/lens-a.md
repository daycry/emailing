# Lente A — T-10 — intento 2

**Resultado: sin gaps nuevos de requisitos. Critical 0 / Important 0 / Minor 0. B-01 corregido en el alcance examinado.** No acepta T-10 ni acredita QA.

Revisión estática por subagente genérico con contexto fresco, conforme a `custom-agents:adversarial-review` 2.0.1. Base global aceptada: `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`. Se conserva el juicio R1 sobre los 55/55 archivos; sólo se reevalúan las correcciones, sin reabrir aprobaciones previas sin evidencia nueva. Tabla canónica leída: `docs/roadmap/2026-10-24-mcp-google/tasks.md:1128`–`1140`; informes A/B previos leídos.

Patch completo recorrido: `review-t10-attempt2/corrections.patch`, 102 líneas, tres archivos. SHA256 verificado: `ce966ead0a4c5eb991b16b45cd8dc9648e6518acdb134623fb23007c04ab7d09`. Los tres cambios se contrastaron con sus fuentes y llamantes actuales.

## Criterios T-10 corregidos y preservados

✓ significa conformidad estática o preservación del juicio R1, no aceptación ni ejecución por este revisor. Se contrastaron los criterios de `tasks.md:499`–`509`, los contratos generales de `spec.md:118`, `:147`–`148`, `:161`–`163`, la trazabilidad de `improvement-plan.md:284`–`286` y el recorrido `test-plan.md:135`–`141`.

| Criterio | Veredicto | Evidencia del intento 2 |
| --- | --- | --- |
| T-10.1: catálogo37 real | ✓ preservado | El patch no cambia registro, esquemas, operaciones ni discovery. Se conserva R1 y sus oráculos; no hay evidencia nueva de regresión en este criterio. |
| T-10.2: modalidades y destinatarios comunes T-17/T-18, templates.read condicional | ✓ preservado | No cambia entrada, principal, renderizado ni política de destinatarios. Las dos pruebas añadidas utilizan el adaptador/servicio existente; no crean una modalidad de envío nueva. |
| T-10.3: idempotencia común por cuenta; conflicto y aislamiento | ✓ preservado | No cambia aceptación, locks ni namespace. La nueva regresión conserva exactamente el recibo original tras el resultado y el tombstone (`tests/Feature/McpSendTest.php:666`). No sustituye las carreras ya revisadas en R1. |
| T-10.4: consentimiento, cuota, supresión y autorización antes SMTP | ✓ preservado | Sólo se restringe la ejecución del guard histórico del modelo en saves de resultado; no cambia autorización de llamadas, guards del pipeline, cuotas ni supresiones. La creación sigue comprobando procedencia: `app/Models/MailMessage.php:18`–`23`, llamante `app/Services/AccountMailTransport.php:118`–`123`. |
| T-10.5: aceptación/publicación/SMTP/incertidumbre; sólo log/Mailpit | ✓ corregido/preservado | El resultado conocido puede llegar al cierre durable tras el borrado admitido del actor: guard de `MailMessage.php:18`–`19`, cadena `AccountMailTransport.php:148`–`152` → `DeliveryLifecycle.php:164`–`174`. La prueba borra en transport_result después de obtener logged y exige estado durable logged, un intento, lease nula y un único mensaje (`McpSendTest.php:644`–`666`). El escenario de pérdida SQL conserva requested, salida42, sending antes de recovery, ambiguous y un solo mail (`WorkerRecoveryTest.php:662`–`674`). No hay nuevo transporte. |
| Subtarea: reutilizar T-18 sin campañas/contactos ficticios ni segundo SMTP | ✓ preservado | El patch no cambia el pipeline; el helper de la regresión reclama el outbox real y llama a `AccountMailTransport::sendDelivery` (`McpSendTest.php:722`–`728`). |
| Subtarea: correlación cliente/grant/request/outbox/auditoría sin token | ✓ corregido/preservado | `McpSendTest.php:661`–`666` exige actor/grant.user_id nulos tras FK, grant/cliente retenidos y recibo idéntico. El guard sigue disparándose al cambiar origin/cuenta/grant/cliente/actor y al crear (`MailMessage.php:18`–`23`); la nueva prueba de intento existente intenta otro actor, exige LogicException y verifica que SQL conserva el original (`McpSendTest.php:669`–`686`). |
| Subtarea: concurrencia real y conteos atribuidos | ✓ preservado | Las pruebas de concurrencia aceptadas por la revisión R1 no se modifican. Los nuevos dos casos son una intercalación determinista y un negativo de procedencia; no se presentan como sustitutos de concurrencia multiproceso. |

## B-01 y puertas de la Lente A

| Control | Veredicto | Evidencia y límite |
| --- | --- | --- |
| B-01: guardar outcome conocido después de SET NULL concurrente | ✓ corregido | La instancia retenida no marca actor como dirty por un cambio SQL externo. El guard ahora sólo consulta grant al crear o cambiar procedencia. Eloquent `update` hace fill/save (`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php:1180`–`1186`); `isDirty` compara sus cambios locales (`Concerns/HasAttributes.php:2261`–`2264`) y el UPDATE escribe sólo dirty (`Model.php:1546`–`1549`), por lo que el actor viejo en memoria no se repone sobre NULL. `DeliveryLifecycle.php:167`–`174` conserva cuenta, fencing y cierre transaccional. |
| B-01: no permitir atribuir otro actor al intento existente | ✓ | La lista dirty incluye actor y el mismo check sigue exigiendo el actor del grant. El negativo nuevo exige el mensaje exacto y vuelve a leer SQL (`McpSendTest.php:679`–`686`). La regla API (`MailMessage.php:26`–`27`) no cambia. |
| Test pertinente y evidencia de la corrección | ✓ focal | `B01-red-summary.json`: dos casos/15 aserciones, una LogicException en la intercalación exacta, exit2; `B01-green-summary.json`: mismos dos casos/26 aserciones, cero fallos/errores/skips, exit0, único delta de fuente desde RED `app/Models/MailMessage.php`. Ambos declaran full_suite_executed=false y T10_accepted=false. Se leen los resúmenes permitidos; no se ejecutan tests ni se inspeccionan sus directorios de runs. |
| sql-redelivery usa preparación controlada sin relajar el oráculo | ✓ | Único cambio en `WorkerRecoveryTest.php:672`: activa el mismo contrato existente. El padre espera identidad/preparación y emite go limitado (`WorkerRecoveryTest.php:322`–`335`); el hijo prepara y exige permiso antes de la acción (`tests/support/delivery-action.php:31`–`55`, `DeliveryFixtureProtocol.php:149`–`165`). Continúan los asserts de transporte/estado y el exit esperado; sólo se separa la preparación del presupuesto de acción. No se ha recibido ni se afirma resultado terminal del focal SQL en curso. |
| Alcance/Archivos y criterios intactos | ✓ | Los tres archivos aparecen en `tasks.md:493`. `scope-review2.json` declara la base aceptada y listas vacías de fuera_de_alcance, criterios_alterados, avisos, info y excluidos_usuario; no se observan cambios de criterios en el patch. |
| Puertas relajadas sin justificar | ✓ | No se elimina, omite o debilita ningún test/assert; se agregan dos negativos/oráculos pertinentes. El cambio de preparación de sql-redelivery conserva su acción real y sus resultados exigidos. |
| Verificación de tareas completadas | ✓ preservado | T-10 sigue en-progreso (`tasks.md:484`), criterios sin aceptar y Verificación final pendiente. El patch no declara completada ninguna tarea. La evidencia focal no cumple por sí sola la Verificación general de `tasks.md:495`. |
| Constitución, generados, OpenAPI y prosa | ✓ no aplica | `docs/CONSTITUTION.md` no existe. El patch no toca commands/agents/hooks, specs OpenAPI ni documentación: no aplican interop, API diff ni docs-style. No se ejecutan scripts bajo la restricción de lectura estática. |
| Evidencia para cierre T-10/QA | ✗ pendiente, no gap nuevo | No se reclama cierre. Suite completa y QA independiente siguen pendientes; el focal SQL en ejecución no se ejecutó ni se promovió a verde desde esta revisión. |

## Cobertura y límites

Cobertura del intento 2: 3/3 archivos y 102/102 líneas del patch, más los llamantes actuales enumerados. No se vuelve a certificar por lectura nueva todo el incremento global: su cobertura55/55 y sus aprobaciones se heredan explícitamente de R1. `lenses-review2.json` selecciona A+B+D y C=false; este informe corresponde sólo a A.

Sin gaps nuevos confirmados de requisitos o puertas relajadas. B-01 queda corregido desde esta lente con regresión focal observada en los resúmenes permitidos; su aceptación conjunta corresponde a la fusión. La prueba nueva usa log y no acredita una segunda ejecución SMTP ni la suite completa.

No se ejecutaron aplicación, tests, Docker, red, DB ni mutaciones Git. No se leyeron .env, configuración global, transcripciones, requests/raw ni inventarios de procesos. Única escritura: este informe. No se modifica el ledger ni se cierra T-10/QA.
