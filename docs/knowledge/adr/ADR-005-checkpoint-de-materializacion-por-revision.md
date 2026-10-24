---
id: ADR-005
titulo: Checkpoint durable de materialización por revisión
estado: "aceptada (validada: revisión de dos lentes, 2026-10-24, intento 2)"
fecha: 2026-10-24
iniciativa: 2026-10-21-plataforma-emailing
---

# ADR-005: Checkpoint durable de materialización por revisión

## Contexto

La revisión T15 D-01 detectó que cada disparo vacío recorría los destinatarios
de las campañas ya materializadas para descartarlas. La reproducción con 365
campañas de 200 destinatarios confirmó 73.366 lecturas por clave por disparo,
frente a una sin histórico, con tres repeticiones tras calentamiento. Son
contadores de la sesión MySQL, no una medida de capacidad ni un SLA.

## Decisión

Se conserva en `campaigns.materialized_revision_id` la identidad de la última
revisión materializada. El indicador generado `materialization_pending` compara
esa identidad con `reviewed_revision_id`; su índice permite descartar las
campañas completas antes de consultar destinatarios. El selector sigue validando
que la revisión pertenece a la cuenta y campaña vigentes y el dominio vuelve a
comprobar estado y fecha bajo los locks de cuenta y campaña, en ese orden.

El materializador escribe el checkpoint en la misma transacción que las
entregas y su outbox inicial. Una revisión nueva invalida el indicador por
identidad, sin depender de resets en cada entrada de edición. El checkpoint
certifica exclusivamente la materialización SQL; no cambia el estado autorizado,
programado o pausado, ni afirma publicación AMQP, inicio de transporte, aceptación
SMTP o entrega externa. La identidad retenida no añade una FK circular a las
cascadas de cuenta/campaña; las escrituras de dominio y el backfill verifican
cuenta, campaña y revisión, como requiere la referencia revisada existente.

La migración incremental clasifica el histórico en una pasada: exige un snapshot
con el número de destinatarios declarado y, para cada uno, una entrega de esa
revisión/hash y su evento inicial `delivery.ready` de generación 1. Un snapshot
vacío coherente está completo. Un snapshot parcial, referencia ajena o par sin
outbox no recibe checkpoint. El materializador rechaza los pares dañados sin
inventar una republicación: su reparación requiere reconciliación controlada de
la evidencia, especialmente si pudo existir aceptación SMTP.

## Alternativas descartadas

- Recorrer `NOT EXISTS delivery` en cada disparo conserva el coste recurrente
  proporcional a todos los destinatarios históricos, aun con índices.
- Marcar la campaña como enviada confunde materialización con transporte y
  altera el ciclo de autorización y pausa.
- Un booleano reseteado por cada entrada de edición puede quedar obsoleto cuando
  cambia la revisión por otro camino. La comparación de identidades evita ese
  acoplamiento.

## Consecuencias y validación

El coste del backfill se paga durante la migración; no se reparte como un
histórico de una campaña por cada futuro disparo. El replay bajo el checkpoint
evita volver a recorrer destinatarios. Las filas certificadas y sus eventos
iniciales deben conservarse por la autoridad de dominio: borrar pares mediante
SQL ajeno a esa autoridad invalida la evidencia y no se cubre con reparación
automática ni reenvío ciego.

La regresión está en `SchedulerTriggerTest` y el escenario poblado se conserva
como evidencia privada T15-fix1. Se verifican rollback, replay, concurrencia,
revisión nueva, vacío, migración con datos y el límite de 10.000 destinatarios.
Esta entrada permanece **propuesta** hasta revisión independiente. No promueve
ADR-003 ni acredita el QA integral de fases posteriores.

Fuente: [diseño de la plataforma](../../roadmap/2026-10-21-plataforma-emailing/design.md)
y [T15 del ledger](../../roadmap/2026-10-21-plataforma-emailing/tasks.md).
