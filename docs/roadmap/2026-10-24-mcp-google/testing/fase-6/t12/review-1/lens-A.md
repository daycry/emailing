# T-12 — Lente A — intento 1 de 3

Procedencia: proyección estructurada del mensaje final del revisor nativo por el orquestador. El rol fue de solo lectura; no se atribuye al revisor la escritura de este archivo. Prompt original 3.0.1 y referencias recuperadas de 3.0.2 tras la sustitución de la caché durante la revisión. Sin override de modelo.

Diff completo de dos ficheros, base `30b4049288d58b454f3dd1ba1d5bd49dcfbbd93b`, SHA-256 `fa20e071cb23155248c19d1ec0fb3ddaa6e94cca6968dd1d6eaf4c24f3e79107`. Referencias a las fuentes congeladas de la cápsula privada `review-1/context/`.

**Veredicto: sin defectos de conformidad introducidos por el incremento. Critical 0, Important 0, Minor 0.** T-12 continúa en progreso. La revisión permite continuar hacia QA; no acredita su cierre ni reabre T-01–T-11 sin evidencia nueva.

| Criterio u objetivo | Resultado | Evidencia y límite |
|---|---|---|
| CA1: negativos de las tres operaciones restantes | Conforme para el incremento | `tests/Feature/McpSendTest.php:88`: payloads válidos más dos campos extra, invocación directa sin scopes y límites de `connection_info`. Helpers HTTP en `:786`. Foco aportado: 1 caso/76 aserciones sin problemas. |
| CA1 completo: negativos, 37 operaciones, aislamiento y concurrencia | Todavía no verificable | Catálogo en `McpSendTest.php:53`; negativos en `McpDataToolsTest.php:138` y `McpBusinessToolsTest.php:105`. Ejecución completa pendiente, correctamente sin marcar. |
| CA2: fuentes, runtime, migraciones, terminales, recursos y limpieza | Todavía no verificable | 506/506 fuentes focales contrastadas y hashes JUnit/resumen correctos. No cubre por sí solo ambos perfiles, capturas e inventarios completos. |
| CA3: separar simulación y pruebas reales | Conforme | El ledger distingue Google/clientes/manuales reales y no atribuye QA independiente a comprobaciones parciales. |
| CA4: regresiones completas Mailpit/log | Todavía no verificable | Regresión completa de root en ejecución, sin presumir resultados ni los de workers/scheduler. |
| Escenarios sin duplicar runners | Conforme para el delta | Único método añadido en `McpSendTest.php:88`. Conserva los nueve E2E existentes y sus launchers. |
| QA focal, suite y reconciliación | Todavía no verificable | Foco y procedencia comprobados; suite y QA independiente pendientes. |
| Matriz CA y conservación de intentos | Conforme para registro parcial | Matriz y causa del primer rojo conservadas en el bloque T12; no se presentan como aceptación final. |
| Alcance y criterios aprobados | Conforme | Dos ficheros declarados; scope original sin fuera de alcance, criterios alterados, avisos ni exclusiones de usuario. |
| Tests del comportamiento añadido | Conforme | Rechazos, metadatos, fila SQL hidratada íntegra, conteos y estado. Sintaxis correcta; ningún porcentaje de cobertura atribuido. |
| Verificación declarada de tareas cerradas | Conforme respecto al delta | T12 sigue abierta; se distinguen verificaciones parciales y comandos de aceptación previstos. |
| Puertas relajadas | Conforme | Ningún test eliminado, skip, umbral o regla debilitados. `McpSendTest.php:125–128` valida formato y progresión de `observed_at` y compara estrictamente todos los demás campos; reloj SQL confirmado en `SendingApiService.php:126`. |
| Constitución | No aplica | Ausencia comprobada en la raíz real. |
| Generados e interop | No aplica | El diff no toca comandos, agentes ni hooks. |
| OpenAPI | No aplica | Ninguna spec cambia. |
| Memoria vinculante | Conforme | ADR-006 aceptada leída; ADR-008/009 propuestas. Sin contradicción observada con scopes, invariantes, idempotencia y separación entre evidencia local y real. |

Comprobaciones propias: selector de capacidades exit 0; hash del patch; comparación pura de 506 fuentes focales, cero discrepancias; hashes JUnit y resumen coincidentes; `php -n -l` del test congelado exit 0; consulta dirigida de ADR exit 0. Sin SQL, aplicación, E2E, build o bootstrap.

Evidencia aportada, no ejecutada por la lente: foco corregido exit 0, 1 caso, 76 aserciones, cero fallos/errores/omitidos, fuentes estables y contención del árbol propio. Las filas pendientes son límites explícitos, no defectos nuevos ni aceptación implícita. C y D desactivadas por el selector.
