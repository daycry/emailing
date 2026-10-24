# Revisión 15 — Lente D — T-14, intento 2/3

**Resultado: sin hallazgos de rendimiento introducidos o reabiertos. Critical: 0 · Important: 0 · Minor: 0.**

La corrección elimina la inferencia histórica de A-01 sin añadir consultas, recorridos, IO ni trabajo bajo bloqueo. La reevaluación conserva las aprobaciones anteriores con sus límites; no se ha identificado evidencia nueva que justifique reabrirlas.

Revisión de contexto fresco mediante fallback genérico y runtime heredado. `opus/high` describe el tier declarado por el plugin, sin afirmar ejecución de Claude/Opus. La lente D está activada por la ruta de la migración, según `lenses.stdout.json`.

**Integridad y alcance**

Fuente exclusiva de proyecto y evidencia: `storage/phase3-t14-review2/`. Se leyó íntegro el prompt de D y `previous-verdicts.md`, incluida la fusión raíz y los tres informes originales. Se consultaron las instrucciones y heurísticas pertinentes de `adversarial-review`.

- Base: `defc14eb7ad40ef1b8c651a36d338a8365d75669`.
- `increment.patch`: **198.535 bytes, 2.397 líneas, 24 diffs**; SHA256 `0d9e938a4d7934fecee6c1b5033b6cf410bf83f284568f2c0c273fa5affffa29`.
- `capsule-manifest.json`: SHA256 `a0889303a1d7ba363bc473eecaea7e05f26a30c0dc8ea52f6863eda760a39da4`. Verificación propia de **4.420/4.420 entradas**, sin ausencias ni diferencias.
- `correction.patch`: **518 líneas**; SHA256 `a466bca1c7add79ada5e67844c6527d0baa49daca1d78648b0e112f3b3143652`. Sus tres informes históricos reproducidos coinciden literalmente con las secciones del incremento ya leídas.
- `increment.patch.json` se parseó completo: 24 cambios, 785 fuentes, 3.607 entradas de evidencia, seis gates finales y 43 registros diagnósticos/históricos. Se contrastaron los hashes de los 24 archivos finales, las 15 versiones anteriores, las 785 fuentes y las 3.607 entradas de evidencia.
- Scope: **24/24 en alcance**, sin archivos fuera, exclusiones efectivas, avisos ni información adicional. Los patrones vigentes no ocultan cambios de este incremento.

Estos controles de integridad no equivalen a lectura semántica de todos los archivos inventariados.

**Reevaluación de A-01**

| Criterio | Veredicto | Evidencia |
|---|---|---|
| Una página vacía no debe negar comienzos históricos | ✓ Corregido | `resources/views/livewire/activity.blade.php:83` limita la afirmación a los registros vinculados de esa página. `docs/aplicacion/actividad-y-metricas.md:81` explica la diferencia entre comienzos registrados y asociaciones recuperables. |
| Histórico con comienzos y cero asociaciones | ✓ Regresión significativa | `tests/Feature/ActivityTest.php:558` crea dos entregas, aceptada y ambigua, con dos comienzos y un puntero compartido; ejecuta down/up real y comprueba que quedan sin asociación. Verifica contador, estado, total de intentos y texto presentado. |
| Cero real y página fuera de rango | ✓ Cubiertos por el verde | El mismo test comprueba una entrega pendiente con cero comienzos y una entrega procesada mediante log con un registro: su segunda página tiene `total=1`, `count=0`, sin negar el comienzo existente. |
| Corrección sin coste nuevo por registro | ✓ | `correction.patch:455` cambia exclusivamente el texto del estado vacío productivo. Las consultas, paginación, proyecciones y bloqueos permanecen iguales. |

El RED `fix1-causal-red-20261023T232829154345Z` termina con **1 fallo/7 aserciones**. Su `stdout.txt:94` conserva el texto incorrecto y `:100` identifica la exclusión literal que falla; la traza apunta al `assertDontSee` del test. No acredita los escenarios posteriores a esa primera aserción fallida.

