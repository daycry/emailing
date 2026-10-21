# Infraestructura compartida del laboratorio

La [documentación detallada](../docs/README.md) desarrolla la [arquitectura](../docs/infraestructura/arquitectura.md), la [operación](../docs/infraestructura/operacion.md), la [integración de aplicaciones](../docs/infraestructura/integracion-aplicaciones.md), los [respaldos y traslados](../docs/infraestructura/backup-migracion.md) y la [resolución de problemas](../docs/infraestructura/troubleshooting.md). Esta página conserva los comandos de inicio rápido.

Requiere Docker Desktop con contenedores Linux y Docker Compose. Es un laboratorio de un único host, sin alta disponibilidad. No instala ni modifica las aplicaciones existentes.

Desde la raíz del repositorio, en PowerShell:

```powershell
powershell -NoProfile -File infra/init.ps1
docker compose --env-file infra/.env -f infra/compose.yaml config --quiet
docker compose --env-file infra/.env -f infra/compose.yaml up -d --build --wait --wait-timeout 240
powershell -NoProfile -File infra/smoke.ps1
```

Cada ejecución normal de `smoke.ps1` crea nuevos trabajos y mensajes y comprueba las operaciones de escritura. Después de recrear contenedores, ejecuta `powershell -NoProfile -File infra/smoke.ps1 -VerifyPersistence` para comprobar los marcadores de la ejecución anterior. Los marcadores antiguos se conservan en los servicios; el script no borra datos.

`init.ps1` genera secretos aleatorios y conserva un `.env` existente. Consulta `infra/.env` con tu editor para las credenciales. No publiques ese archivo ni la salida completa de `docker compose config` o `docker inspect`.

| Servicio | Desde Windows/Laragon | Desde aplicaciones Docker en la red compartida |
|---|---|---|
| RabbitMQ AMQP | `127.0.0.1:5672` | `rabbitmq:5672` |
| RabbitMQ UI | http://127.0.0.1:15672 | `rabbitmq:15672` |
| Cronicle UI | http://127.0.0.1:3012 | `cronicle:3012` |
| MySQL | `127.0.0.1:3308` | `mysql:3306` |
| SMTP Mailpit | `127.0.0.1:11025` | `mailpit:1025` |
| Mailpit UI | http://127.0.0.1:18025 | `mailpit:8025` |

RabbitMQ usa `RABBITMQ_ADMIN_USER` y `RABBITMQ_ADMIN_PASSWORD`, vhost `emailing-lab`. Son credenciales administrativas del laboratorio; cada aplicación futura debe recibir su propio vhost y usuario con permisos específicos antes de conectarse. MySQL crea la base `emailing` y el usuario `emailing` con `MYSQL_EMAILING_PASSWORD`, separado de root. Cronicle crea `admin` con `CRONICLE_ADMIN_PASSWORD` una sola vez, antes de abrir el servidor. Después conserva los cambios de contraseña hechos desde su UI. Mailpit captura correos de prueba; no es un proveedor de entrega real.

Los puertos están publicados únicamente en localhost. MySQL 3308 y Mailpit 11025/18025 evitan conflictos observados con servicios existentes. Los límites de CPU y memoria son presupuestos iniciales del laboratorio, no resultados de un benchmark. Mailpit conserva como máximo 1000 mensajes y Cronicle caduca el historial de trabajos a los 30 días.

## Conectar otras aplicaciones

Solo este proyecto declara RabbitMQ, Cronicle, MySQL y Mailpit. En el Compose de cada aplicación declara la red existente:

```yaml
services:
  web:
    # image/build propios de la aplicación
    networks: [infra]
  worker:
    # image/build y comando propios del worker
    networks: [infra]
networks:
  infra:
    external: true
    name: emailing-lab
```

El ejemplo muestra la conexión de red; añade la imagen y el comando reales de cada aplicación. Arranca primero infraestructura. Cronicle programará llamadas HTTP autenticadas a las aplicaciones; no recibe el socket Docker ni controla directamente otros contenedores. Los workers pertenecen a sus aplicaciones y Docker mantiene sus procesos. No hay Supervisor.

## Operación

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml ps
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 cronicle
docker compose --env-file infra/.env -f infra/compose.yaml stop
docker compose --env-file infra/.env -f infra/compose.yaml start
# Recrear contenedores conservando los volúmenes:
docker compose --env-file infra/.env -f infra/compose.yaml up -d --force-recreate --wait --wait-timeout 240
```

Los volúmenes conservan MySQL, RabbitMQ, datos/logs/cola de Cronicle y mensajes de Mailpit. No utilices `down -v` si quieres conservarlos. Cambiar contraseñas en `.env` no cambia automáticamente cuentas ya creadas en volúmenes existentes; rótalas en cada servicio. Mantén la clave secreta de Cronicle al trasladar sus datos. El hostname fijo conserva la identidad de ambos servicios.

Cronicle se construye desde la release original [v0.9.137](https://github.com/jhuckaby/Cronicle/releases/tag/v0.9.137); la imagen local no es una imagen Docker oficial de Cronicle. Node está fijado por digest; las dependencias npm transitivas se resuelven durante el build. Para mover el laboratorio copia configuración, secretos y respaldos de volúmenes, y adapta los puertos según el host.
