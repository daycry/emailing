# T07 — Lente D — intento 1 de 3

**Veredicto: sin hallazgos de rendimiento.** Critical: 0; Important: 0; Minor: 0.

Revisión de contexto fresco mediante subagente genérico heredado: el agente nativo reviewer no estaba disponible. Se aplicó la lente D al nuevo exportador y verificador local del manual; no se reabrió el código de aplicación T01–T06 ya revisado. Se leyeron completos `docs/manual/exportar.mjs`, `docs/manual/verificar.py`, `docs/manual/tema.css` y `docs/manual/README.md`. No se ejecutaron generadores, renders, pruebas, instalaciones, consultas ni benchmarks propios.

| Comprobación | Resultado y evidencia |
|---|---|
| N+1 o consultas en bucle | ✓ Los dos programas operan sobre archivos documentales locales y un Chromium propio; no contienen consultas ni llamadas al runtime de la aplicación. |
| Serialización costosa evitable | ✓ `docs/manual/exportar.mjs:82–101` renderiza diagramas secuencialmente sobre la misma página y estado Mermaid, retirando cada contenedor. El volumen real es 8 diagramas y la entrada admite como máximo 30, de hasta 24 000 caracteres cada uno (`:51–52`). No hay escenario documentado que justifique denunciar esta serialización como defecto. |
| Trabajo repetido o crecimiento inadecuado | ✓ La búsqueda de listas del índice (`docs/manual/exportar.mjs:114–127`) puede volver a recorrer listas anidadas, y la comprobación textual (`docs/manual/verificar.py:63–69`) busca cada bloque en el texto completo. A escala real —879 bloques, 54 páginas, 24 capítulos— no hay evidencia de degradación material ni requisito de procesar documentos de otro orden de magnitud. No se elevan microoptimizaciones a gaps. |
| Render y memoria | ✓ La extracción recorre páginas y spans (`docs/manual/verificar.py:45–62`); las previsualizaciones son opcionales y se procesan página a página, con hojas de contacto de hasta 16 páginas (`:74–90`). No se acumulan todos los mapas de bits del documento en memoria. |
| Efecto en peticiones y workers | ✓ El CLI se ejecuta expresamente para mantener el manual y cierra su navegador y servidor (`docs/manual/exportar.mjs:152–154`); no introduce trabajo en rutas, consultas o workers de la aplicación. |

Como evidencia preexistente, los recibos `pass1/bounded-receipt.json` y `pass2/bounded-receipt.json` registran salida 0 y aproximadamente 15,54 s y 10,15 s respectivamente. `pass2/validation.json` registra PASS, 54 páginas, 24 capítulos, 8 diagramas y 879/879 bloques coincidentes. Son ejecuciones del orquestador, no mediciones nuevas de esta lente; sirven para acotar el volumen revisado y no constituyen una garantía de rendimiento para entradas arbitrarias.

No se identificó ningún escenario de carga real con fichero/línea y orden de magnitud que sustente un hallazgo. Los gaps de contenido o enlaces corresponden a otras lentes y no se duplican aquí. Este informe no sustituye QA ni aprueba esos aspectos.
