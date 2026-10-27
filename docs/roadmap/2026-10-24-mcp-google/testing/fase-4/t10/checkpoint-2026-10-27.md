# Checkpoint de desarrollo T-10 — 27 de octubre de 2026

T-10 sigue en progreso. Este checkpoint conserva el trabajo antes de la pausa solicitada: aplicación **18/20**, MCP/Google **9/14**. La aceptación técnica, la revisión independiente y QA de T-10 siguen pendientes. Véanse el [ledger canónico](../../../tasks.md) y la [evidencia estructurada](checkpoint-2026-10-27.json).

## Comportamiento implementado

Se incorporan `connection_info`, `mail_send` y `mail_request_get` al catálogo registrado de 37 herramientas. El envío MCP utiliza la autoridad y el servicio transaccional comunes con la API: cuenta verificada, permisos actuales, `templates.read` para contenido de plantilla, idempotencia y recibo inmutable por cuenta, destinatarios, outbox, supresiones y cuota. La procedencia queda correlacionada con cliente/grant y actividad, sin guardar el bearer en la auditoría. Mailpit/log continúan como transportes habilitados.

## Verificaciones conservadas

| Ejecución | Resultado | Fuentes y alcance |
| --- | --- | --- |
| API pública y ciclo de vida | 31/31; 792 aserciones; exit 0 | 413 fuentes; 28 casos originales y tres regresiones de preparación, cancelación y caducidad. |
| Unit+Feature original, sin filtro | 477 casos; 13.977 aserciones; 473 aprobados y cuatro errores; exit 2 | 413 fuentes originales. Todos los errores figuran en la evidencia JSON. |
| Oráculo público Outbox antes de corregir | Un caso; una aserción; un error; exit 2 | Demora controlada de 45 s, inventario completo y plazo original de 40 s. |
| Outbox corregido, sobre archivos finales | 4/4; 96 aserciones; exit 0 | Caso Linux original y tres regresiones; 413 fuentes finales. |

Cada ejecución conserva su snapshot; las tandas no se suman como una suite nueva. Entre la regresión de 477 casos y el focal final sólo cambian `OutboxIntegrationTest.php` y `outbox-action.php`. El código de producto, los contratos y el reportero de inventario permanecen iguales. Los resultados históricos fallidos e interrumpidos se conservan. La siguiente regresión completa prevista contiene 480 casos.

## Causa comprobada y corrección del fixture

El plazo de confirmación empezaba antes del arranque y de la captura completa de fuentes. En el fallo original el hijo llegó a `input_received`, sin `loaded_sources` ni `before_dispatch`; el evento continuaba `pending`. El contraste aislado del mismo hijo agotó el plazo original a los 40,11 s y completó después un inventario de 9.848 archivos, incluidos 9.566 de vendor, a los 152,61 s con un presupuesto de preparación de 180 s. La causa física de la latencia variable del host sigue sin determinarse.

El fixture separa arranque (180 s), preparación (180 s) y confirmación (40 s), con un techo de control de 460 s. Los marcadores incluyen fixture, evento, generación y SHA del helper; el padre abre `dispatch` después de validar `prepared`. El hijo comprueba `abort` y la caducidad con su propio reloj monotónico. La limpieza publica `abort` y termina el contenedor propio. Se preservan las once aserciones originales de caída, estado SQL, mensaje persistente y reconciliación del mismo evento.

El focal verde verifica dos caídas reales con exit 137 y reconciliación RabbitMQ/SQL, tres capturas Linux completas, cancelación de preparación sin abrir `dispatch` y acción caducada antes del dispatcher. En ambos negativos el proceso termina, el evento permanece `pending`, `published_at` sigue vacío y no aparecen mensajes de correo.

## Errores pendientes

La corrección focal de Outbox no acepta los tres errores restantes de `WorkerRecoveryTest`:

- Pérdida de conexión SQL tras la respuesta SMTP 250.
- Revalidación de contacto, consentimiento, remitente y campaña antes del envío.
- Límite del timeout SMTP respecto a la lease.

Su diagnóstico continúa pendiente. No se atribuye una causa común sin evidencia ni se eliminan estos casos de la próxima regresión. El focal de cuatro pruebas no sustituye la suite completa ni QA independiente.

## Límites y siguiente paso

Pint pasa en los 40 archivos PHP del incremento: 38 anteriores y los dos archivos finales de Outbox. Estos dos se normalizaron a LF con igualdad de tokens PHP significativos y se volvieron a verificar con el focal sobre sus bytes finales. Los controles estáticos y la selección exacta se comprueban antes del commit y conservan recibos locales. No se acredita un porcentaje de cobertura, consumo real de tokens, coste ni horas IA: esos valores permanecen desconocidos.

Al retomar: diagnosticar los errores restantes, ejecutar Unit+Feature completo con el inventario de 480 casos, admitir sus originales y pasar revisión adversarial y QA independientes antes de aceptar T-10. Después continúan clientes/perfiles, recuperación, Google real, aislamiento/carga y documentación final. Los ensayos privados de preparación de T-11 no aceptan esa tarea.

La privacidad del historial publicado sigue pendiente de revisión y remediación. Este checkpoint no certifica ni reescribe el historial. No se publican configuraciones privadas, transcripciones, cuerpos de solicitudes ni claves.
