# Revisión adversarial — Lente A, intento 3 de 3

**Veredicto: sin gaps nuevos o pendientes en el delta posterior al intento 2.** Critical: 0; Important: 0; Minor: 0. La revisión de conformidad permite continuar las puertas posteriores; QA2 permanece pendiente y QA1 conserva su resultado rojo.

Lente A de contexto fresco por subagente genérico y modelo heredado real: reviewer nativo/modelo solicitado no disponibles. Leídos `adversarial-review/SKILL.md` y el prompt literal A completo de `references/lens-prompts.md`, aplicado con contrato OpenAPI y puertas relajadas. No se corrigió código ni se ejecutaron pruebas SQL/E2E.

## Traspaso y alcance

Se conserva la tabla completa de `docs/roadmap/2026-10-30-proveedores-email/tasks.md:368`: C1-01 y B1-01 corregidos/verificados; A1-03 corregido/verificado; D2 sin hallazgos. A2 y las cabeceras originales B2/C2/D2 acreditan el mismo veredicto. No hay evidencia nueva que justifique reabrir lo aprobado o rebatido. T-01/T-02 siguen completadas; T-03–T-06 siguen en progreso.

Revisión limitada al parche reconstruido `storage/providers-email-20261030/review-3-production.patch`, las dos pruebas nuevas de feedback, el ajuste de ActivityTest posterior a revisión2 y el diff E2E contra `testing/qa-1/sources/mail-providers.spec.mjs`. Leídos sus llamantes, vistas, spec, plan, criterios del ledger y test-plan. El parche productivo completo modifica únicamente `DeliveryLifecycle.php`.

`storage/providers-email-20261030/scope-review3.json` registra 174 cambios, `fuera_de_alcance: []`, `criterios_alterados: []`, `avisos: []`, `info: []`, sin exclusión de usuario. Única exclusión estándar: `.claude/.progress-last.lock`. Exit0 comunicado por el orquestador, no ejecutado por esta lente. Las cuatro rutas de código/tests examinadas están declaradas en `tasks.md:293` y sirven a CA-08/13/14/15/17.

## Tabla por tarea y criterio

✓ expresa conformidad de este delta o conservación explícita del juicio previo; no cierra tareas completas. La puerta pendiente se distingue del número de gaps.

