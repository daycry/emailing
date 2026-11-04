# Controlador de navegador Docker: cualificación local

Fecha:4 de noviembre de2026. Rama:`feature/t12-browser-docker`.

El controlador de ejecución está implementado. Integra configuración sintética,
reserva de puertos, adquisición atribuida, arranque, salud, procedencia de
imágenes, fixtures, reporter Playwright y una segunda fase de limpieza después
de cerrar el árbol nativo. No se ha ejecutado el ensayo de navegador Docker.

| Comprobación | Resultado y alcance |
|---|---|
| Unittest |49 casos Python locales, cero fallos y errores. |
| Frontera PHP |44 comprobaciones puras, sin Laravel/configuración/SQL. |
| Barrera y ledger |15 checks de filesystem reales, sin Laravel/SQL/Docker; lectores compartidos, exclusión, petición tardía denegada y fusión de IDs. |
| Node |12 casos: transporte simulado y generación de configuraciones sintéticas temporales. Los cuerpos de claves/contraseñas no se leen ni publican. |
| Sintaxis/Pint |6 PHP,6 módulos Node y Pint sobre6 PHP, exit0. |
| Contención nativa real |Sonda de entrada con stdin GATE, exit0; sin SQL/Docker. |
| Captura de comandos real |Proceso fijo bajo el supervisor nativo; stdout binario esperado y stderr vacío. Sólo archivos propios nuevos; sin SQL/Docker. |
| Casos E2E |9 títulos originales y sus aserciones conservados. Los49 casos locales no acreditan ejecución de esos nueve recorridos. |
| Alcance/fuentes |Fuentes locales estables; herramientas de ledger/alcance oficiales en verde, sin criterios alterados. |

Las44 comprobaciones PHP,15 checks de filesystem y12 casos Node están anidados en los49 tests Python;
no se suman otra vez. [qualification.public.json](qualification.public.json)
registra25 hashes de fuentes, códigos y cantidades. Las salidas privadas y los
archivos de configuración generados permanecen fuera de la evidencia pública.

La primera cualificación de47 casos se conserva en
[qualification-first-47.public.json](qualification-first-47.public.json). Después
se añadió la prueba del presupuesto de limpieza y se corrigió su límite global;
sólo cambian controller y su test. La cualificación de48 casos anterior a la
barrera también se conserva en
[qualification-before-barrier-48.public.json](qualification-before-barrier-48.public.json).
La revisión vigente es la de49 casos y añade la barrera compartida/exclusiva.
La cualificación de49 casos con11 checks anterior a la fusión durable del ledger
se conserva en [qualification-before-ledger-49.public.json](qualification-before-ledger-49.public.json).

## Fallos observados y corregidos

- Un resultado con campos inesperados se rechazaba, pero ya se había asignado
  al objeto que se publicaría. El nuevo test falló1/1 antes de corregir. Ahora
  se valida antes de asignar tanto el resultado de ejecución como el de limpieza.
- El plazo global inicial de limpieza era menor que la suma máxima de sus
  comandos declarados. El test virtual falló1/1 con600s frente a810s requeridos.
  La fase dispone de1200s y su helper de180s; los timeouts de casos E2E permanecen
  en60000ms. La selección, asserts, reintentos y umbrales del producto no cambian.

[controller-red-green.public.json](controller-red-green.public.json) es una
proyección explícita del coordinador de los resultados nativos observados.
No contiene salidas SQL, DTO privados ni cuerpos de autenticación.

## Qué prueban las imágenes y la limpieza

Antes de bootstrap de fixtures se comparará el código público con la imagen PHP.
Antes de Playwright se comprobarán también el manifest Vite y los assets
referenciados entre PHP y Nginx. Estas comparaciones se han probado con respuestas
simuladas; no se han ejecutado contra imágenes reales en esta cualificación.

Las pruebas de limpieza incluyen fallo individual y de todas las paradas,
identidades/imágenes/etiquetas ajenas, red adicional, SQL helper fallido,
journal corrupto, falta de claim y arranque remoto sin confirmación. Un conjunto
vacío observado no convierte una operación remota pendiente en limpieza válida.

La barrera protege la ventana de FPM que la parada del navegador no cierra:
HTTP y CLI de reloj/lectura toman leases compartidas antes de bootstrap. La
limpieza espera con la exclusiva, deshabilita el marcador y conserva exclusión
hasta salir. La entrada tardía se rechaza antes de Laravel. Las peticiones
normales siguen siendo concurrentes. Su filesystem se ha probado en este host;
el recorrido real FPM Linux continúa pendiente.

El ledger de IDs se fusiona bajo un lock separado y se reemplaza de forma
durable antes de enviar la respuesta Docker. El cleanup comprueba ausencia de
intentos/códigos/ventanas registrados, además de usuarios/clientes. No se
presenta una respuesta HTTP exitosa si esa atribución no pudo persistirse.

La [guía operativa](../../../../../../desarrollo/navegador-google-docker.md)
explica comandos y límites. La [revisión3 A+B+C+D](../browser-review-3/receipt.public.json)
terminó sin gaps. El coordinador integra los25 archivos de código con sus
hashes exactos y conserva la asociación de conocimiento del ledger original.
Las49 pruebas locales pasan también desde el checkout integrado, con561
fuentes inventariadas. Faltan regresión completa de ese checkout exacto, ejecución real de nueve casos por
perfil, estabilidad prevista y QA independiente. No hay aceptación Docker,
Google real, clientes nativos ni cierre de T-12.

## Regresión original separada

El checkout original terminó su suite completa con623 casos y13739 aserciones,
cero fallos/errores/skips,538 fuentes iguales y limpieza nativa/daemon verificada.
Su build Vite terminó con código0. Son pruebas del código original: esa suite
no contenía el arnés nuevo y no sirve para saltar su prerrequisito de fuentes.
