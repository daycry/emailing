# Revisión 14 — Lente A — T-14, intento 1/3

**Resultado: ✗ conformidad incompleta. Critical: 0 · Important: 1 · Minor: 0.**

Revisión independiente de contexto fresco, fallback genérico con runtime heredado. `opus/high` es el tier declarado por el plugin; no identifica un runtime Claude ejecutado. Fuente exclusiva: `storage/phase3-t14-review1/`. No se ejecutaron aplicación, bootstrap, pruebas, SQL, broker, SMTP, navegador ni servicios, y no se modificaron fuentes ni Git. Solo se escribe este informe.

## Integridad y alcance

- Patch: SHA256 `238dd30a77e00290d55df57abfaaca143d377a23191cedde3e599c46d60716e9`, 143.415 bytes y 1.918 líneas, 21 ficheros; base `defc14eb7ad40ef1b8c651a36d338a8365d75669`.
- `capsule-manifest.json`: SHA256 `f430fbc70590076bca6d86b885e9d65126430cd8746f59bc22231d1c8e63b460`. Se verificaron sus **2.766 entradas**, sin faltantes ni diferencias. También coinciden los 21 hashes finales y los 15 hashes de versiones anteriores declarados en `increment.patch.json`.
- `increment.patch.json` se leyó y procesó completo: metadatos, 21 registros de cambio, inventario de 782 fuentes, 1.958 registros de evidencia, seis gates seleccionados y 30 informes históricos. Procesar un inventario/hash no equivale a leer semánticamente todos sus archivos.
- El scope registrado incluye los 21 ficheros, sin exclusiones efectivas, avisos, información adicional ni archivos fuera de alcance. Sus patrones vigentes excluyen `CONTINUE-HERE*.md`, `.claude/**` y `docs/knowledge/journal/**`; ninguno oculta un cambio de este patch. Se contrastó además el alcance real con `tasks.md:612`: runtime, migración, UI, pruebas y documentación de actividad/estado T-13 son pertinentes.
- T-14 continúa **en-progreso** (`tasks.md:605`); el incremento no cierra ninguna tarea histórica adicional. T-15–T-20 siguen pendientes. No se exige su implementación aquí ni se les atribuye aceptación.

## T-14 → criterios de aceptación

Las rutas de esta sección son relativas al repositorio; corresponden a las copias selladas en `sources/`.

| Criterio real | Veredicto | Evidencia y límite |
|---|---|---|
| `tasks.md:619`: aceptado SMTP, fallido, suprimido y ambiguo distinguibles | ✓ | `app/Services/ActivityService.php:17`, `:87`, `:104`; `resources/views/livewire/activity.blade.php:4`, `:43`, `:77`. Pruebas reales de rechazo/reintento/aceptación, resultado ambiguo, fallo agotado y supresión: `tests/Feature/ActivityTest.php:165`, `:192`, `:203`, `:239`. No se llama entrega al buzón a SMTP aceptado. |
| `tasks.md:620`: listados, agregados y descargas solo de cuenta autorizada | ✓ | `ActivityService.php:34`, `:47`, `:65`, `:121`, `:126`, `:142`, `:151`, `:172`; joins de procedencia con cuenta en `:72`–`:75`. Búsqueda OR y cursor CSV agrupados en `:115` y `:186`. `app/Services/MailSettingsService.php:108`–`:125` reautoriza también en callback de descarga. Negativos y revocación: `ActivityTest.php:147`, `:286`, `:302`, `:319`, `:386`, `:423`. |
| `tasks.md:621`: alertas comprobables, ventana/denominador/fuente y ausencia de datos distinta de cero incidencias | ✓ | Los contadores/tasas y avisos externos cumplen: `ActivityService.php:69`, `:128`–`:136`, vista `:24`–`:36`, guía `docs/aplicacion/actividad-y-metricas.md:94`. El defecto A-01 afecta a la explicación histórica del detalle, no a estos porcentajes o avisos externos. |

## T-14 → descripción, subtareas y contratos de apoyo

