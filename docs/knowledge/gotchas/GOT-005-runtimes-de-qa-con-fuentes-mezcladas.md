---
id: GOT-005
tipo: gotcha
area: testing
estado: "aceptada (validada: revisión de dos lentes, 2026-10-24, intento 2)"
fuente: docs/roadmap/2026-10-21-plataforma-emailing/tasks.md
fecha: 2026-10-24
---

# Una imagen de QA puede ejecutar fuentes y migraciones de versiones distintas

## Síntoma

La prueba con Cronicle funciona contra el listener nativo y falla al inicializar
la fixture Docker: el hijo devuelve exit 0, pero su salida contiene SQLSTATE
42S02 por `campaigns` ausente y no cumple el contrato JSON esperado. El driver
rechaza esa salida. La fixture ya había creado algunas filas antes de fallar;
una lista de errores de limpieza vacía no demuestra que esas filas se retiraron.

## Causa raíz

El override de QA montaba app, config, routes y bootstrap actuales sobre una
imagen anterior, conservando su directorio database. El inventario real de esa
imagen tenía diez migraciones; el ZIP congelado tenía quince. Los diez hashes
comunes coincidían y faltaban las cinco migraciones posteriores. La identidad
SQL protegida confirmó las diez migraciones realmente aplicadas. Comprobar unos
hashes de clases cargadas no acredita el esquema, las vistas ni los recursos
que utiliza el resto del runtime.

## Qué hacer en su lugar

Antes de crear fixtures, contrastar los archivos visibles en cada runtime con el
artefacto previsto, incluidas las migraciones. Reconstruir la imagen o montar el
conjunto completo de fuentes de QA que corresponda; conservar el inventario
real y su correspondencia con el ZIP. No modificar la lógica de producto para
ocultar una tabla que falta por esta mezcla de versiones.

Exigir el código de salida y el contrato de respuesta de las herramientas de
fixture. Un error debe devolver un estado no cero y un diagnóstico depurado.
Registrar la identidad SQL y la propiedad de la fixture antes de sus primeras
escrituras, para poder observar y recuperar un estado parcial aunque la
inicialización no llegue a devolver su resultado normal. La recuperación debe
limitarse a recursos atribuidos y verificar el estado final de la base de pruebas.

## Evidencia

El [ledger de T-15](../../roadmap/2026-10-21-plataforma-emailing/tasks.md) conserva
`cronicle-real-daemon-v3-20261024T015119926713Z`, la observación de filas parciales
y `partial-state-recovery-v3-20261024T020352551038Z`: solo se retiraron filas propias
y se confirmó `migrations: 0` con la identidad protegida. El inventario
`image-migration-inventory-20261024T020447306371Z` contrasta las diez migraciones
de la imagen con las quince del ZIP. `daemon-v4-preflight-20261024T020722931073Z`
verifica los quince hashes después del montaje de database y prueba que una
fixture deliberadamente inválida sale con 1 y diagnóstico depurado.

`cronicle-real-daemon-v4-20261024T020743248472Z` completa los perfiles nativo y
Docker con el daemon real. Estos resultados prueban ese escenario de laboratorio;
no sustituyen el QA integral ni una restauración de respaldo.
