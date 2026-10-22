---
id: ADR-002
titulo: Laravel y Livewire con MySQL e infraestructura compartida
estado: "aceptada (validada: revisión de dos lentes, 2026-10-22, intento 2)"
fecha: 2026-10-21
iniciativa: plataforma-emailing
---

# ADR-002: Laravel y Livewire con MySQL e infraestructura compartida

## Contexto

La plataforma interna necesita cuentas aisladas, Teammates, permisos, campañas y API. Se compararon Laravel/Livewire, Django/Celery y NestJS/React, además de servicios Linux nativos frente a Docker Compose. RabbitMQ permanece elegido por [ADR-001](ADR-001-rabbitmq-centralizado.md). El laboratorio ya tiene RabbitMQ, MySQL, Cronicle y Mailpit con QA de protocolos, persistencia y UI verde; no necesita alta disponibilidad.

## Decisión

Se construye un monolito modular PHP 8.4 / Laravel 13 con Livewire 4 compatible resuelto por Composer, vistas propias en español y MySQL 8.4/InnoDB. La elección fija framework y componentes; no obliga a incorporar todos los paquetes de un starter kit. Se permiten el esqueleto Laravel y patrones oficiales de Livewire/Fortify/Tailwind, con dependencias runtime estables y locks. No requiere Flux Pro ni funciones accesorias del kit.

Se reutiliza la infraestructura mediante la red externa `emailing-lab`. El Compose independiente de emailing solo añade sus servicios y workers. Docker mantiene workers principales en primer plano, sin Supervisor. Cronicle programa acciones finitas mediante HTTP interno autenticado; RabbitMQ conserva trabajos y cada aplicación ejecuta sus consumidores con vhost y usuario propios.

Cuentas poseen datos y API keys; identidades participan mediante membresías multi-cuenta Admin/Read-only/Restricted. La autorización central se aplica en UI, API y trabajos. La primera fase es C-01, auth/cuentas/Teammates/permisos; C-02 a C-07 siguen en el plan completo.

El adaptador externo `vladimir-yuldashev/laravel-queue-rabbitmq` estable v15.0.2 declara compatibilidad con Laravel 13, pero se valida en runtime antes de envíos. Outbox, confirms, ack, idempotencia y resultados SMTP ambiguos siguen siendo responsabilidades explícitas. El SMTP inicial por cuenta apunta a Mailpit; secretos fuera de Git, documentos y mensajes de cola.

## Alternativas descartadas

- **Django/Celery:** válida integración RabbitMQ, sin motivo suficiente para cambiar el ecosistema PHP elegido.
- **NestJS/React:** mayor ensamblaje y frontend separado sin requisito que lo necesite.
- **Servicios Linux nativos:** viables, pero Compose aporta el perfil reproducible solicitado; se conserva perfil web Laragon con misma lógica y migraciones.
- **Supervisor común o por aplicación:** innecesario con workers principales administrados por Docker.
- **Infraestructura en cada Compose:** duplicaría instancias; solo se declara en `infra/compose.yaml` y se reutiliza por red externa.
- **PostgreSQL:** no hay necesidad específica frente al MySQL elegido, desplegado y probado.

## Consecuencias

Web y workers comparten código y reglas de cuenta con comandos distintos. MySQL/RabbitMQ usan DNS internos desde Docker y puertos publicados desde Laragon. Cada aplicación conserva credenciales, vhost y datos; las migraciones de emailing preservan datos ajenos y marcadores de smoke. La portabilidad, el adaptador y la recuperación ante fallos necesitan pruebas de aplicación; no se deducen del QA de infraestructura.

Un volumen no sustituye un respaldo y aceptación SMTP no demuestra entrega final. No se fijan costes, capacidad máxima ni SLA. [design.md](../../roadmap/2026-10-21-plataforma-emailing/design.md) desarrolla los compromisos.

## Estado

Aceptada tras la revisión de dos lentes, intento 2, y el cierre documental de fase 1. El diseño O1 + D2 fue validado por el usuario el 2026-10-21. Runtime, cuentas y Teammates tienen pruebas nativas y Docker; esta aceptación no acredita la entrega fiable, la capacidad ni los módulos pendientes del roadmap.
