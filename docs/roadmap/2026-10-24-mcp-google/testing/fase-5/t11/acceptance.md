# Aceptación técnica T11

Fecha: 2026-10-30. T11 completada; fase5,1/1; ampliación MCP,11/14.
El ledger canónico es [tasks.md](../../../tasks.md). La plataforma principal
permanece en18/20. Los estados pendientes de los recibos originales corresponden
al momento de su emisión y se conservan sin reescribirlos.

| Criterio aprobado | Evidencia de aceptación |
| --- | --- |
| Contratos, migraciones, claves y configuración persistentes en Laragon/Docker; reinicio y revocación | [Ciclos reales](implementation.md), [Laragon](laragon-admission.public.json) y [Docker](docker-admission.public.json): HTTPS verificado, ambos protocolos, parada/arranque, rotación y rechazo HTTP401 de credenciales revocadas. Los448 miembros del artefacto están identificados; el [artefacto final](fix1/artifact.json) sólo cambia las dos composiciones y se ejecutó con scheduler. |
| Ejemplos ficticios de los tres clientes, distinguiendo sus productos | [Guía de clientes](../../../../../aplicacion/mcp-clientes.md) y ejemplos Codex, Claude Code y OpenCode con dominio reservado y callbacks exactos; Claude Desktop se documenta por separado. No se modifican configuraciones de clientes del operador. |
| Matriz con productos/versiones, URL, transporte, registro y red; filas documentales sin aprobación | [Matriz de clientes y perfiles](../../client-matrix.md), con versiones realmente utilizadas en los perfiles y observaciones documentales de los clientes. La conexión de clientes nativos y Google real mantiene la puerta T14. |
| Backup/rotación conserva datos y revocaciones; snapshot antiguo exige invalidación | [Persistencia PHP](backend-summary.json), ciclos reales con snapshot actual/antiguo, rotación con clave anterior y rechazo HTTP posterior; transacción, idempotencia y rollback. La [guía operativa](../../../../../aplicacion/mcp-operacion.md) mantiene el acceso cerrado ante fallos. Sus [cuatro casos PowerShell](fix1/restore-recipe-admission.json) verifican que no se reabre automáticamente. |
| Credenciales/callbacks privados y publicación sin secretos ni datos personales | Configuración y CA privadas del laboratorio, ejemplos ficticios, selección explícita de fuentes y [publicación QA](qa-2026-10-30/README.md). Revisión C, controles de patrones/rutas del árbol seleccionado e inspección visual de19 capturas y12 páginas PDF. No se certifica todo el historial Git. |

Las tres subtareas quedan cubiertas por el runner público y sus135 pruebas,
la configuración TLS/origen/callbacks y las guías de instalación, diagnóstico,
revocación, rotación y recuperación. No se ejecutó `migrate:fresh` sobre la base
operativa. Las pruebas SQL comprobaron la base y el usuario exclusivos de testing.

La revisión1 encontró dos Important: daemon del scheduler ausente y reapertura
PowerShell tras un fallo. Se corrigieron ambos. El [scheduler](fix1/scheduler-admission.json)
ejecutó dos pasadas periódicas reales por perfil, incluyendo parada, recuperación
de mutex abandonado y reinicio. La revisión2 [A](review-2/lens-a.md),
[B](review-2/lens-b.md), [C](review-2/lens-c.md) y [D](review-2/lens-d.md)
cerró con cero defectos Critical/Important/Minor. No se aceptó deuda ni rebates.

| Verificación | Resultado, sin sumar ejecuciones solapadas |
| --- | --- |
| Persistencia y recuperación PHP, tras el rojo original | 8 casos/61 aserciones;0 fallos, errores u omitidos. |
| Runner Python, tras el rojo de argumentos contra el contrato | 135 pruebas;OK. |
| Ciclos completos Laragon/Docker | 12 smokes HTTPS, ambos protocolos, reinicio/rotación/revocación/restore; limpieza propia acreditada. |
| Composición final con scheduler | Cuatro ticks reales y dos smokes adicionales Docker;446 fuentes funcionales idénticas al artefacto de los ciclos completos. |
| QA independiente, una ejecución original | [9/9](qa-2026-10-30/report/report.md),0 fallos/flaky/omitidos/interrumpidos; [PDF](qa-2026-10-30/report/report.pdf), JSON nativo y capturas originales conservados. Fuentes448 estables y fixtures propios retirados. |

Documenter actualizó las guías e índices. Knowledge Gate aprobó mediante el
servicio canónico `emailing.GOT-017`, versión1, operación
`2b0858cb9079fa8bf6b6ee550af46d2368450ac612ef0edf1a44c04ee523e075`.
El SHA final de la entrada aprobada es
`2f93c3b4ec8bd593f3d56ef6d28b04d3ac434cab23136bc77bb276d5791ce6a3`;
las cuatro entradas previamente aprobadas conservan su asociación y contenido.

La corrección del fallo compara dos representaciones SQL hidratadas del modelo;
mantiene todos los atributos y las comprobaciones de identidad, clave y revocación.
La sonda previa al rebootstrap aisló el default nulo y el orden de atributos.

T12 conserva la regresión global; T13, la revisión consolidada; T14, Google,
clientes reales y manuales del destino privado. La restauración global de SQL,
broker y volúmenes corresponde a T19 de la plataforma principal. El laboratorio
continúa con Mailpit/log. Tiempo, tokens y coste reales siguen desconocidos.
