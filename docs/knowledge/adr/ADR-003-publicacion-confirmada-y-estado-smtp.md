---
id: ADR-003
titulo: Publicación confirmada y estados durables antes de SMTP
estado: propuesta
fecha: 2026-10-22
iniciativa: 2026-10-21-plataforma-emailing
---

# ADR-003: Publicación confirmada y estados durables antes de SMTP

## Contexto

El diseño aprobado requiere outbox SQL, confirmaciones del broker, deduplicación
y recuperación sin reenvío ciego tras una posible aceptación SMTP. La inspección
del adaptador RabbitMQ 15.0.2 instalado demuestra que `pushRaw()` utiliza
`mandatory`, pero su publicación no espera confirms ni registra devoluciones.
`RabbitMQJob::release()` publica otra copia y confirma el original sin confirmar
esa publicación. Su prueba de reconexión normal no demuestra recuperación ante
esas ventanas de fallo.

## Decisión

Los mensajes de entrega de correo se publican y consumen mediante servicios de
dominio sobre php-amqplib, la dependencia ya instalada, con confirms y control
SQL explícitos. Se conserva el adaptador Laravel para trabajos ordinarios y su
prueba de compatibilidad. No se modifica `vendor`, no se cambia RabbitMQ y no se
incorpora un MTA.

La implementación corresponde a T-10–T-16; esta propuesta todavía no acredita
que esos servicios estén implementados o probados.

### Invariantes de implementación

1. Una entrega por destinatario, con identificador estable y contexto de cuenta
   persistido. El payload JSON versionado contiene referencias mínimas, sin
   secretos ni objetos PHP serializados. SQL determina la cuenta válida.
2. Entrega y evento outbox nacen en una transacción. El dispatcher usa una
   reserva con token y vencimiento, publica fuera de la transacción y finaliza
   mediante comparación del token vigente.
3. Canal exclusivo, exchange/cola durables, mensaje persistente,
   `confirm_select()`, `mandatory=true` y espera acotada de ack/nack/return.
   Un ack acompañado de return no es éxito. Timeout conserva la incertidumbre
   para reconciliar el mismo identificador.
4. El consumidor utiliza acknowledgements manuales y prefetch configurable.
   Solo confirma después de persistir un resultado o un reintento con su outbox.
   La reentrega de un resultado terminal no vuelve a llamar al transporte.
5. `reserved` significa que no ha podido empezar SMTP. La transición a
   `sending` se persiste **antes** de cualquier IO de transporte y exige token,
   generación y reserva vigentes. Una reserva antigua no puede efectuarla.
6. Un `reserved` vencido puede recuperarse. Un `sending` vencido se clasifica
   `ambiguous`; nunca se reutiliza automáticamente para enviar. El fencing SQL
   no puede cancelar una operación SMTP que ya está ejecutándose.
7. Un resultado negativo demostrable anterior a aceptación sigue reintentos
   acotados. Una desconexión sin prueba de rechazo, un crash en `sending` o la
   pérdida de persistencia tras aceptación quedan ambiguos, sin retry ciego.
8. Los reintentos conservan `delivery_id` y crean una nueva generación de
   outbox con `available_at`. No utilizan `release()` como garantía durable ni
   nack/requeue inmediato como mecanismo de backoff.
9. Consentimiento, supresión, campaña pausada y remitente habilitado se revisan
   justo antes de `sending`. Baja y arranque bloquean el mismo recurso para
   definir su orden. Una baja ya completada impide un arranque posterior.
10. Cuotas atómicas por cuenta y por pool de relay, con orden fijo de locks.
    Un resultado ambiguo consume cuota de forma conservadora. Esperar cuota no
    incrementa los intentos SMTP. El pool se identifica explícitamente; compartir
    host/usuario no demuestra compartir una IP.
11. Agotar intentos persiste `failed` y outbox de fallo en la misma transacción.
    La cola de fallos no tiene un consumidor que reenvíe automáticamente.
12. Cronicle dispara acciones finitas e idempotentes; un único propietario del
    calendario. Campañas y totales se derivan de entregas SQL sin llamar
    «entregados» a mensajes solo aceptados por SMTP.

## Alternativas descartadas

- **Confiar exclusivamente en `pushRaw()` y `release()` estándar:** no cubre
  confirmaciones perdidas, devoluciones y publicación de reintentos.
- **Reutilizar todo lease vencido:** un worker antiguo podría seguir enviando
  mientras otro inicia SMTP; es inseguro después de entrar en `sending`.
- **Usar solo DLX o TTL para conservar reintentos/fallos:** no constituye la
  evidencia SQL durable requerida y puede introducir pérdida o espera de cola.
- **Prometer exactly-once SMTP:** una aceptación externa y la transacción SQL
  no comparten commit. El estado ambiguo es necesario para reflejar ese límite.

## Consecuencias

Se mantienen el framework, el broker y la infraestructura aprobados. El envío
tiene un flujo de recuperación explícito y requiere pruebas MySQL/RabbitMQ con
procesos Linux reales, cortes y puntos de fallo. Aceptar conservadoramente un
estado ambiguo puede exigir revisión humana; no se sacrifica esa garantía para
mostrar una campaña como terminada.

Las pruebas deben cubrir rollback SQL, broker caído, confirm perdido, crash
después de confirm, return con ack, dos dispatchers/consumidores, fencing de una
reserva vencida, crash en `sending`, aceptación seguida de fallo SQL, reentrega
tras estado terminal, retry acotado, publicación de fallo recuperable, baja
concurrente, cuota agregada y programación repetida. Mocks no prueban por sí solos
esas ventanas de crash.

## Evidencia

- `vendor/vladimir-yuldashev/laravel-queue-rabbitmq/src/Queue/RabbitMQQueue.php`:
  `pushRaw`, `bulkRaw`, `publishBasic` y `ack` en la versión bloqueada 15.0.2.
- `vendor/vladimir-yuldashev/laravel-queue-rabbitmq/src/Queue/Jobs/RabbitMQJob.php`:
  `release` publica mediante `laterRaw` antes del ack original.
- `vendor/php-amqplib/php-amqplib/PhpAmqpLib/Channel/AMQPChannel.php`:
  `confirm_select`, `wait_for_pending_acks_returns`, handlers de ack/nack/return.
- [Confirmaciones y acknowledgements](https://www.rabbitmq.com/docs/confirms),
  [TTL](https://www.rabbitmq.com/docs/ttl) y [dead lettering](https://www.rabbitmq.com/docs/dlx).
- [Diseño aprobado](../../roadmap/2026-10-21-plataforma-emailing/design.md) y
  [ledger](../../roadmap/2026-10-21-plataforma-emailing/tasks.md).

## Evidencia parcial tras revisión 13

El outbox, publisher y consumidor de T-12/T-13 están implementados y revisados
sin defectos pendientes en las cuatro lentes. El
[informe de revisión](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/review-13.md)
conserva el full final de 183 pruebas/2227 aserciones y la
[adenda de baja posterior a AMQP](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/review-13-supplement.md),
con 1 prueba/51 aserciones sobre el mismo runtime. Se conserva la distinción
entre reserva recuperable, posible aceptación ambigua y resultado durable
previo al ACK; no hay afirmación de exactly-once SMTP.

La propuesta completa incluye todavía actividad/totales, disparos finitos de
Cronicle y QA integral de T-14–T-16. Esta evidencia parcial no acredita esos
pendientes ni el despliegue operativo; por ello ADR-003 conserva `propuesta`.

## Estado

`propuesta` — detalle de implementación sujeto a revisión y a las pruebas de
entrega; no es una declaración de finalización de C-04/C-06.
