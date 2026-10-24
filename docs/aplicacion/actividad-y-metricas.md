# Actividad y métricas de la cuenta

**Estado: componente T-14 revisado y con gates de código verdes.** Esta guía
describe el contrato de la pantalla y sus datos. La [revisión 15](../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/review-15.md)
conserva los resultados y límites; el [ledger del roadmap](../roadmap/2026-10-21-plataforma-emailing/tasks.md)
mantiene la trazabilidad de los fallos y correcciones. El QA de navegador
nativo/Docker y la aceptación humana siguen pendientes en T-16.

Actividad permite consultar las entregas lógicas de campañas, sus intentos y
los registros independientes de la cuenta activa. La fuente es la base SQL
local. **Aceptación SMTP, entrega al buzón y apertura son hechos distintos.**
El laboratorio mantiene log privado y Mailpit; el envío externo sigue deshabilitado.

## Acceso y filtros

Abre **Actividad** en el menú de la aplicación con una cuenta activa y el permiso
`activity.read`. Este permiso permite consultar detalle, exportar la selección
y descargar los archivos privados disponibles; no requiere `settings.read`.
La gestión del transporte y las acciones de campañas mantienen sus permisos
propios. Consulta [cuentas y Teammates](cuentas-y-equipo.md) para asignar accesos.

La selección inicial comprende hoy y los 29 días anteriores, en UTC. Puedes
filtrar por fechas, población, estado, destinatario o asunto, e ID de campaña
de la cuenta. El texto de búsqueda tiene un máximo de 200 caracteres. La lista
se pagina en grupos de 25; al cambiar filtros vuelve a la primera página.

Si un filtro es inválido, la pantalla muestra el error y oculta los resultados,
los contadores y el CSV hasta que lo corrijas. No interpreta una selección
inválida como cero actividad. Al corregirla vuelve a consultar con los permisos
vigentes de la cuenta.

Las fechas delimitan la **creación de la entrega lógica o del registro
independiente**, no la fecha del último reintento. Desde incluye las 00:00 del
primer día; Hasta incluye todo el último día y excluye las 00:00 del siguiente.
Las fechas y horas de esta pantalla son UTC, aunque el navegador use otra zona.
Una entrega creada antes de la ventana no entra en ella por haberse reintentado
hoy: amplía la ventana para encontrarla.

Un ID ajeno no permite consultar otra cuenta. Las actualizaciones, el detalle,
los filtros y las descargas revalidan el permiso y la cuenta. Tras cambiar de
cuenta, vuelve a abrir Actividad; una instancia abierta con otro contexto no
debe seguir actuando sobre él. Revocar la membresía o reducir permisos impide
las siguientes acciones autorizadas por esa membresía.

## Qué cuenta cada población

| Población | Unidad de recuento | Interpretación |
|---|---|---|
| Entregas lógicas de campañas | Una entrega por destinatario de la revisión autorizada | Incluye entregas pendientes o suprimidas sin ningún intento. Los reintentos conservan la misma entrega. |
| Pruebas explícitas | Un registro de prueba solicitado desde Plantillas o Configuración de correo | Describe pruebas de esa cuenta, no destinatarios únicos de una campaña ni conexiones SMTP demostradas. |
| Históricos de procedencia desconocida | Un registro conservado sin asociación reconstruible | Puede ser un intento anterior de una entrega ya contada en campañas. No equivale a otro email único. |

Los tres contadores permanecen separados incluso al seleccionar todas las
poblaciones. No los sumes como «emails enviados» ni como personas únicas. Tener
un actor registrado no convierte automáticamente un histórico en una prueba.

## Estados e intentos

| Estado de la pantalla | Qué significa |
|---|---|
| Pendiente | Trabajo durable aún no iniciado o en espera de su siguiente oportunidad. Una cuota o una pausa pueden mantenerlo pendiente. |
| Reservada | Un consumidor tiene una reserva vigente; todavía no acredita aceptación SMTP. |
| En proceso | El inicio está registrado; el resultado de transporte aún no es terminal. |
| Aceptado por SMTP | El relay aceptó el mensaje. No demuestra llegada al buzón ni lectura. |
| Archivo privado (log) | Se conservó un `.eml` privado mediante el modo log, sin conectar SMTP. |
| Fallido | Resultado local fallido. El detalle permite distinguir los registros de intento conservados. |
| Suprimido | La comprobación de supresión impidió continuar con ese destinatario. |
| Aceptación ambigua | No se pudo confirmar el resultado tras una posible aceptación. No se reenvía automáticamente desde este panel. |
| Bloqueado | La operación no está habilitada o no puede continuar con la configuración vigente; consulta el diagnóstico depurado. |

**Ver detalle e intentos** muestra la referencia, población, actor disponible,
campaña, remitente, estado y próxima disponibilidad UTC cuando exista. El detalle
puede seguir abierto al cambiar los filtros: conserva la referencia elegida y
vuelve a comprobar su cuenta. Ciérralo para dejar de mostrar esa selección.

En una entrega de campaña, los intentos iniciados pertenecen a esa entrega y
pueden tener generaciones sucesivas. Dos rechazos y una aceptación pueden dejar
una entrega aceptada con tres registros de intento: la entrega sigue contando
una sola vez. La lista de intentos se pagina de diez en diez y permite acceder
a todos los registros vinculados, no solo al último.

