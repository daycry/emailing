# Qué hace y cómo persiste cada servicio

**Última actualización: 2026-10-21.** Configuración real en [compose.yaml](../../infra/compose.yaml); parámetros propios de Cronicle en [bootstrap.js](../../infra/cronicle/bootstrap.js).

## Versiones y recursos declarados

| Servicio | Imagen | Memoria máxima | CPU máxima |
|---|---|---|---|
| RabbitMQ | `rabbitmq:4.4.0-management` | `768m` | `1.0` |
| Cronicle | `emailing-lab/cronicle:0.9.137`, construcción local | `512m` | `1.0` |
| MySQL | `mysql:8.4.11` | `1g` | `1.0` |
| Mailpit | `axllent/mailpit:v1.27.4` | `256m` | `0.5` |

Son límites iniciales de configuración, no memoria reservada ni resultados de rendimiento. Los servicios comparten los recursos disponibles en Docker Desktop. Las imágenes de RabbitMQ, MySQL y Mailpit están fijadas por etiqueta; no están fijadas por digest.

Todos usan `restart: unless-stopped`. Una parada explícita se respeta; un estado `unhealthy` por sí solo no implica que Docker reinicie el contenedor.

## RabbitMQ: trabajos y administración

RabbitMQ recibe y distribuye mensajes AMQP. La variante Management incorpora UI y API HTTP.

| Uso | Desde el host | Dentro de Docker |
|---|---|---|
| Protocolo AMQP | `127.0.0.1:5672` | `rabbitmq:5672` |
| Management HTTP | `http://127.0.0.1:15672` | `http://rabbitmq:15672` |

La cuenta inicial utiliza `RABBITMQ_ADMIN_USER` y `RABBITMQ_ADMIN_PASSWORD`. El vhost inicial es `emailing-lab`. Los datos viven en `/var/lib/rabbitmq`, volumen `emailing-infra_rabbitmq-data`.

El healthcheck ejecuta `rabbitmq-diagnostics -q check_running`. Comprueba el nodo, pero no sustituye probar la autenticación y permisos de cada aplicación.

El smoke usa la API HTTP de Management para crear una cola durable `lab.smoke.<marcador>`, publicar un mensaje persistente y consumirlo con requeue. El mensaje queda disponible para comprobar persistencia. Esta prueba no ejecuta el adaptador AMQP de la futura aplicación. No se ha configurado una política general de TTL, longitud máxima o limpieza automática de estas colas.

El máximo efectivo del cuerpo de un mensaje, consultado en el broker el 2026-10-21, es `16777216` bytes: **16 MiB**. El repositorio no declara un `max_message_size` propio. Puedes consultar el valor efectivo sin mostrar credenciales:

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml exec -T rabbitmq rabbitmqctl eval 'application:get_env(rabbit, max_message_size).'
```

La comprobación devolvió `{ok,16777216}`. No constituye una prueba de rendimiento ni recomienda enviar cuerpos de ese tamaño. Acuerda tamaños de trabajo, límites de cola y mensajes fallidos por aplicación; los adjuntos grandes pueden referenciar almacenamiento externo.

## Cronicle: programación y tareas finitas

Cronicle ofrece su UI en <http://127.0.0.1:3012>. El usuario inicial es `admin`; su contraseña proviene de `CRONICLE_ADMIN_PASSWORD`.

La imagen es local. El [Dockerfile](../../infra/cronicle/Dockerfile) descarga la release original `v0.9.137`, instala dependencias npm de producción, construye los recursos y ejecuta como usuario `node`.

La base Node 22 Bookworm slim está fijada por digest en el Dockerfile. La release Cronicle está fijada por etiqueta, mientras que las dependencias npm transitivas se resuelven durante la construcción. Conservar la imagen construida ayuda a reproducir el mismo binario durante un traslado.

El comando de arranque ejecuta primero `lab-bootstrap.js` y después `exec node lib/main.js --foreground`. `init: true` incorpora un init mínimo para gestionar procesos hijos. No utiliza un demonio separado de Supervisor.

### Qué configura el bootstrap

| Parámetro | Valor actual | Efecto |
|---|---|---|
| `base_app_url` | `http://127.0.0.1:3012` | URL del laboratorio. Cambiar puerto o acceso remoto exige revisar este valor. |
| `master` | `true` | Declara el único servidor como master sin esperar elección de failover. |
| `foreground` | `true` | Mantiene el proceso principal en primer plano. |
| `smtp_hostname` / `smtp_port` | `mailpit` / `1025` | Notificaciones capturadas localmente. |
| `job_data_expire_days` | `30` | Retención configurada del historial de jobs. |
| `max_jobs` | `10` | Máximo global configurado de jobs simultáneos de Cronicle. |
| `job_log_max_size` | `10485760` | Límite configurado de 10 MiB por log de job. |
| `server_comm_use_hostnames` | `true` | Comunicación mediante identidad de hostname. |
| `web_direct_connect` | `false` | Evita conexión directa del navegador a otros nodos. |

