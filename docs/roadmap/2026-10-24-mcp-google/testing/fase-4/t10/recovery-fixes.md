# Correcciones de recuperación y auditoría de T-10

Estado al 28 de octubre de 2026: correcciones revisadas y verificadas de forma focal. La regresión completa Unit + Feature de 485 casos sigue en curso; el QA funcional independiente permanece pendiente. Este documento no acepta T-10 ni acredita Google o clientes nativos reales.

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
| Unit + Feature completos | En ejecución, 485 casos exigidos | Mismas 415 fuentes que los dos focos verdes finales; aún sin veredicto global. |
| QA independiente | Preflight estático pasa; funcional pendiente | Ledger, cobertura de escenarios, contrato, OpenAPI, privacidad y selección nativa de nueve E2E y tres repeticiones de E2E-09. El listado no ejecuta escenarios. |

Los resultados solapados no se suman como una suite única. Los originales, recibos, streams y fuentes se conservan privadamente; sus identificadores están registrados en el ledger. La selección pública de patrones no certifica la privacidad del historial Git. Sólo se utilizan Mailpit/log y la identidad exclusiva `emailing_testing`; el envío externo continúa deshabilitado.
