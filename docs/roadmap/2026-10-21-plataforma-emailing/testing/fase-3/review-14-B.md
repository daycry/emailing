# Revisión 14 · T-14 · Lente B · intento 1/3

**Resultado: sin defectos de corrección confirmados. Critical: 0 · Important: 0 · Minor: 0.**

Revisión independiente de contexto fresco sobre la cápsula `storage/phase3-t14-review1`, exclusivamente de lectura. Se aplicó el prompt completo de `lens-B.md`, incluido el perfil backend y sus reglas de errores, compatibilidad, transacciones y procedencia. Se utilizó el fallback genérico y el runtime heredado: `opus/high` es el tier declarado por el frontmatter; este informe no afirma ejecución de Claude/Opus.

## Objeto e integridad

- Base aprobada: `defc14eb7ad40ef1b8c651a36d338a8365d75669`.
- SHA256 del patch: `238dd30a77e00290d55df57abfaaca143d377a23191cedde3e599c46d60716e9`, coincide con el esperado.
- SHA256 de `capsule-manifest.json`: `f430fbc70590076bca6d86b885e9d65126430cd8746f59bc22231d1c8e63b460`, coincide con el esperado.
- Verificación propia de las **2766 entradas** del manifiesto: cero ausencias o discrepancias. También se verificaron las 782 entradas de `all_source_files` y las 21 fuentes modificadas, con sus versiones anteriores cuando existen.
- `increment.patch.json` se leyó y parseó completo como índice: fuentes, evidencia, selección de lentes, scope, gates, diagnósticos históricos y handoff. Leer/verificar un índice o un hash no equivale a leer semánticamente cada archivo indexado.
- `scope.stdout.json`: 21 ficheros en alcance, cero fuera de alcance y cero excluidos efectivamente; las exclusiones vigentes se identifican en el artefacto. Lentes seleccionadas A/B/D. Esta entrega corresponde a B.

## Cobertura semántica

Se leyeron **íntegros los 1918 renglones de `increment.patch` y sus 21 diffs**:

1. `app/Livewire/Activity.php`.
2. `app/Services/AccountMailTransport.php`.
3. `app/Services/ActivityService.php`.
4. `app/Services/MailSettingsService.php`.
5. `app/Services/TemplateService.php`.
6. `database/migrations/2026_10_23_000011_add_activity_provenance.php`.
7. `docs/RAG-INDEX.md`.
8. `docs/README.md`.
9. `docs/aplicacion/actividad-y-metricas.md`.
10. `docs/aplicacion/arquitectura.md`.
11. `docs/aplicacion/desarrollo.md`.
12. `docs/aplicacion/entrega-y-recuperacion.md`.
13. `docs/aplicacion/plantillas-y-transporte.md`.
14. `docs/infraestructura/integracion-aplicaciones.md`.
15. `docs/infraestructura/operacion.md`.
16. `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md`.
17. `resources/views/components/app-layout.blade.php`.
18. `resources/views/livewire/activity.blade.php`.
19. `routes/web.php`.
20. `tests/Feature/ActivityTest.php`.
21. `tests/Feature/OutboxIntegrationTest.php`.

La lectura adicional incluyó las clases runtime modificadas completas, las fuentes nuevas completas, el esquema de mensajes/campañas/deliveries y sus migraciones dependientes, `DeliveryLifecycle`, `AccountContext`, `Permissions`, las operaciones de autorización/pausa/reanudación de `CampaignService`, los consumidores de transporte y descarga, `campaign-pagination.blade.php`, la implementación instalada de `HandlesPagination` y el relay controlado. Se contrastaron los criterios pertinentes de spec/diseño, T-14 y su ledger, el plan de mejora y ADR-003. El archivo histórico completo de tareas y todos los módulos anteriores del repositorio no se releen como una nueva auditoría global; la cobertura de los 21 ficheros se refiere al incremento completo y al contexto indicado.

## Criterios y escenarios contrastados

