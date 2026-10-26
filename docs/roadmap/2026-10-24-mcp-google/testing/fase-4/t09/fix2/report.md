# T-09 fix2: aislamiento de las repeticiones E2E

La regresión de desarrollo queda verde: **ocho E2E originales8/8 y estabilidad separada3/3**, sin fallos, flaky, omitidos ni reintentos. Revisión nueva y QA3 independiente pendientes; T-09 no está aceptada y el contador sigue8/14.

## Causa y prueba controlada

[QA2](../qa2/report.md) conserva NO-VERDE: la primera repetición pasó y las otras dos fallaron en contacts_create con HTTP200/result.isError=true. El reporter no conservó su código cerrado. E2E-08 derivaba el email sólo del UUID común de la tanda, compartía cuenta y conservaba el contacto como dependencia de una campaña hasta el teardown global.

Dos casos HTTP reales y25 aserciones sobre la base exclusiva de testing prueban la hipótesis: tres creaciones con el mismo email producen una aceptación y dos validation_failed; tres emails separados por índice producen tres aceptaciones. [JUnit](raw/diagnosis/diagnosis-junit.xml), [identidad compartida](raw/diagnosis/repeat-isolation-same_run.json) y [repeticiones aisladas](raw/diagnosis/repeat-isolation-isolated_repeats.json). validation_failed es una observación del experimento; no se presenta como respuesta recuperada del fallo histórico.

## Corrección

E2E-08 combina el UUID de la tanda y el repeatEachIndex real de Playwright para sus cuatro etiquetas: email de remitente, email de contacto, nombre de plantilla y nombre de campaña. Conserva identidad/cuentas del fixture, todas las aserciones, timeouts, retry0, guardas y limpieza atribuida. [Diff exacto](raw/source-fix.patch). No cambia PHP, esquema, unicidad de contactos ni comportamiento de negocio.

## Resultados nativos separados

| Ejecución | Resultado | Evidencia original |
|---|---|---|
| Diagnóstico HTTP | 2 casos/25 aserciones; exit0 | [JUnit](raw/diagnosis/diagnosis-junit.xml) |
| Ocho E2E completos | 8 pasan, sin reintentos; exit0 | [Reporter](raw/browser/playwright-results.json), [gate VERDE](raw/browser/qa-gate.stdout.json) |
| Tres repeticiones E2E-08 | 3 pasan, sin reintentos; exit0 | [Reporter](raw/stability/playwright-results.json), [gate VERDE](raw/stability/qa-gate.stdout.json) |
| SQL propia tras cada tanda | 28 categorías cero/tx0; exit0 | [Navegador](raw/sql-browser/stdout.txt), [estabilidad](raw/sql-stability/stdout.txt) |

Las dos tandas de navegador tienen las mismas399 fuentes finales, sin cambios durante la ejecución. Respecto al backend completo412/7967 ya admitido por QA2, sólo cambia el archivo E2E; las398 entradas restantes son idénticas. Ese backend se conserva como baseline anterior a este cambio de datos de prueba: no se afirma otra ejecución. No se suman tandas solapadas ni se atribuye QA3 a resultados de root.

Callback cerrado, teardown nativo y binds exclusivos18967/18968/18743 comprobados; cada tanda usa un UUID propio atribuido antes/después por nombres de directorio y sus manifiestos permitidos. La existencia o ausencia del recibo natural del launcher consta en evidence.json; otras evidencias no lo sustituyen. No se afirma ausencia global de procesos ni se consultan sus metadatos.

Las cuatro capturas de campañas enlazadas en evidence.json son originales con SHA verificado; los ordinales de archivo no inventan repeatEachIndex ausentes del reporter. Root inspeccionó las cuatro capturas: [registro visual](visual-review.json), sin hallazgos; las tres de estabilidad muestran las campañas distintas con sufijos0/1/2 conservadas en la misma cuenta. Root también inspeccionó las siete páginas PDF y24 capturas de QA2: [registro](raw/root-qa2-visual-admission.json), conservando límites de scroll y aceptación humana.

## Alcance pendiente

QA1 y QA2 conservan sus resultados NO-VERDE. Faltan revisión nueva y QA3 antes de aceptar/publicar T-09. Google real, clientes reales, perfiles Laragon/Docker, recuperación global y M-01–M-04 siguen pendientes, al igual que T-10–14 y principalT-19/T-20. Sólo Mailpit/log; SMTP externo deshabilitado.

Privacidad: sólo reglas de patrones públicos sobre originales permitidos, sin cargar configuración privada, comparar sus valores secretos, abrir artefactos excluidos ni certificar el historial. No hay medida atribuible de tokens, coste u horas IA; la duración de ejecución no se convierte en tiempo de trabajo IA.
