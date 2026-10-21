---
id: ADR-001
titulo: RabbitMQ como broker centralizado compartido
estado: aceptada
fecha: 2026-10-21
iniciativa: plataforma-emailing
---

# ADR-001: RabbitMQ como broker centralizado compartido

## Contexto

La aplicación interna de emailing compartirá infraestructura de colas con otras aplicaciones. Cada aplicación utilizará una o varias colas y sus propios consumidores. El volumen inicial comunicado para emailing es de 100–200 mensajes diarios, con crecimiento posible; se prevén 5–10 procesos workers repartidos, sin carga agregada ni distribución definitiva. El usuario comparó RabbitMQ, Kafka y Beanstalkd, y confirmó «pues RabbitMQ».

## Decisión

Se utiliza RabbitMQ como broker centralizado. Cada aplicación y entorno tendrá su vhost, credenciales y permisos específicos, y una o varias colas con consumidores autorizados. Las aplicaciones podrán usar lenguajes diferentes.

Docker con la variante Management, versión fijada, almacenamiento persistente y UI privada es la propuesta de despliegue a concretar. Esta decisión no elige lenguaje, framework, base de datos ni tamaño/topología del despliegue y no aprueba el alcance o presupuesto completos.

## Alternativas descartadas

- **Kafka:** alternativa válida con colas y retención/replay; esas necesidades no han sido requeridas para este alcance. La elección no implica que Kafka sea menos robusto o más lento.
- **Beanstalkd:** alternativa de trabajos con protocolo acotado; se prioriza el modelo de vhosts, permisos y routing de RabbitMQ para la infraestructura compartida.

## Consecuencias

La integración del framework elegido deberá probar publicación, confirms, consumo, acknowledgements, idempotencia y recuperación. Los vhosts separan acceso y topología, pero comparten recursos físicos. La outbox y el estado de negocio permitirán recuperar publicaciones y tratar resultados SMTP ambiguos sin reenviar a ciegas.

La persistencia en un único contenedor no proporciona disponibilidad ante perder el host. Réplicas y dominios de fallo se decidirán según el objetivo de disponibilidad. La gestión de workers utilizará un responsable de supervisión por proceso. No se ha desplegado ningún contenedor con esta decisión.

## Estado

`aceptada` — elección explícita del usuario el 2026-10-21: «pues RabbitMQ». El diseño de aplicación continúa en borrador; la aprobación se limita al broker.

Referencias: [diseño](../../roadmap/2026-10-21-plataforma-emailing/design.md), [vhosts](https://www.rabbitmq.com/docs/vhosts), [confirmaciones](https://www.rabbitmq.com/docs/confirms), [imagen Docker](https://hub.docker.com/_/rabbitmq).