| Tarea / criterio | ✓/✗ | Evidencia y escenario |
|---|---|---|
| T-01/T-02 — criterios y verificaciones de tareas completadas | ✓ Conservado | A2 documenta los recibos T01 20/265, T02 41/61 y schema1/13, exit0, con identidad testing cuando aplica. Este delta no cambia configuración/contratos de esas tareas ni aporta evidencia que invalide ese juicio. No se repitieron pruebas para suplir evidencia. |
| T-06 — CA-05/07/08/13: callback tras requeue impide otro inicio durable | ✓ | `app/Services/DeliveryLifecycle.php:75` conserva fencing antes de consultar feedback; `:81`–84 termina ambiguo y retorna antes de las ramas API/campaña. `AccountMailTransport.php:76` toma el mutex de cuenta, `:85` llama a esa guarda y `:95` toma cuota después. El webhook comparte mutex en `ProviderWebhookService.php:22`. `tests/Feature/MailProviderDeliveryTest.php:177` autentica delivered tras429 y exige retorno null, ambiguous, diagnóstico cerrado, attempts1, ausencia de claim y exactamente una llamada HTTP (`:190`–197). |
| T-06 — CA-08/11/13: callback de intento previo durante IO actual impide otra reprogramación | ✓ | `DeliveryLifecycle.php:182` vuelve a tomar mutex y aplica fencing; `:187` consulta feedback de cualquier intento. `:219`–221 acota por account_id y delivery_id, sin limitarse al mensaje vigente. Un resultado retryable pasa a unconfirmed y luego ambiguous (`:208`–209). `MailProviderDeliveryTest.php:200` inyecta delivered del primer mensaje durante el segundo SMTP454, exige IO fuera de transacción (`:207`), exactamente dos llamadas SMTP (`:204`), segundo mensaje smtp_unconfirmed, delivery ambiguous y ningún claim adicional (`:220`–225). |
| T-06 — CA-05/07: retry ordinario sigue permitido sólo con negativa demostrada | ✓ | La conversión de `DeliveryLifecycle.php:187` sólo actúa cuando hay feedback; el cálculo/límite/backoff permanecen intactos en `:194`–206. El verde focal incluye `test_rate_limit_has_one_safe_requeue_but_permanent_or_uncertain_results_do_not_retry`; su código sigue exigiendo segunda generación/espera y cuatro llamadas totales en `MailProviderDeliveryTest.php:159`–174. |
| T-06 — CA-15: Activity reconoce aceptación API propia sin perder aislamiento | ✓ | `tests/Feature/ActivityTest.php:159`–161 cambia9→10 y exige una fila api_accepted. Es coherente con los diez estados de `app/Services/ActivityService.php:18`, incluido el nuevo estado. Se conservan el assert de cuenta ajena (`ActivityTest.php:158`), unknown0 y comprobación de todas las etiquetas (`:162`). La nota roja y la ejecución focal4/73 se conservan en `tasks.md:380`; no se presenta como nueva ejecución propia. |
| T-06 — CA-01/02/14: clear, permisos y teclado E2E mantienen garantías | ✓ | Comparación directa con copia ORIGINAL QA1: los selectores de limpieza pasan a rol checkbox/nombre accesible en `tests/e2e/mail-providers.spec.mjs:94`, `:123` y `:223`; la etiqueta sigue asociada al input en `resources/views/livewire/partials/mail-provider-form.blade.php:40`. Se mantienen check, guardado/recarga, perfil alternativo,403 y foco teclado. `mail-providers.spec.mjs:118` añade espera de dashboard tras volver aA, antes de navegar y comprobar su perfil; no sustituye aislamiento ni revocación. |
| T-06 — CA-14/15: estados/feedback visibles E2E mantienen significado | ✓ | `mail-providers.spec.mjs:175` exige el texto exacto de ambiguous en un span dentro de la lista visible, evitando seleccionar la opción del filtro. `:214` exige queja y fecha UTC dentro de la lista; coincide con el texto compuesto en `resources/views/livewire/activity.blade.php:48`. Se conserva `toBeVisible`, supresión/backend feedback y el recuento IO; no se convierte la mera existencia oculta en éxito. |
| T-06 — CA-17: no relajación de puertas | ✓ | El diff completo E2E frente a QA1 conserva los seis escenarios y sus asserts; modifica selección/sincronización y añade la espera. No cambia timeout, retries, skip ni umbral. Activity añade comprobación explícita API. Las dos pruebas nuevas amplían el oráculo de no reenvío. El rojo QA1 permanece publicado y no se reescribe como verde. |
| T-06 — CA-14/17: QA local independiente completa | ✗ Pendiente declarado | `tasks.md:391` conserva QA1:2/6 pasan,4 fallan; `:393` mantiene QA2 pendiente. `testing/qa-1/report.md` conserva NO-VERDE y gate exit1. T-06 no está cerrada; esta puerta pendiente no es una verificación falsamente declarada ni un nuevo gap de implementación. |
| Alcance/constitución/generados/API | ✓ / no aplica | Rutas declaradas en `tasks.md:293`; no existe `docs/CONSTITUTION.md`. El delta no toca commands/agents/hooks: interop no aplica. No hay OpenAPI adicional desde intento2; se conserva la validación A2 y no se repite un diff sin cambios. No aplica docs-style a este delta de código. No se declara cobertura porcentual: no hay gate porcentual configurado (`tasks.md:296`). |

## Evidencia focal consultada

Los resúmenes se leyeron directamente; no se suman tandas solapadas ni equivalen a QA de navegador.

| Resumen bajo `storage/providers-email-20261030/` | Resultado |
|---|---|
| `late-feedback-red-20261031T000919836240Z/test-summary.json` | Exit1;1 caso/6 aserciones;1 fallo,0 errores/omitidos. Conserva el rojo del callback posterior a requeue. |
| `late-feedback-green-20261031T001013302259Z/test-summary.json` | Exit0;3 casos/38 aserciones;0 fallos/errores/omitidos. Incluye callback tardío, callback temprano y retry ordinario. |
| `prior-feedback-green-20261031T001220176603Z/test-summary.json` | Exit0;1 caso/11 aserciones;0 fallos/errores/omitidos. Callback del intento anterior durante segundo IO. |

Los tres resúmenes acreditan identidad real testing guardada y `private_configuration_dumped:false`. La ejecución Activity4/73 se toma de la nota explícita del ledger; no se atribuye a estos resúmenes de feedback.

## Gaps y límites

Sin gaps nuevos o reabiertos. C1-01/B1-01/A1-03 conservan **corregido**, según fusión2 y evidencia anterior; no se reauditaron sin delta. No se acredita cancelación de IO ya iniciado ni exactly-once externo: el mutex serializa callback frente al comienzo durable y la guarda final impide nuevas generaciones tras feedback conocido. Esa delimitación consta en `tasks.md:393`.

No se ejecutaron SQL/E2E, migraciones, pruebas de proveedor real, inventarios globales o acciones sobre procesos. No se leyeron/hasharon entornos privados, claves, JSON privados, snapshots SQL ni rutas prohibidas. Única escritura: este informe. Siguiente paso: fusionar A3 con las otras lentes y completar QA2 independiente conservando QA1 original; M-01/M-02 siguen pendientes.
