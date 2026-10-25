# Aceptación técnica de T-08 — Herramientas de datos MCP

Fecha: 2026-10-25. T-08 queda **completada técnicamente en laboratorio**. La ampliación continúa en-progreso con **8/14** tareas aceptadas; la aplicación principal conserva 18/20 y la infraestructura 3/3.

Las veinte operaciones de contactos, listas, consulta de importaciones y plantillas usan los servicios comunes de la aplicación. La cuenta y los permisos proceden de la conexión delegada vigente. Se verifican también en llamadas directas; los argumentos mantienen sus tipos JSON y se contrastan con el catálogo fijado. Los resultados usan DTOs explícitos, cursores autenticados y el límite de respuesta del SDK. La vista previa usa el renderer y sanitizador comunes y no envía correo.

| Evidencia | Resultado | Alcance y procedencia |
|---|---|---|
| [Revisión 1](review-1/report.md) | A+B sin hallazgos | Diff de 49 archivos; C/D no activadas por el selector oficial. La revisión estática no sustituye QA. |
| [Backend QA1](qa1/raw/backend/backend-junit.xml) | 60 casos / 1410 aserciones, exit0 | Contrato, tipos JSON, negocio y regresión de consentimiento; resultados originales de QA1. |
| [Regresión de protocolo QA1](qa1/raw/protocol-regression/backend-junit.xml) | 2 casos / 215 aserciones, exit0 | Tanda separada, sin sumar ejecuciones repetidas. |
| [Navegador QA2](qa2/report.md) | 7/7, un intento por escenario, exit0 | Reporter original: cero fallos, flaky, omitidos e interrupciones. E2E-07 cubre el catálogo de datos, paginación, preview, argumentos y auditoría. |
| [Gate QA2](qa2/raw/browser/qa-gate.stdout.json) | VERDE, exit0 | Aplicado al [reporter original](qa2/raw/browser/playwright-results.json); sin reconstruir resultados. |
| [Estabilidad QA1](qa1/raw/stability/playwright-results.json) | E2E-07, 3/3 | Estabilidad separada sobre las mismas fuentes; no se presenta como tres escenarios nuevos ni como ejecución de QA2. |
| [Informe PDF QA2](qa2/report.pdf) | 9 páginas y 14 PNG originales inspeccionados | Revisados por QA y root. El PDF contiene miniaturas; los PNG conservan la resolución completa. |

Root contrastó los hashes de los artefactos y los originales, las fuentes archivadas y el árbol actual. Los siete runs de QA2 contienen las mismas **380 fuentes** que QA1 y el producto revisado, sin cambios durante las ejecuciones. SHA-256 del manifiesto común: `fe68bde6b717186554fbc546758a11792222d8508d96ae0a47e284d10371e28f`. La [evidencia QA2](qa2/evidence.json) tiene SHA-256 `50b09ea2a15d6a528089d01f0027336ca210f0fd20e19db12cafd09e38d1ea58`.

## Historia conservada y cierre de recursos

[QA1 mantiene NO-VERDE 6/7](qa1/report.md): E2E-01 agotó el tiempo en la comprobación HTTP del proxy de denegación, antes del flujo de producto. El [diagnóstico posterior](review-1/diagnostic.md) no reprodujo el fallo ni confirmó su causa. QA2 ejecutó los siete escenarios originales sin cambios de producto, tests, configuración, tiempos de espera o seguridad. Su resultado permite aceptar esta tanda; **no demuestra que la causa histórica se haya corregido**.

QA liberó explícitamente el runtime tras terminar sus sesiones. La [comprobación SQL propia](qa2/raw/browser-sql/stdout.txt) acredita guard de testing aprobado, nivel transaccional cero y 21 categorías propias con cero filas. Los [binds exclusivos](qa2/raw/browser-sockets/stdout.txt) acreditan disponibles los puertos 18967, 18968 y 18743. Hay recibos de GlobalTeardown y callback; no existe recibo natural de cleanup del launcher. No se infiere ausencia de procesos ni se consultan sus metadatos.

## Límites de aceptación

Esta aceptación cubre el catálogo de veinte operaciones de T-08 y sus regresiones locales. No acredita las catorce herramientas de campañas/actividad/supresiones de T-09, las tres de envío/estado/conexión de T-10 ni las 37 operaciones completas. Las importaciones se consultan por su reporte; estas herramientas no aceptan rutas del host ni suben archivos.

Google se simuló en el límite de proveedor y el cliente de laboratorio realizó el canje OAuth. Google real, Codex, Claude Code, OpenCode, Docker operativo, SMTP externo y la aceptación humana **M-01–M-04 siguen pendientes**. Se mantienen Mailpit/log y el envío externo deshabilitado. Las comprobaciones de privacidad de la entrega se complementan con la revisión del árbol y del índice exacto antes de publicar. No hay medición atribuible de tokens, coste ni horas reales.
