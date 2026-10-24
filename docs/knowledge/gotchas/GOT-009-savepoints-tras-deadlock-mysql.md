---
id: GOT-009
tipo: gotcha
area: backend
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 23)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Limpiar el límite transaccional tras un deadlock

## Síntoma

La API responde 503 ante un deadlock MySQL real (40001/1213), sin recibo
aceptado, pero Laravel conserva nivel transaccional 2. El siguiente trabajo
de la misma conexión puede usar un savepoint que ya no representa la unidad
original. La prueba provoca expresamente un orden inverso desde otro proceso
PHP; ese orden no forma parte del pipeline productivo.

## Causa raíz

MySQL aborta la transacción víctima completa. El controlador traduce la
excepción SQL a un error HTTP cerrado antes de que el pipeline pueda registrar
bindings sensibles. Laravel intenta después deshacer el savepoint por ese
error traducido; un fallo de ese rollback impide decrementar sus niveles.
No basta con consultar PDO::inTransaction: el aislamiento real registró true
junto con nivel 2 y cero peticiones aceptadas.

## Qué hacer

AccountKeyService conserva el nivel de entrada y, si falla la operación,
restaura explícitamente ese límite. Si el savepoint se perdió, hace rollback
completo. El fallo sigue siendo 503; no se convierte en admisión válida ni se
reenvía SMTP. El test exige cero trabajo parcial y permite reintentar la misma
clave de idempotencia una vez liquidado el deadlock.

## Evidencia

`SendingApiTest::test_real_mysql_deadlock_rolls_back_acceptance_and_allows_same_key_retry`.
Los rojos privados `t18-deadlock10` y `t18-deadlock11-isolate` conservan SQLSTATE,
código de driver, nivel Laravel, estado PDO y recibos del proceso rival.
El ledger T-18 registra el resultado posterior y la regresión completa;
esta nota sigue propuesta hasta revisión y QA.
