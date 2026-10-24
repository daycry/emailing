Lente C — revisión global **17**, T-15, intento acotado **2/3**: **sin hallazgos de seguridad introducidos o reabiertos identificados**.

**Critical: 0 · Important: 0 · Minor: 0.**

Revisor genérico con runtime heredado; el frontmatter `opus/high` no acredita ese modelo en ejecución. Se aplicó `custom-agents:adversarial-review` exclusivamente al incremento archivado en `storage/phase3-t15-review2`. Los motivos de activación son `.env.example` y la cabecera Authorization de `scripts/cronicle-trigger.cjs:34`. Se utilizó criterio propio de seguridad, sin convertir esta revisión en una auditoría general.

| Criterio de seguridad | Veredicto | Evidencia y escenario comprobado |
|---|---|---|
| Credencial dedicada y fallo cerrado | ✓ | `app/Http/Middleware/CronicleToken.php:17` deshabilita configuración ausente o malformada; `:23` exige el Bearer hexadecimal exacto y compara mediante `hash_equals`. Una sesión interactiva no autoriza la acción. |
| Separación de sesión y CSRF | ✓ | `bootstrap/app.php:14` incorpora la ruta interna separadamente; `routes/internal.php:9` aplica el middleware dedicado. Leídas las rutas web como contexto: no se introduce una exclusión global de CSRF. |
| Entrada cerrada y acotada | ✓ | `SchedulerTriggerInput.php:15` limita el cuerpo a 1024 bytes, exige JSON y profundidad 4; `:26` admite exclusivamente `action=materialize|dispatch`. El consumidor no aporta cuenta, límite, comando, credencial ni URL. |
| Inyección y ejecución arbitraria | ✓ | `SchedulerActions.php:45` usa predicados y correlaciones fijos. El backfill de `2026_10_24_000012_add_materialization_checkpoint.php:22` es SQL estático. El cliente procesa JSON sin evaluar código. |
| Cuenta, campaña y revisión vigentes | ✓ | `SchedulerActions.php:51` correlaciona revisión, cuenta y campaña. `DeliveryMaterializer.php:20` bloquea cuenta/campaña y `:26` vuelve a resolver la revisión con ambas pertenencias antes de utilizar el checkpoint. |
| Autorización y vencimiento bajo locks | ✓ | `DeliveryMaterializer.php:22` revalida estado autorizado/programado, autorización, revisión y UTC después de adquirir los locks. El checkpoint no adelanta ni sustituye esa comprobación. |
| Checkpoint atómico y seguro ante replay | ✓ | `DeliveryMaterializer.php:19` mantiene una única transacción para parejas y checkpoint; `:54` comprueba cardinalidad y `:60` rechaza parejas históricas incompletas antes de escribir `materialized_revision_id` en `:75`. No se introduce republicación ciega. |
| Revisión nueva, pausa y concurrencia | ✓ | La columna generada de la migración `:16` compara identidades. Una revisión nueva vuelve a quedar pendiente; pausa/reanudación conservan identidad. `SchedulerTriggerTest.php:147`, `:182` y `:484` comprueban rollback, transición válida y convergencia concurrente. |
| Backfill sin certificación de asociaciones ajenas | ✓ | Migración `:24–48`: exige pertenencia de revisión, cardinalidad del snapshot y pareja delivery/outbox correlacionada por cuenta, campaña, revisión, destinatario y hash; el evento inicial debe ser `delivery.ready`, generación 1. Los snapshots parciales o ajenos no se certifican. |
| Reservas, backoff y fencing | ✓ | El incremento reutiliza `OutboxDispatcher`; `OutboxDispatcher.php:19` conserva claim transaccional y `SKIP LOCKED`; `:43` condiciona la finalización a identidad, generación, token y lease vigentes. |
| Trabajo controlado por el servidor | ✓ | `SchedulerActions.php:22–27` limita claims y presupuesto y comprueba reloj monotónico entre operaciones. No incorpora límites elegidos por el consumidor ni promete cancelar una operación ya iniciada. |
| Secretos y diagnósticos depurados | ✓ | `SchedulerTriggerController.php:24` devuelve un diagnóstico fijo y progreso desconocido ante fallos. `cronicle-trigger.cjs:79` construye la salida con campos controlados. No devuelve excepciones, cuerpos crudos o credenciales. |
| SSRF y redirecciones | ✓ | `cronicle-trigger.cjs:16` valida la configuración privada del operador; `:32` deshabilita seguimiento de redirecciones. La URL no procede de los parámetros del job y rechaza usuario, contraseña, query, fragmento u otra ruta. |
| Cliente e infraestructura | ✓ | `cronicle-trigger.cjs:49` acota respuesta; `:91` acota stdin y su espera. `infra/compose.emailing-cronicle.yaml:4` añade dos binds de lectura al Cronicle existente, sin socket Docker ni consumidor permanente. |

No se identificó un escenario concreto de explotación nuevo que justifique asignar CWE o abrir un defecto graduado. No se reabren los veredictos anteriores aprobados.

**D-01, dentro del alcance de esta lente**

