# Portabilidad de los archivos fijados por hash

**Corrección validada en índice y en el commit6ff7858, con revisión independiente A+B+C sin gaps.** No cambia el contenido de los contratos ni ninguno de sus ocho hashes publicados. T-10 sigue en progreso y el QA de navegador permanece pendiente.

## Fallo reproducido

`ContractArguments` exige el SHA-256 publicado del catálogo MCP. La copia de trabajo conserva CRLF y cumple ese hash; Git había normalizado su objeto a LF. Una carga real de `connection_info` con el archivo LF falla con `LogicException` de integridad y exit2. La misma clase, sin cambios, carga el archivo CRLF publicado con exit0. No se arrancaron Laravel ni base de datos para reproducirlo.

El aislamiento encuentra ocho entradas fijadas por hash: siete JSON con CRLF y la spec REST con LF. Un checkout LF incumplía los siete hashes JSON; la conversión CRLF incumplía el hash REST. Cambiar todos los archivos a un único final de línea o relajar el validador no conserva las identidades aprobadas.

La comprobación del objeto Git y de los filtros produjo primero dos tests con 22 subcasos fallidos. Después se añadió la verificación de atributos del origen seleccionado, para impedir que un atributo local sin publicar ocultara el fallo: la versión final de tres tests falla contra el HEAD anterior y pasa contra el índice corregido. Esa última reproducción contra HEAD se hizo después de preparar los atributos locales; no se presenta como TDD cronológico. [Originales y hashes de evidencia](evidence.json).

## Corrección y regresión

`.gitattributes` declara `-text` para cada uno de los ocho archivos y conserva sus finales de línea al almacenarlos en Git. El índice se renormaliza explícitamente sólo para esos archivos. Los siete JSON cambian de representación en Git, sin diferencias al interpretar JSON; la spec REST conserva sus bytes LF. Los bytes de la copia de trabajo permanecen intactos.

La regresión comprueba los hashes de los ocho objetos, sus atributos desde el commit o índice seleccionado, y los filtros `core.autocrlf=false`, `true` e `input`. Usa la versión 0.1.0 del manifiesto y contrasta el pin de ejecución del catálogo y el de la spec REST. No ejecuta scripts del contrato ni lee configuraciones privadas.

```powershell
# Antes del commit: comprobar exactamente el índice que se revisa.
python tests/packaging/test_pinned_contract_checkouts.py --source index -v
# Después del commit: comprobar los objetos y atributos publicados.
python tests/packaging/test_pinned_contract_checkouts.py -v
```

Resultado admitido del índice: tres tests, exit0. Git observado: 2.53.0.windows.2. Las comprobaciones de HEAD requieren soporte de `git check-attr --source`; no se cambia ninguna configuración Git global.

## Relación con el backend y límites

El [backend completo](../backend-2026-10-30.json) termina en una única tanda sin filtros: 485 casos, 13.537 aserciones, cero fallos/errores/omisiones y ningún aviso registrado, con PHP8.5.12. Sus415 fuentes conservan exactamente los mismos bytes después del arreglo de empaquetado. La captura posterior contiene416 entradas: sólo añade el test Python de empaquetado, que no se carga ni ejecuta desde PHP. Las dos capturas y sus ZIP/streams se admiten por separado; no se suman tandas ni se atribuyen los tests nuevos a la ejecución PHP anterior.

Dos sondas adicionales usan la clase real en un contenedor Linux readonly, sin red, usuario33:33 y la imagen fijada por digest. PHP observado:8.4.27. El catálogo LF anterior falla por integridad; el catálogo con sus bytes publicados carga correctamente. Ambos contenedores propios quedan retirados. Son sondas puras de la clase, sin Laravel/DB; no acreditan todavía el perfil Docker de aplicación ni los clientes reales.

El postflight de sólo lectura del backend verifica identidad SQL exclusiva, cero otras conexiones, transacción0 y una única tabla restante, la de migraciones. No se mutan datos en esa comprobación. Los originales se conservan privadamente; sólo se publica esta proyección sin credenciales. La situación del historial Git antiguo mantiene su seguimiento separado.

[Revisión independiente del empaquetado](../review-3/report.md). Los originales conservan el estado pendiente de HEAD y QA que existía al revisar.

Comprobación posterior real: commit `6ff7858e6e3ad76d617f4ef62c68ed6b1ad35f81`, tres tests sobre HEAD, exit0. Recibo y streams admitidos;416 fuentes sin drift, con415 originales idénticas. [Hashes del resultado](evidence.json). La QA de navegador continúa pendiente.
