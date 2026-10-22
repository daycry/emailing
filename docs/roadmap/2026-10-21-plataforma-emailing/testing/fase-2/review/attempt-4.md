# Revisión de dos lentes — intento 4 · T-09 / reparación B-03

**Cero Critical y un Important pendiente.** Primer intento del incremento
específico B-03, tras la decisión del orquestador registrada en el
[intento anterior](attempt-3.md). La corrección focal de B-03 cumple su objetivo,
pero introduce una dependencia incorrecta entre limpiezas independientes.
No comienza QA2 ni se cierra T-09.

| Campo | Evidencia |
|---|---|
| Base Git | `9109d87749f86d300d927e34de1d96e7c06ab6af` |
| Preimagen | Snapshot de revisión 3; 28 fuentes QA y tres fuentes adicionales recuperadas en memoria con sus hashes originales |
| Incremento | 16 archivos, 54.636 bytes, 572 líneas |
| SHA256 | `c801a6a284ed0b9cea3e2dce639daeecf7441ceb5f8e3de1200417789ce3132e` |
| Snapshot | [patch](attempt-4.patch) · [manifiesto](attempt-4.patch.json) |
| Alcance | exit 0; 145 cambios incluidas evidencias, cero fuera de alcance/avisos/exclusiones de usuario |
| Lentes | A+B; selector C=false, D=false |
| Integridad | Ambas leen el incremento completo; 42/42 fuentes congeladas intactas al principio y al final; A verifica además 16/16 preimágenes y 36/36 hashes del handoff B-03 |
| Roles | Revisores genéricos de contexto fresco; tier frontmatter resuelto, runtime heredado por ausencia de reviewer nombrado y override Claude literal |
| Medición | 2026-10-22T22:35:48Z–22:42:31Z; tokens, coste y horas IA reales desconocidos/null |

## Criterios de la lente A

Las rutas siguientes parten de `testing/fase-2/`.

| Criterio | Resultado | Evidencia |
|---|---|---|
| Raíz efectiva por runtime | ✓ | `temporary-uploads.php:18`; RED confirma disk contact-imports y directory temporary |
| Handle exacto ligado a run/actor/cuenta/contenido | ✓ | `temporary-uploads.mjs:19`, `temporary-uploads.php:66` |
| Normalización limitada de la representación firmada HTTP | ✓ | `temporary-uploads.php:49`; probe privado conserva las dos firmas válidas |
| Rechazo de rutas, identidad, firma, configuración o hash alterados | ✓ | `temporary-uploads.php:31`, `:137`; GREEN focal |
| Escape real y conservación de ajenos | ✓ | `b03-green.mjs:16`, `b03-junction-probe.mjs:5`; junction Windows y symlink Docker |
| Upload sin importar y diagnóstico atribuibles | ✓ | `specs/E2E-05.spec.mjs:24`, `diagnostic.mjs:18` |
| Cleanup tras revocación y eliminación SQL | ✓ | `b03-teardown-order.mjs:13`; dos archivos eliminados por perfil, exit 0 |
| Históricos preservados y declaraciones honestas | ✓ | `finalize-extras.py:30`; [informe focal](../raw/b03-fix/report.md) |
| Ejecución real de pruebas declaradas | ✓ | RED exit 1 esperado; GREEN4 y orden de teardown exit 0; fallos intermedios preservados |
| Alcance declarado y estados fieles | ✓ | Ledger T-09 en progreso, alcance y verificación sin cierre anticipado |
| Bytes publicados idénticos sin ocultar whitespace de código | ✓ | `.gitattributes`; A comprueba 38/38 fuentes QA con blobs idénticos con/sin filtros de Git |

## Dictamen fusionado y traspaso

| ID | Grado | Veredicto | Evidencia / escenario |
|---|---|---|---|
| B-01/B-02/D-01/UI-01 | Important anteriores | Corregidos, conservados | [Intento 2](attempt-2.md); sin evidencia nueva que los reabra |
| QA-01 | Important anterior | Corrección focal aprobada, conservada | Navegación publicada en `9109d87`; QA2 completa pendiente |
| B-03 | Important anterior | Objetivo focal corregido y aprobado | Raíz efectiva, handles y cleanup probado en ambos perfiles; históricos no atribuibles preservados |
| B-04 | Important nuevo | Pendiente de corrección | `specs/helpers.mjs:17`: una excepción en cleanupTemporaryRun interrumpe la limpieza independiente de archivos y fixtures SQL |

**B-04 verificado por el orquestador.** La nueva primera sentencia del `finally`
puede lanzar al detectar un hash o ruta alterados. Las llamadas posteriores a
`cleanup-owned.py`, `resource('precleanup')` y `command('cleanup')` ya no se
ejecutan. B reprodujo el control de flujo en memoria con operaciones sustituidas
por stubs: error `Registered temporary content changed`, llamadas
`["temporary"]`. Sin importar el módulo ejecutable, crear archivos ni tocar
servicios. La lectura de la función congelada confirma el escenario.

Se devuelve al implementador del harness para aislar esas operaciones y propagar
los errores después de intentar las limpiezas independientes. El siguiente será
el intento 2 de 3 de este incremento, quinto registro histórico. No se acepta
deuda ni se relajan aserciones. Readiness E06 sigue provisional, QA1 conserva
8 aprobadas/12 fallidas, QA2 y M-01 humano siguen pendientes. No hay Jira
configurado ni publicaciones externas asociadas a esta revisión.
