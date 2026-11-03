# T-19: comprobaciones preliminares del protocolo RabbitMQ

Fecha: 2026-11-03. **Resultado preliminar favorable; T-19 sigue en progreso.**
Estas comprobaciones del coordinador preparan la segunda aplicación mínima de
protocolo y sus identidades. No son QA independiente, un ensayo de carga ni una
restauración. No ejecutan el negocio de Emailing.

## Entorno y aislamiento

Se utilizó el RabbitMQ del laboratorio definido en `infra/compose.yaml`, imagen
`rabbitmq:4.4.0-management`, mediante AMQP en loopback. Cada ejecución creó
usuarios y vhosts temporales con un UUID nuevo. Sus permisos se limitaron al
prefijo de su propia topología, sin etiquetas administrativas. Antes de crear
recursos se comprobó que esos nombres no existían; después se verificó su
eliminación. Ningún servicio compartido se reinició ni se purgaron colas ajenas.

Los procesos PHP no arrancaron Laravel ni abrieron MySQL. Python supervisó sus
descendientes mediante un Job Object propio en Windows, con un límite de 300
segundos para cada prueba. Las credenciales aleatorias se transmitieron a PHP
por stdin y no aparecen en las proyecciones publicadas.

## Publicación confirmada y efecto único

La prueba `wire` registró un consumidor antes de publicar. Envió tres mensajes
persistentes y obtuvo tres confirmaciones del publisher: secuencias `1, 1, 2`,
con un cuerpo idéntico en la repetición de `1`. Cada cuerpo tenía 2 KiB.

| Comprobación observada | Resultado |
| --- | ---: |
| Publicaciones / confirmaciones | 3 / 3 |
| Efectos sintéticos únicos persistidos | 2 |
| Repeticiones que no crearon otro efecto | 1 |
| Frames ACK enviados por el consumidor | 3 |
| Mensajes rechazados | 0 |
| Mensajes listos / consumidores tras cerrar el consumidor | 0 / 0 |
| Acceso a `/api/overview` con la credencial de aplicación | HTTP 401 |
| Usuario y vhost propios eliminados | Sí, ausencia verificada |

El consumidor persistió el efecto antes de emitir el ACK. La observación pasiva
de la cola se hizo después de cerrar su conexión. Los ACK del consumidor y las
confirmaciones del publisher son observaciones distintas. Esta prueba no demuestra
durabilidad frente a pérdida de energía del host, cortes del broker ni recuperación
del archivo de efectos en otra máquina.

Los [eventos del consumidor](wire/consumer-events.jsonl), los
[eventos del publisher](wire/publisher-events.jsonl), la
[observación final de la cola](wire/queue-observation.json) y el
[resultado](wire/result.public.json) se conservan como copias literales.

## Dos identidades y denegación cruzada

La prueba `acl` creó dos vhosts y dos usuarios temporales, A y B. Cada identidad
configuró su topología, publicó con confirmación y consumió su propio mensaje
como control positivo. Después intentó publicar, consumir y configurar en el
vhost contrario.

| Identidad | Control positivo propio | Publicar ajeno | Consumir ajeno | Configurar ajeno |
| --- | --- | --- | --- | --- |
| A | Correcto | Denegado, 530 | Denegado, 530 | Denegado, 530 |
| B | Correcto | Denegado, 530 | Denegado, 530 | Denegado, 530 |

Las seis denegaciones ocurrieron al intentar entrar en el vhost ajeno. No se
presentan como pruebas separadas de permisos sobre recursos dentro de un vhost
compartido. El control positivo permite distinguir la denegación de credenciales
incorrectas o de un broker indisponible. Los dos usuarios y vhosts se eliminaron,
y su ausencia se volvió a comprobar desde el proceso supervisor.

Véanse el [resultado ACL](acl/result.public.json), las observaciones
[A](acl/application-a-observed.json) y [B](acl/application-b-observed.json), y el
[recibo del supervisor](acl/bounded.public.json).

## Procedencia y límites de la evidencia

El [índice](index.json) contiene SHA-256, tamaño y nombres de las 14 copias
literales de evidencia. Una regla Git local evita convertir sus finales de línea.
Antes de copiarlas se aplicaron las reglas públicas de patrones únicamente a esa
selección explícita: cero hallazgos. No se leyeron configuraciones privadas para
esa comprobación.

El recibo de fuentes `wire` se capturó después de observar su terminación; sus
fuentes no se habían modificado desde el lanzamiento. El supervisor `acl` sí
capturó las fuentes antes de ejecutar y comprobó sus hashes al terminar. Las
copias de esos prototipos permanecen privadas en `storage/`: aún deben promoverse
a rutas ejecutables del proyecto, revisarse y verificarse antes de constituir
una prueba pública reproducible. Estos recibos no sustituyen ese trabajo.

Los dos primeros intentos de preparación se detuvieron al comprobar la ausencia
de namespaces, antes de crear recursos. Se corrigió el oráculo: listar colas no
acreditaba la inexistencia de un vhost; la salida JSON de usuarios identifica el
usuario mediante `user`, mientras que los vhosts utilizan `name`. Sus resultados
fallidos se conservaron privadamente y no se contabilizan como pruebas aprobadas.

Siguen pendientes las distribuciones reales 2/3 y 4/6 workers, los parámetros y
umbrales previos a la carga, recursos y latencias, los fallos y la restauración
coherente de los seis volúmenes y archivos privados de aplicación. También faltan
la reconciliación de envíos posteriores al snapshot, la conservación de
revocaciones y la integración aceptada de MCP/Google. Google OAuth todavía no
está configurado, según confirmación del usuario de esta fecha. Ningún criterio
global ni manual humano queda aprobado por este informe.
