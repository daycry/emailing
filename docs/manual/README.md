# Manual completo: lectura y actualización

La [guía completa en Markdown](guia-completa.md) es la fuente del manual de uso,
instalación desde cero y administración. El [PDF](guia-completa.pdf) contiene el
mismo contenido, con índice paginado y diagramas renderizados. La
[matriz documental](cobertura-documentacion.md) relaciona funciones, interfaces y
configuración con las secciones que las explican.

## Leer y utilizar

Para utilizar la aplicación, lee la guía o el PDF. No necesitas instalar estas
herramientas de edición. En Markdown los bloques `mermaid` se ven como diagramas
en lectores compatibles, incluido GitHub; el PDF ya lleva los gráficos vectoriales.
Los comandos del manual se distinguen por su terminal: PowerShell, Bash o ejemplos
de HTTP. Copia el bloque completo; sus saltos de línea pueden ser significativos.

## Preparar las herramientas de edición

Este procedimiento es para quien mantiene el documento. Requiere Node 22 o
posterior, Chrome instalado, Python con PyMuPDF y, para las capturas, Pillow.
`npm ci` en la raíz instala las dependencias de desarrollo del proyecto, incluido
Playwright; esto es independiente de construir la aplicación solo con Docker.

Las dependencias del exportador se instalan en una carpeta externa. No se añaden
a las dependencias de la aplicación ni a sus lockfiles. Ejemplo en PowerShell:

```powershell
$docsTools = Join-Path $env:USERPROFILE '.claude\tool-cache\emailing-manual'
npm install --prefix $docsTools --save-exact --ignore-scripts --no-audit --no-fund markdown-it@15.0.3 mermaid@12.2.0
```

Utiliza un entorno Python aislado de tu elección e instala `PyMuPDF` y `Pillow`
en ese entorno. Comprueba que `python` y `node` correspondan a las herramientas
elegidas. El exportador usa el canal Chrome instalado de Playwright; puedes
seleccionar otro ejecutable Chrome con `--chrome`.

## Generar, numerar el índice y verificar

Desde la raíz del repositorio, genera primero una copia de trabajo. La carpeta
`storage/manual-build` contiene intermedios, no los datos operativos de la aplicación.

```powershell
node docs/manual/exportar.mjs --tools-dir $docsTools --output-dir storage/manual-build/pass-1
python docs/manual/verificar.py --input storage/manual-build/pass-1
node docs/manual/exportar.mjs --tools-dir $docsTools --output-dir storage/manual-build/pass-2 --toc-pages storage/manual-build/pass-1/toc-pages.json
python docs/manual/verificar.py --input storage/manual-build/pass-2 --render
```

La primera exportación obtiene el número de página de cada capítulo mediante los
marcadores del propio PDF. La segunda imprime esos números en el índice. Comprueba
que los marcadores de la segunda exportación conserven la misma numeración; si el
contenido o el estilo cambian la paginación, repite usando su nuevo `toc-pages.json`.

El exportador solo carga Mermaid desde un servidor temporal en loopback. No
solicita recursos externos durante el render. Los diagramas generados se conservan
como SVG en `diagramas/`; el HTML, las comprobaciones y el PDF de trabajo quedan
en la carpeta indicada por `--output-dir`, dentro del proyecto. Sin esa opción
utiliza `storage/manual-build`.

Los enlaces entre capítulos se convierten en destinos internos del PDF. Las
referencias a otros archivos públicos se convierten en enlaces HTTPS a la rama
documentada del repositorio; abrir esas fuentes requiere conexión y acceso al
repositorio. Ningún enlace de referencia depende del servidor temporal que se
cierra al terminar la exportación. El contenido necesario para usar la herramienta
permanece explicado en el propio manual.

`verificar.py` compara el hash de la fuente y del PDF, los capítulos, los bloques
de texto, las etiquetas de diagramas, los enlaces estables, el tamaño A4 y los límites de texto. Su
resultado `PASS` no sustituye la revisión visual: abre todas las hojas de contacto
y las páginas PNG en `pass-2/previews`, revisa tablas, diagramas, comandos, índice
y pies. Corrige antes de publicar si hay recortes, solapes o texto ilegible.

Una vez revisado, copia únicamente el PDF final a su ruta de entrega:

```powershell
Copy-Item storage/manual-build/pass-2/guia-completa.pdf docs/manual/guia-completa.pdf
```

Si cambia la fuente durante una exportación, el exportador se detiene. Una
modificación posterior de Markdown requiere regenerar y volver a verificar el
PDF. Mantén también la matriz documental y los índices del proyecto actualizados.

Motor y formato: [Mermaid](https://mermaid.ai/open-source/config/usage.html),
Markdown y Chromium. El HTML/CSS sigue el flujo de la skill `custom-agents:to-pdf`;
la ejecución utiliza Playwright y el Chrome ya instalado. El estilo está en
[tema.css](tema.css); la conversión, en [exportar.mjs](exportar.mjs), y la validación,
en [verificar.py](verificar.py).
