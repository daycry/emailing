# Revisión de dos lentes — intento 6 · T-06/T-09 / corrección QA2

**Aprobado: cero Critical, Important y Minor.** Primer intento de tres del
nuevo incremento de corrección posterior a QA2; sexto registro histórico.
Permite ejecutar QA3 y no cierra T-09. Las aprobaciones anteriores se conservan
sin atribuirles una nueva auditoría completa de los criterios que no cambiaron.

| Campo | Evidencia |
|---|---|
| Base Git | `86f6d50ea06356c6e1a89674e7c42c930aeecc32` |
| Incremento | 11 archivos; 44 fuentes congeladas y 23 evidencias focales |
| SHA256 del patch | `6710537c7f33756652bac7e003085d4e05e51bf396be4bc670eee38c19775fe8` |
| Snapshot | [patch](attempt-6.patch) · [manifiesto](attempt-6.patch.json) |
| Alcance | exit 0; cero archivos fuera de alcance, avisos y exclusiones de usuario |
| Lentes | A+B; selector C=false y D=false, sin avisos |
| Roles | Dos revisores genéricos frescos; tier frontmatter resuelto opus/high, runtime heredado; no se atribuye ejecución Claude |
| Integridad | SHA antes/después: 44/44 fuentes y 23/23 evidencias, cero diferencias; preimágenes verificadas |
| Medición | 2026-10-23T00:29:04Z–00:37:26Z; ocho minutos de reloj; tokens, euros y horas IA reales desconocidos/null |

## Criterios de la lente A

Las rutas de las specs parten de `testing/fase-2/`. Las referencias del producto
parten de la raíz del repositorio.

