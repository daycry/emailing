# T07 — Lente C — intento documental 2 de 3

Fecha: 2026-10-31. Contexto fresco. Revisor genérico heredado: el agente nativo reviewer/Opus no estaba disponible. Se aplicó el prompt literal de la lente C de `adversarial-review`.

## Veredicto

**Sin hallazgos de seguridad introducidos o reabiertos por las correcciones documentales T07.**

Critical: 0. Important: 0. Minor: 0. No hay un escenario de explotación concreto que sustente un hallazgo CWE en este delta.

El intento C1 también concluyó sin hallazgos. Se revisaron los efectos nuevos de las correcciones A1-01, A1-02 y A1-03/B1; no se reabrieron aspectos aprobados ni el runtime T01–T06.

## Evidencia del delta

| Frontera | Lectura y resultado |
|---|---|
| Resolución de referencias documentales | `docs/manual/exportar.mjs:54`–78 recorre enlaces del Markdown propio. Para referencias relativas aplica URL normalization, exige el origen sintético esperado, resuelve la ruta dentro del proyecto, excluye directorios y nombres protegidos y consulta `stat` antes de construir el enlace HTTPS al repositorio. No lee el contenido del destino ni lo devuelve por HTTP. No se introduce una lectura arbitraria de secretos o un endpoint remoto. |
| SSRF y recursos del renderer | La conversión nueva produce atributos `href`; no navega esos enlaces ni descarga sus destinos. `docs/manual/exportar.mjs:84`–105 mantiene GET sobre loopback y el servidor limitado a los recursos Mermaid de su dist, con comprobación de ruta real. El navegador bloquea peticiones ajenas a su origen y service workers. Los nuevos enlaces GitHub no amplían esa admisión de red. |
| Inyección HTML/plantilla | `docs/manual/exportar.mjs:33`–35 mantiene escape de metadatos y HTML Markdown deshabilitado; `:90` conserva Mermaid strict y sin HTML labels; `:132` rechaza imágenes Markdown. La reescritura utiliza atributos de tokens renderizados por MarkdownIt, sin concatenar el href en una plantilla HTML propia. No aparece un origen atacante web/MCP en este CLI local de autoría. |
| Verificador de enlaces | `docs/manual/verificar.py:47` y `:74`–79 extraen las URI del PDF, comparan el origen temporal y la presencia de las referencias estables del recibo. No hacen solicitudes a esas URI, ejecutan su contenido ni introducen deserialización ejecutable. |
| Instalación Laragon corregida | `docs/manual/guia-completa.md:380`–454 incorpora selección de PHP, terminal/PATH, extensiones, Composer/Node y comprobación del lock. No introduce una receta de exposición pública del repositorio, bypass de requisitos o desactivación de TLS. Las herramientas y comandos son del operador local. |
| Opciones documentadas tras A1-02 | `docs/manual/guia-completa.md:678` explica Recordarme y el cierre de sesión en equipo compartido; `:1043`–1071 distingue conservación/borrado de credenciales SMTP genéricas y perfiles; `:1907`–1929 explica memoria y rotación de logs. Son aclaraciones de controles existentes; no amplían autorización ni publican credenciales. |

Las referencias al repositorio requieren conexión y acceso, como declara `docs/manual/README.md:66` y siguientes. No se interpreta su construcción como una publicación de archivos locales o una comprobación de disponibilidad remota.

## Identidad y límites

Se contrastó `scope-review2.json`: 343 archivos en el alcance declarado, ninguno fuera de alcance ni criterio alterado. Se leyeron los informes previos A/B/C/D, el exportador y verificador actuales, las secciones corregidas del manual, el README de autoría y `docs/manual/verificacion.json`.

Los hashes recalculados de los dos artefactos públicos coinciden con la instantánea encargada:

- Markdown: `0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe`.
- PDF: `330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7`.

El recibo canónico declara 57 páginas, 24 capítulos, 8 diagramas, 919/919 bloques, 26 referencias estables y cero enlaces al renderer. Son evidencias aportadas; esta lente no ejecutó generador, verificador, render, instalación, suites, SQL ni red. No se leyeron ni calcularon hashes de entornos privados, claves o artefactos prohibidos. Única escritura: este informe. No sustituye la QA visual independiente pendiente ni constituye una auditoría completa.
