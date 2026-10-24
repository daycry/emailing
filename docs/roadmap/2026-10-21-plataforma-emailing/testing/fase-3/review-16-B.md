## Lente B — revisión global 16, T-15, intento 1/3

**Sin defectos de corrección identificados en el incremento revisado.** Totales pendientes: **0 Critical, 0 Important, 0 Minor**.

Revisión realizada con persona backend y subagente genérico, por no estar disponible el tipo dedicado `reviewer`. El runtime heredado no acredita ejecución Claude/Opus. No se modificaron archivos ni se ejecutaron aplicación, pruebas, SQL, HTTP, SMTP, Docker, broker, navegador o servicios.

### Veredictos

| Criterio | Veredicto | Evidencia y análisis |
|---|---|---|
| Autenticación exclusiva mediante credencial dedicada | ✓ | `app/Http/Middleware/CronicleToken.php:17` deshabilita configuración inválida; `:22` exige el Bearer vigente con comparación constante. Cookies, sesión, cuerpo y query no sustituyen la credencial. Pruebas negativas en `tests/Unit/CronicleTokenTest.php:29` y `tests/Feature/SchedulerTriggerTest.php:113`. |
| Métodos, JSON e inputs inválidos | ✓ | `CronicleToken.php:27` restringe POST y rechaza query; `app/Support/SchedulerTriggerInput.php:15` limita tamaño, media, profundidad, estructura y acciones. Los errores son explícitos y anteriores al trabajo. Casos de método, campos adicionales, comandos, profundidad y tamaño en `SchedulerTriggerTest.php:156`. |
| Aislamiento respecto de navegador y CSRF | ✓ | `bootstrap/app.php:14` registra las rutas internas separadamente; `routes/internal.php:9` aplica únicamente la autenticación dedicada. Las rutas web y su registro permanecen conservados; no se añade una excepción global de CSRF. Leídos también `routes/web.php` y `routes/console.php`. |
| Selección por vencimiento e identidad correcta | ✓ | `app/Services/SchedulerActions.php:44` utiliza UTC SQL, autorización y revisión; `:49` correlaciona ID, cuenta y campaña. `:55` busca destinatarios sin entrega y evita que un prefijo completado monopolice el selector. Escenarios de revisión ajena/inexistente, campañas futuras y dos cuentas en `SchedulerTriggerTest.php:172`, `:228` y `:274`. |
| Materialización atómica, replay y concurrencia | ✓ | `SchedulerActions.php:68` reutiliza el materializador aprobado. `app/Services/DeliveryMaterializer.php:19` bloquea cuenta/campaña, revalida vencimiento y escribe entregas/outbox en una transacción. Las identidades y constraints existentes se conservan. Pruebas de revalidación, dos procesos, 10.000 destinatarios e interrupción posterior al commit en `SchedulerTriggerTest.php:256`, `:298`, `:349` y `:403`. |
| Dispatcher finito y resultados honestos | ✓ | `SchedulerActions.php:22` acota claims y presupuesto del servidor; `:27` comprueba reloj monotónico entre intentos. Mantiene las transacciones, leases, fencing y backoff de `OutboxDispatcher`. `app/Support/SchedulerRunResult.php:18` distingue trabajo, claims y finalización de email desconocida. Broker inaccesible y operación lenta cubiertos en `SchedulerTriggerTest.php:370` y `:381`. |
| Cliente fijo y protocolo de finalización | ✓ | `scripts/cronicle-trigger.cjs:10` acepta únicamente acciones explícitas; `:16` valida configuración privada; `:32` impide redirects; `:47` limita respuesta y `:59` verifica el contrato cerrado. `:79` genera diagnósticos saneados. Stdin y lectura privada tienen vencimientos en `:91` y `:125`. Leídas completas las siete pruebas Node. |
| Integración real y preservación de infraestructura | ✓ | El override añade únicamente dos binds de lectura al Cronicle existente. Evidencia v4 correlaciona daemon, cliente, UID/GID 1000, ruta HTTP nativa/Docker, resultados y estado durable. Los tres jobs previos permanecen deshabilitados e idénticos. El censo y la API finales acreditan ausencia observada de recursos y jobs propios. |

No se encontró un escenario reproducible introducido por este incremento que justificase un gap graduado.

### Contraste de evidencia

Se verificaron independientemente, mediante operaciones puras sobre archivos archivados:

- **6.306 entradas** de `capsule-manifest.json`, sin ausencias ni diferencias.
- SHA-256 y tamaño del patch; **25 postimágenes y ocho preimágenes**.
- Correspondencia de las **799 entradas de fuentes** y **5.487 entradas de evidencia** de `increment.patch.json` con el manifiesto de cápsula.
- Request, receipt, hashes de ambos streams y contenido/hash de cada entrada ZIP de los diez gates finales, daemon v4, full fallido y RED/GREEN de calendario.
- **29 recibos hijos** del daemon v4, **107 del censo final** y cuatro del preflight corregido.
- Los **15 resultados del daemon**, correlacionados con inicio, resultado y perfil del cliente: 12 positivos y tres negativos esperados.

