# T-11 · corrección B-01

La acción «Vaciar selección de destinatarios» retira explícitamente todas las
listas y contactos del formulario, incluidos los eliminados. Conserva el borrador
guardado hasta guardar; permite elegir alternativas y revisar el mismo borrador.
Reconsulta cuenta, membresía/scopes, versión y estado draft/reviewed, sin escribir
SQL. Marca cambios sin guardar e invalida la confirmación anterior.

| Comprobación | Resultado |
|---|---|
| Recuperación de borrador, código anterior | RED 1 failed / 9 assertions; exit 1; 27,94 s |
| Misma regresión con corrección | GREEN 1 passed / 33 assertions; exit 0; 27,83 s |
| Limpieza de revisión, código anterior | RED 1 failed / 1 assertion; exit 1; 24,31 s |
| Misma regresión con corrección | GREEN 1 passed / 16 assertions; exit 0; 29,26 s |
| CampaignTest, incluidos cuatro casos ConcurrentCampaignTest | 28 passed / 301 assertions; exit 0; 284,72 s |
| Pint --test de los dos PHP modificados | exit 0 |
| Build con Node 22 | exit 0; 58 módulos; 55,65 s |

El primer RED ya acreditaba save404 y ausencia de efectos antes de exigir el
control inexistente. Los dos métodos son idénticos en RED/GREEN/final. El archivo
entero varía porque se añadió después una tercera regresión de permisos revocados;
ese caso se comprueba en la suite final, sin atribuirle RED independiente.
[Verificación por método](../raw/t11-b01-method-verification.json) y
[ocho recibos con streams originales](../raw/t11-b01-verification-receipts.json).
El resumen público omite cwd/rutas absolutas; los 16 streams conservan sus bytes.
El build inicial falló por spawn EPERM; se conserva junto al build autorizado.
CIM fue denegado durante un diagnóstico; no demuestra terminación ni motivó un
reinicio. Los handles propios terminaron y el implementer liberó expresamente
emailing_testing a 2026-10-23T09:14:21.060181Z.

Solo cambian componente Campaigns, su vista y CampaignTest. Root contrastó las
16 fuentes finales contra árbol/ZIP; 13 permanecen idénticas y tres autorizadas
cambian. Manifest SHA256 `68275ea630a84269e7a36ddbb9182030170ff4b7b4e3298ae6dabe1e4348ec3c`. Verificados 38 artefactos del
handoff, ocho recibos y 16 streams; las dos fuentes ZIP RED/GREEN conservan el
producto anterior en RED y las mismas expectativas de regresión. Green/final
tienen las mismas 16 fuentes. No se alteró ningún resultado original.

Ventana fix1 2026-10-23T08:49:50Z–2026-10-23T09:17:59Z; reloj 28m.
Tokens/euros/horas IA reales NULL. La corrección queda pendiente de segunda
revisión del mismo incremento, con traspaso de las tablas de revisión 9.

Feature 133/1404, plantillas 20/185 y benchmarks anteriores corresponden al
snapshot inicial y no se repitieron ni se relabelan sobre este fix. Pint global
anterior conserva exit 1 por tres helpers activos y nueve copias privadas de QA,
pendiente de T-20. T-10/T-11, worker literal, E2E-08/M-01 y QA integral siguen
abiertos; total 9/20, ADR-003 propuesta y envío externo deshabilitado.
