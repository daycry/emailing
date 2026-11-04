# Imágenes nuevas para el navegador Docker

Se construyeron los targets `app` y `web` del Dockerfile revisado desde una
copia aislada de las561 fuentes públicas exactas de la regresión en curso.
Los hashes de entrada y las fuentes originales coincidieron antes/después.
La copia no incluye configuración privada, vendor/node_modules del host,
datos runtime ni metadata Git. Las imágenes usan etiquetas nuevas con UUID;
no se sustituyen las etiquetas de las aplicaciones existentes.

| Artefacto | Evidencia |
|---|---|
| PHP/FPM |Build nativo terminal exit0; imagen Linux/amd64, directorio `/var/www/emailing`, usuario explícito `www-data`. |
| Nginx |Build nativo terminal exit0; imagen Linux/amd64. |
| MySQL |ID local Linux/amd64 inspeccionado; no se reconstruye ni arranca el servidor. |
| Identidad |Los IDs de ambos iidfiles coinciden con la inspección por ID; etiqueta de artefacto UUID exacta en las dos imágenes propias. |
| Recursos |Se pidieron límites de memoria1g y CPU quota100000 mediante el CLI; no se atribuye medición independiente de esos límites. |

Los [recibos e IDs](builds.public.json) son una proyección del coordinador de
terminales nativos e inspecciones seleccionadas. Conservan también los digests
base observados. No se leen ni publican variables de entorno de las imágenes.
El primer lector de metadata falló por campos opcionales ausentes; se corrigió
el lector privado sin relajar los checks de identidad, usuario PHP o etiquetas.
Ese fallo no provocó repetir las construcciones ni cambiar el controlador.

La construcción en este contexto independiente puede solaparse con la suite:
no escribe assets de la aplicación del host ni utiliza sus bases, configura
el perfil o prepara fixtures. La conciliación del daemon de la regresión está
acotada a su propia etiqueta UUID, distinta de la de estos artefactos.
Se conserva la exclusión de SQL de pruebas y build del host en paralelo.

La puerta estricta de regresión completa anterior al controlador de navegador
permanece intacta. Antes de sus fixtures deberá verificar los305 archivos de
aplicación dentro de PHP; después, igualdad de manifest y assets públicos entre
PHP y Nginx. No se afirma igualdad binaria con el build del host. Las imágenes
preparadas no acreditan arranque, SQL, TLS, navegador, Google o clientes reales.
Perfil y T-12 continúan pendientes de E2E y QA.
