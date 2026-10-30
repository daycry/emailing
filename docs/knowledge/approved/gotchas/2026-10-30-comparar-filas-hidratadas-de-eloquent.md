---
area: testing
category: GOTCHA
estado: aprobado
evidencia: validated_case
fecha: 2026-10-30
fuentes:
  - tests/Feature/McpPersistenceTest.php
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-5/t11/implementation.md
id: emailing.GOT-017
iniciativa: 2026-10-24-mcp-google
proposed_by: debug-root-cause
tags:
  - stack:laravel
  - database:mysql
  - area:testing
tipo: gotcha
titulo: Comparar filas hidratadas al verificar persistencia de Eloquent
version: 1
---
Un digest de `getAttributes()` puede cambiar inmediatamente al recargar una fila aunque sus datos persistidos permanezcan iguales. El objeto recién insertado no incorpora necesariamente los valores por defecto SQL y conserva el orden de asignación de atributos. MySQL devolvió `last_used_at = null` y otro orden de columnas: el test confundía esa diferencia con una pérdida de identidad tras rebootstrap.

La próxima vez que veas un hash de atributos distinto justo después de `fresh()`, compara primero las claves y los valores compartidos antes de atribuirlo a un reinicio.

Recarga la fila antes del digest inicial y compara representaciones persistidas equivalentes. Conserva todos los atributos, las comprobaciones de identidad y clave y el rechazo de la credencial revocada. No elimines campos ni cambies el modelo para adaptar los datos a una serialización accidental.

La reproducción focal falló antes del cambio. Una sonda anterior a cualquier rebootstrap comprobó la columna adicional y dos lecturas SQL iguales. Después del cambio, los ocho casos de McpPersistenceTest pasaron con61 aserciones, cero fallos, errores u omitidos; el informe citado conserva el alcance y distingue rebootstrap de reinicio real.
