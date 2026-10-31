# T07 — Lente D — intento documental 2 de 3

Fecha: 2026-10-31. Contexto fresco; subagente genérico heredado porque el agente nativo reviewer/Opus no estaba disponible.

**Veredicto: sin hallazgos de rendimiento. Critical: 0; Important: 0; Minor: 0.**

Se aplicó el contrato y el prompt literal D de `custom-agents:adversarial-review`. Se leyeron los cuatro informes completos DOC1 y su fusión/corrección en `docs/roadmap/2026-10-30-proveedores-email/tasks.md`. D1 no tenía hallazgos; los tres Important fusionados eran requisitos de instalación, contenido y enlaces PDF. Se reevalúan exclusivamente las correcciones T07 y sus posibles efectos nuevos. No se reabre lo aprobado en T01–T06 ni los aspectos de rendimiento ya aceptados sin evidencia nueva.

## Comprobaciones del delta

| Comprobación | Resultado y evidencia |
|---|---|
| Recorrido y resolución de enlaces | ✓ `docs/manual/exportar.mjs:54–78` visita los tokens y sus hijos una vez; hace `stat` asíncrono únicamente para cada referencia relativa documental. La carga real es 26 referencias: 26 comprobaciones locales de metadatos por exportación, sin consultas ni peticiones HTTPS. La conversión a GitHub compone una cadena; no descarga el destino. La espera secuencial sobre ese volumen no acredita una degradación material que permita reportar un gap. |
| Verificación de URI PDF | ✓ `docs/manual/verificar.py:46–47` recoge enlaces durante el recorrido de páginas que ya existía; `:74–79` filtra el origen temporal, construye una vez un conjunto de URI y comprueba las 26 referencias esperadas contra él. Para U enlaces PDF y R referencias documentales, las nuevas guardas requieren trabajo proporcional a U + R y memoria proporcional a U; no vuelven a abrir el PDF por cada enlace ni realizan E/S de red. |
| Aumento de contenido | ✓ El artefacto pasa de 54 a 57 páginas y de 879 a 919 bloques, manteniendo 24 capítulos y 8 diagramas. El renderer sigue limitado a 6–30 diagramas de hasta 24 000 caracteres (`docs/manual/exportar.mjs:51–52`); las correcciones de prosa no añaden consultas, workers ni código de petición. No hay evidencia nueva para reabrir la aceptación D1 del render secuencial y la búsqueda de bloques. |
| Liberación y memoria | ✓ Continúan el cierre del navegador/servidor en `docs/manual/exportar.mjs:179–181` y las previsualizaciones por página, con hojas de contacto de 16 páginas como máximo, en `docs/manual/verificar.py:82–98`. La incorporación de enlaces conserva solo cadenas y un conjunto pequeño; no incorpora mapas de bits adicionales ni procesos persistentes. |

No se identificó un escenario de carga real con fichero/línea y orden de magnitud que justifique un defecto introducido o reabierto. Las observaciones de complejidad anteriores describen la inspección estática; no son resultados de un benchmark.

## Identidad y evidencia preexistente

Los hashes calculados de los dos archivos públicos canónicos coinciden con `docs/manual/verificacion.json` y con la segunda pasada:

- Markdown: `0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe`.
- PDF: `330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7`.

Se leyeron los recibos ya existentes de `storage/providers-email-20261030/manual/fix1-final1/` y `fix1-final2/`: ambos registran exit 0, en 11,1891202 s y 10,782803 s respectivamente. Sus validaciones registran PASS, 57 páginas, 24 capítulos, 8 diagramas, 919/919 bloques, 26 referencias estables y cero enlaces temporales, referencias ausentes o problemas. Son ejecuciones aportadas por el orquestador, no mediciones propias de esta lente ni una comparación controlada de rendimiento.

## Límites

Lectura estática de los programas completos, README, tema, traspaso y recibos públicos expresamente autorizados. No se ejecutaron generadores, renders, instalaciones, builds, SQL, E2E, comandos operativos, proveedores reales ni benchmarks. No se leyeron entornos privados, claves, volcados ni los artefactos prohibidos. Solo se escribe este informe; no se modifica fuente, roadmap ni conocimiento. La revisión visual independiente del PDF sigue siendo una puerta posterior y este veredicto no la sustituye.