| Criterio | Veredicto | Evidencia y razonamiento |
|---|---|---|
| Cuenta y permiso vigentes en UI, filtros, agregados y acciones | ✓ | `app/Services/ActivityService.php:34`, `:47`, `:65`, `:126`; `app/Livewire/Activity.php:43`, `:50`, `:54`, `:68`. La autoridad deriva de membresía actual. El contexto esperado rechaza el cambio válido de cuenta con 409; la falta de membresía/scope conserva 403. Render no captura esas excepciones. |
| Identificadores ajenos y predicados de búsqueda/cursor | ✓ | `app/Services/ActivityService.php:57`, `:71`, `:85`, `:88`, `:113`, `:142`, `:186`. Ambas ramas tienen cuenta antes del UNION; joins de negocio conservan cuenta y revisión. Búsqueda y cursor agrupan sus OR. El punto limita la rama correcta y hace imposible la otra; inexistencia termina en 404 explícito. |
| Filtros inválidos y recuperación visible | ✓ | `app/Livewire/Activity.php:71`; `resources/views/livewire/activity.blade.php:17`, `:19`, `:89`. Solo se captura `ValidationException`; se mantienen errores visibles y se ocultan datos, métricas, detalle y CSV hasta corregir. El nuevo focal reproduce el rojo y después su corrección. |
| Navegación, lista, detalle y paginadores independientes | ✓ | `routes/web.php:29`; `resources/views/components/app-layout.blade.php:1`; `app/Services/ActivityService.php:121`, `:151`; `app/Livewire/Activity.php:51`, `:58`. Lista de 25, intentos de 10, nombres de página separados y reset al cambiar selección. Se comprobó el comportamiento instalado de `nextPage/setPage`; no se dedujo un reset incorrecto del hook genérico. La usabilidad visual en navegador no se ejecutó aquí. |
| Entregas sin intento, todos los intentos y compatibilidad de entradas existentes | ✓ | `app/Services/ActivityService.php:71`, `:151`; `app/Services/AccountMailTransport.php:29`, `:109`; `app/Services/MailSettingsService.php:105`; `app/Services/TemplateService.php:137`. El join de mensaje actual es izquierdo; la consulta de intentos usa `delivery_id`, sin limitarse al puntero actual. `origin` es opcional en la firma previa; sus dos entradas UI registran explícitamente `test` y la llamada anterior conserva `unknown`. |
| Procedencia durable en la misma transacción de comienzo | ✓ | `app/Services/AccountMailTransport.php:62`, `:109`, `:110`, `:112`; `app/Services/DeliveryLifecycle.php:123`. Cuenta bloqueada, validación de revisión/campaña, actor autorizador, delivery y generación se escriben junto al mensaje y la transición durable. Perder fencing revierte las escrituras relacionadas. El actor no se toma de la sesión del worker ni del viewer. |
| Migración, backfill, FK y lifecycle | ✓ | `database/migrations/2026_10_23_000011_add_activity_provenance.php:18`, `:20`, `:31`, `:47`; `tests/Feature/ActivityTest.php:525`, `:558`, `:575`; `tests/Feature/OutboxIntegrationTest.php:600`. FK compuesta evita cuenta ajena; unicidad evita repetición de generación conocida. Backfill exige puntero único por cuenta y deja generación nula. Down elimina primero FK/índices propios. Casos MySQL conservados cubren down/up repetido, referencias ambiguas/ajenas, borrado de campaña/cuenta y actor eliminado. |
| Unidades, denominadores y estados honestos | ✓ | `app/Services/ActivityService.php:78`, `:87`, `:128`; `resources/views/livewire/activity.blade.php:24`, `:34`, `:66`. Una entrega lógica no se multiplica por reintentos. Pruebas e históricos se mantienen separados. Un marcador de comienzo acredita un inicio durable; una prueba bloqueada/suprimida sin marcador acredita cero y el legado sin marcador conserva desconocido. Porcentajes comparten todos los filtros y no hay porcentaje sin muestra. Los textos conservan desconocidos de entrega final/feedback/reputación. |
| Errores, diagnósticos, escape y ambigüedad | ✓ | `app/Services/ActivityService.php:42`; `resources/views/livewire/activity.blade.php:44`, `:47`, `:78`. Diagnósticos usan allowlist/fallback fijo; metadatos se escapan con Blade. El panel distingue fallo, log, aceptación y ambigüedad; no expone una acción de reenvío automático. |
| CSV acotado y descarga privada autorizada | ✓ | `app/Services/ActivityService.php:165`, `:172`, `:184`, `:188`; `app/Services/MailSettingsService.php:108`, `:113`, `:120`. CSV neutraliza fórmulas, conserva fuente/ventana, limita a 200 filas y reautoriza cada lote. La descarga existente reconsulta registro y scope dentro del callback y no exige settings.read. Evidencia final cubre revocación antes de emitir bytes y entre lotes. La posibilidad documentada de CSV parcial no se convierte en promesa de snapshot global. |

