---
proposed_by: implementer
tipo: gotcha
titulo: Una lectura antigua puede omitir grants al retirar una membresía
category: backend
fecha: 2026-10-25
iniciativa: 2026-10-24-mcp-google
evidencia: Regresión SQL real RED y GREEN, con barreras y REPEATABLE READ
fuentes:
  - app/Services/TeamService.php
  - tests/Feature/McpMembershipRemovalRegressionTest.php
  - tests/support/mcp-membership-race.php
  - docs/aplicacion/mcp-contrato.md
tags:
  - stack:laravel
  - database:mysql
  - area:authorization
---

# Reconocer la instantánea anterior al mutex

La próxima vez que veas una lectura ordinaria dentro de una transacción antes de esperar un mutex de cuenta, comprueba si una enumeración posterior necesita datos confirmados durante esa espera.

En REPEATABLE READ, adquirir después un bloqueo no renueva la instantánea de las lecturas ordinarias. Retirar la membresía y enumerar grants con esa vista puede omitir una autorización emitida concurrentemente. Si se crea otra membresía para la misma cuenta y persona, el grant omitido vuelve a encontrar permisos vigentes.

La regresión emite autoridad en un proceso mientras otro conserva una instantánea previa y espera el mutex. Antes de la corrección, la retirada/reinvitación reactivaba el permiso: 1 fallo y 10 aserciones. La prueba verifica el conflicto SQL real 1205, aislamiento y cierre de ambas transacciones.

La corrección descubre clientes con una lectura primaria nueva sin locks y une ese resultado con los cambios propios aún no confirmados del llamante. Después relee y bloquea autoridad vigente en la transacción original, conservando cuenta → membresía → cliente → grant → token. La conexión de lectura se libera, sin reemplazar la conexión del llamante. Así se evitan tanto la instantánea antigua como tomar locks de grants antes de los clientes.

Se descartaron mover una sola lectura fuera de removeMember, porque un llamante puede aportar ya una instantánea anterior, y cambiar globalmente el aislamiento, porque altera otras operaciones. La unión con cambios propios evita perder grants creados dentro de la transacción del llamante.

No se reprocesan grants ya revocados: con 600 tombstones y una autorización viva, las lecturas relevantes bajaron de 1204 a 5. Las tres regresiones focales pasaron con 35 aserciones; la batería de autoridad/equipos pasó 20 pruebas y 213 aserciones con el mismo manifiesto de fuentes `4c6f39452c7cb0bb97056272a5049defbe7a56366d35c76bdeeaf64a2a880885`. No son mediciones de SLA ni sustituyen revisión independiente o QA.

Referencia del comportamiento transaccional: https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html
