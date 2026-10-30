---
area: testing
category: GOTCHA
estado: aprobado
evidencia: validated_case
fecha: 2026-10-26
fuentes:
  - tests/Concerns/ReplaysMigrationHistory.php
  - tests/Feature/MigrationReplayTest.php
  - tests/Feature/ActivityTest.php
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix1/report.md
  - docs/roadmap/2026-10-24-mcp-google/testing/fase-4/t09/fix1/evidence.json
  - docs/knowledge/gotchas/GOT-003-barreras-de-prueba-atomicas.md
  - docs/knowledge/gotchas/GOT-011-replay-cronologico-de-migraciones.md
id: emailing.GOT-014
iniciativa: 2026-10-24-mcp-google
proposed_by: debug-root-cause
tags:
  - stack:laravel
  - database:mysql
  - platform:windows
  - area:testing
tipo: gotcha
titulo: El fallo de una traza no determina qué DDL debe restaurarse
version: 1
---
La próxima vez que veas un replay de migraciones terminar con un error de escritura de evidencia, una traza incompleta o rename/code5 en Windows, comprueba por separado los DDL completados y los errores de publicación antes de interpretar el estado restaurado.

## Síntoma y causa probada

En QA de T-09, publicar la traza tras un DDL produjo un error de sustitución de archivo. El fixture trataba publicación y migración dentro de la misma frontera de error: un down completado podía no entrar en la lista de restauración, o un up completado seguido de fallo de publicación detenía el resto de los up. Dos reproducciones con MySQL real e inyección limitada al escritor demostraron que la tabla de auditoría quedaba sin restaurar.

Un experimento Win32 separado reprodujo rename/code5 reteniendo el destino sin permitir su sustitución. El snapshot con nombre nuevo funcionó mientras el anterior seguía retenido. El proceso que retenía el archivo de QA1 permanece sin identificar; esta evidencia no lo atribuye a antivirus ni a un servicio concreto.

## Aplicación en fixtures de recuperación

Registra el DDL completado antes de publicar su evidencia y mantén un canal de error separado para la publicación. Publica snapshots de progreso con nombres inmutables y un archivo final único. Intenta los up correspondientes a los down realmente completados, en el orden de dependencias vigente. Si falla un DDL de restauración, conserva ese fallo y detén las dependencias que no pueden restaurarse correctamente.

El error de evidencia debe seguir siendo visible: propágalo después de restaurar cuando sea la única causa, y conserva la excepción primaria cuando ya existía. No conviertas un fallo del escritor en éxito silencioso. Este criterio se refiere a la reposición obligatoria de fixtures de prueba; no cambia los requisitos transaccionales de auditoría de operaciones de negocio.

El teardown final no demuestra un replay correcto. Comprueba la ventana real de restauración, sus pasos completados y el resultado final antes del teardown. Distingue snapshots parciales de la traza final; el flag restored sólo corresponde al resultado final.

## Regresión y límites

MigrationReplayTest conserva el mismo SHA entre RED y GREEN y cubre snapshots inmutables, fallo de evidencia tras down/up/final, excepción primaria más error de evidencia y fallo DDL real. La focal pasa seis casos/48 aserciones; el histórico original de ActivityTest pasa un caso/25 aserciones y acredita20 pasos restaurados. La suite Unit+Feature posterior pasa412 casos/7967 aserciones, sin fallos, errores ni omitidos, sobre399 entradas de fuente estables. El informe fix1 conserva hashes y ámbitos; estas cifras no acreditan QA2, Google ni clientes externos.

Esta causa complementa GOT-003: la publicación atómica evita lecturas incompletas pero sustituir un destino puede fallar bajo un bloqueo Windows. También complementa GOT-011: el orden cronológico no basta si el escritor de evidencia altera la lista de DDL completados. Se conservan los bytes y la autoridad del legado.
