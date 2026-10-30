# Correcciones de recuperación y auditoría de T-10

Estado al 30 de octubre de 2026: la nueva regresión completa termina y se admite con 485 casos/13.537 aserciones, exit0 y cero fallos, errores, omisiones o avisos registrados. WorkerRecovery pasa24 casos/710 aserciones y Outbox23/369 dentro de esa misma tanda. [Resultado completo y procedencia](backend-2026-10-30.json). El QA funcional independiente permanece pendiente. Este documento no acepta T-10 ni acredita Google o clientes nativos reales.

Durante la preparación de portabilidad se reproduce un fallo distinto de empaquetado: Git cambiaba los bytes de entradas fijadas por hash. La [corrección del índice y su regresión](packaging/report.md) conservan los ocho hashes publicados, con tres tests verdes. Sus415 fuentes originales de backend siguen byteidénticas; sólo se añade un test Python ajeno al runtime PHP. Revisión independiente de ese delta y comprobación de HEAD tras commit pendientes.

La [revisión inicial](review-1/report.md) y la [revisión de las correcciones](review-2/report.md) contienen ahora copias exactas de los seis informes originales, con sus SHA-256 y límites de alcance. Son revisiones históricas ya realizadas, no nuevas ejecuciones de pruebas.

## Preparación de los workers de prueba

Los errores originales agotaban el tiempo de preparación antes de llegar a medir la acción prevista. El inventario completo de fuentes podía terminar después de la espera del padre; el proxy SQL también podía cerrarse antes de que el worker estuviera listo. La redelivery SQL conservaba además una espera antigua de 150 segundos.

El protocolo ahora distingue preparación y acción. El hijo comunica inicio y carga con identidad de fuentes, espera un permiso ligado a esa identidad y comprueba cancelación y caducidad antes de consumir. El padre abre ese permiso cuando la preparación ha terminado. La limpieza cancela y limita su actuación a los procesos y contenedores propios. El proxy tiene una vida máxima declarada y diferencia cierre solicitado de presupuesto agotado.

Se conservan las aserciones de dominio: corte después de la aceptación SMTP, estado durable, recuperación ambigua y un solo correo. La prueba lenta comprueba al menos cien segundos de preparación y cero intentos antes del permiso. Las pruebas negativas comprueban cancelación y permiso caducado sin consumo ni envío.

## Resultado de transporte tras borrar al actor

La revisión independiente encontró que borrar al actor durante el transporte aplicaba `SET NULL` en SQL, mientras la instancia Eloquent conservaba el actor anterior. La validación de procedencia al guardar impedía persistir un resultado de transporte ya conocido.

`MailMessage` valida actor y grant al crear o cambiar la procedencia. Una actualización exclusiva del resultado conserva el tombstone de SQL. Las regresiones comprueban el borrado exactamente en `transport_result`, antes de `finish`, y el rechazo de una sustitución de actor. El transporte de estas dos pruebas es log con MySQL real; no acreditan una nueva ejecución SMTP.

## Resultados separados

| Tanda | Resultado | Alcance |
| --- | --- | --- |
| Negativas de preparación | 2 casos / 27 aserciones, cero problemas | Cancelación y permiso caducado. |
| Positiva original | 4 casos / 187 aserciones, 3 pasan y 1 error | Conserva el error de redelivery SQL de 150 s; no se presenta como verde. |
| Repetición SQL tras la corrección | 1 caso / 35 aserciones, cero problemas | Preparación controlada de la redelivery y aserciones originales. |
| Auditoría antes del fix | 2 casos / 15 aserciones, 1 error esperado | Reproduce el resultado durable rechazado; el control de sustitución de actor pasa. |
| Auditoría tras el fix | 2 casos / 26 aserciones, cero problemas | Único cambio respecto al rojo: `MailMessage.php`. |
| Proxy SQL puro | 4 pruebas pasan | Sockets propios y motivos de cierre. |
| Revisión fresca A/B/D, intento 2 | Cero Critical / Important / Minor pendientes | B-01 corregido; no sustituye QA. |
| Unit + Feature, tanda del 28 interrumpida | 320 eventos de prueba superada; 321 preparaciones iniciadas, de 485 casos exigidos | PHP 8.4.18. Detenida por petición del usuario, sin fallos/errores registrados hasta el corte; sin resultado completo admitido. |
| Unit + Feature, tanda del 29 interrumpida | 205 eventos de prueba superada; 206 preparaciones iniciadas, de 485 casos exigidos | PHP 8.5.12. Detenida por petición del usuario, sin fallos/errores registrados hasta el corte; mismas 415 fuentes y sin resultado completo admitido. |
| Unit + Feature, nueva tanda del 30 | 485 casos / 13.537 aserciones, exit0; cero problemas o avisos registrados | PHP8.5.12,415 fuentes estables e inventario exacto admitido. Una sola tanda sin filtros; no se suman parciales. |
| Empaquetado Git, índice corregido | 3 tests pasan | Ocho identidades conservadas; atributos del índice y filtros LF/CRLF/input. No cambia el pin ni se relaja el guard. |
| QA independiente del 30 | 18 comprobaciones estáticas pasan; funcional pendiente | Ledger, cobertura de escenarios, contrato, OpenAPI, privacidad y selección nativa de nueve E2E y tres repeticiones de E2E-09. El listado no ejecuta escenarios; 415 fuentes sin cambios. |

Los resultados solapados no se suman como una suite única. Los originales, recibos, streams y fuentes se conservan privadamente; sus identificadores están registrados en el ledger. La selección pública de patrones no certifica la privacidad del historial Git. Sólo se utilizan Mailpit/log y la identidad exclusiva `emailing_testing`; el envío externo continúa deshabilitado.

## Punto de reanudación

Registros sanitizados: [28 de octubre](interruption-2026-10-28.json) y [29 de octubre](interruption-2026-10-29.json). Ambos procesos están detenidos; no se fabrica un recibo de terminación del capturador ni un JUnit completo. No se acredita el teardown de la base tras las interrupciones. Antes de volver a ejecutar, comprobar la identidad SQL exclusiva y el estado de los fixtures en una ventana sin otros escritores. Repetir el inventario completo sin filtros; los eventos parciales no permiten reanudar desde el caso siguiente ni sustituir una suite completa verde. Después queda el QA funcional independiente.

Los 320 eventos del día 28 y los 205 del día 29 no se suman: corresponden a tandas solapadas y runtimes distintos. Los focos verdes de actor y SQL usaron PHP 8.4.18. Tampoco la tanda nativa de PHP 8.5.12 acredita por sí sola el perfil de despliegue PHP 8.4. El preflight independiente del 29 pasa sus 18 comprobaciones estáticas, con fuentes de navegador sin ejecutar; conserva su alcance separado.

## Cierre posterior de T-10

El 30 de octubre se admite el QA independiente9/9 y09×3, con documentación actualizada y aceptación de root. Los pendientes y booleanos de las capturas anteriores describen su momento de emisión; el [cierre técnico](acceptance.md) y el ledger contienen el estado vigente. No se modifican los originales de backend, revisión o QA.
