Lente C de seguridad, revisión global 16, intento 1/3 de T-15: **sin hallazgos de seguridad introducidos o reabiertos identificados**. Totales: **Critical 0 · Important 0 · Minor 0**.

Revisor genérico por indisponibilidad del tipo dedicado `reviewer`, con runtime heredado. Revisión de solo lectura sobre `storage/phase3-t15-review1`; sin modificaciones ni ejecución de aplicación, pruebas, SQL, HTTP, SMTP, Docker, broker o navegador. No se realizó una auditoría general del proyecto ni de T16–T20.

Los motivos deterministas de activación fueron `.env.example` y la cabecera Authorization añadida en `scripts/cronicle-trigger.cjs:34`. Se aplicó criterio propio de seguridad; no se utilizaron referencias adicionales de `cybersecurity`.

| Criterio de seguridad | Veredicto | Evidencia y razonamiento |
|---|---|---|
| Credencial dedicada y configuración inválida | ✓ | `app/Http/Middleware/CronicleToken.php:17`: deshabilita el endpoint ante configuración ausente o malformada. Valida exactamente un Bearer hexadecimal y compara mediante `hash_equals`. |
| Sesión, cookies y credenciales alternativas | ✓ | `routes/internal.php:9`, `bootstrap/app.php:14`: ruta separada del grupo web, con middleware propio. No utiliza identidad interactiva ni acepta credencial desde cuerpo o query. |
| Métodos y parámetros arbitrarios | ✓ | `app/Http/Middleware/CronicleToken.php:27`, `app/Support/SchedulerTriggerInput.php:26`: exige POST sin query y objeto con el único campo `action`, limitado a `materialize` o `dispatch`. |
| JSON acotado | ✓ | `app/Support/SchedulerTriggerInput.php:15`: máximo de 1024 bytes, media JSON y profundidad 4. Los errores tienen diagnósticos fijos. |
| Inyección o ejecución arbitraria | ✓ | `app/Services/SchedulerActions.php:44`: consultas con predicados fijos y correlaciones SQL; el consumidor no aporta SQL, comandos, cuenta, límite ni destino. El cliente procesa JSON sin evaluar código. |
| Identidad de cuenta/campaña/revisión | ✓ | `app/Services/SchedulerActions.php:49`: correlaciona revisión con cuenta y campaña. `app/Services/DeliveryMaterializer.php:19` vuelve a comprobar estado y revisión bajo locks de cuenta/campaña, creando entrega/outbox en la misma transacción. |
| Preservación de reservas y publicación | ✓ | `app/Services/SchedulerActions.php:31` reutiliza el dispatcher existente. `app/Services/OutboxDispatcher.php:19` y `:43` conservan claim transaccional, vencimiento y finalización condicionada por token vigente. |
| Límites controlados por servidor | ✓ | `app/Services/SchedulerActions.php:22`: claims entre 1–100 y presupuesto entre 1–60000 ms. El presupuesto se comprueba entre operaciones; no se presenta como cancelación de IO en curso. |
| Secretos y errores depurados | ✓ | `app/Http/Controllers/SchedulerTriggerController.php:24`, `scripts/cronicle-trigger.cjs:79`: respuestas y completion utilizan campos/códigos controlados, sin excepciones, cuerpos crudos, cabeceras o credenciales. Respuestas del endpoint llevan `no-store`. |
| SSRF y filtración por redirección | ✓ | `scripts/cronicle-trigger.cjs:16`, `:32`: endpoint y token proceden de configuración privada del operador, no del job. Rechaza credenciales en URL, query, fragmento y otra ruta; no sigue redirecciones. |
| Protocolo del cliente | ✓ | `scripts/cronicle-trigger.cjs:45`, `:91`: respuesta limitada a 4096 bytes y validada contra contrato cerrado; stdin limitado y con deadline. Los parámetros del job sólo eligen la acción permitida. |
| Infraestructura y navegador | ✓ | `infra/compose.emailing-cronicle.yaml:5`: añade únicamente dos montajes de lectura al Cronicle existente. `bootstrap/app.php:18` mantiene la configuración web previa; no introduce exenciones CSRF para escrituras de navegador. |

No hay un escenario concreto de explotación sustentado en el incremento leído; por ello no se asignan CWE ni se proponen reparaciones.

La evidencia archivada consultada refuerza puntos concretos, sin sustituir la revisión del código:

- Los **15 resultados seguros** del daemon conservan UID/GID 1000, configuración legible y SHA del cliente coincidente con la fuente revisada. Los perfiles nativo y Docker registran `/up` 200 y rechazo sin Bearer 401 con `no-store`. La evidencia ACL declara acceso restringido al propietario y SYSTEM; los montajes observados del cliente y configuración son de lectura.
- Los resultados negativos por credencial registran fallo del job y diagnóstico fijo 401. El caso de respuesta perdida registra timeout y replay posterior sin nuevas creaciones; no implica rollback ni cancelación del servidor.
- La comparación independiente de ambos ZIP contiene **213 fuentes** y encuentra exclusivamente el cambio de timestamps en `tests/Feature/ActivityTest.php:457`. El daemon conserva su manifiesto histórico `ad773…`; el full final conserva `d265…`. No se atribuye al daemon una ejecución sobre la foto posterior.
- Se leyeron los resultados saneados de API final: ausencia de eventos propios y trabajos propios activos, y preservación de los tres hashes de tareas base. El censo y los informes de asentamiento son observaciones históricas; no acreditan estado futuro.
- Los contadores del full final son 227 pruebas y 2701 aserciones; Activity 20/237, Scheduler 13/155 y PHP puro 11/80. Estos verdes no constituyen prueba exhaustiva de seguridad ni cierre del QA de fase.

**Integridad y cobertura semántica son comprobaciones distintas.** Se verificaron las **6306 entradas de `capsule-manifest.json`**, sin discrepancias. El patch coincide con SHA-256 `215cb7551c068d1492d7305b2dbbab8fd2f164d38c2c715fb5d873411bcc14f6`. Se procesó íntegramente `increment.patch.json`, incluidos sus inventarios de 25 archivos cambiados, 799 fuentes y 5487 artefactos de evidencia. La integridad de esos inventarios no implica lectura semántica de todos sus archivos.

Se leyó semánticamente el **patch completo de 1981 líneas**. Cobertura de los 25 paths cambiados:

| Path | Lectura |
|---|---|
| `.env.example` | Completa |
| `app/Http/Controllers/SchedulerTriggerController.php` | Completa |
| `app/Http/Middleware/CronicleToken.php` | Completa |
| `app/Services/SchedulerActions.php` | Completa |
| `app/Support/SchedulerRunResult.php` | Completa |
| `app/Support/SchedulerTriggerInput.php` | Completa |
| `bootstrap/app.php` | Completa |
| `config/cronicle.php` | Completa |
| `docs/aplicacion/programacion-con-cronicle.md` | Completa |
| `docs/infraestructura/integracion-aplicaciones.md` | Completa |
| `docs/infraestructura/operacion.md` | Completa |
| `docs/infraestructura/servicios.md` | Completa |
| `docs/knowledge/README.md` | Completa |
| `docs/knowledge/gotchas/GOT-005-runtimes-de-qa-con-fuentes-mezcladas.md` | Completa |
| `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md` | Criterios T15 y añadido T15 completo; no toda la prosa histórica sin cambios |
| `infra/compose.emailing-cronicle.yaml` | Completa |
| `routes/internal.php` | Completa |
| `scripts/cronicle-trigger.cjs` | Completa |
| `tests/Feature/ActivityTest.php` | Completa |
| `tests/Feature/SchedulerTriggerTest.php` | Completa |
| `tests/Unit/CronicleTokenTest.php` | Completa |
| `tests/Unit/SchedulerTriggerControllerTest.php` | Completa |
| `tests/Unit/SchedulerTriggerInputTest.php` | Completa |
| `tests/support/cronicle-trigger.test.cjs` | Completa |
| `tests/support/scheduler-request.php` | Completa |

Como contexto se leyeron spec, design, improvement-plan y ADR-003; los servicios completos `DeliveryMaterializer`, `OutboxDispatcher` y `ConfirmedDeliveryPublisher`; rutas web, proveedor de aplicación, guard de base de pruebas, Compose base de aplicación/infraestructura y configuración Nginx. Se consultaron el brief y handoff T15, informes archivados de daemon, asentamiento y corrección de calendario, resultados seguros del daemon, observaciones seleccionadas de runtime/montajes/ACL, estado final de Cronicle, continuidad de fuentes y salidas de gates seleccionados.

**Límite real:** no se reclama lectura semántica de las 4106 líneas históricas del ledger, de las 799 fuentes completas ni de los 5487 artefactos de evidencia. El resultado corresponde al incremento y al contexto indicado. ADR-003 sigue siendo propuesta; este informe no acredita SMTP exactamente una vez, entrega externa, capacidad medida, despliegue operativo ni aceptación de T16–T20.