## Evidencia final contrastada

Se contrastaron directamente request/receipt, stdout/stderr, manifiesto y **las 196 entradas de cada ZIP** de los seis gates seleccionados. En los seis coinciden comando, hashes de streams y manifiesto, salida terminal 0 y ausencia de cambios de fuentes. Manifiesto común: `59e8aecd5ed47deefad1d001683063b22ee1a56e38a77329d8454a15f0117c21`.

- `activity-final-exact-20261023T215959177563Z`: 19 pruebas, 212 aserciones; stderr vacío.
- `migration-final-20261023T220944786414Z`: 1 prueba, 15 aserciones; stderr vacío.
- `full-final-20261023T221135766386Z`: 202 pruebas, 2441 aserciones; stderr vacío.
- `format-final-check-20261023T215926999922Z`: comprobación Pint terminal 0.
- `build-final-20261023T215741456940Z`: build terminal 0.
- `final-owned-settlement-20261023T225545600570Z`: censo archivado terminal 0. No se consultaron procesos o servicios vivos desde esta revisión.

La lectura semántica de protocolo se concentró en `evidence/storage/phase3-t14/implementation/activity-final-exact-20261023T215959177563Z/protocol/` y los cuatro directorios `relay-*` hermanos. El escenario de retry conserva una entrega con tres comienzos y tres mensajes de generaciones 1/2/3, mismo autorizador y viewer distinto. El stream SMTP original registra tres conexiones, dos rechazos RCPT, un DATA y un 250 final. El escenario ambiguo separado registra DATA y pérdida de la respuesta final, sin 250. Un tercer relay registra el rechazo terminal y el cuarto no registra conexiones para las pruebas bloqueadas/suprimidas. No se publican cuerpos ni datos privados de los artefactos.

Los planes SQL originales y las consultas UI archivadas muestran el fixture de 601 deliveries/1202 mensajes, consultas de página sin incremento por fila (6 frente a 6), accesos de ventana mediante índices y puntos por PRIMARY con una fila estimada. Son estimaciones del optimizador y una comprobación de ausencia de N+1 en ese fixture; no son medición de capacidad o carga.

Los runs iniciales y de corrección se revisaron como historia diagnóstica: el 404 incorrecto inicial, expresión datetime de fixture, puerto leído incorrectamente y getter de excepción de streaming no se trasladan como defectos a las fuentes finales. El primer SMTP con puerto cero no se considera prueba causal de un relay exitoso. El posterior rojo de validación en render y su focal verde se contrastaron con el catch final. La proyección final usa `transport_started_at`; no se le atribuye la antigua constante de intentos.

## Límites y decisión de B

No se ejecutó aplicación, autoloader, SQL, broker, SMTP, navegador, tests ni servicios; no hubo mutación Git, del proyecto ni de la cápsula. Solo se escribió este informe. Los hashes cubren más archivos que la lectura semántica: no se afirma haber auditado el contenido de todas las 2766 entradas, 1958 rutas de evidencia o 782 fuentes indexadas.

Los gates verdes son evidencia limitada que respalda los escenarios indicados, no una aprobación automática de todos los criterios. No se acredita cobertura de código, exactly-once SMTP, entrega al buzón, feedback externo, navegador E2E ni aceptación humana. T-15 a T-20 conservan su alcance posterior íntegro; T-10 a T-13 solo se inspeccionaron como consumidores del incremento y no se reabren sin regresión nueva. ADR-003 sigue propuesta.

**B no identifica gaps corregibles con escenario reproducible y evidencia suficiente en este incremento. Totales finales: Critical 0 / Important 0 / Minor 0.**
