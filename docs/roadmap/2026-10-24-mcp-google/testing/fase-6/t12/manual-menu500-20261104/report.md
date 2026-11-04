# Manual: diagnóstico del menú500 y actualización PDF

El manual explica ahora el caso en que el login funciona, pero los módulos
devuelven500 por tablas ausentes. Incluye la señal `SQLSTATE[42S02]`, identificación
del destino, revisión/copia y comandos de migración no destructiva. Distingue
Laragon de Docker y la base operativa de la de pruebas. No hace necesario Google
ni un proveedor de correo para consultar los módulos.

Se actualizan Markdown, matriz documental, PDF y metadatos. La corrección local
de esquema está documentada en el [informe operativo](../operacion-local-schema-20261104/report.md).
Este incremento documental no cambia código, permisos ni las561 fuentes de la
regresión backend en curso.

## Comprobación del artefacto final

| Comprobación | Resultado |
|---|---|
| Formato |58 páginas A4,24 capítulos, ocho diagramas Mermaid vectoriales. |
| Texto y diagramas |928/928 bloques representados; cero etiquetas de diagrama ausentes. |
| Límites |Cero textos fuera de página o cuerpos por debajo del umbral del verificador. |
| Índice |Mapas de páginas de las pasadas3/4 idénticos; capítulo Fuentes en58. |
| Enlaces |26 enlaces documentales estables; ninguno al renderizador temporal. |
| Privacidad |Cero hallazgos del inspector de patrones sobre el PDF final; no acredita privacidad histórica. |

[Validación final](validation.json) y [mapa de capítulos](toc-pages.json).
El [PDF publicado](../../../../../../manual/guia-completa.pdf) conserva el SHA256
`f5fee71a532bd6868a34bc401d27f6e2d409b212faf38ba82585c682773d11c8`;
su [fuente](../../../../../../manual/guia-completa.md),
`69e033036dff7caec52c08f2723ea81eaa02ac22a43b3b98d4bfb4ad08033563`.
Los hashes del estilo y los resultados se conservan en
[verificación](../../../../../../manual/verificacion.json).

## Revisión visual del coordinador

Se renderizaron todas las páginas de la revisión final y se inspeccionaron sus
cuatro hojas de contacto, que cubren1–58. Se abrieron individualmente las
páginas2,54,57 y58 de esa revisión; la55 se había abierto antes del ajuste CSS
limitado al capítulo de fuentes. Se comprueban índice, nueva receta, tablas,
diagramas y cierre; no se observaron recortes, solapes o texto ilegible en esa
revisión. El capítulo final empieza en página propia para evitar un párrafo
aislado. No se atribuye revisión individual ampliada de las58 páginas.

La exportación utiliza las dependencias existentes, sin instalación, cambios de
lockfiles o acceso externo durante render. Las cuatro pasadas y sus resultados
se conservan en el área privada de construcción. El primer índice cambió al
mejorar la maquetación; sólo las dos pasadas finales acreditan estabilidad.

No es QA independiente ni ejecución de una instalación desde cero. La reparación
de una base local existente tampoco acredita restauración, envío real, Google o
clientes externos. T-12/T-19/T-20 y los manuales humanos mantienen sus puertas.
