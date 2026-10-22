# Revisión de dos lentes — intento 5 · T-09 / aislamiento B-04

**Aprobado: cero Critical, Important y Minor.** Segundo intento de tres del
incremento específico B-03, quinto registro histórico. B-04 está corregido;
se conservan las aprobaciones anteriores. La revisión permite ejecutar QA2,
sin cerrar T-09 ni modificar la primera pasada de QA.

| Campo | Evidencia |
|---|---|
| Base Git | `9109d87749f86d300d927e34de1d96e7c06ab6af` |
| Preimagen | [Snapshot del intento 4](attempt-4.patch.json), hash `c801a6a284ed0b9cea3e2dce639daeecf7441ceb5f8e3de1200417789ce3132e` |
| Incremento | Tres archivos, 10.549 bytes, 148 líneas |
| SHA256 | `0b197a8aa7cf2214137b65de21554d20a3309136607618898b9719177166d279` |
| Snapshot | [patch](attempt-5.patch) · [manifiesto](attempt-5.patch.json) |
| Alcance | exit 0; 159 cambios incluidas evidencias, cero fuera de alcance/avisos/exclusiones de usuario |
| Lentes | A+B; selector C=false, D=false |
| Integridad | Lectura completa y 43/43 fuentes intactas al principio y al final; preimágenes 3/3 y manifiesto B-04 37/37 comprobados |
| Roles | Revisores genéricos frescos; tier frontmatter resuelto, runtime heredado por ausencia de reviewer nombrado y override Claude literal |
| Medición | 2026-10-22T22:48:45Z–22:53:03Z; consumos reales desconocidos/null |

## Criterios de la lente A

Las rutas de código parten de `testing/fase-2/`.

| Criterio | Resultado | Evidencia |
|---|---|---|
| Todas las limpiezas independientes se intentan | ✓ | `specs/helpers.mjs:21`; captura individual de temporales, archivos propios, precleanup y fixtures |
| Causas originales conservadas y rechazo al llamador | ✓ | `specs/helpers.mjs:19`, `:27`; AggregateError conserva razones de contextos y errores posteriores |
| Regresión sobre módulo real | ✓ | `b04-cleanup-test.mjs:20`; carga completa con SourceTextModule y sustitución exclusiva de efectos externos |
| Fallos aislados y simultáneos | ✓ | `b04-cleanup-test.mjs:51`; siete escenarios, secuencia completa e identidad/número exacto de causas |
| RED/GREEN real con el mismo test | ✓ | [Informe focal](../raw/b04-fix/report.md): RED cinco fallos de siete, exit 1; GREEN siete aprobados, exit 0; SHA del test idéntico |
| Alcance y estados fieles | ✓ | T-09 abierta, QA2 pendiente; delta limitado a ledger, función cleanup y regresión |
| Aceptación global de T-09 | Pendiente declarado | QA1 sigue 8 aprobadas/12 fallidas; QA2 y M-01 humano pendientes |

## Dictamen fusionado y traspaso

| ID / criterio | Grado | Veredicto | Evidencia |
|---|---|---|---|
| B-01/B-02/D-01/UI-01 y QA-01 | Important anteriores | Corregidos, conservados | [Traspaso completo anterior](attempt-4.md); sin evidencia nueva que los reabra |
| B-03 | Important anterior | Corregido, conservado | Configuración/ownership y verificación HTTP real aprobados en intento 4 |
| B-04 | Important anterior | Corregido y aprobado por A+B | Cada operación se intenta y todos los errores originales se propagan juntos |
| Nuevos defectos | — | Ninguno confirmado | Lectura completa de patch, módulo y regresión |

B repitió el mismo test estrictamente en memoria: RED 5/7 fallidos y GREEN
7/7 aprobados, coincidentes con los recibos. Esa comprobación de revisión usó
Node v23.8.0; los recibos originales de implementación usan v22.22.0. No se
confunden ambos entornos. Los revisores no modificaron archivos ni ejecutaron
servicios, DB, fixtures, suites o build.

No se atribuye un porcentaje de cobertura instrumental. No hay constitución ni
cambios OpenAPI/interop aplicables a este incremento. QA1 conserva su resultado
rojo, readiness E06 sigue provisional y no se promueven tareas futuras. Jira no
está configurado. Siguiente paso: QA2 completa con código congelado y un único
actor de pruebas, antes de documentar y cerrar la puerta de fase 2.
