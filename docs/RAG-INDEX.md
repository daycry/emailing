# Emailing — índice de conocimiento para agentes

**Última actualización: 2026-10-21.** Este índice resume documentación técnica y enlaza fuentes; no sustituye el código ni los resultados de pruebas.

## Estado real y navegación

Fuente: [README de documentación](README.md), [roadmap](roadmap/README.md) e [informe QA de infraestructura](roadmap/2026-10-21-infra-laboratorio/testing/report.md).

La infraestructura está implementada y probada: cuatro servicios healthy, protocolos, persistencia tras recreación, conectividad desde otro contenedor y tres E2E aprobados. La plataforma de emailing Laravel/Livewire está en desarrollo. No atribuir a infraestructura los módulos futuros de Teammates, campañas, API, outbox o entrega SMTP externa.

## Topología y aislamiento

Fuente: [arquitectura](infraestructura/arquitectura.md), [Compose](../infra/compose.yaml) y [ADR-001](knowledge/adr/ADR-001-rabbitmq-centralizado.md).

Proyecto `emailing-infra`; bridge local `emailing-lab`; hostnames `lab-rabbitmq` y `lab-cronicle`. Un único host, sin HA ni Supervisor. Cada aplicación tendrá su propio Compose, workers y credenciales; se conectará mediante red externa. Cronicle no monta Docker socket. Vhosts aíslan permisos/topología, no recursos físicos.

## Versiones, salud y límites

Fuente: [servicios](infraestructura/servicios.md), [bootstrap](../infra/cronicle/bootstrap.js) y [healthcheck Cronicle](../infra/cronicle/health.js).

RabbitMQ `4.4.0-management`, límite efectivo de body consultado `16777216` bytes = 16 MiB. Cronicle local `0.9.137` sobre Node 22 fijado por digest; master único, foreground, usuario node, init mínimo. MySQL `8.4.11`; Mailpit `v1.27.4`. Cronicle conserva historial 30 días, logs de job de hasta 10 MiB y máximo de 10 jobs simultáneos. Mailpit conserva hasta 1000 mensajes. Logs Docker `json-file`: tres archivos de 10 MB. Límites CPU/memoria son configuración inicial, no benchmark.

## Puertos y credenciales

Fuente: [configuración](infraestructura/configuracion.md), [.env.example](../infra/.env.example) e [init.ps1](../infra/init.ps1).

| Servicio | Windows/Laragon | Red Docker |
|---|---|---|
| RabbitMQ AMQP | `127.0.0.1:5672` | `rabbitmq:5672` |
| RabbitMQ UI | `127.0.0.1:15672` | `rabbitmq:15672` |
| Cronicle | `127.0.0.1:3012` | `cronicle:3012` |
| MySQL | `127.0.0.1:3308` | `mysql:3306` |
| SMTP Mailpit | `127.0.0.1:11025` | `mailpit:1025` |
| Web Mailpit | `127.0.0.1:18025` | `mailpit:8025` |

Secretos en `infra/.env` ignorado, generados aleatoriamente y conservados si ya existe. No leer, imprimir ni copiar sus valores a documentación. RabbitMQ cuenta administrativa inicial por variables; MySQL root y usuario `emailing` separados; Cronicle `admin` inicial y marcador `.lab-initialized`. Editar `.env` no rota cuentas existentes. Conservar `CRONICLE_SECRET_KEY` al trasladar datos.

## Operación y pruebas

Fuente: [operación](infraestructura/operacion.md) y [smoke.ps1](../infra/smoke.ps1).

Arranque: init, `config --quiet`, `up -d --build --wait --wait-timeout 240`, smoke. Siempre pasar `--env-file infra/.env -f infra/compose.yaml`. Smoke normal crea marcadores y escrituras nuevos; RabbitMQ se prueba mediante Management HTTP, no mediante el adaptador AMQP de aplicación. `-VerifyPersistence` usa estado previo, comprueba datos anteriores y ejecuta otro job Cronicle. Los fixtures permanecen; la prueba no borra datos. Recreación sin borrar volúmenes probada. No repetir escrituras o recreaciones solo para leer documentación.

## Integración prevista de aplicaciones

Fuente: [integración](infraestructura/integracion-aplicaciones.md), [ADR-002](knowledge/adr/ADR-002-laravel-mysql-compose.md) y [diseño](roadmap/2026-10-21-plataforma-emailing/design.md).

Laravel/Livewire y MySQL elegidos. Compose de aplicación pendiente; no duplicará servicios comunes. Workers propios en primer plano, reconexión y autenticación con usuario/vhost dedicados todavía por aprovisionar. Cronicle programará acciones finitas por HTTP interno autenticado, endpoint pendiente; alias DNS de aplicación único. Un solo propietario por calendario. Aislar base de pruebas y preservar `emailing.lab_smoke`.

## Persistencia y recuperación

Fuente: [respaldos y traslado](infraestructura/backup-migracion.md).

Seis volúmenes `emailing-infra_{rabbitmq-data,mysql-data,cronicle-data,cronicle-logs,cronicle-queue,mailpit-data}`. Datos Linux gestionados por Docker Desktop, no carpetas del repositorio. Recetas de respaldo/restauración propuestas, aún no ensayadas. Copia fría requiere parar productores/consumidores de todas las aplicaciones y los servicios; no tar de volúmenes vivos. Definitions de RabbitMQ no incluyen mensajes. Conservar secretos, identidades y la imagen Cronicle; futura `APP_KEY` pertenece al respaldo de aplicación. No sobrescribir volúmenes operativos durante un ensayo.

## Incidencias conocidas

Fuente: [troubleshooting](infraestructura/troubleshooting.md).

Mailpit web usa 18025; 11025 es SMTP y falla si se abre como HTTP. `localhost` de un contenedor es el propio contenedor. Network external inexistente requiere arrancar infra. Contraseña UI Cronicle puede diferir del valor inicial. Un nombre nuevo de proyecto puede crear volúmenes vacíos. Healthy no valida permisos de aplicación ni entrega a Internet.

## Entregabilidad y riesgo de listas de bloqueo

Fuente: [entregabilidad](infraestructura/entregabilidad.md). Controles previstos, todavía no implementados ni probados en C-05/C-06. Mailpit solo captura pruebas; relay externo, IP real y proveedor pendientes. Propuesta inicial: relay gestionado, no MTA propio. En ese patrón el último salto hacia el MX sale de la IP del relay, no necesariamente de la IP de la aplicación. Ninguna IP dedicada, consulta DNSBL ni autenticación garantiza ausencia de bloqueo o llegada a inbox.

Diseño previsto: dominio/remitente autorizados, SPF/DKIM/DMARC y alineación; TLS y DNS/PTR del relay; consentimiento, bajas, supresiones de quejas/rebotes confirmados, cuotas por cuenta y agregadas por transporte, alertas/pausas y reintentos acotados. SMTP 250 es aceptación del relay. Feedback requiere mecanismo real del proveedor, autenticación y correlación/deduplicación; SMTP genérico puede no ofrecerlo. Mostrar cobertura y datos desconocidos sin inventar tasas 0 %. IP compartida implica reputación de otros emisores; dedicada exige mantenimiento y calentamiento, no mejora universal. No rotar IPs para eludir bloqueos.
