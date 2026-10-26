# T-09 fix1: evidencia y restauración del fixture

Estado: implementación verificada: suite completa **412 pruebas/7967 aserciones**, sin fallos, errores ni omitidos; recibo y fuentes admitidos por root. Revisión2 A+B+D cerrada sin gaps. QA2 independiente pendiente. MCP permanece8/14; no se acepta T-09.

## Diagnóstico

La QA1 terminó NO-VERDE144 casos/3092 aserciones, un error y nueve clases. Sus17 casos nuevos de negocio pasaron con959 aserciones. El error Windows/code5 se produjo al sustituir el archivo de evidencia de una ventana de migración. La traza final quedó incompleta. Las tablas ausentes tras el teardown no prueban restauración histórica.

Un experimento aislado reproduce el mismo error al retener el destino sin permitir su sustitución: creación0, reemplazo cien veces sin retención0, reemplazo con retención2, snapshot nuevo con el anterior retenido0 y reemplazo tras liberarlo0. No identifica qué retenía el archivo original; no se atribuye a antivirus ni a otro proceso sin evidencia.

Una copia congelada del fixture, instrumentada sólo en sus dos publicaciones de evidencia, permite fallar la escritura tras DDL real. Los dos casos dejan sin restaurar la tabla de auditoría: el fallo de publicación se confundía con fallo de migración y detenía la recuperación.

## Corrección y regresiones

El fixture publica snapshots de progreso inmutables y un archivo final único. Registra por separado los errores de evidencia; siempre intenta restaurar los DDL completados. Conserva la excepción primaria, falla también si sólo falla la evidencia y sigue deteniendo migraciones dependientes cuando el DDL de restauración falla realmente. No cambia migraciones históricas, aserciones de producto, ni la barrera atómica compartida.

El test de regresión es idéntico por SHA entre RED y GREEN. Cubre snapshots nuevos, errores tras down/up/final, causa histórica más fallo de evidencia y un fallo DDL real. La prueba histórica original completa20 pasos y acredita restauración sin errores. Una expectativa anterior de dos herramientas en discovery se actualiza a34 con página100; se conservan inicialización legacy y revocación viva.

| Tanda independiente | Casos | Aserciones | Fallos | Errores | Exit |
| --- | ---: | ---: | ---: | ---: | ---: |
| controlled_restoration_failure | 2 | 8 | 2 | 0 | 1 |
| regression_red | 6 | 28 | 4 | 0 | 1 |
| regression_green | 6 | 48 | 0 | 0 | 0 |
| historical_green | 1 | 25 | 0 | 0 | 0 |
| transport_red | 1 | 5 | 1 | 0 | 1 |
| transport_green | 1 | 12 | 0 | 0 | 0 |
| full_unfiltered | 412 | 7967 | 0 | 0 | 0 |

No se suman tandas solapadas ni se presentan como suite completa.

## Suite completa, validación pendiente y límites

La imagen Linux anterior carecía de laravel/mcp y Opis. Una imagen separada se construyó con los lockfiles actuales; su probe sin red ni montajes confirma Laravel13.38.0, MCP1.1.1, Opis2.6.0 y AMQP3.7.5. No se modificaron servicios ni volúmenes operativos.

La suite Unit+Feature completa, sin filtro, termina exit0:412 pruebas,7967 aserciones,41 clases,0 fallos/errores/omitidos. Sus399 fuentes permanecen idénticas durante la ejecución y coinciden con el árbol actual al admitir el recibo, los streams, ZIP/manifiesto, helpers y JUnit originales. El subconjunto T-09 contiene151 casos/3175 aserciones en11 clases; está incluido en el total y no se suma.

La [revisión2 A+B+D](../review2/report.md) ya está admitida y no tiene gaps pendientes. La QA2 independiente debe contrastar íntegramente el backend completo y su inventario contra las fuentes fijadas, repetir la regresión corregida y ejecutar los ocho E2E originales más estabilidad3 separada. No se presenta la suite de implementación como QA independiente. Se conservan la QA1 roja y todas las evidencias nativas. Google real, clientes reales y M01-M04 siguen pendientes. Sólo Mailpit/log; SMTP externo deshabilitado.

Los hashes, ámbitos y limitaciones verificables están en [evidence.json](evidence.json). El [informe QA1 original](../qa1/report.md) permanece intacto.
