# Aceptación técnica de fase 3 — OAuth y MCP

Fecha: 2026-10-25. T-04, T-05, T-06 y T-07 quedan **completadas técnicamente en laboratorio**. La iniciativa continúa en-progreso, con **7/14** tareas aceptadas. La aplicación principal conserva 18/20 y la infraestructura 3/3.

La [revisión 2 A+B+C+D](review-2/report.md) terminó sin defectos pendientes. Root contrastó los originales de QA, sus fuentes archivadas y el árbol revisado: las cinco ejecuciones capturadas de QA2 contienen las mismas **352 fuentes**, sin cambios durante la ejecución ni diferencias respecto al producto revisado. Las comprobaciones de integridad y las pruebas funcionales tienen alcances distintos; ninguna sustituye la aceptación humana.

| Evidencia | Resultado | Procedencia y límite |
|---|---|---|
| [Backend QA1](qa-1/report.md) | 77 passed, 1808 assertions, exit0 | Doce clases, 2048.91 s. Se referencia una vez; QA2 no volvió a ejecutarlo. |
| [Navegador QA2](qa-2/report.md) | 6/6 E2E al primer intento, exit0 | Cero fallos, flaky, omitidos o interrumpidos; reporter original 125897.972 ms. |
| [Gate QA2](qa-2/raw/qa-gate.json) | VERDE, exit0 | Aplicado al [JSON original](qa-2/raw/playwright-results.json), sin reemplazar resultados. |
| [Privacidad QA2](qa-2/raw/public-data-check.json) | 1786 archivos, 0 hallazgos, exit0 | Estado del árbol al cierre de QA; la publicación requiere su comprobación posterior sobre el índice exacto. |
| [Informe PDF](qa-2/report.pdf) | Seis páginas revisadas por QA y root | Doce PNG originales inspeccionados; las vistas superiores E2E-05/06 están rotuladas y enlazan sus originales completos. |
| [Cierre SQL](qa-2/raw/sql-closure.json) | Guard de testing aprobado, tx0, doce categorías propias con cero filas | Comprobación de lectura de los recursos del fixture; no se borraron datos ajenos ni se consultó la base operativa. |

Las clases del lote backend son `McpAuthorizationTest`, `McpRevocationConcurrencyTest`, `TeammateTest`, `McpMembershipRemovalRegressionTest`, `McpOAuthTest`, `McpOAuthConcurrencyTest`, `McpOAuthRegistrationRegressionTest`, `McpPruneCapacityRegressionTest`, `McpTransportTest`, `McpProtocolMetadataRegressionTest`, `McpConsentToolsTest` y `GoogleLoginTest`. El comando y los recibos originales están en [procedencia QA2](qa-2/raw/provenance.json) y el ledger. Los resultados no se atribuyen individualmente a cada tarea ni se suman a tandas históricas.

## Alcance aceptado

| Tarea | Comportamiento acreditado |
|---|---|
| T-04 | Contexto delegado explícito, cuenta del grant y permisos actuales; revalidación transaccional y revocación de membresía, incluidas carreras con instantáneas SQL y coste acotado sobre grants activos. |
| T-05 | Descubrimiento sobre origen configurado, PKCE S256, códigos de un solo uso y tokens opacos temporales propios; reautorización, registro controlado y poda finita. Programar la poda no acredita ejecutar el scheduler operativo. |
| T-06 | Guard MCP dedicado, Streamable HTTP y perfil legacy contratado, revalidación por llamada, metadatos coherentes, sesiones y límites acotados. |
| T-07 | Consentimiento por cliente/cuenta/capacidades, retorno tras acceso, POST+CSRF, canje automático por el cliente de laboratorio, conexiones propias, revocación y auditoría segura. |

Las dos herramientas productivas `lists_get` y `lists_create` permiten comprobar aislamiento y permisos a través del SDK. Son prerrequisitos adelantados: **no completan T-08 ni acreditan las 37 operaciones**. T-08 conserva la verificación de sus veinte operaciones; T-09 y T-10 completarán el catálogo y el envío común.

## Historia y recursos

[QA1 conserva NO-VERDE](qa-1/report.md): el navegador no arrancó por `spawn EPERM`, no ejecutó los seis E2E y no se convirtió en un resultado aprobado. QA2 repitió el mismo caller fuera del sandbox de creación de procesos de la herramienta, con ChromiumSandbox habilitado, proxy de denegación y fuentes/asserts intactos. No se deshabilitó el sandbox de Chromium ni se instaló un paquete para obtener el verde.

Setup, fixture de reloj y teardown SQL propios terminaron con exit0; el callback se cerró. Tras la liberación de QA, root pudo reservar y cerrar exclusivamente los puertos 18967, 18968 y 18743. **No apareció el recibo natural de cleanup del launcher**: no se inventa su existencia ni se usa un inventario de procesos/PID como prueba. El cierre se atribuye a los recibos seguros, la consulta SQL propia y las reservas de puertos observadas. No se tocaron infraestructura, volúmenes operativos ni SMTP externo.

El [handoff QA2](qa-2/raw/qa-handoff.json) tiene SHA-256 `d62763ae809acb36e4d6bdb4829072c6a55543eba038aa1e58fbe491ff8d2b51`. Root verificó sus 39 artefactos referenciados, más el propio handoff, cinco ejecuciones terminales, ZIP/fuentes/streams, reporte original, PDF y recursos liberados. El manifiesto común de fuentes tiene SHA-256 `276f853d903367ff2150e2f84807dba153743cdfa71bfb1f36940a8da1ade10e`.

## Pendiente para la entrega completa

M-01–M-04 conservan su estado [pendiente](qa-2/raw/manual-status.json): Google real en Laragon y Docker; claridad del consentimiento confirmada por una persona; clientes reales con versiones y evidencia atribuida; reinicio, restauración y configuración. El nombre histórico de una captura `google-real-consent.png` corresponde al formulario real de la aplicación con **proveedor Google simulado**.

Esta fase acredita PHP cli-server nativo, MySQL de testing, SDK real y cliente protocolario de laboratorio. No acredita Apache/Nginx, despliegue TLS/Docker completo, Google real, Codex/Claude Code/OpenCode reales, rendimiento global, la suite completa del proyecto ni T-12/T-14/T-19. La aceptación global y las comprobaciones humanas siguen pendientes. Mailpit/log continúan como transportes de laboratorio, con envío externo deshabilitado.
