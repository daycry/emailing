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
  - docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-1-C.md
  - docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-2-C.md
  - vendor/symfony/mailer/Transport/Smtp/EsmtpTransport.php
id: emailing.GOT-018
iniciativa: 2026-10-30-proveedores-email
proposed_by: implementer
tags:
  - lenguaje:php
  - libreria:symfony-mailer
  - dominio:smtp
tipo: gotcha
titulo: Exigir ESMTP para impedir la degradacion que elude STARTTLS
version: 1
---
# Exigir ESMTP para impedir la degradacion que elude STARTTLS

## Sintoma
Con requireTls=true, un peer que rechazaba EHLO y aceptaba HELO conseguia DATA sin TLS. El probe C1 y la regresion de cable antes del fix acreditaron el caso con datos sinteticos; no fue un incidente real.

## Causa raiz
Symfony Mailer8.1.7 retorna el fallback HELO antes de comprobar TLS. SocketStream::isTLS describe TLS implicito configurado y no se actualiza al completar STARTTLS: no es un oraculo del estado real de ese handshake.

## Que hacer
En perfiles seguros exigir ESMTP y fallar cerrado ante EHLO rechazado con una excepcion propia que no activa el fallback del vendor. La ruta normal conserva el handshake y la verificacion de certificados. Plain queda limitado al laboratorio. No deshabilitar verify_peer ni inventar codigos SMTP.

La proxima vez que veas requireTls junto a fallback EHLO/HELO, revisa los retornos reales antes de acreditar cifrado.

## Evidencia y limites
El dataset downgrade era rojo con tls=false,data=true. Tras el fix exige cero AUTH, sobre y DATA, conserva STARTTLS/SMTPS validos y se incluye en90casos/1045aserciones verdes. Revision independiente del fix y QA global pendientes al proponer; no constituye aprobacion de memoria.
