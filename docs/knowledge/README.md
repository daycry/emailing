# Conocimiento técnico

| ID | Decisión | Estado | Iniciativa |
|---|---|---|---|
| [ADR-001](adr/ADR-001-rabbitmq-centralizado.md) | RabbitMQ como broker centralizado compartido entre aplicaciones | Aceptada por el usuario | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/spec.md) |
| [ADR-002](adr/ADR-002-laravel-mysql-compose.md) | Laravel/Livewire y MySQL con Compose de aplicación e infraestructura compartida | Aceptada; revisión de dos lentes, intento 2 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/spec.md) |
| [ADR-003](adr/ADR-003-publicacion-confirmada-y-estado-smtp.md) | Publisher confirmado y estados SQL antes de SMTP para recuperar sin reenvío ciego | Propuesta; QA integral de entrega y validaciones posteriores pendientes | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/spec.md) |
| [ADR-004](adr/ADR-004-limites-del-runtime-y-origen-publico.md) | FastCGI privado y origen confiable de enlaces secretos | Aceptada; revisión de dos lentes, intento 2 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/spec.md) |
| [ADR-005](adr/ADR-005-checkpoint-de-materializacion-por-revision.md) | Checkpoint SQL por revisión para excluir el histórico materializado del selector | Aceptada; revisión 17, intento 2 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
| [GOT-001](gotchas/GOT-001-errores-livewire-tras-reintento.md) | Frontend: errores conservados y mensajes visibles en Livewire | Aceptada; revisión de dos lentes, intento 6 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
| [GOT-002](gotchas/GOT-002-limpieza-de-uploads-de-qa.md) | Testing: raíz efectiva, atribución y continuidad de la limpieza de uploads | Aceptada; revisión de dos lentes, intento 6 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
| [GOT-003](gotchas/GOT-003-barreras-de-prueba-atomicas.md) | Testing: publicación atómica y validación de identidad de barreras | Aceptada; revisión 12, barreras de prueba | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
| [GOT-004](gotchas/GOT-004-limpieza-delete-pending-en-windows.md) | Testing: eliminación pendiente en Windows y conservación del error primario | Aceptada; revisión de dos lentes, intento 13 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
| [GOT-005](gotchas/GOT-005-runtimes-de-qa-con-fuentes-mezcladas.md) | Testing: fuentes y migraciones coherentes en la imagen de QA y recuperación de fixtures parciales | Aceptada; revisión 17, intento 2 | [plataforma-emailing](../roadmap/2026-10-21-plataforma-emailing/tasks.md) |
