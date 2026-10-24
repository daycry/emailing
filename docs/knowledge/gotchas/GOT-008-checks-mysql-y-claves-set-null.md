---
id: GOT-008
tipo: gotcha
area: testing
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 23)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# CHECK de MySQL sobre una clave con SET NULL

## Síntoma

La primera focal de T-18 falla antes de ejecutar sus aserciones: MySQL devuelve
3823 al incluir `actor_id IS NULL` en un CHECK de `mail_messages`. Esa columna
tiene una FK de usuario con `ON DELETE SET NULL`. La alternativa con trigger
falla con 1419: binary logging está habilitado y el usuario de pruebas no tiene
SUPER ni autorización para crear esa rutina en tal configuración. Ambos
resultados se conservan como rojos, seis pruebas y cero aserciones en cada uno.

## Causa y solución aplicada

No equivale a un error de sintaxis del esquema ni prueba que un trigger sea un
requisito del contrato. Se conservan la FK de usuario y los CHECK/FKs compuestos
de cuenta, rama y procedencia. El constructor de transporte API escribe actor
nulo y el modelo rechaza atribuir un usuario interactivo a ese origen. Esta
parte se aplica en la aplicación; no se afirma que SQL por sí solo prohíba una
escritura directa de `actor_id` por un administrador de la base.

No cambiar SUPER, `log_bin_trust_function_creators` o privilegios globales para
hacer pasar una migración de aplicación. Antes de elegir triggers, valorar sus
requisitos de operación, respaldo y restauración y comprobar si el contrato
realmente exige esa técnica. Un fallo de migración no se transforma en una
aceptación de runtime o de la implementación.

## Evidencia y estado

El [ledger T-18](../../roadmap/2026-10-21-plataforma-emailing/tasks.md) conserva
los dos diagnósticos y los resultados posteriores. La
[migración](../../../database/migrations/2026_10_24_000013_create_sending_api.php),
el [modelo](../../../app/Models/MailMessage.php) y las
[pruebas de fuentes](../../../tests/Concerns/ExercisesSendingApi.php) distinguen
la protección SQL de la de aplicación y comprueban mezclas de procedencia. Esta
entrada espera revisión independiente y no se promueve por ejecutar focales.
