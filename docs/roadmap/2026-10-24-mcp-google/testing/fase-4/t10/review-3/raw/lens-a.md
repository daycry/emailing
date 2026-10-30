Lente A · intento 3/3 · base `4ad549b8f9464d4c58dac68d1f40ebd942b17ce8` → índice congelado · 14 archivos.

**Sin gaps nuevos: Critical 0 / Important 0 / Minor 0. PKG-01 corregido en el índice.** T-10 conserva la aceptación funcional pendiente. Revisión mediante agente genérico de contexto fresco, modelo heredado, sin atribución a Opus.

Se mantienen los juicios anteriores: A sin gaps en R1/R2; B-01 corregido en R2; B y D sin defectos pendientes. Sólo se reevalúan las correcciones y el delta nuevo.

Para abreviar las evidencias: `L` = `docs/roadmap/2026-10-24-mcp-google/tasks.md`; `E` = `docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t10`.

| Criterio T-10 | Dictamen | Evidencia |
|---|---|---|
| Catálogo real de 37 operaciones | ✓ preservado | `L:499`. Catálogo y fixtures equivalentes antes/después salvo EOL. Comprobador estático ejecutado: 37 operaciones, 37 permisos, 76 esquemas, cero fallos. |
| Modalidades/destinatarios compartidos y templates.read condicional | ✓ preservado | `L:500`. No cambia producto ni contenido del contrato; se mantiene R1/R2. |
| Idempotencia MCP/API, conflicto y aislamiento entre cuentas | ✓ preservado | `L:501`. Sin cambios en aceptación, locks o pruebas funcionales anteriores. `E/backend-2026-10-30.json:33` conserva los ocho casos de McpApiIdempotencyTest. |
| Consentimiento, cuota, supresión y autorización antes SMTP | ✓ preservado | `L:502`. No cambia el pipeline ni sus guards; los siete JSON tienen identidad semántica comprobada. |
| Estados de aceptación/publicación/SMTP/incertidumbre y Mailpit/log | ✓ preservado | `L:503`. No cambia código de estados/transporte; B-01 permanece corregido según R2. |
| Reutilizar T-18 sin recursos ficticios ni segundo SMTP | ✓ preservado | `L:507`. Ningún archivo de aplicación cambia en este intento. |
| Correlación cliente/grant/request/outbox/auditoría sin token | ✓ preservado | `L:508`. Sin cambios de procedencia ni de su corrección anterior. |
| Concurrencia real y conteos atribuidos | ✓ preservado | `L:509`. No se modifican los oráculos anteriores; el nuevo Python acredita empaquetado. |

✓ preservado significa conservación del juicio anterior y ausencia de regresión en este delta; no significa nueva ejecución funcional ni aceptación.

| Control adicional | Dictamen | Evidencia |
|---|---|---|
| PKG-01: conservar los ocho inputs publicados en Git y filtros de checkout | ✓ corregido en índice | `.gitattributes:15` declara `-text` para los ocho inputs. `tests/packaging/test_pinned_contract_checkouts.py:69`, `:77`, `:83` verifican atributos del origen, hashes de objetos y filtros false/true/input. Ejecución propia: tres tests OK, exit0. |
| Alcance y Archivos | ✓ | `L:493` incorpora atributos, prueba Python, OAuth/REST y fixtures. `review3/scope.stdout:89`: fuera_de_alcance vacío; `:695`: criterios_alterados vacío. Los 14 archivos de la cápsula coinciden byte a byte con el índice. |
| Criterios con su test | ✓ para este delta | Lectura completa del test nuevo: hashes obtenidos del manifiesto/REST, contraste con pin runtime y asserts estrictos de bytes; ningún test previo eliminado o debilitado. |
| Verificación de tareas cerradas | ✓ sin cierre nuevo | T-10 sigue en-progreso (`L:484`) y criterios abiertos. `L:495` añade la verificación de empaquetado; `L:1057`–`:1061` distingue backend admitido, índice verde y pendientes. |
| Evidencia backend y límites descritos | ✓ | `E/backend-2026-10-30.json:2`: 485 casos/13.537 aserciones; `:26`: despliegue no calificado; `:27`: T10_accepted=false. `E/packaging/report.md:30` separa las 415 fuentes previas del único test Python añadido. |
| OpenAPI diff obligatorio | ✓ | Linter oficial contra `review3/oauth-base.json` y el after congelado: exit0, cambios=[], rompedores=0. |
| Puertas relajadas | ✓ sin relajación injustificada | `whitespace=cr-at-eol` permite la representación CRLF publicada y documentada en `E/packaging/report.md:15`; se conservan hashes y guard runtime. No se desactivan tests ni asserts. |
| Constitución | No aplica | `docs/CONSTITUTION.md` no existe, comprobado. |
| Generados/interop | No aplica | El delta no toca commands/, agents/ o hooks/. |
| Guía adicional de prosa | No aplica | Corrección de empaquetado con fixtures/contratos predominantes; la documentación registra su evidencia. |
| HEAD publicado y QA funcional | No verificable todavía | `E/packaging/evidence.json:150`–`:153` mantiene pendientes HEAD posterior, revisión y QA. `L:1061` conserva esos límites. |

Gaps: **ninguno**. No se registra deuda aceptada ni se reabre ningún juicio anterior sin evidencia nueva.

Lo ejecutado:

- `python -B tests/packaging/test_pinned_contract_checkouts.py --source index -v` → **3 tests OK, exit0**.
- `python -B .../skills/api-contract/scripts/openapi-lint.py --diff storage/mcp-google-portability-20261030/review3/oauth-base.json storage/mcp-google-portability-20261030/review3/after/docs/api/mcp-oauth.openapi.json --json` → **exit0; cero cambios y rompedores**.
- `python -B scripts/check-mcp-contract.py --json` → **exit0**, 596 casos de herramientas, 34 de cursor, 26 de errores y 48 OAuth; failures=[].
- Comprobación Python de empaquetado en memoria: reconstrucción completa de `corrections.patch` contra before/after, hashes de cápsula e igualdad con blobs del índice → **14/14**; equivalencia EOL y JSON → **7/7**. SHA-256 del patch completo: `69bfaa40c953995715cceef16abdd2fc3b6d296a5fb675d7c64f111fbb5529a0`.
- Puertas previas leídas: seis exit0, stderr vacíos; ledger conserva cinco avisos de Changelog de tareas pendientes.

Se consultó directamente ADR-006 aceptada, sin contradicción introducida. No se ejecutaron selectores adicionales de capacidades/memoria por el límite de comandos del brief. No se aporta porcentaje de cobertura.

Límites: sólo lectura, sin escritura de informes/código/índice; sin bootstrap, aplicación PHP, DB, red, Docker, procesos globales ni configuración privada. No se acredita despliegue, Google/clientes reales, privacidad histórica o QA de navegador. Fuera de mi lente: seguridad corresponde a C; D no se activa para este delta.
