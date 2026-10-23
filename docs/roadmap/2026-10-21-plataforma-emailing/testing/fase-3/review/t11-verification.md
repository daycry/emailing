# Verificación del componente T-11 antes de revisión

Se verifican composición, revisión, autorización y programación UTC de campañas.
Las 16 fuentes corresponden al manifest
`22042e586f36dcb9b563e9a14c2bc012acf84d23e0288e71b1f4c9f415c4b7cb`.
Esto no cierra T-11 ni CA-05/E2E-08 con worker real.

| Comprobación | Resultado real |
|---|---|
| `php artisan test --filter=CampaignTest` | 25 passed, 247 assertions, 190,91 s; exit 0 |
| `php artisan test --filter=TemplateTest` | 20 passed, 185 assertions, 59,79 s; exit 0; incluye CompiledTemplateTest |
| `php artisan test --testsuite=Feature` | 133 passed, 1404 assertions, 873,73 s; exit 0 |
| Pint `--test` sobre los 13 PHP modificados | exit 0 |
| Build con Node 22.22.0 | exit 0; 58 módulos; 33,27 s |
| Benchmark puro `validateBatch`, HTML 96.025 bytes | 100: 0,2512206 s; 200: 0,2764786 s; 10.000: 3,9095198 s; pico 10 MiB |

Las cuatro pruebas de concurrencia utilizan procesos separados sobre MySQL:
doble autorización, editar frente a autorizar, pausa concurrente y revocación
de permiso antes de adquirir el mutex de cuenta. Los IDs, contenido y permisos
se validan contra la cuenta. La autorización exige la revisión exacta y no
incorpora cambios de plantilla, remitente, cuenta o audiencia sin revisar.
Pausa/reanudación conservan la revisión y fecha autorizadas.

El renderer compila cada campo una vez por lote y mantiene la validación de cada
expansión: variables acotadas, escape, tipos, UTF-8, longitud, tamaño del resultado
y asunto sin CR/LF. El benchmark mide esa fixture de CPU; no incluye SQL,
broker ni SMTP y no establece capacidad global ni SLA.

## Fallos conservados

- Primer arranque: colisión de un helper con un método final de PHPUnit; no
  ejecutó pruebas ni operaciones de base de datos.
- Segunda ejecución: 13 passed / 9 failed / 139 assertions; se corrigieron
  directivas Blade y comparación de claves del snapshot JSON sin debilitar la
  igualdad de valores y tipos.
- Tercera ejecución: 24 passed / 1 failed / 241 assertions; la aserción buscaba
  un nombre en todo el documento y alcanzaba el selector independiente. La
  corrección verifica el DOM de audiencia y los IDs de su paginador. Se conservan
  las cuatro pruebas concurrentes.
- Build inicial: spawn EPERM. La repetición autorizada pasó con Node 22.
- El Pint global anterior mantiene su exit 1 por 12 archivos QA de fase 2:
  tres helpers activos y nueve copias históricas privadas. Pint acotado no lo
  convierte en verde. Su resolución corresponde a T-20, conservando las copias
  de evidencia y corrigiendo el código activo con trazabilidad.

Los [recibos públicos](../raw/t11-verification-receipts.json) enlazan los streams
originales y sus hashes, con todos los resultados anteriores. El resumen de
recibos omite cwd y rutas absolutas de ejecutables/scripts; los streams no se
han modificado. Los originales privados, manifest y ZIP se conservaron. Root
verificó también las 16 fuentes del ZIP y su manifest embebido como entrada 17;
un primer check que esperaba solo 16 entradas se corrigió sin cambiar el ZIP.

Todos los procesos del implementer terminaron y la base de pruebas exclusiva
se liberó el 2026-10-23T08:28:30.650416Z. La ventana de implementación fue
07:13:27Z–08:29:47Z, 1 h 16 min de reloj; tokens, euros y horas IA reales NULL.
No hubo migración de producto ni limpieza global de RabbitMQ/Mailpit.

## Alcance pendiente

No se ejecutó QA de navegador ni aceptación humana M-01 en este incremento.
El recorrido durable outbox/worker para borradores, fechas futuras, pausa y baja
después de encolar sigue pendiente de T-12/T-13/T-16. La elegibilidad de `due()`
no acredita ese recorrido. Envío externo deshabilitado, solo log/Mailpit; sin
feedback de proveedor ni tasas de reputación inventadas. Total 9/20 y ADR-003
propuesta. La revisión independiente se registra por separado.
