---
id: GOT-011
tipo: gotcha
area: testing
estado: aceptada (validada: revisión de dos lentes, 2026-10-25, intento 23)
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Reproducir migraciones históricas en orden cronológico

## Síntoma y causa

Un fixture de backfill ejecutaba el `down` de una migración antigua mientras
seguían aplicadas migraciones posteriores. MySQL rechazó eliminar `delivery_id`
con error 3959: un CHECK posterior todavía dependía de esa columna. Las tres
pruebas fallaron antes de verificar el backfill; no justificaba relajar el CHECK.

## Qué hacer

En una base MySQL exclusiva de pruebas, seleccionar las migraciones aplicadas
desde el objetivo y resolver sus fuentes mediante el migrador instalado.
Revertir en orden descendente, ejecutar las comprobaciones del esquema histórico
y restaurar en orden ascendente. Verificar fuente, conexión y ausencia de una
transacción exterior antes del DDL. Conservar el error primario y cualquier
fallo de restauración; una restauración incompleta debe fallar la prueba.

`ReplaysMigrationHistory` aplica ese procedimiento sin enumerar migraciones
posteriores concretas. Cada ventana genera una traza UUID privada con hashes,
orden y resultado, sin datos de aplicación ni bindings SQL. Las migraciones
históricas y sus aserciones de retención/backfill permanecen intactas.

## Evidencia

La primera regresión literal `delivery` conserva 161 pruebas pasadas y tres
errores 3959. La focal causal `t18-migration22` pasa las tres pruebas con 55
aserciones y cuatro ventanas restauradas. Solo cambian el helper y dos fixtures;
las fuentes desplegadas permanecen idénticas. La segunda regresión literal
`t18-delivery24` pasa 164 pruebas con 3071 aserciones, incluidas las tres
causales y cuatro ventanas restauradas. Esta entrada sigue propuesta,
pendiente de revisión; no declara QA aprobadas.
