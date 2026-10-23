# Emailing — documentación

Documentación de la infraestructura compartida y de la aplicación interna: acceso, cuentas, Teammates, contactos, listas, importación CSV, plantillas y transporte de laboratorio.

**Última actualización: 2026-10-23.** La infraestructura tiene QA verde. Las fases 1 y 2 Laravel/Livewire están implementadas y probadas en perfiles QA nativo y Docker. QA3 de fase 2 acredita 20/20 casos, sin fallidos, flaky, omitidos ni interrumpidos. M-01 humana sigue pendiente. De la base operativa sólo constan 13 tablas y cinco filas de `lab_smoke`; su estado de migraciones no está acreditado. T-10–T-20 y el cierre global siguen pendientes.

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
| [Arquitectura de aplicación](aplicacion/arquitectura.md) | Identidades, membresías, autorización, módulos actuales, propiedad y auditoría. |
| [Guía de cuentas y Teammates](aplicacion/cuentas-y-equipo.md) | Registro, recuperación, selector de cuenta e invitación y gestión de personas. |
| [Contactos e importación CSV](aplicacion/contactos-e-importacion.md) | Contactos compartidos, listas, consentimiento, formato CSV, deduplicación y resultados privados. |
| [Plantillas y transporte](aplicacion/plantillas-y-transporte.md) | Variables, preview aislada, pruebas de la versión guardada, log privado, Mailpit y estados SMTP. |
| [Entrega y recuperación](aplicacion/entrega-y-recuperacion.md) | Outbox revisado, consumidor en validación, estados durables, reintentos, cuotas y diagnóstico de envíos ambiguos. |
| [Decisiones técnicas](knowledge/README.md) | ADR del broker, stack y runtime; propuesta de entrega y gotchas aceptados de frontend y limpieza QA. |
| [Roadmap](roadmap/README.md) | Especificaciones, evaluaciones, planes, tareas y pruebas. |
| [Índice para agentes](RAG-INDEX.md) | Resumen de conceptos y fuentes para recuperación de contexto. |

La guía de entrega distingue el outbox revisado de T-12 y el consumidor de T-13 todavía en validación. No acredita el QA integral de campañas ni un contrato de API implementada. El stack y las unidades actuales se explican en infraestructura y aplicación.

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
| Aplicación | PHP 8.4 / Laravel 13.38 / Livewire 4 | Fortify, cuentas y Teammates; contactos/CSV, plantillas y transporte por cuenta con MySQL. |

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

El [informe de fase 1](roadmap/2026-10-21-plataforma-emailing/testing/report.md) conserva 8/8 E2E y la regresión FB01. El [informe final de fase 2](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.md) y su [PDF](roadmap/2026-10-21-plataforma-emailing/testing/fase-2/report-attempt-3.pdf) acreditan veinte casos: diez en nativo 8082 y diez en Docker 8083, un intento por caso, con ambos gates exit 0 idénticos. La matriz incluye E2E-01–04 y ampliaciones de E2E-05–07. QA1 conserva 8 aprobados/12 fallidos y QA2, 13/7; QA3 no reescribe esos resultados. La revisión A+B, intento 6, no detectó gaps.

M-01 humana y M-02 siguen pendientes. La trazabilidad de GWT no acredita ejecución de los futuros E2E-08–11. El PDF final tiene diez páginas y veinte colocaciones de imágenes; el recibo conserva el fallo inicial de contar recursos deduplicados y su verificación posterior sobre la misma exportación.

Para abrir la aplicación empieza por [desarrollo y pruebas](aplicacion/desarrollo.md). El aprovisionamiento no migra `emailing`; conserva sus marcadores de laboratorio. Invitaciones y recuperación usan Mailpit. El transporte de pruebas por cuenta ya ofrece log privado o Mailpit; preparar datos SMTP no habilita un relay externo. Campañas, bajas, supresiones, cuotas, API y entrega fiable siguen pendientes.
