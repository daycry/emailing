# Emailing — documentación

Documentación de la infraestructura compartida del laboratorio y punto de entrada al diseño de la aplicación interna de emailing.

**Última actualización: 2026-10-21.** Los servicios de infraestructura están implementados y tienen QA verde. La aplicación Laravel/Livewire está en desarrollo; sus módulos no se consideran terminados por disponer de infraestructura.

## Qué consultar

| Documento | Qué explica |
|---|---|
| [Arquitectura de infraestructura](infraestructura/arquitectura.md) | Separación entre infraestructura y aplicaciones, red, identidad y límites del laboratorio. |
| [Servicios](infraestructura/servicios.md) | RabbitMQ, Cronicle, MySQL y Mailpit: versiones, responsabilidades, salud y persistencia. |
| [Configuración y credenciales](infraestructura/configuracion.md) | Variables, puertos, secretos iniciales y cambios de configuración. |
| [Operación diaria](infraestructura/operacion.md) | Arranque, parada, logs, comprobaciones, smoke y recreación. |
| [Integración de aplicaciones](infraestructura/integracion-aplicaciones.md) | Cómo compartir servicios desde otros Compose o desde Laragon, sin duplicarlos. |
| [Respaldos y traslado](infraestructura/backup-migracion.md) | Qué conservar y recetas de copia/recuperación pendientes de ensayo. |
| [Resolución de problemas](infraestructura/troubleshooting.md) | Puertos, autenticación, volúmenes, Cronicle y conectividad. |
| [Entregabilidad y reputación](infraestructura/entregabilidad.md) | IP emisora, autenticación de dominio, bloqueos y controles previstos antes de enviar a Internet. |
| [Decisiones técnicas](knowledge/README.md) | Memoria de decisiones: broker y stack de aplicación. |
| [Roadmap](roadmap/README.md) | Especificaciones, evaluaciones, planes, tareas y pruebas. |
| [Índice para agentes](RAG-INDEX.md) | Resumen de conceptos y fuentes para recuperación de contexto. |

No se publica todavía una guía de usuario de campañas ni un contrato de API implementada: pertenecen al desarrollo pendiente de la aplicación. Las pantallas administrativas de infraestructura sí están cubiertas en estas guías.

## Primer arranque

Requiere Windows con PowerShell, Docker Desktop en modo contenedores Linux y Docker Compose. Ejecuta desde la raíz del repositorio:

```powershell
powershell -NoProfile -File infra/init.ps1
docker compose --env-file infra/.env -f infra/compose.yaml config --quiet
docker compose --env-file infra/.env -f infra/compose.yaml up -d --build --wait --wait-timeout 240
powershell -NoProfile -File infra/smoke.ps1
```

`init.ps1` conserva un `infra/.env` existente. La primera construcción de Cronicle descarga la release y dependencias; necesita acceso de red.

| Interfaz | URL local | Acceso |
|---|---|---|
| RabbitMQ Management | <http://127.0.0.1:15672> | Usuario y contraseña de RabbitMQ en `infra/.env`. |
| Cronicle | <http://127.0.0.1:3012> | Usuario `admin`, contraseña inicial en `infra/.env`. |
| Mailpit | <http://127.0.0.1:18025> | Sin autenticación en este laboratorio local. |

**El puerto 11025 recibe SMTP; la web de Mailpit está en 18025.**

## Cómo se organiza

El proyecto Compose `emailing-infra` mantiene una instancia de cada servicio. La red Docker `emailing-lab` permite que las aplicaciones utilicen esas instancias. Cada aplicación mantiene su código, sus workers, sus colas y sus credenciales.

| Tecnología | Versión declarada | Función |
|---|---|---|
| RabbitMQ Management | `4.4.0-management` | Broker AMQP y consola de administración. |
| Cronicle | `0.9.137`, imagen local sobre Node 22 | Programación y ejecución de tareas finitas. |
| MySQL | `8.4.11` | Datos SQL persistentes. |
| Mailpit | `v1.27.4` | Captura SMTP de pruebas y consulta web. |

La configuración ejecutable vive en [infra/compose.yaml](../infra/compose.yaml). [infra/README.md](../infra/README.md) conserva la referencia rápida junto al código. Esta sección de `docs/` desarrolla su operación.

## Comandos habituales

```powershell
docker compose --env-file infra/.env -f infra/compose.yaml ps
docker compose --env-file infra/.env -f infra/compose.yaml logs --tail 50 cronicle
docker compose --env-file infra/.env -f infra/compose.yaml stop
docker compose --env-file infra/.env -f infra/compose.yaml start
```

Los volúmenes conservan datos entre recreaciones. Su existencia no sustituye un respaldo fuera del host.

## Qué está comprobado

El [informe de QA](roadmap/2026-10-21-infra-laboratorio/testing/report.md) registra cuatro servicios saludables, pruebas de protocolos, persistencia tras recreación y tres E2E de UI aprobados. Las recetas de respaldo y recuperación de estas guías se distinguen de esas pruebas: todavía requieren un ensayo de restauración.
