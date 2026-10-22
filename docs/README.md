# Emailing — documentación

Documentación de la infraestructura compartida y de la fase 1 de la aplicación interna: acceso, cuentas y Teammates.

**Última actualización: 2026-10-22.** La infraestructura tiene QA verde. La fase 1 Laravel/Livewire está implementada y sus pruebas automáticas pasan en perfiles QA nativo y Docker. M-01 humana sigue pendiente. La base operativa no tiene aplicadas las migraciones de aplicación; los módulos posteriores continúan en desarrollo.

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
| [Preparar y probar la aplicación](aplicacion/desarrollo.md) | Dependencias, scripts, aislamiento SQL, perfiles 8082/8083, Compose y límites de operación. |
| [Arquitectura de cuentas y permisos](aplicacion/arquitectura.md) | Identidades, membresías, autorización, invitaciones, propiedad y auditoría. |
| [Guía de cuentas y Teammates](aplicacion/cuentas-y-equipo.md) | Registro, recuperación, selector de cuenta e invitación y gestión de personas. |
| [Decisiones técnicas](knowledge/README.md) | Memoria de decisiones: broker y stack de aplicación. |
| [Roadmap](roadmap/README.md) | Especificaciones, evaluaciones, planes, tareas y pruebas. |
| [Índice para agentes](RAG-INDEX.md) | Resumen de conceptos y fuentes para recuperación de contexto. |

No se publican guías de campañas ni contrato de API implementada: esos módulos siguen pendientes. El stack y las unidades actuales se explican en infraestructura y aplicación; no se crean páginas vacías para los módulos futuros.

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
| Aplicación | PHP 8.4 / Laravel 13.38 / Livewire 4 | Acceso, cuentas y Teammates, con Fortify y MySQL. |

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

El [informe de infraestructura](roadmap/2026-10-21-infra-laboratorio/testing/report.md) registra cuatro servicios saludables, protocolos, persistencia tras recreación y tres E2E de UI aprobados. Las recetas de respaldo todavía requieren un ensayo de restauración.

El [informe de aplicación](roadmap/2026-10-21-plataforma-emailing/testing/report.md) acredita 8/8 E2E de fase 1 en nativo 8082 y Docker 8083, sin flaky, skipped ni interrupted. Incluye la regresión FB01 de errores del mismo formulario. M-01 humana sigue pendiente; los 25 GWT trazados no equivalen a 25 ejecutados. E2E-05–11 y M-02 siguen en fases futuras.

Para abrir la aplicación empieza por [desarrollo y pruebas](aplicacion/desarrollo.md). El aprovisionamiento no migra `emailing`; conserva sus marcadores de laboratorio. SMTP de invitaciones y recuperación usa Mailpit. SMTP configurable por cuenta, campañas y entrega externa siguen pendientes.