| Criterio | Resultado | Evidencia |
|---|---|---|
| Aceptaciones previas T-01–T-04 conservadas | ✓ conservado | Sin cambios funcionales del incremento; Feature final mantiene pruebas de runtime, autenticación, recuperación, permisos y concurrencia. Cierre histórico de fase 1 conservado. |
| T-05: recursos compartidos y scopes/recursos ajenos | ✓ | `specs/E2E-05.spec.mjs:12–46`; focal completa en ambos perfiles; 403/404 estrictos. |
| T-06: CSV, consentimiento y resumen aislado | ✓ | `tests/Feature/ContactImportTest.php:90–162`, `:213–285`; E2E-05 mantiene carga de 9.990 filas. |
| T-07: sandbox y scripts | ✓ conservado | E2E-06:52–66 sin cambios; regresiones Feature finales conservadas. |
| T-07: variables, escape, cuenta y scopes | ✓ | E2E-06:25–48; focal completa en ambos perfiles. |
| T-08: log privado, sin SMTP, descarga autorizada | ✓ | E2E-07:17–27; prueba Feature de cero conexiones SMTP conservada. |
| T-08: secretos y TLS inválido | ✓ conservado | E2E-07:5–16; pruebas Feature de secretos y TLS real conservadas. |
| T-08: Mailpit sólo acredita aceptación SMTP | ✓ | E2E-07:28–31 exige `smtp_accepted` y «No acredita entrega». |
| T-08: SMTP externo permanece bloqueado | ✓ | E2E-07:37–41 exige aviso exacto, recursos y fingerprint SQL intactos. |
| T-09: cierre con matriz completa | Pendiente declarado | QA2 conserva 13 aprobados/7 fallidos; QA3 de veinte casos y M-01 humano siguen pendientes. |
| Alcance declarado coincide con el cambio | ✓ | T-06/T-09 incluyen producto mínimo, regresiones, harness y evidencia; scope exit 0 sin exclusiones ocultas. |
| Storage privado fuera de producción | ✓ | E2E-05:34–35 y E2E-07:22–23 exigen 403 y ausencia de cuerpo privado; diagnóstico HTTP en ambos perfiles. |
| Recursos ajenos mantienen 404 | ✓ | E2E-05:37–40, E2E-06:46–48 y E2E-07:25–27. |
| Upload auténtico alcanza binding, rechazo 403 sin efectos | ✓ | E2E-05:78–95 usa referencia firmada y `_finishUpload`, exige metadata `fil` y compara recursos, archivos y hashes. |
| Permisos se consultan de nuevo | ✓ | `app/Livewire/ContactImports.php:28–29`, `:59`, `:65`; E2E-05:98–100 y Feature:271–285. |
| Esperar `create` antes de rellenar | ✓ | E2E-06:20–24 correlaciona respuesta y reset; conserva validez, rechazo y ausencia de persistencia; barrera real diagnosticada. |
| Errores visibles y búsqueda validada | ✓ | `ContactImports.php:66` usa `validateOnly`; Feature:213–240 exige alerta antes/después de buscar y excepción al superar 150 caracteres. No se afirma prueba UX HTTP de este último límite. |
| Rechazos de archivo sin efectos | ✓ | Feature:220–233 comprueba contactos/importaciones/archivos; caso ausente compara auditoría antes/después. |
| Rechazo SMTP acreditado por mensaje y persistencia | ✓ | E2E-07:39–41 y `resources.php:61`; HTTP 200 o `memo.errors` vacío no se consideran suficientes. Fingerprint ordenado y acotado a A/B sin valores crudos. |
| RED/GREEN y suite declarada | ✓ | [Verificación](../raw/qa2-fix/feature-verification.json): RED2 dos fallos y GREEN2 15/184 con mismo test; formato posterior separado; Feature final 90/977, exit 0. |
| Resultados focales conservan procedencia | ✓ | [Tandas](../raw/qa2-fix/focal-runs.json): primera 6/2, siguiente E07 1/1, diagnóstico separado, final E07 2/0. |
| Observación nativa sin causa inventada | ✓ | [Observaciones](../raw/qa2-fix/network-observations.json); la causa sigue sin demostrar. Correlacionar llamadas no se presenta como arreglo causal probado. |
| Veinte casos, límites, retries y teardown intactos | ✓ | Config 360 s, un worker y cero retries; helpers/B-03/B-04 conservan bytes; siete escenarios B-04 aprobados. |
| GOT-001/GOT-002 sustentados | ✓ | Ambos textos revisados con fuentes y recibos; limpieza acotada a handles propios, nunca al historial ajeno. |
| ADR-003 mantiene alcance futuro | ✓ | Sigue propuesta; entrega, publisher y worker de T-10–T-16 no se presentan como implementados. |
| Constitución, interop, OpenAPI y porcentaje de cobertura | No aplica | No existe constitución ni gate porcentual configurado; el incremento no modifica comandos/agentes/hooks ni contrato OpenAPI. |

## Dictamen fusionado y traspaso

| ID / criterio | Grado | Veredicto | Evidencia |
|---|---|---|---|
| B-01/B-02/D-01/UI-01 y QA-01 | Anteriores | Corregidos, conservados | [Traspaso anterior](attempt-5.md); sin evidencia nueva que los reabra. |
| B-03/B-04 | Important anteriores | Corregidos, conservados | Helpers idéntico a la base; root/ownership y operaciones de cleanup independientes conservados. |
| Nuevos defectos | — | Ninguno confirmado | A+B leyeron todo el incremento y las fuentes afectadas; root verificó de nuevo todos los hashes. |

B verificó además la implementación instalada de `validateOnly`: conserva el
error bag de otros campos. Ejecutó 21 comprobaciones puras PHP/JS y 15
compilaciones Python en memoria, aprobadas. No hubo escrituras, DB, fixtures,
servicios, navegador ni QA3 durante la revisión.

Tras el dictamen se añaden este registro, su snapshot y la traza del ledger;
se promueven exclusivamente GOT-001/GOT-002 con el texto revisado intacto.
ADR-003 continúa propuesta. Estos cambios administrativos posteriores no se
confunden con los bytes del snapshot de revisión. Jira no está configurado.
Siguiente puerta: QA3 completa sobre commit publicado, sin cambios de fuentes
durante la ejecución. Los resultados rojos anteriores permanecen intactos.
