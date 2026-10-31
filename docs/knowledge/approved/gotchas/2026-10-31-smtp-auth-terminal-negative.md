---
area: backend
category: GOTCHA
estado: aprobado
evidencia: validated_case
fecha: 2026-10-31
fuentes:
  - app/Services/ObservedSmtpTransport.php
  - app/Services/SmtpConnector.php
  - tests/Unit/MailProviders/ProviderSmtpWireTest.php
  - docs/roadmap/2026-10-30-proveedores-email/tasks.md
  - docs/roadmap/2026-10-30-proveedores-email/testing/backend/final-provider-units-20261031T001512155255Z/test-summary.json
  - docs/roadmap/2026-10-30-proveedores-email/testing/qa-3/raw/qa-gate.json
  - docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-1-B.md
  - docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-2-B.md
  - app/Services/MailProviders/ProviderSmtpTransport.php
  - tests/Feature/MailProviderDeliveryTest.php
  - docs/roadmap/2026-10-30-proveedores-email/testing/backend/final-provider-pipeline-20261031T001326898488Z/test-summary.json
id: emailing.GOT-019
iniciativa: 2026-10-30-proveedores-email
proposed_by: implementer
tags:
  - lenguaje:php
  - dominio:smtp
  - propiedad:idempotencia
tipo: gotcha
titulo: Retener el negativo terminal de AUTH sin impedir fallback valido
version: 1
---
# Retener el negativo terminal de AUTH sin impedir fallback valido

## Sintoma
Con TLS valido, LOGIN/PLAIN rechazados con535 acababan como fallo previo a terminador reintentable. Un mock que inyecta SmtpRejected535 no prueba la clasificacion real del conector.

## Causa raiz
El conector solo retenia negativos del mensaje. Symfony prueba mecanismos, usa RSET y envuelve el resultado en el wrapper HELO, perdiendo el codigo en la excepcion exterior.

## Que hacer
Separar el primer fallo del mensaje y el ultimo fallo de negociacion. Un exito de negociacion limpia el candidato, RSET/QUIT no lo borran y el wrapper conserva el negativo observado.535 definitivo no reintenta;454 transitorio puede hacerlo; un fallback autentico exitoso continua. Guardar solo etapa/codigo/flags, nunca AUTH ni excepciones crudas.

La proxima vez que veas smtp_before_terminator con credenciales definitivamente rechazadas, sigue AUTH y los mecanismos alternativos por el conector real antes de cambiar el limite de reintentos.

## Evidencia y limites
El dataset auth-535 era rojo por retryable=true. El verde90/1045 incluye535permanente,454temporal y fallback valido. La prueba SQL nueva del worker impide una nueva generacion por535/politicaTLS. Revalidacion independiente y QA global pendientes al proponer; este candidato no se aprueba por un verde unitario.