El GREEN `fix1-causal-green-20261023T233018388447Z` termina con **1 prueba/25 aserciones**. Se contrastaron ambos ZIP: el verde cambia el texto y amplía el test con la página fuera de rango, conservando las comprobaciones históricas.

El protocolo final:

`storage/phase3-t14-review2/evidence/storage/phase3-t14/implementation/fix1-activity-exact-20261023T233258528346Z/protocol/historical-attempt-links-db0d0e58-a11d-43da-8a3a-448d62b169a8.json:15`

registra los dos históricos con dos comienzos/cero vínculos, el cero real y la página con un registro total/cero visibles. Los hashes de las seis clases cargadas y de la vista coinciden con las fuentes finales. Los comienzos históricos son datos de fixture; el caso paginado usa transporte log. No se presentan como conexiones SMTP observadas.

**Criterios de rendimiento**

Las rutas de código siguientes corresponden a las copias selladas en `sources/`.

| Criterio y escenario concreto | Veredicto | Evidencia y límite |
|---|---|---|
| Listado con 25 resultados frente a una selección de un resultado | ✓ Sin N+1 demostrado | `app/Services/ActivityService.php:71` resuelve procedencia mediante joins; la vista consume campos proyectados. El fixture final registra **6 consultas frente a 6** para `page()`. No mide un render completo de Livewire. |
| Cuenta, ventana y estados sobre 601 entregas/1.202 mensajes sintéticos | ✓ Sin regresión nueva | `ActivityService.php:65` aplica cuenta/ventana y estados antes del UNION; `:128` agrega en SQL. Los planes seleccionados usan acceso `range` e índices de actividad, con seis filas estimadas por rama pertinente. |
| Primera página de 25 registros | ✓ Aprobación conservada | `ActivityService.php:123` mantiene límite y orden estable. El derivado presenta `filesort` y 14 filas estimadas en ese fixture; eso no demuestra degradación operativa para el volumen inicial confirmado de 100–200 emails/día. |
| Detalle individual con histórico existente | ✓ Consulta puntual conservada | `ActivityService.php:89` limita por identidad la rama correcta e imposibilita la contraria. Los tres puntos d/m/d usan `PRIMARY`, acceso `const` y una fila estimada. |
| Intentos de una entrega con tres comienzos | ✓ Sin consulta por intento | `ActivityService.php:151` filtra cuenta/entrega y pagina de diez en diez, con actor mediante join. El COUNT archivado usa `activity_delivery_generation_once`. |
| CSV con más de 200 registros | ✓ Memoria de filas acotada por lote | `ActivityService.php:184` usa cursor fecha/referencia y `limit(200)`. El ensayo de revocación registra un lote autorizado y un máximo de 200 filas emitidas antes del rechazo siguiente. No hay lectura de artefacto por fila exportada. |
| Inicio de cada intento de campaña | ✓ Sin nuevo trabajo creciente bajo lock | `app/Services/AccountMailTransport.php:109` añade una consulta puntual para actor; la asociación se escribe en la transacción de inicio. El IO permanece después del commit. La corrección revisada no modifica este camino. |
| Migración de histórico | ✓ Aprobación conservada | `database/migrations/2026_10_23_000011_add_activity_provenance.php:31` hace backfill mediante SQL agrupado, sin consulta PHP por mensaje; no adivina generación. No existe medición del DDL/backfill a escala operativa. |

Evidencia SQL propia contrastada en el protocolo del focal final:

- `mysql-explain-adde156c-c351-44b9-8487-b71c3b2a7969.json:17` conserva las cardinalidades; `:235` y `:236`, los conteos 6/6.
- `mysql-actual-ui-queries-e8d51a55-45d1-4352-b013-70b7ae01c459.json` conserva los planes de COUNT, página, métricas, puntos y COUNT de intentos.
- `tests/Feature/ActivityTest.php:435` permite comprobar la distribución del fixture y qué llamadas quedan dentro del log.