El máximo de jobs de Cronicle no limita el número de consumidores RabbitMQ de otras aplicaciones. Son procesos y mecanismos distintos.

### Qué conserva al reiniciar

El bootstrap reescribe la configuración del contenedor con la clave `CRONICLE_SECRET_KEY`. En el primer arranque prepara storage, genera salt y hash bcrypt para `admin`, y crea `/opt/cronicle/data/.lab-initialized`.

Con ese marcador presente, no reemplaza la contraseña del usuario. Un cambio realizado desde la UI sobrevive a recreaciones. No elimines el marcador como método de rotación: pertenece al estado inicializado.

El [healthcheck](../../infra/cronicle/health.js) consulta `/api/app/config`, interpreta su envoltura JSON sin evaluarla y exige `code: 0`. También exige que `master_hostname` coincida con el hostname del contenedor. Una página HTTP que responda no basta para declarar salud.

| Directorio del contenedor | Volumen |
|---|---|
| `/opt/cronicle/data` | `emailing-infra_cronicle-data` |
| `/opt/cronicle/logs` | `emailing-infra_cronicle-logs` |
| `/opt/cronicle/queue` | `emailing-infra_cronicle-queue` |

Cronicle puede ejecutar jobs shell dentro de su propio contenedor. La integración prevista con aplicaciones utilizará HTTP interno autenticado para acciones finitas. No contiene el código de todas las aplicaciones ni acceso al socket Docker.

## MySQL: datos relacionales

MySQL escucha en `mysql:3306` dentro de Docker y `127.0.0.1:3308` desde Windows. El puerto externo 3308 evita los servicios MySQL/MariaDB que ya estaban ocupando otros puertos del laboratorio.

La inicialización crea la base `emailing` y el usuario `emailing` con `MYSQL_EMAILING_PASSWORD`. `root` utiliza otra contraseña, `MYSQL_ROOT_PASSWORD`. La aplicación debe utilizar su usuario asignado.

Los datos residen en `/var/lib/mysql`, volumen `emailing-infra_mysql-data`. El healthcheck ejecuta `SELECT 1` como usuario de aplicación sobre la base `emailing`; comprueba autenticación y acceso básico.

El smoke conserva una tabla `lab_smoke` con marcadores. Las futuras migraciones deben preservarla y no ejecutar un borrado global de la base compartida con estas pruebas.

## Mailpit: buzón de pruebas

Mailpit captura mensajes SMTP y los presenta en una web. El laboratorio no lo configura para entregar correos a destinatarios externos.

| Función | Desde Windows | Desde Docker |
|---|---|---|
| Recepción SMTP | `127.0.0.1:11025` | `mailpit:1025` |
| Web y API | `http://127.0.0.1:18025` | `http://mailpit:8025` |

No requiere usuario ni contraseña en esta configuración local. La base SQLite es `/data/mailpit.db`, dentro de `emailing-infra_mailpit-data`. `MP_MAX_MESSAGES=1000` limita la cantidad conservada; un marcador antiguo puede dejar de estar disponible al superar la retención.

El healthcheck ejecuta `/mailpit readyz`. Ver un mensaje en Mailpit demuestra recepción local, no entrega a Internet.

## Cómo persisten los datos de Docker

Los seis volúmenes son volúmenes nombrados, no carpetas montadas desde `C:\laragon\www\emailing`. En Docker Desktop viven en el almacenamiento Linux gestionado por Docker.

La ruta de montaje suele tener la forma `/var/lib/docker/volumes/<volumen>/_data`, dentro de ese entorno Linux. Consulta [respaldos](backup-migracion.md) para inspeccionar nombres y copiar los datos mediante Docker.

La rotación del driver `json-file` conserva hasta tres archivos de 10 MB por contenedor. Esta rotación limita stdout/stderr; no sustituye la retención de datos ni de logs propios almacenados en volúmenes.