La evidencia final conserva:

| Observación | Resultado archivado |
|---|---|
| Activity literal | 20 pruebas, 237 aserciones |
| Scheduler literal | 13 pruebas, 155 aserciones |
| PHP puro | 11 pruebas, 80 aserciones |
| Node nativo/Linux | Siete casos por perfil |
| Pint | Aprobado |
| Full final | 227 pruebas, 2.701 aserciones |
| Full anterior | Salida 1; 226 aprobadas, una fallida, 2.684 aserciones |
| Daemon real | Lotes 20+3 en ambos perfiles; replay sin nuevas identidades |
| Liquidación observada | Cero procesos propios; 105 contenedores ausentes; 200 respuestas broker 404 para 100 nombres; únicamente migraciones vacías en SQL |
| API final | Ocho IDs propios comprobados, cero eventos propios, cero jobs propios activos y tres hashes de baseline conservados |

El daemon mantiene su manifiesto histórico **`ad773…`**. La foto final es **`d265…`**. Se contrastó que el único archivo diferente es `tests/Feature/ActivityTest.php` y que el diff cambia exclusivamente los timestamps de la fixture. Las otras **212 fuentes** coinciden, con proyección **`f66a02c5e904751e5377879275b8590776f893dc4d4b831544abb3d594e213fb`**. No se atribuye al daemon una ejecución sobre la foto posterior.

También se leyó la causa y recuperación del fallo Docker: imagen con diez migraciones frente a quince fuentes congeladas, incorporación del bind mínimo de `database`, fixture deliberadamente inválida con salida 1 y recuperación atribuida del estado parcial. El exit 0 inicial y `cleanup.errors=[]` no se utilizaron como prueba de inicialización o limpieza correcta.

### Cobertura semántica

Se leyeron íntegramente estos **24 archivos cambiados**:

- `.env.example`
- `app/Http/Controllers/SchedulerTriggerController.php`
- `app/Http/Middleware/CronicleToken.php`
- `app/Services/SchedulerActions.php`
- `app/Support/SchedulerRunResult.php`
- `app/Support/SchedulerTriggerInput.php`
- `bootstrap/app.php`
- `config/cronicle.php`
- `docs/aplicacion/programacion-con-cronicle.md`
- `docs/infraestructura/integracion-aplicaciones.md`
- `docs/infraestructura/operacion.md`
- `docs/infraestructura/servicios.md`
- `docs/knowledge/README.md`
- `docs/knowledge/gotchas/GOT-005-runtimes-de-qa-con-fuentes-mezcladas.md`
- `infra/compose.emailing-cronicle.yaml`
- `routes/internal.php`
- `scripts/cronicle-trigger.cjs`
- `tests/Feature/ActivityTest.php`
- `tests/Feature/SchedulerTriggerTest.php`
- `tests/Unit/CronicleTokenTest.php`
- `tests/Unit/SchedulerTriggerControllerTest.php`
- `tests/Unit/SchedulerTriggerInputTest.php`
- `tests/support/cronicle-trigger.test.cjs`
- `tests/support/scheduler-request.php`

El **25.º archivo**, `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md`, se revisó mediante todos sus hunks nuevos y las tareas pertinentes T-14–T-20. No se reclama lectura íntegra de las aproximadamente 3.000 líneas históricas previas ya publicadas.

Se leyeron además spec, diseño, plan, ADR-003, brief/handoff de T-15, servicios y migraciones relacionados, productor v4, soporte Cronicle, preflight, corrección del helper, censo y comprobación final de API. Los inventarios completos se verificaron por hash; **eso no constituye lectura semántica de sus miles de archivos ni de toda la suite**.

### Límites

Los resultados son observaciones archivadas, no estado vivo posterior. Las respuestas autenticadas completas de schedule/active jobs no están archivadas: se conservan resultados saneados, hashes y productor. No se consultó historial completado. Los seis números de PID reutilizados carecían de firma propia; no prueban procesos propios vivos.

La prueba nativa utiliza un listener HTTP PHP alcanzable; no acredita despliegue Apache. El presupuesto es cooperativo y una operación iniciada puede excederlo. Claims y publicación AMQP no acreditan SMTP ni entrega al buzón.

T-16–T-20 permanecen pendientes; ADR-003 conserva `propuesta`. Este veredicto corresponde exclusivamente a la corrección del incremento T-15 y no sustituye QA de fase, aceptación de navegador, carga, restauración o despliegue.
