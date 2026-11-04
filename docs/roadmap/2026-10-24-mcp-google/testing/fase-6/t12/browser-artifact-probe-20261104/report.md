# Verificación de bytes de las imágenes, sin aplicación ni SQL

La sonda aislada verifica305 archivos públicos de aplicación dentro de la imagen
PHP contra los hashes exactos del inventario561 de la suite en curso. También
compara el manifest Vite y sus dos assets referenciados entre PHP y la imagen
web:los tres coinciden. Usa las funciones existentes
`scripts/browser_lab/qualification.py::verify_images`, sin cambiar sus checks.

Los recibos son una proyección del coordinador de esa ejecución, no una
cualificación completa del perfil:

- [Resultado](artifact-result.public.json):ejecución nativa exit0, fuentes561
  estables y verificación de artefacto satisfactoria.
- [Archivos de aplicación](application-provenance.public.json):305 coincidencias,
  antes de cualquier bootstrap de fixtures; ninguno se realizó.
- [Assets](image-provenance.public.json):manifest y dos archivos iguales.
- [Limpieza](cleanup.public.json):dos contenedores propios retirados y ausencia
  posterior verificada por sus nombres exactos.

## Aislamiento y atribución

Se adquirió ausencia de dos nombres nuevos con UUID, distintos de los del
controlador y de la regresión. Se verificaron IDs de imagen, etiquetas propias,
IDs/nombres de contenedor, red `none`, raíz de sólo lectura y cero montajes. Las
imágenes no declaran volúmenes que pudieran crear almacenamiento anónimo.

Los procesos fueron únicamente esperas acotadas, `php -n` y herramientas de
hash. Se reemplazó el entrypoint de las imágenes; no arrancaron FPM o Nginx. PHP
ejecutó como33:33 y el contenedor web como65534:65534, sin capacidades y con
límites solicitados de96m,0.25 CPU y16 procesos. No se publicaron puertos, leyó
configuración privada ni cargó autoload/Laravel. No hubo consultas SQL o correo.

El hijo de verificación aguardó la puerta stdin y se ejecutó bajo el supervisor
nativo existente con presupuesto120s. Su Job se cerró antes de la limpieza
atribuida. Los errores de la ejecución permanecen primarios; la ausencia global
de contenedores ajenos no se inventaría ni comprueba. No se purgaron imágenes,
volúmenes, redes o recursos de otras aplicaciones.

Una primera invocación del helper privado no pudo importar el inventario; falló
antes de crear la carpeta de ejecución o llamar a Docker. Se corrigió sólo su
ruta de importación. La sonda real posterior fue única y pasó; no cambió código
cualificado ni se reinició la regresión.

## Qué sigue pendiente

Se acredita procedencia de archivos del artefacto, no funcionamiento de la
aplicación. Las [imágenes construidas](../browser-images-20261104/report.md)
conservan sus IDs. El controlador deberá repetir sus verificaciones dentro de
su propio ciclo y superar primero el prerrequisito completo sobre las561
fuentes. No se sustituyó ese gate ni se adoptó el verde antiguo de538 fuentes.

Faltan arranque del perfil, identidad SQL real, fixtures, nueve escenarios por
perfil, estabilidad y QA independiente. No se acredita TLS, Google, clientes
nativos, instalación de destino o restauración global. T-12 sigue en progreso.