| Requisito contrastado | Veredicto | Evidencia |
|---|---|---|
| UI de Actividad accesible, filtros, paginación, detalle y descargas | ✓ | `routes/web.php:28`, `:29`; navegación condicionada por `activity.read` en `resources/views/components/app-layout.blade.php:1`; componente `app/Livewire/Activity.php:34`–`:87`; vista completa. Páginas de 25 registros y 10 intentos, filtros UTC, detalle por referencia y CSV de la selección. La paginación reutilizada usa el nombre de cada paginator. No es una validación de navegador. |
| Entregas sin intento e intentos sucesivos sin inflar entregas lógicas | ✓ | `ActivityService.php:71`–`:87`, `:151`–`:162`; poblaciones y denominadores separados en `:126`–`:136`. Los intentos se consultan por `delivery_id`, no solo por el puntero actual. `ActivityTest.php:147`, `:165`, `:343`. |
| Procedencia nueva durable, cuenta/entrega/generación/actor/origen en la misma transacción de inicio | ✓ | `app/Services/AccountMailTransport.php:61`–`:116`: bloqueo de cuenta, validación de entrega/campaña y creación del registro con asociación antes de `DeliveryLifecycle::started`; IO solo después del commit. `DeliveryLifecycle.php:68`–`:127` mantiene campaña bloqueada, fencing y rollback al perder reserva. Prueba sin sesión del worker y actor original distinto del viewer en `ActivityTest.php:165`. |
| Entrypoints explícitos de prueba; el actor no convierte legado en prueba | ✓ | `AccountMailTransport.php:29`–`:35` conserva default `unknown` y valida origen. `MailSettingsService.php:105` y `app/Services/TemplateService.php:137` pasan `origin: 'test'`. `ActivityTest.php:227`. |
| Migración up/down/backfill sin adivinar generación | ✓ | `database/migrations/2026_10_23_000011_add_activity_provenance.php:10`–`:60`: FK compuesta de cuenta/entrega con cascade, unicidad de generación conocida, índices, down inverso. Backfill solo del puntero único de la misma cuenta; no rellena generación histórica. `ActivityTest.php:525`, `:558`, `:575`; migración repetida en `OutboxIntegrationTest.php:597`. |
| Procedencia histórica honesta en la presentación | ✗ | La separación de poblaciones, generaciones desconocidas y marcas legacy nulas es correcta (`ActivityService.php:87`, `ActivityTest.php:254`, `:525`). El estado vacío de intentos incumple este requisito: A-01. Es el mismo defecto, contado una sola vez. |
| Lifecycle de cuenta/campaña/usuario y FK compuesta | ✓ | `ActivityTest.php:558` comprueba rechazo de cruce de cuenta y generación duplicada sin actualización parcial; `:575` comprueba cascades de campaña/cuenta y actor borrado desconocido. Las FK previas de usuario usan null-on-delete; no se inventa un actor sustituto. |
| Revalidación de cuenta/scope después de mount, filtros inválidos visibles y sin datos obsoletos | ✓ | `Activity.php:16`, `:41`, `:48`, `:54`, `:62`, `:68`–`:85`. El catch cubre únicamente `ValidationException`; cuenta y 403/409 quedan fuera. Vista `:17`–`:18`, `:88` oculta CSV, métricas, lista y detalle con filtros inválidos. `ActivityTest.php:377` prueba corrección en la misma instancia; `:302`, `:423` prueban revocación/cambio de cuenta. |
| Teammate con solo `activity.read`, sin exigir `settings.read` | ✓ | Activity usa su propio servicio; `MailSettingsService::messages/download` autoriza `activity.read`. `ActivityTest.php:286` permite actividad/detalle/archivo/CSV y deniega Configuración. |
| Escape, diagnósticos depurados y CSV protegido/acotado | ✓ | Blade usa escape de metadatos; diagnóstico por allowlist en `ActivityService.php:21`–`:44`; `csvCell` en `:165`–`:169`; streaming de 200 filas con reautorización por lote y antes del primer byte en `:172`–`:197`. `ActivityTest.php:319`, `:362`, `:386`. No se exportan cuerpos ni errores SMTP crudos. |
| Métricas separan entrega, prueba, legado, registros e inicios; esperas y fuente honestas | ✓ salvo A-01 | `ActivityService.php:78`, `:87`, `:128`–`:136`, `:190`; vista `:24`–`:36`, `:66`–`:70`; `ActivityTest.php:254`, `:343`. Cero de pruebas explícitas bloqueadas/suprimidas sin inicio se distingue del inicio legacy desconocido. Cuota/pausa siguen pendientes. Sin muestra devuelve porcentaje null; feedback externo permanece desconocido. |
| Ambigüedad sin reenvío automático añadido | ✓ | La vista informa la restricción y no contiene acción de reenvío. `ActivityTest.php:192`. No se modifica el protocolo de recuperación aprobado de T-13. |
| Consultas/índices y ausencia de consultas por cada fila | ✓ con límite medido | `ActivityService.php:65`–`:162`, índices de migración, `ActivityTest.php:435`. Evidencia final de 601 entregas/1.202 mensajes: page25 y page1 usan seis consultas cada una; los tres puntos d/m/d usan PRIMARY/const y una fila estimada. El UNION derivado puede materializar/ordenar la cohorte; estos EXPLAIN son estimaciones, no benchmark de capacidad ni prueba de T-19. |

