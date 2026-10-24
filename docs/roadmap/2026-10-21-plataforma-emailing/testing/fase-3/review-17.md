# Revisión 17: programación con Cronicle y checkpoint por revisión

Fecha: 2026-10-24. T-15, intento acotado 2/3. Resultado: **cero Critical, Important o Minor nuevos**. El Important D-01 de la revisión 16 queda **corregido**, sin deuda aceptada ni rebate. T-16–T-20 y la aceptación humana siguen pendientes.

Se ejecutaron cuatro revisiones independientes con contexto fresco: [requisitos A](review-17-A.md), [corrección B](review-17-B.md), [seguridad C](review-17-C.md) y [rendimiento D](review-17-D.md). Se emplearon revisores genéricos con runtime heredado; el tier `opus/high` del frontmatter no identifica el modelo ejecutado. Las lentes C y D fueron seleccionadas por los archivos sensibles y el selector/migración afectados.

## Criterios y corrección

| Criterio | Resultado | Evidencia principal |
|---|---|---|
| Disparo confirma trabajo durable, sin afirmar entrega de correo | ✓ | `SchedulerRunResult` conserva `emails_completed: null`; pruebas de controlador y respuesta perdida. |
| Autenticación dedicada y sesión/CSRF separados | ✓ | `CronicleToken`, ruta interna y pruebas de credencial ausente/incorrecta. |
| Entrada y acciones finitas | ✓ | JSON cerrado; `materialize` y `dispatch`; límites controlados por el servidor. |
| Replay, autorización y pertenencia | ✓ | Revalidación bajo locks de cuenta/campaña antes del checkpoint. |
| Checkpoint atómico por revisión | ✓ | Entregas, outbox y checkpoint se confirman o revierten en una transacción. |
| Actualización de instalaciones existentes | ✓ | Migración incremental prueba completos, parciales, vacío, referencias ajenas y falta de outbox, sin borrar datos. |
| D-01: histórico ya materializado | ✓ Corregido | Selector indexado por indicador generado; comparación poblada antes/después. |
| Integración del Cronicle compartido | ✓ | Cliente fijo, dos montajes RO y 15 jobs reales en perfiles nativo/Docker. |

El mismo test poblado se ejecutó antes y después de la corrección, con tres ticks vacíos tras calentamiento en cada etapa:

| Campañas / destinatarios históricos | Claves por tick, antes → después | Lecturas siguientes, antes → después |
|---|---:|---:|
| 0 / 0 | 1 → 1 | 0 → 0 |
| 10 / 2.000 | 2.011 → 2 | 2.000 → 0 |
| 50 / 10.000 | 10.051 → 2 | 10.000 → 0 |
| 365 / 73.000 | 73.366 → 2 | 73.000 → 0 |

Se conservan las 73.000 entregas y sus 73.000 eventos. Son contadores de sesión MySQL, no consultas SQL independientes, capacidad ni SLA. El verde poblado mantiene su manifiesto histórico `42e71e8f…`: frente al final, coinciden 216 fuentes y cambia solamente `SchedulerTriggerTest.php`, con pruebas posteriores propias.

## Verificación ejecutada

Los nueve gates finales conservaron 217 fuentes del [manifiesto de ejecución](raw/t15-source-manifest.json), SHA-256 `c074241dbca980962d401bac2957f1aea0330765044b7612bd40c439692f0177`, sin cambios durante sus ejecuciones.

| Comando o integración | Resultado observado | Evidencia publicada |
|---|---|---|
| `php artisan test --filter=SchedulerTriggerTest` | 18 pruebas / 247 aserciones | [stdout](raw/t15-scheduler-stdout.txt), [recibo](raw/t15-scheduler-receipt.json) |
| Outbox focal | 20 / 299 | [stdout](raw/t15-outbox-stdout.txt), [recibo](raw/t15-outbox-receipt.json) |
| Activity focal | 20 / 237 | [stdout](raw/t15-activity-stdout.txt), [recibo](raw/t15-activity-receipt.json) |
| Tres clases PHP puras | 11 / 80 | [stdout](raw/t15-pure-php-stdout.txt), [recibo](raw/t15-pure-php-receipt.json) |
| Pint sobre 17 archivos PHP afectados | Aprobado | [stdout](raw/t15-pint-stdout.txt), [recibo](raw/t15-pint-receipt.json) |
| Cliente Node nativo | 7 pruebas | [stdout](raw/t15-node-native-stdout.txt), [recibo](raw/t15-node-native-receipt.json) |
| Cliente Node Linux | 7 pruebas | [stdout](raw/t15-node-linux-stdout.txt), [recibo](raw/t15-node-linux-receipt.json) |
| Cronicle real, nativo y Docker | 15 jobs: 12 positivos y 3 negativos esperados | Contraste detallado en los cuatro informes; archivos privados excluidos de publicación. |
| `php artisan test` | **232 pruebas / 2.793 aserciones** | [stdout](raw/t15-full-stdout.txt), [recibo](raw/t15-full-receipt.json) |

El daemon actualizado ejecutó lotes 20+3 y replay sin nuevas creaciones, con 16 migraciones y sus hashes comprobados por perfil. La respuesta perdida nativa conserva el commit y no duplica al repetir. El listener nativo fue PHP CLI; esta prueba no acredita Apache. No hubo envío SMTP ni recepción en bandeja.

El asentamiento final archivado observó 51 contenedores propios ausentes, 100 respuestas broker 404, cero procesos propios y base de pruebas con únicamente `migrations: 0`. La API observó ausencia de los dos eventos propios y de sus jobs activos, conservando tres hashes de baseline. No se consultó el historial completado; tampoco se infiere retención a partir de no purgarlo.

## Trazabilidad y límites

La cápsula privada inmutable contiene 7.690 entradas verificadas, 33 paths cambiados, 806 fuentes y 6.862 evidencias. SHA-256 del manifiesto: `047d3fd6529eeb809688408e07718f874415756bcd7ce506f4ff5423f538c4ab`; del patch: `ab3c39463a16f14286bdbdb505754e94411e6ed3a4ee17e6025191ba11f71bc1`. Los revisores recorrieron todos los hunks del diff de 2.866 líneas. Cada informe declara los cuerpos históricos sin cambios que no releyó íntegramente; los hashes y los verdes no sustituyen cobertura semántica.

Los fallos intermedios de comparación de objetos y observadores de consultas se conservan separados de las repeticiones corregidas. No se concede cobertura a aserciones no alcanzadas. Un contador secundario del auditor conservó el literal `15` aunque sus comprobaciones y los inventarios reales exigían 16 migraciones; se registra esa discrepancia sin alterar los archivos históricos ni atribuirles una corrección inexistente.

Los pares históricos sin outbox no se certifican ni se republican a ciegas: requieren reconciliación controlada y pueden continuar devolviendo 503 mientras sean candidatos. El presupuesto de ejecución es cooperativo y no interrumpe SQL/AMQP ya iniciado.

Se publican recibos y streams acotados. Los ZIP, helpers privados, credenciales y respuestas autenticadas quedan fuera de Git; el manifiesto identifica también productores privados y no representa un checkout íntegro reproducible de esa orquestación. Los comandos públicos y la [guía de integración](../../../../aplicacion/programacion-con-cronicle.md) permiten configurar pruebas nuevas con credenciales propias. Las futuras promociones de estado de conocimiento son cambios de prosa y no se atribuirán a este manifiesto histórico.

La revisión acepta el incremento T-15. No cierra el QA de recuperación de T-16, la API, la carga cruzada, la restauración ni el despliegue operativo de T-20. ADR-003 permanece propuesta.
