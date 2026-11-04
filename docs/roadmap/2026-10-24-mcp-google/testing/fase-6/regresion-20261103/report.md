# Regresión cruzada de T-12: diagnóstico del 3 de noviembre

Estado al4 de noviembre: causas aisladas, correcciones aplicadas, verificación
focal y nueva suite completa verdes; build de la aplicación verde.
No acepta T-12, T-19 ni el roadmap completo. Google real no está configurado.

## Resultados originales conservados

| Ejecución | Casos | Aserciones | Fallos | Errores | Omitidos | Salida PHPUnit |
|---|---:|---:|---:|---:|---:|---:|
| Completa, sin filtro | 613 | 13.489 | 6 | 1 | 0 | 2 |
| Reproducción mínima de actividad, TTL y snapshots | 3 | 81 | 2 | 0 | 0 | 1 |
| Reparación del conflicto DDL deliberado, aislada | 1 | 10 | 0 | 1 | 0 | 2 |
| Focal tras los fixes: toda la clase de migraciones más actividad y TTL | 8 | 194 | 0 | 0 | 0 | 0 |
| Nueva completa, sin filtro, composición de538 fuentes | 623 | 13.739 | 0 | 0 | 0 | 0 |

Las ejecuciones utilizaron base y usuario reales `emailing_testing`, guard
exclusivo previo y fuentes estables. La completa seleccionó los 63 archivos
Unit/Feature entonces existentes y conservó 514 fuentes sin cambios. PHP 8.5.12,
sockets/PDO MySQL/OpenSSL habilitados, memoria 256M. Terminó normalmente tras
14.314,066 segundos de PHPUnit; no se amplió ningún timeout de test.

El ejecutor nativo informó salida1 para las tres órdenes rojas; los recibos y JUnit
conservan las salidas PHPUnit anteriores. No se normalizan a éxito. La
contención registrada es la del árbol Windows; no prueba ausencia global de
contenedores o recursos de otras aplicaciones.

El [índice de evidencia](evidence-index.public.json) identifica doce originales
seleccionados y la configuración generada de atributos que evita alterar sus
bytes en Git. Conserva SHA-256, resúmenes y observaciones del coordinador; estas
últimas se identifican como proyecciones. No se copian requests, configuraciones
privadas, archivos de propietario, claves ni carpetas históricas completas.

## Tres causas distintas

| Causa | Evidencia que la confirma | Corrección |
|---|---|---|
| Expectativa de métricas anterior a los proveedores | `McpBusinessToolsTest` esperaba nueve estados; la respuesta y `ActivityService::STATES` incluyen además `api_accepted`. El fallo se repite en el caso aislado. | Mantener igualdad estricta y añadir el estado API con valor cero en las poblaciones vacías. No eliminar campos de la respuesta. |
| Lecturas distintas del reloj al calcular vencimiento y timestamps | La completa obtiene299 donde el test exige300; la reproducción mínima pasa al no cruzar el segundo. El [ensayo del modelo real](oauth-clock-proof.public.json), sin SQL ni bootstrap, obtiene300 con reloj fijo y299 al pasar de00:00:00.950000 a00:00:01.010000. Usa el formato real de fecha de MySQL. | Congelar el reloj únicamente en la prueba de TTL exacto. Se mantienen300/3600, canje, replay, revocación, grant y ausencia de refresh token; no se altera OAuth de producción. |
| Ventana histórica y reparación limitadas a tres migraciones | Hay siete migraciones aplicadas desde `create_mcp_sessions`: tres MCP y cuatro de proveedores. El helper ejecuta14 pasos y15 snapshots; la prueba esperaba6/7. Tras el conflicto deliberado sólo reparaba las tres antiguas, y el teardown intentaba retirar índices de proveedores que seguían ausentes (1091). El caso aislado repite ese error. | Declarar las siete migraciones aprobadas, conservar orden exacto down/up y snapshots únicos. Tras guardar el fallo intencional, reparar las dependientes no restauradas según la traza, antes del teardown normal. Verificar también columnas, tablas e índices de proveedores. |

Se descartan como explicación común los cambios de fuente durante ejecución,
un filtro parcial y la ausencia de sockets: manifiestos, selección, plataforma y
guardas lo contradicen. Las comprobaciones T-19 posteriores usaron únicamente
contenedores PHP dormidos sin red/SQL/broker, con fuentes originales estables;
no se ejecutó carga SQL paralela durante la completa.

## Alcance y comprobaciones que siguen

El alcance T-12 incorpora explícitamente `MigrationReplayTest.php` para esta
integración, sin cambiar criterios de aceptación. Los cuatro archivos de tests
modificados, incluido el negativo previo de `McpSendTest`, pasan la
[revisión2](../t12/review-2/lens-A.md), con lentes A+B+D sin gaps; conservan
pendiente QA sobre la composición final. Los tres fixes de este
diagnóstico no modifican código de aplicación ni migraciones de producción.

La [tanda focal verde](focal-green/evidence-index.public.json) incluye toda
`MigrationReplayTest` y los casos de actividad/TTL; terminó con ocho casos,
194 aserciones y cero fallos, errores u omitidos. Conservó538 fuentes estables
en la composición con las herramientas T-19 ya integradas. Se preservan cuatro
originales literales, con sus hashes y atributos propios. Se comprobó de nuevo
su coincidencia con las fuentes actuales el4 de noviembre, sin repetir SQL.
La [nueva suite completa](full-green/evidence-index.public.json) terminó
exit0 con623 casos,13.739 aserciones y cero fallos, errores u omitidos. Seleccionó
los65 archivos Unit/Feature completos, incluidos los tests de herramientas T-19
presentes en esas538 fuentes. Sus manifiestos antes/después coinciden, con
contención nativa, identidad SQL real y limpieza del daemon verificadas. La
puerta estricta original `scripts/broker_lab/load.py::prerequisite` acepta el
recibo. Siete originales literales se conservan con hashes y atributos propios;
el índice distingue sus bytes de las observaciones del coordinador.

`npm run build` terminó exit0: Vite7.3.7,58 módulos y manifest/assets generados.
Los538 archivos de fuente conservaron sus bytes. Este build pertenece al
checkout original: no acredita imágenes Docker con otros bytes ni los nuevos
controladores de navegador/restauración preparados después en worktrees.

Con estos resultados se cierra la fase4 de depuración de los tres oráculos de
test, sin cambiar código de aplicación ni DDL de producción. Para las
migraciones se reutilizan [GOT-011](../../../../../knowledge/gotchas/GOT-011-replay-cronologico-de-migraciones.md)
y [emailing.GOT-014](../../../../../knowledge/approved/gotchas/2026-10-26-restaurar-migraciones-ante-fallos-de-evidencia.md);
no se duplica esa causa ya documentada. La expectativa `api_accepted` conserva
el contrato de proveedores; no introduce una decisión de producto. La causa de
los relojes se entrega como propuesta independiente pendiente de Curator.

Siguen pendientes los nueve E2E por perfil, estabilidad y QA independiente.
Los nuevos arneses deben superar la regresión sobre sus propias fuentes exactas
antes de ejecutar su laboratorio. Google y los clientes reales siguen T-14.
