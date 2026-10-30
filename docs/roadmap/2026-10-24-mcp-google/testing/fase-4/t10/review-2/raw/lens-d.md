# Lente D — T-10, intento 2

Fecha: 2026-10-28.

**Veredicto: sin hallazgos de rendimiento introducidos o reabiertos por las correcciones. Critical: 0; Important: 0; Minor: 0.**

Revisión estática de contexto fresco mediante subagente genérico, conforme a `custom-agents:adversarial-review` 2.0.1 y su prompt D. Se conserva el veredicto D del intento 1 sobre los 55 archivos: no se reabre lo aprobado. La resolución funcional de B01 corresponde a la lente B; este informe evalúa exclusivamente si su corrección introduce una degradación reportable.

## Alcance y activación

Se leyó completo el parche R1→actual de tres archivos y 102 líneas facilitado en `storage/worker-recovery-review-20261027/review-t10-attempt2/corrections.patch`. SHA-256 verificado mediante lectura: `ce966ead0a4c5eb991b16b45cd8dc9648e6518acdb134623fb23007c04ab7d09`.

El selector facilitado `storage/worker-recovery-review-20261027/lenses-review2.json` mantiene D=true y C=false, con motivos de ruta en migración, pruebas de migración/worker y proxy SQL. Es una selección sobre el alcance acumulado, no evidencia de una regresión en estas correcciones.

Carga conservada del intento anterior: 100–200 envíos diarios, 5–10 workers y lotes de hasta 100 destinatarios. No se presupone un SLA ni una carga de miles de solicitudes simultáneas.

## Cobertura y evidencia

| Corrección o ruta contrastada | Resultado |
|---|---|
| `app/Models/MailMessage.php:18–20` | La creación conserva la lectura puntual del grant. Un guardado que solo cambia el resultado evita esa lectura; una modificación de procedencia conserva la consulta existente. No añade SQL, esperas, bloqueos ni recorridos del historial. |
| Coste de `isDirty` | Se contrastó la implementación instalada en `vendor/laravel/framework/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php:2261` y `:2340`: recorre los atributos del modelo y comprueba las cinco claves indicadas. El coste depende del ancho fijo de una fila, no del número de entregas. Con 200 guardados son 200 recorridos de unas decenas de atributos; no hay un escenario de volumen declarado que haga material este trabajo frente al SQL evitado. En inserciones, `! $message->exists` evita ese recorrido por cortocircuito. |
| Llamantes del modelo | `AccountMailTransport.php:120–121` mantiene las dos lecturas de grant al iniciar un intento MCP, ya aceptadas en R1. `AccountMailTransport.php:148–155` y `DeliveryLifecycle.php:164–189` llevan el resultado al guardado dentro de la transacción existente, sin ampliar la sección de E/S de transporte. Para 200 intentos MCP con un resultado cada uno, el cambio puede evitar 200 consultas de validación de procedencia al finalizar; es un cálculo estático de consultas, no una medición de latencia. |
| `tests/Feature/McpSendTest.php:629–687` | Las dos regresiones añaden escenarios acotados de un envío y un registro cada uno. La eliminación en `transport_result` se inyecta mediante un observador del test; no modifica el observador de producción. No introduce un bucle por destinatario ni un proceso adicional por envío de negocio. |
| `tests/Feature/WorkerRecoveryTest.php:672` | El único cambio usa el protocolo controlado existente para el segundo worker de un caso SQL. Se siguieron `worker` (`:294–339`), `finish` (`:393–396`), `tests/support/delivery-action.php:31–57` y `tests/support/DeliveryFixtureProtocol.php:93–146`. La preparación y barrera preceden al consumo y pertenecen exclusivamente al helper de prueba. La demora configurada por defecto es cero; el mayor presupuesto es un límite, no una espera fija. No añade una segunda preparación por worker ni cambia el consumidor de producción. |

Se leyeron íntegramente los tres hunks del parche y las regiones actuales correspondientes de los tres archivos. El modelo se leyó completo. Los archivos de llamantes y del framework se consultaron solo en las regiones necesarias para comprobar el efecto del parche. El informe D anterior se leyó como traspaso de veredictos, sin volver a auditar los 55 archivos ni reabrir costes heredados.

## Hallazgos

Sin hallazgos. No se identificó una ruta nueva con degradación medible justificable mediante un escenario de carga real y un orden de magnitud. No se proponen microoptimizaciones, cambios de estilo ni refactors.

## Límites

No se ejecutaron aplicación, pruebas, Docker, red, consultas DB, inventarios ni inspecciones de procesos. No se leyeron `.env`, configuración global, credenciales, transcripciones, requests/raw ni artefactos de storage ajenos al parche, selector e informe D anterior autorizados. La única escritura es este informe; no se modificaron fuentes ni Git.

No hay benchmark, perfil, `EXPLAIN` ni medición de contención en esta revisión. Los recuentos anteriores se deducen del código; no acreditan tiempos ni capacidad. Se mantienen los demás veredictos previos y no se emite aceptación de T-10 ni verde de pruebas: el focal SQL activo, la suite completa y QA conservan sus puertas pendientes.
