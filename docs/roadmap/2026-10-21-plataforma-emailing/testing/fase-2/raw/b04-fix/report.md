# B-04: aislamiento de limpiezas independientes

La función real `cleanup` de `specs/helpers.mjs` intenta ahora todas las limpiezas aunque una falle. Conserva cada objeto de error original, incluidos los de `context.close`, y rechaza al llamador con un `AggregateError` después de intentar temporales, archivos propios, precleanup y fixtures SQL. Un fallo de limpieza sigue siendo un fallo del test.

## Evidencia RED y GREEN

La regresión evalúa el módulo completo `helpers.mjs` con `vm.SourceTextModule`. Sustituye únicamente sus importaciones con efectos externos; ejecuta las funciones reales `cleanup`, `closeContext`, `resource` y `command`. Los stubs de procesos rechazan cualquier llamada inesperada. No se accede a DB, servicios, fixtures ni browser.

El mismo test, sin cambios entre tandas, exige el orden completo de llamadas y la identidad de todos los objetos `Error` originales. Sus siete escenarios son: éxito, hash temporal rechazado, cierre de contexto rechazado, archivos propios rechazados, precleanup rechazado, fixtures rechazados y seis fallos simultáneos.

- [RED](red.json): exit 1, 7 escenarios, 5 fallos. El error `Registered temporary content changed` corta las limpiezas posteriores; los errores de contexto se sustituyen o pierden.
- [GREEN](green.json): exit 0, 7/7. Todas las limpiezas se intentan y las seis causas simultáneas llegan al llamador.
- [Hashes antes](red-exit.json) y [después](green-exit.json): el test conserva SHA256 `08ec0cef409cb60d13421c74178f39e5f7c9e8d51b45d369730d027a50dc39f7`.

Se preservan stdout originales y preimagen exacta en `raw/artifacts/b04-fix/`, fuera de publicación. Node utilizado: v22.22.0; `--experimental-vm-modules` se limita a esta regresión focal. El archivo nuevo está en la raíz de fase-2, fuera de `testDir: './specs'`; no añade casos a Playwright.

## Alcance y verificación

Único archivo existente cambiado: `specs/helpers.mjs`, sólo la función `cleanup`; CRLF conservado. Único ejecutable nuevo: `b04-cleanup-test.mjs`. PHP, registro de temporales, configuración, app e infraestructura permanecen iguales. El manifiesto B-03 original conserva su hash y las otras 35 fuentes coinciden byte por byte.

[Validación final](validation.json): 2/2 comprobaciones de sintaxis y control de datos públicos exit 0. [Manifiesto nuevo](source-sha256.json): 37 fuentes; [diff existente](incremental.patch). El primer validador de evidencia se detuvo por comparar separadores POSIX con claves Windows del manifiesto B-03; [recibo conservado](validation-attempt-1.json). Se corrigió únicamente ese comando puntual, sin cambiar ni repetir la regresión RED/GREEN.

QA1 conserva 8 aprobadas y 12 fallidas; QA2 no se ha ejecutado. B-04 queda para revisión fresca del coordinador. No se han iniciado procesos persistentes ni creado fixtures para esta reparación.
