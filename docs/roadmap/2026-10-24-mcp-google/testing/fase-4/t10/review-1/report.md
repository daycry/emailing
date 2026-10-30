# T-10: revisión 1, registro histórico

La revisión inicial encontró **un Important, B-01**. La corrección y su revisión posterior constan en [revisión 2](../review-2/report.md); este registro conserva el resultado original y no representa un defecto todavía abierto.

Se revisaron los 55 archivos del incremento desde la base aceptada `f38e282edeeffe885ed56d5d951d89f54fb9b0f1`, incluidos los dos helpers nuevos entonces sin seguir. Selector A+B+D; C no activada por su heurística. Tres revisores de contexto fresco usaron el fallback genérico con modelo heredado: el runtime no expuso el tipo nativo reviewer y no se atribuye un modelo no aplicado. Método del bundle custom-agents 2.0.1; revisión estática, sin ejecución de aplicación o pruebas.

| Lente | Resultado original | Informe original |
| --- | --- | --- |
| A, requisitos | Sin hallazgos | [Lente A](raw/lens-a.md) |
| B, corrección | 0 Critical, 1 Important, 0 Minor | [Lente B](raw/lens-b.md) |
| D, rendimiento | Sin hallazgos | [Lente D](raw/lens-d.md) |

B-01: eliminar al actor durante el transporte aplicaba `SET NULL` en SQL, mientras una instancia retenida conservaba su actor anterior. El guard del modelo rechazaba guardar el resultado conocido y bloqueaba el cierre durable. La revisión pidió reproducir esa intercalación y preservar la validación de procedencia sin perder el resultado.

Las tres copias de `raw/` conservan exactamente los bytes de los informes privados originales. Sus SHA-256 están en [evidence.json](evidence.json); los atributos Git existentes preservan esos bytes. Los números de línea y los estados de ejecución dentro de los originales corresponden a su momento de revisión. Se publican el 30 de octubre como trazabilidad, sin ejecutar otra revisión ni promover resultados pendientes.

La revisión no es una auditoría completa de seguridad, no acredita pruebas funcionales y no acepta T-10. Consultar el [ledger vigente](../../../../tasks.md) y el [resumen de correcciones](../recovery-fixes.md).