## A-01 — Important — Una lista sin asociaciones no demuestra que no empezó el transporte

**Localización:** `resources/views/livewire/activity.blade.php:83`.

**Escenario concreto:** existe una entrega histórica `D` con `attempts = 2` y estado `smtp_accepted` o `ambiguous`, pero su puntero antiguo no permite una asociación única y válida de cuenta (o no conserva un mensaje reconstruible). El esquema histórico permite `mail_message_id` nullable sin FK (`database/migrations/2026_10_23_000010_add_delivery_reservations.php:16`). El nuevo backfill deja honestamente sin `delivery_id` los mensajes de asociación ambigua (`000011_add_activity_provenance.php:35`–`:43`).

`ActivityService::attempts()` (`app/Services/ActivityService.php:154`–`:162`) no recupera mensajes sin esa asociación. Al abrir D devuelve cero filas, aunque `detail->attempts` siga siendo 2 (`ActivityService.php:78`). El `@empty` muestra entonces: «La entrega aún no ha iniciado el transporte o se detuvo antes de hacerlo». Esta conclusión pre-IO es falsa o desconocida; puede contradecir incluso el contador y estado que el mismo detalle muestra.

**Contrato incumplido:** `spec.md:102` exige que el panel muestre solo eventos demostrables; `spec.md:114` e `improvement-plan.md:207` exigen estados/métricas reales y observados. La ampliación T-14 del ledger (`tasks.md:3283`–`:3288`) y la guía de actividad (`docs/aplicacion/actividad-y-metricas.md:146`–`:153`) mantienen explícitamente la procedencia histórica desconocida. La ausencia de filas vinculadas acredita «no hay registros vinculados», no «no empezó el transporte».

**Impacto/graduación:** Important por ofrecer una conclusión operativa incorrecta sobre inicios anteriores en una funcionalidad cuyo requisito central es reflejar hechos/ausencia de evidencia. No se ha identificado una fuga ni una acción nueva de reenvío; no se eleva a Critical. Se requiere distinguir el cero acreditado de las asociaciones históricas ausentes al presentar el vacío.

**Cobertura del caso:** el test inicial abre una entrega sin intentos (`ActivityTest.php:147`–`:162`); el backfill prueba población/generación desconocida pero abre la entrega con un mensaje recuperado (`:549`–`:552`). Ninguno abre una entrega con comienzos registrados y cero asociaciones recuperables. El hallazgo deriva de las ramas concretas de código y del esquema permitido; no se ejecutó una reproducción durante esta revisión.

## Verificaciones declaradas y evidencia final

La verificación declarada en `tasks.md:615` **sí tiene evidencia ejecutada**. No hay gap de «verificación declarada sin evidencia». Los siguientes son los únicos gates considerados finales; los recibos históricos no se promueven a aceptación:

| Run | Resultado contrastado |
|---|---|
| `activity-final-exact-20261023T215959177563Z` | `php artisan test --filter=ActivityTest`: exit 0, 19 passed / 212 assertions, 513,53 s PHPUnit. |
| `migration-final-20261023T220944786414Z` | Test incremental down/up: exit 0, 1 passed / 15 assertions, 48,62 s PHPUnit. |
| `full-final-20261023T221135766386Z` | `php artisan test`: exit 0, 202 passed / 2441 assertions, 2598,46 s PHPUnit. |
| `format-final-check-20261023T215926999922Z` | Pint `--test`: exit 0, `result: passed`. |
| `build-final-20261023T215741456940Z` | Build: exit 0, termina correctamente. |
| `final-owned-settlement-20261023T225545600570Z` | Exit 0; stdout resume 10 runs, 46 contenedores propios, 42 nombres broker, 99 PID históricos, cero firmas nativas coincidentes y guard de pruebas true. No es un censo nuevo ejecutado por el revisor. |