Las 1.202 filas de mensajes se reparten entre cuentas y fechas; no son 1.202 filas de una cuenta en la ventana seleccionada. El SELECT por asunto registrado entre los puntos prepara el identificador del test. El acceso `ALL` a dos actores, la materialización del derivado y las temporales del agregado no constituyen por sí solos un hallazgo.

**Gates y evidencia contrastados**

Se contrastaron request/receipt originales, ambos streams, manifiestos y **196 entradas de cada ZIP** de los seis gates finales. Coinciden comandos y hashes, todos terminan en cero, stderr está vacío y `source_changed_during_run=[]`. Comparten el manifiesto:

`30d3025450edd434386fb0a045f45cf5cd95a92e1f010e5fd033fbd86f8e594f`.

Las 195 fuentes de producto coinciden con `sources/`; el auxiliar restante coincide con su copia archivada.

| Gate final | Resultado observado |
|---|---|
| `fix1-pint-check-20261023T233126541808Z` | Pint en comprobación: passed |
| `fix1-build-final-20261023T233158836869Z` | Build completado |
| `fix1-activity-exact-20261023T233258528346Z` | **20 passed / 237 assertions** |
| `fix1-migration-final-20261023T233853373319Z` | **1 passed / 15 assertions** |
| `fix1-full-final-20261023T233955193754Z` | **203 passed / 2.466 assertions** |
| `fix1-owned-settlement-20261024T002545020815Z` | Resumen archivado: cinco runs, 46 contenedores, 42 nombres broker, 77 PID históricos, cero coincidencias nativas y guard true |

El censo anterior es evidencia archivada; esta revisión no consultó recursos vivos.

También se contrastaron recibos, streams y ZIP de los diagnósticos inicial, cuatro correcciones causales, unidades/filtros, corrección del filtro y plan observado. Sus rojos permanecen históricos. No se promueve el intento inicial con puerto cero a éxito SMTP ni se atribuye la proyección antigua de intentos a las fuentes finales con `transport_started_at`.

Los cuatro streams JSONL del relay del focal final conservan: tres conexiones con dos rechazos y un `final_250`; un caso separado con DATA y respuesta final perdida; un rechazo terminal; y cero conexiones en las pruebas bloqueada/suprimida. Esto no acredita entrega al buzón, feedback externo ni capacidad.

**Cobertura semántica y límites**

Se recorrieron todos los hunks de los **24 diffs** y la corrección completa, incluidos los informes históricos reproducidos. Se leyeron completos los cinco archivos PHP runtime afectados, la migración, las vistas afectadas, rutas, los 638 renglones de `ActivityTest` y la guía nueva de 167 líneas. En `OutboxIntegrationTest` y documentación histórica, la cobertura comprende los hunks y contexto pertinente, incluido el escenario incremental de `:595`.

Contexto adicional: contrato T-14 y tareas posteriores; requisitos pertinentes de spec, diseño y plan; ADR-003 íntegra; `AccountContext`, `MailMessage`, paginador compartido, migración 000010 y transición transaccional de inicio de `DeliveryLifecycle`. No se afirma lectura semántica de las 785 fuentes ni de las 3.607 evidencias completas.

EXPLAIN aporta estimaciones; 6/6 mide cantidad de consultas. Ninguno es un benchmark ni demuestra coste constante al crecer el histórico. No se acredita throughput, memoria de una exportación extensa, coste operativo del backfill ni un plan específico del CSV con cursor. Esos límites ya estaban declarados y la corrección no aporta una regresión nueva que permita graduarlos como defectos.

No se ejecutaron aplicación, tests, SQL, broker, SMTP, navegador ni servicios; no se modificaron archivos ni Git. Se respetaron las exclusiones privadas y se decodificó con detección de BOM cuando correspondía.

T-15–T-20, el QA de navegador/perfiles y E2E-08/09/M-01 permanecen pendientes. ADR-003 sigue propuesta. **D no identifica gaps pendientes: Critical 0 / Important 0 / Minor 0.**