La corrección elimina la búsqueda recurrente por destinatarios históricos del selector: `SchedulerActions.php:46` selecciona `materialization_pending`, respaldado por `campaign_materialization_due` en la migración `:54`. No introduce un bypass de autorización para conseguirlo.

Se contrastaron directamente los cuatro estadios de `history-cost.json`, antes y después:

| Histórico completo | Lecturas por clave anteriores, tres ticks | Lecturas por clave corregidas, tres ticks |
|---|---:|---:|
| 0 destinatarios | 1 / 1 / 1 | 1 / 1 / 1 |
| 2.000 destinatarios | 2.011 / 2.011 / 2.011 | 2 / 2 / 2 |
| 10.000 destinatarios | 10.051 / 10.051 / 10.051 | 2 / 2 / 2 |
| 73.000 destinatarios | 73.366 / 73.366 / 73.366 | 2 / 2 / 2 |

En el estadio de 73.000, `Handler_read_next` pasa de 73.000 por tick a cero y permanecen 73.000 deliveries y 73.000 outbox. Son contadores reales de sesión archivados, no consultas SQL independientes ni un SLA de latencia.

El verde poblado conserva su manifiesto histórico. La comparación independiente con el full final encuentra **216/217 fuentes idénticas** y únicamente diferente `tests/Feature/SchedulerTriggerTest.php`. No se atribuye al verde histórico una ejecución sobre la foto posterior. La valoración global de rendimiento corresponde a la lente D.

**Evidencia contrastada**

- Se verificaron los nueve gates finales mediante recibos, hashes de ambos streams y contenido de sus ZIP: **217 entradas por ZIP, sin discrepancias**, manifiesto `c074241dbca980962d401bac2957f1aea0330765044b7612bd40c439692f0177`, exit 0 y sin cambios de fuentes registrados.
- Se leyó el resultado terminal del full: **232 pruebas y 2.793 aserciones**. El Scheduler literal registra **18/247**. Los verdes respaldan esos escenarios ejecutados; no sustituyen esta revisión ni constituyen aceptación global.
- Se contrastaron los **15 resultados seguros del daemon actualizado**: 12 positivos y tres errores esperados. Ambos perfiles ejecutan lotes 20+3; las credenciales inválidas fallan con diagnóstico 401 y la respuesta perdida conserva timeout y replay sin nuevas creaciones.
- Los resultados de ambos perfiles registran UID/GID 1000, configuración privada legible y SHA del cliente revisado. La observación ACL declara dos reglas, protección y acceso exclusivo del propietario/SYSTEM.
- Los snapshots nativo y Docker contienen **16 fuentes de migración y 16 migraciones aplicadas**. Se compararon sus hashes y los seis orígenes PHP cargados con las fuentes de la cápsula, sin discrepancias. Después de materializar, revisión y checkpoint coinciden, el indicador pendiente es cero y el estado sigue siendo `authorized`.
- Se leyeron los fallos anteriores de comparación de objetos y observadores de consulta, separados de sus repeticiones corregidas. No se consideran ejecutadas las aserciones que aquellos fallos impidieron alcanzar.
- Se consultaron las observaciones saneadas de asentamiento/API y el handoff: ausencia observada de recursos propios y conservación de tres hashes de baseline. No se reejecutó su censo ni se examinó estado vivo.

**Integridad y cobertura semántica**

Verificadas **7.690/7.690 entradas** del manifiesto, sin ausencias ni discrepancias.

- SHA-256 de `capsule-manifest.json`: `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`.
- SHA-256 de `increment.patch`: `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1`.
- `increment.patch.json` se procesó íntegramente como estructura: **33 paths cambiados, 806 fuentes y 6.862 evidencias**.
- La puerta archivada de alcance incluye los 33 paths, sin fuera de alcance, avisos, información oculta ni exclusiones de usuario.

Se recorrieron semánticamente los cambios de los **33 paths**, incluidos todos los hunks nuevos del ledger y los cuatro informes anteriores. Se leyeron completos los nuevos componentes ejecutables y tests de Scheduler, middleware, controlador, input, resultado, cliente y soporte, además del materializador y la migración modificados.

En `ActivityTest.php` y `OutboxIntegrationTest.php` se revisaron los hunks y sus métodos/contexto pertinente; **no se releyeron íntegros sus cuerpos históricos sin cambios**. Tampoco se reclama lectura íntegra del historial publicado del ledger ni de las secciones documentales intactas. Se consultaron adicionalmente `CampaignService`, modelos, rutas web, proveedor y constraints de outbox para comprobar pertenencia, mutabilidad y autoridad de escritura. Las salidas críticas comprimidas se releyeron mediante fragmentos pequeños y comprobaciones estructuradas; no se infirió código omitido.

Los hashes no equivalen a lectura semántica de las 806 fuentes o de las 6.862 evidencias. No se reclama esa cobertura.

Solo se realizaron lecturas y comprobaciones puras sobre archivos archivados. No se modificaron archivos ni se ejecutaron aplicación, pruebas, SQL, HTTP, SMTP, Docker, broker, navegador o servicios.

El resultado se limita a seguridad introducida por T-15/fix1. No acredita Apache, despliegue operativo, SMTP, entrega externa, retención de historial completado o aceptación de T16–T20. ADR-003 permanece propuesta.