Una página sin intentos vinculados no permite concluir que el transporte no
empezó. Una entrega histórica puede conservar inicios o un resultado de
aceptación aunque sus registros de intento no se puedan asociar con certeza.
El contador de inicios y la lista de registros expresan información distinta.

Las pruebas y los históricos son registros independientes. Su existencia no
demuestra cuántas conexiones SMTP ocurrieron; una prueba también puede quedar
suprimida o bloqueada antes del transporte. La generación histórica desconocida
se muestra como tal, sin deducirla de la generación actual de una entrega.

El número de registros conservados se separa de los inicios acreditados. Una
prueba explícita suprimida o bloqueada antes de empezar puede acreditar cero
inicios; un histórico sin ese dato se muestra desconocido. Incluso un inicio
durable registrado en SQL no demuestra por sí solo que se abriera una conexión
SMTP: el modo log genera un archivo privado sin usar esa conexión.

Los diagnósticos usan mensajes admitidos por la aplicación. No muestran la
respuesta SMTP cruda, credenciales ni un mensaje de excepción arbitrario.
Un código desconocido conserva una indicación genérica para la operación autorizada.
Consulta [entrega y recuperación](entrega-y-recuperacion.md) para reservas,
reintentos acotados, cuotas y resultados ambiguos; Actividad no añade un atajo
para saltarse esos controles.

## Porcentajes, ventanas y alertas

Cada tarjeta calcula las aceptadas SMTP entre **todos los registros de esa
población que cumplen los filtros**. Numerador y denominador comparten ventana,
estado, búsqueda y campaña seleccionados. Son estados actuales de una cohorte
creada en esas fechas; no un recuento de transiciones ocurridas durante el día.

Por ejemplo, siete entregas actualmente aceptadas y tres pendientes producen
7/10 = 70 %. Filtrar solo Aceptado por SMTP produce 7/7 = 100 % por la propia
selección. Ninguno de esos porcentajes mide entrega al buzón. Sin registros,
la pantalla indica **sin muestra**, no una tasa de 0 %. Una muestra de un solo
registro tampoco permite concluir que el servicio sea fiable o tenga buena reputación.

Las incidencias locales señalan estados actuales Fallido, Bloqueado o Aceptación
ambigua. Las esperas por cuota o pausa se distinguen de un fallo final. Un
intento anterior fallido puede quedar en el detalle de una entrega que después
se aceptó; la tarjeta no representa una tasa histórica de errores por intento.

**Entrega final, aperturas, clics, rebotes, quejas y listas de bloqueo permanecen
desconocidos.** No hay proveedor de feedback integrado. La ausencia de esos
datos no significa cero incidencias ni reputación saludable. La pantalla no
acredita una IP fuera de listas de bloqueo, un SLA ni capacidad bajo carga.
La [guía de entregabilidad](../infraestructura/entregabilidad.md) distingue los
controles del laboratorio de la preparación necesaria para un relay externo.

## CSV y archivos privados

**Descargar CSV de esta selección** aplica los mismos filtros y conserva ventana
UTC y fuente de los datos. Exporta referencias, población, estado, diagnóstico
depurado y metadatos de la selección; no incluye cuerpos completos ni respuestas
SMTP crudas. Las celdas que podrían interpretarse como fórmulas se protegen
como texto: puede aparecer un apóstrofo inicial al leer el archivo directamente.

La exportación lee lotes de hasta 200 registros y vuelve a comprobar cuenta y
permiso antes de cada lote. Si cambian los permisos durante la descarga, puede
quedar un archivo parcial; no lo interpretes como una exportación completa.
Si el trabajo cambia mientras se descarga, las filas y sus estados también
pueden cambiar: el CSV no es un snapshot transaccional global ni promete
coincidir después con el total que viste en la pantalla.

Cuando un intento tiene un archivo disponible, el enlace de descarga vuelve
a comprobar `activity.read`, cuenta y pertenencia del registro. Los `.eml`
permanecen en almacenamiento privado, sin una URL pública al directorio. La
consulta del detalle no muestra su cuerpo como HTML del panel. Consulta
[plantillas y transporte](plantillas-y-transporte.md) para el contenido del log
y la diferencia entre archivo privado y mensaje capturado por Mailpit.

## Procedencia histórica y alcance de la validación

Los nuevos intentos de campaña conservan su entrega, generación y actor de
autorización junto al inicio durable. El actor no es la persona que está mirando
la pantalla. Si la identidad ya no está disponible, se muestra desconocida.

La migración histórica solo asocia el último mensaje señalado por una entrega
cuando la referencia es única y corresponde a la misma cuenta. No reconstruye
los intentos antiguos sin vínculo y no inventa su generación. Es normal que
convivan una entrega conocida y registros históricos desconocidos.

La comprobación focal es `php artisan test --filter=ActivityTest` sobre la base
de pruebas protegida. Coordina su ejecución con las demás suites que usan esa
base. Sus resultados, la suite completa y la revisión se registran en el ledger;
el QA de navegador y perfiles nativo/Docker de la fase permanece en T-16.
La integración Cronicle, API, carga entre aplicaciones y restauración conservan
sus tareas posteriores y no se acreditan mediante esta pantalla.
