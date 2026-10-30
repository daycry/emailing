# QA independiente de T11

[Informe](report/report.md) y [PDF](report/report.pdf): una tanda original de
Playwright,9/9 correctos, sin fallos, flaky, omitidos ni interrumpidos. El
[JSON nativo](raw/results.json) se conserva byte a byte, al igual que las19
capturas originales y los recibos/proyecciones publicados.
El Markdown original conserva su línea vacía final; `.gitattributes` limita
la excepción de ese aviso de whitespace a dicho informe, sin alterar sus bytes.

Esta selección contiene56 archivos originales del handoff QA. El manifiesto
`final-hashes.json` registra67 artefactos de origen; sus12 PNG de previsualización
del PDF se conservan sólo en el área privada de inspección. No hacen falta para
leer o volver a renderizar el PDF publicado. Una de esas previsualizaciones
produjo coincidencias de la expresión de identificadores sobre bytes comprimidos
IDAT; se verificó el contexto y no se modificó ninguna regla del checker.
La selección original de QA y sus flags `published: false` documentan el momento
del handoff, antes de esta publicación; no cambian los resultados funcionales.

Root verificó los67 hashes de origen, el recibo terminal, las448 fuentes, los
nueve IDs/resultados nativos y la limpieza mediante sus proyecciones seguras.
También revisó las cinco hojas de capturas y las tres hojas de las12 páginas PDF,
ampliando las páginas10/11 para descartar recortes de la miniatura. No se leen ni
publican entornos, solicitudes privadas, snapshots SQL o cuerpos de settlements.

Las comprobaciones de privacidad se limitan a los artefactos seleccionados y al
árbol actual; no certifican todo el historial Git. Google y los clientes nativos
reales, manuales del destino y regresión global mantienen sus puertas T12–T14.