Para cada uno se leyeron request/receipt y stdout/stderr originales mediante procesamiento local; comandos de request/receipt coinciden, cambios de fuentes son `[]`, stderr está vacío y hashes de ambos streams coinciden. Se verificaron **196 entradas de cada ZIP**, sin diferencias frente a su manifiesto; las 195 fuentes de producto coinciden con `sources/` y el auxiliar `run.py` coincide con su copia ubicada en `evidence/storage/phase3-t14/implementation/`. Los seis comparten manifiesto `59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`.

La evidencia protocol/relay del focal final conserva una entrega con tres inicios, generaciones 1/2/3 y estados rechazado/rechazado/aceptado; el actor es el autorizador original y difiere del viewer. El relay causal registra tres conexiones/RCPT, dos rechazos, un DATA y un final 250. El relay de ambigüedad registra un DATA y pérdida deliberada de respuesta final, sin final 250. Las pruebas bloqueada/suprimida no registran conexiones ni artefactos. Son observaciones de transporte controlado, sin acreditar AMQP nuevo, entrega al buzón, Internet, reputación ni exactly-once SMTP.

Se contrastaron además los diagnósticos rojos conservados: inicial 4 failed/9 passed, correcciones parciales 1 failed/3 passed y filtros/unidades 1 failed/1 passed. Las correcciones actuales se localizan en `ActivityService.php:148` (404), `ActivityTest.php:99` (update SQL sin caster datetime), `:119`–`:123` (extrae port de JSON) y `:333` (getter real de la excepción de stream), y `Activity.php:71`–`:79` (solo validación capturada). No se atribuye éxito SMTP causal al antiguo intento port0, ni una proyección constante de intentos a la versión posterior con `transport_started_at`.

## Otros criterios y cobertura real de lectura

- **Constitución:** no existe `docs/CONSTITUTION.md` en el inventario sellado. No se inventan principios.
- **Interop/E2–E3:** no aplica el check condicional de `commands/`, `agents/` o `hooks/`; el diff no toca esas carpetas. La documentación de la actividad y los índices sí cambian en esta tarea.
- **OpenAPI:** el diff no contiene una spec OpenAPI; no aplica el lint contractual condicional.
- **Cobertura de código:** no hay porcentaje medido de cobertura de diff en la evidencia usada. Los 19/202 casos y hashes no se presentan como cobertura de código ni semántica completa.
- **Lectura semántica:** se recorrieron los 21 cambios completos del patch y sus fuentes finales correspondientes: Activity, ActivityService, cambios de transporte/fachadas, migración, navegación/rutas/vista, los 596 renglones de ActivityTest, delta de OutboxIntegrationTest y todos los cambios de las diez piezas documentales/ledger. Se leyó completa la nueva guía de 162 líneas. Contexto adicional consultado: AccountContext, MailSettingsService messages/download, MailMessage, paginación/feedback compartidos, lifecycle de inicio/finalización y migraciones anteriores relevantes; criterios/alcance de spec, design, improvement-plan, bloque T-14 e historial nuevo del ledger, y ADR-003 completo.
- **Límite de lectura:** no se relee semánticamente el conjunto de 782 fuentes/vendor, todas las suites históricas ni cada artefacto de los 1.958 registros de evidencia. Esos inventarios sí se procesaron y sus bytes incluidos en la cápsula sí se verificaron por hash. La lectura de evidencia se concentró en los seis gates finales, protocolos/planes/relay del focal final y diagnósticos causales señalados. No se ejecuta el caso A-01 ni QA de navegador/perfiles; T-16 conserva ese alcance. T-10–T-13 solo se examinan como contexto necesario de las nuevas asociaciones, sin reabrir componentes aprobados por hechos anteriores.

**Totales finales: 0 Critical · 1 Important · 0 Minor.** Un único defecto A-01, compartido por las filas de veracidad histórica; no se cuenta dos veces. La suite verde no lo cubre ni completa por sí sola la conformidad de T-14.
