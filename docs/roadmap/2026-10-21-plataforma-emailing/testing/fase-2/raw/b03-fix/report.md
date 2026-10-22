# B-03 · Reparación focal del teardown

La corrección está implementada y su verificación focal pasa en nativo y Docker. No se ha ejecutado QA2 ni se modifica el gate de QA1: 8 aprobadas, 12 fallidas, 0 flaky. Revisión específica del coordinador pendiente.

## Causa y RED

El finalizador buscaba `app/private/livewire-tmp`, mientras ambos runtimes configuran Livewire con disk `contact-imports` y directory `temporary`. Un upload sin importar no tiene fila `contact_imports`; el cleanup de `source_path` tampoco lo alcanza.

[RED](red.json): dos uploads HTTP reales con UUID, cero importaciones y handle capturado. Tras reproducir el cleanup antiguo, acotado al archivo exacto de cada tanda, los archivos permanecen en ambos perfiles. El proceso termina en exit 1 esperado. Stdout original y exit están conservados en `raw/artifacts/b03-fix/red.*`.

## Corrección

- Raíz y directorio se resuelven mediante `FileUploadConfiguration` y `Storage` dentro de cada runtime, con `TestingDatabaseGuard`. No hay ruta Docker de temporales hardcodeada.
- Cada upload aceptado guarda antes de validar un recibo privado del handle firmado, UUID, actor/cuenta, hash de sesión, nombre original y SHA256 de contenido. El registro validado añade archivos exactos y sidecar `.json`, más prueba HMAC que liga la atribución y los hashes.
- La limpieza valida firma/proof, configuración, nombres, rutas canónicas y hashes de todos los archivos antes de borrar ninguno. No busca por contenido/UUID parcial ni borra recursivamente. Rechaza traversal, identidades alteradas y enlaces que escapan a la raíz.
- La captura exige pertenencia vigente al crear el registro. Su eliminación posterior usa la prueba retenida y sigue funcionando aunque se retiren permisos y se borren los fixtures SQL.
- E2E-05 y el diagnóstico registran sus handles y usan nombres/contenido con UUID. El finalizador ya no sobrescribe recibos históricos ni afirma cero residuos globales.

## Depuración con evidencia

Se aplicó `debug-root-cause` a los problemas de construcción del fixture, dentro de esta reparación. No fueron tres ejecuciones de QA2 ni tres gates de QA.

1. El primer GREEN focal falló estrictamente porque el symlink de archivo Windows no estaba disponible; se conservaron salida y fixture limpio.
2. El segundo falló en registro. El probe mínimo confirmó HMAC válido en ambos formatos: respuesta HTTP firma `/basename.csv`, componente firma `basename.csv`. El rechazo estricto de nombre fue la causa probada. Se normaliza únicamente esa barra de la representación firmada, manteniendo ambos handles y rechazando rutas adicionales.
3. El intento con PHP→cmd no construyó la junction. No se atribuye una causa interna que no se haya probado. [Probe Node22](junction-debug.json) confirmó que `fs.symlinkSync(..., 'junction')` crea el escape real y que PHP lo rechaza por ruta canónica, conservando el destino. Esta API se usa para el fixture Windows; Docker comprueba symlink real.

Las salidas de los fallos focales se preservan en `raw/artifacts/b03-fix/green*.stdout.txt` con sus códigos. La evidencia privada del contraste de handles está en `debug-handles.json` y en recibos de comandos antes de parsear stdout. No se bajaron assertions de eliminación ni preservación.

## GREEN

[GREEN focal, exit 0](green-attempt-4.json), con Node 22.22.0:

| Perfil | Propios eliminados | Propios registrados restantes | Centinela ajeno controlado | Escape real |
|---|---:|---:|---|---|
| Nativo | 2 (payload + sidecar) | 0 | Preservado y retirado después | Junction rechazada por guarda PHP |
| Docker | 2 (payload + sidecar) | 0 | Preservado y retirado después | Symlink rechazado |

Traversal y alteración de cuenta, actor y UUID se rechazan en ambos perfiles; los archivos propios permanecen intactos tras esos rechazos.

[Orden real del teardown, exit 0](teardown-order.json): captura como marketer, revocación de sólo lectura manteniendo escritura, cierre de sesiones y eliminación de fixtures SQL **antes** del cleanup de temporales. Se eliminan 2 archivos por perfil, con 0 registrados restantes. Esto demuestra independencia de membresías/cuentas ya borradas.

## Históricos y corrección de la declaración anterior

La afirmación anterior de «0 residuos temporales» no quedó demostrada: miraba otra raíz. Los JSON, reportes, PDF, capturas y gate originales de QA1 se conservan sin reescribir esa historia.

No se intentó atribuir ni borrar históricos de QA1 sin handle registrado. En particular, el diagnóstico antiguo sin UUID no se deduce por filename o mtime. Los archivos no atribuibles se preservan; **no se afirma cero global**.

El upload de la segunda comprobación focal de B-03 había fallado antes del registro validado. Se recuperaron sólo sus dos archivos mediante coincidencia única de UUID de fixture conservado, nombre original exacto de metadata del servidor y SHA256 completo del request conocido. Se conservó la prueba exacta privada; [recibo público](recovered-green2.json). No hubo recuperación de QA1 ni borrado por substring.

El gotcha y su índice corresponden al coordinador/documenter por el alcance autorizado; esta reparación sólo escribe bajo `testing/fase-2/`. La suite completa queda pendiente del despacho de QA2.


## Cierre de la comprobacion focal

35 comprobaciones de sintaxis: 0 errores. Control de datos publicos: exit 0. El barrido final de los 8 registros firmados confirma 0 archivos propios restantes y 0 fixtures SQL. Se preservan 6 archivos no atribuibles en nativo y 4 en Docker; no se afirma cero global. QA1 y el patch de revision 3 conservan sus hashes. Fuentes congeladas en `source-sha256.json` (36 entradas); QA2 no ejecutada.
