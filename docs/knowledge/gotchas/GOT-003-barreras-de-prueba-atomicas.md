---
id: GOT-003
tipo: gotcha
area: testing
estado: "aceptada (validada: revisión de dos lentes, 2026-10-23, intento 12)"
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-23
---

# Publicar y comprobar barreras de prueba completas

## Síntoma

La prueba Linux de caída tras confirmar una publicación falla al leer JSON
o agota la observación, aunque el hijo registra la confirmación. Un KILL
en la limpieza no demuestra que la caída ocurriera en la ventana requerida.

## Causa raíz

La existencia del archivo se usaba como señal de contenido completo.
La escritura hacía visible el marcador antes de terminar su JSON sobre
el montaje: la observación causal encontró cero bytes y JSON inválido.
La lectura podía adelantarse a la escritura. STDIN quedó descartado;
la latencia del vendor montado no explicaba por sí sola el fallo.

## Qué hacer

Escribir el marcador completo en un temporal propio y publicarlo con
rename. El lector debe esperar JSON válido, identidad, evento, generación
y secuencia esperados dentro del plazo existente. Una señal inválida
permanente o de otro evento debe terminar con un fallo explícito.

En el fixture Linux, comprobar por hash el vendor, el lock y las fuentes
actuales cargadas. No aumentar el plazo para ocultar una barrera defectuosa.
Registrar el estado observado antes del KILL y distinguir esa caída de la
limpieza posterior. Esta disciplina acredita pruebas, no una garantía
de entrega única de emails.

## Evidencia

La regresión
`test_partial_protocol_marker_never_releases_consumer_before_complete_identity`
de [OutboxIntegrationTest](../../../tests/Feature/OutboxIntegrationTest.php)
conserva el mismo cuerpo entre RED y GREEN: primero falla con dos
aserciones y después pasa con tres. El test
`test_atomic_marker_publication_and_permanent_invalid_or_foreign_signal_are_observable`
cubre publicación completa y señales inválidas o ajenas. La implementación
está en [OutboxProtocolBarrier](../../../tests/support/OutboxProtocolBarrier.php).

El [ledger de T-12](../../roadmap/2026-10-21-plataforma-emailing/tasks.md)
registra la lectura de cero bytes, las hipótesis descartadas, Linux 1/26,
focal final 16/143 y suite completa 157/1.633, con hashes de los originales.
Las trazas privadas conservan la secuencia real sin publicar argumentos
sensibles. La [revisión independiente 12](../../roadmap/2026-10-21-plataforma-emailing/testing/fase-3/review/attempt-12.md) valida este método de barreras; no cierra el envío SMTP ni la iniciativa completa.
