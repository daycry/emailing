---
estado: implementada
fecha: 2026-10-21
plan: improvement-plan.md
test-plan: test-plan.md
---

# Infraestructura compartida para el laboratorio

El usuario autoriza «creamos el docker compose de infra y lo ejecutamos para probar si funciona». La autorización cubre esta prueba acotada. La aplicación de emailing, su stack y su presupuesto siguen pendientes.

El laboratorio usa un Compose independiente con RabbitMQ Management, Cronicle, MySQL y Mailpit. Las futuras aplicaciones compartirán la red estable `emailing-lab`, sin declarar instancias adicionales de estos servicios. Docker mantiene los contenedores; no se introduce Supervisor ni se requiere alta disponibilidad.

## Criterios de aceptación

- [x] CA-01: cuatro servicios con versiones fijadas, named volumes y reinicio configurado; arranque mediante Compose sin modificar contenedores ajenos.
- [x] CA-02: publicaciones de puertos limitadas a `127.0.0.1`: RabbitMQ `5672/15672`, Cronicle `3012`, MySQL `3308`, Mailpit `11025/18025`.
- [x] CA-03: secretos aleatorios en `infra/.env` ignorado; ejemplos sin secretos reales; Cronicle no conserva `admin/admin`.
- [x] CA-04: RabbitMQ publica y consume un mensaje; MySQL ejecuta una consulta; Mailpit recibe un correo SMTP de prueba.
- [x] CA-05: Cronicle permite iniciar sesión y ejecutar un trabajo finito; conserva configuración con hostname estable y bootstrap idempotente.
- [x] CA-06: las UIs son accesibles y los datos de prueba persisten tras recrear los contenedores sin borrar volúmenes.
- [x] CA-07: README explica inicio, parada, credenciales locales y uso futuro de la red externa; no se monta el socket Docker ni el código de otras aplicaciones.

No se instalan workers de emailing ni se crea la plataforma. El contenedor existente `sobricontrol-mariadb` y los servicios locales permanecen intactos. Los puertos `3306`, `3307`, `1025` y `8025` están ocupados y no se reutilizan.

Referencias: [plan](improvement-plan.md), [ledger](tasks.md), [pruebas](test-plan.md), [decisión RabbitMQ](../../knowledge/adr/ADR-001-rabbitmq-centralizado.md).

Verificación completada el 2026-10-21: [informe QA](testing/report.md), E2E 3/3 y protocolos/persistencia correctos. No supone aprobación del desarrollo de la plataforma.
