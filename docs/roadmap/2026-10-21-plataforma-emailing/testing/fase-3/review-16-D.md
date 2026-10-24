**Lente D — revisión 16, intento 1/3 de T-15. Resultado: 1 Important.** Critical: 0 · Important: 1 · Minor: 0.

**D-01 — La selección vuelve a recorrer indefinidamente el histórico materializado.**
Localización: `app/Services/SchedulerActions.php:55–66`.

El `EXISTS` de destinatarios busca alguno sin entrega. Para descartar una revisión completamente materializada debe comprobar todos sus destinatarios. Esas campañas conservan `authorized`/`scheduled` y su fecha vencida después de materializarse; por tanto, siguen participando en cada disparo futuro. El límite de una campaña afecta al resultado y a la materialización, pero no limita este trabajo de selección.

Escenario concreto: una campaña diaria de 200 destinatarios, dentro de los **100–200 emails/día acordados**, deja aproximadamente 73.000 destinatarios históricos al año. Una invocación sin trabajo nuevo vuelve a comprobar esos destinatarios y sus entregas. Con el calendario recomendado cada minuto, representa aproximadamente **105,1 millones de comprobaciones diarias**, aunque no haya que crear ninguna entrega. Una campaña completa en la frontera admitida añade hasta 10.000 comprobaciones a cada selección posterior. Es trabajo interno de una consulta, **no 73.000 consultas SQL independientes**.

El plan archivado en `storage/phase3-t15-review1/evidence/storage/phase3-t15/implementation/scheduler-calendar-final-20261024T030805918044Z/protocol/large-reviewed-revision-6356924a-2ace-4f40-aade-a8f54fd6d9a3.json` muestra una subconsulta `dependent=true`, `cacheable=false`, acceso `ref` a destinatarios por revisión y búsqueda `eq_ref` mediante `delivery_recipient_once`. El índice evita escanear todas las entregas por destinatario; no elimina la repetición sobre todas las revisiones completas.

**Grado Important:** el coste por disparo crece con todo el histórico conservado y se repite incluso en reposo. La magnitud anterior es una deducción estática del algoritmo y del escenario indicado. **No medí latencia, CPU ni contadores SQL con ese histórico poblado.** El EXPLAIN archivado contiene una campaña y estimaciones; no demuestra el tiempo del escenario anual. Root debe contrastar esa magnitud antes de decidir corrección o rebate.

| Criterio de rendimiento | Resultado |
|---|---|
| Selección y crecimiento con histórico | ✗ D-01 |
| Bucle del dispatcher | ✓ `app/Services/SchedulerActions.php:21–38`: máximo servidor 1–100 claims; presupuesto comprobado entre operaciones |
| Materialización por destinatario | Sin defecto nuevo adicional: reutiliza materializador y escrituras por lotes existentes |
| E/S del cliente | Sin hallazgo: HTTP y lectura de configuración asíncronos; tamaños y plazos acotados |
| Fixtures y documentación | Sin otro defecto de rendimiento introducido identificado |

No considero defecto que una operación SQL/AMQP ya iniciada exceda el presupuesto cooperativo: el contrato lo declara expresamente.

Cobertura semántica: recorridos los 25 paths cambiados. Leídos los archivos completos siguientes, sujetos a la compresión automática de algunas salidas de herramienta:

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

En `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md` leí el bloque T-15 y los tramos añadidos desde aproximadamente la línea 3751 hasta 4106. **No leí íntegramente las aproximadamente 3.750 líneas históricas anteriores**; las consulté mediante búsquedas de contexto y rendimiento. Por ello, esta entrega no acredita la lectura íntegra del ledger exigida por el encargo.

Contexto adicional contrastado: `DeliveryMaterializer`, `OutboxDispatcher`, estados de `Campaign`, transiciones relevantes de `CampaignService`, búsquedas en lifecycle/consumer, índices de las migraciones de campañas y outbox, volumen de la spec, handoff y plan archivado indicado. No atribuyo cobertura semántica completa a los 799 archivos fuente ni a las 5.487 evidencias inventariadas.

Integridad, separada de lectura semántica: recalculados **6.306 hashes del manifiesto**, sin archivos ausentes ni discrepancias. SHA-256 de `increment.patch`: `215cb7551c068d1492d7305b2dbbab8fd2f164d38c2c715fb5d873411bcc14f6`.

Solo realicé lecturas y comprobaciones puras de archivos. No ejecuté aplicación, SQL, HTTP, SMTP, Docker, broker ni servicios; no modifiqué archivos. Los resultados verdes archivados no se utilizan como aprobación de rendimiento.
