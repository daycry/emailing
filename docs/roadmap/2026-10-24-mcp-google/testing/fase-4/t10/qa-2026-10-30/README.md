# QA independiente del catálogo MCP

**Verde automático: nueve escenarios originales y tres repeticiones separadas de E2E-09.** Los dos gates oficiales tienen exit0; sin fallos, flaky, omisiones ni reintentos. La aceptación técnica de T-10 corresponde al [ledger](../../../../tasks.md), no a este informe de QA.

- [Informe original Markdown](report/report.md) y [PDF revisado, 17 páginas](report/report.pdf).
- [Gate original de los nueve escenarios](runtime/original9/qa-gate.stdout.json) y [gate original de estabilidad](runtime/stability09/qa-gate.stdout.json).
- [Admisión original de backend y fuentes](input-admission.json).
- [Manifiesto de publicación](publication-manifest.json).

Se conservan52 archivos exactamente como los entregó QA: reportes nativos, gates, admisiones, Markdown, PDF, capturas y evidencia segura de render/controles. Un archivo adicional se publica como proyección: `statics/checks.public.json` conserva resultados y hashes, pero omite las rutas locales de ejecutables que contenía el original privado. La comprobación genérica de patrones del agente no detectó esas rutas; root las excluyó antes de publicar. No se modifica la evidencia privada ni se elimina ningún resultado de tests.

La selección original de QA contiene53 entradas. Su manifiesto privado tiene193; root verifica191 archivos permitidos y deja además opacos dos recibos de teardown, aparte de los cuatro raw excluidos por QA antes de lectura/hash. Sólo se publica la selección explícita aquí descrita. No se publican scripts/cachés/configuración privada ni stdout de render fallido que incluya rutas del equipo.

Root revisa las25 capturas mediante siete hojas y el detalle transaccional en resolución original. Revisa también las17 páginas del PDF en cinco hojas y amplía la página8. Se conservan las imágenes originales; las versiones reducidas del PDF no sustituyen esos PNG. El primer render PDF falló por espera de inactividad de red; se conserva privadamente. El segundo sólo cambia la espera del render por DOM listo, imágenes decodificadas y fuentes listas, sin red externa ni cambios en tests o timeouts.

El backend corresponde a una tanda completa485/13.537 sobre415 fuentes. Las ejecuciones de navegador conservan esas415 y añaden exclusivamente la prueba Python de empaquetado, para416. No se suman tandas para acreditar la suite. E2E-09 verifica aceptación y procedencia, sin ejecutar SMTP; el pipeline y sus resultados se comprueban en backend.

Las referencias a evidencia privada dentro del informe original mantienen su procedencia. Los controles `statics/` describen HEAD2a0ba1b anterior a esta publicación. La privacidad de esta selección se vuelve a verificar antes del commit; ni esta entrega ni el análisis de patrones certifican el historial Git. Google real, clientes externos, perfiles de despliegue, M-01–M-04 y el cierre global continúan pendientes.
