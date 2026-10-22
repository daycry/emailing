# Revisión de dos lentes — intento 2 · T-09 / fase 2

**Correcciones aprobadas: cero Critical/Important abiertos.** A+B+D, independientes
y de contexto fresco. QA de navegador sigue pendiente y T-09 en progreso.

| Campo | Evidencia |
|---|---|
| Base | `db18d4a7499fa9b1c9d2baae9ac12f74d9c81966` |
| Diff incremental | 12 archivos, 38.509 bytes, 458 líneas |
| SHA256 | `920ff79542e9ec722889b0d11c3b5f5818091cf2476e132e0285817b30d59da5` |
| Artefactos inmutables | [patch](attempt-2.patch) · [manifiesto](attempt-2.patch.json) |
| Alcance | exit0; 26 archivos incluidas evidencias; cero fuera de alcance, avisos o exclusiones de usuario |
| Selector | C=false, D=true por nueva migración; C no se repite sin disparador nuevo |
| Roles | Subagentes genéricos por falta de `subagent_type: reviewer`; tier frontmatter resuelto, runtime heredado |
| Integridad | Las tres lentes leyeron el diff entero y verificaron 12/12 hashes; fuentes sin cambios durante la revisión |

## Veredictos y traspaso completo

| ID / criterio | Grado anterior | Veredicto | Evidencia |
|---|---|---|---|
| B-01 · cuerpos permitidos | Important | Corregido | `MailInput:19`, `TemplateService:50`, `AccountMailTransport:28`; HTML-only/text-only y tipos cubiertos. B ejecutó probes puros con ausencia/null/`0`, ambos vacíos y arrays. |
| B-02 · ubicación de variables | Important | Corregido | `TemplateRenderer:41–65`; marcas raw antes de DOM. B reprodujo rechazo en atributo/comentario/script y positivo con entidades literales y escape. |
| D-01 · conteos por lista | Important | Corregido | Migración000006:12, `ContactListTest:93`, planes poblados antes/después; acceso `index` pasa a `ref` por list_id, contactos `eq_ref`, diez conteos de1.000. |
| UI-01 · coherencia tras retirar lectura | Important, confirmado al corregir | Corregido | `Contacts:43` y `ContactListTest:55`; guard de entrada anterior a seis acciones, sin cambiar servicios write. No se acredita escalada de escritura. |
| Fechas antiguas | No confirmado | No reabierto | No había evidencia de fallo MySQL; sin evidencia nueva. |
| Handles temporales Livewire | No confirmado | No reabierto | No se había confirmado explotación; sin evidencia nueva. |
| A anterior · requisitos restantes T05–T08 | Aprobados | Conservados | Tabla por criterio siguiente; sin reabrir aprobados sin nueva evidencia. |
| C anterior · seguridad | Sin hallazgos | Conservado | Selector incremental no activa C; B-02 permanece defecto contractual, sin convertirlo en vulnerabilidad. |

## Lente A por criterio

| Tarea / criterio | Veredicto | Evidencia |
|---|---|---|
| T05 · recursos compartidos con teammate autorizado | ✓ Conservado | `ContactListTest:137` |
| T05 · cuenta ajena o scope insuficiente | ✓ UI-01 corregido | `Contacts:43`, `ContactListTest:55` |
| T05 · conteos con volumen | ✓ D-01 corregido | Migración000006 y plan poblado |
| T05 · verificación ejecutada | ✓ | stdout42/531 |
| T06 · resumen CSV y aislamiento | ✓ Conservado | `ContactImportTest:89`, regresión completa |
| T06 · consentimiento con origen/fecha | ✓ Conservado | `ContactImportTest:146` |
| T06 · verificación ejecutada | ✓ | Ledger13/164 y stdout global |
| T07 · aislamiento de scripts/sandbox | ✓ Conservado; navegador aún pendiente | `TemplateTest:240` |
| T07 · variables resueltas/escapadas | ✓ B-02 corregido | `TemplateRenderer:41`, `TemplateTest:93` |
| T07 · cuenta y scopes de preview/prueba | ✓ Conservado | `TemplateService:110/131`, `TemplateTest:319` |
| T07 · contenido permitido enviable en laboratorio | ✓ B-01 corregido | `TemplateTest:78`, `MailTransportTest:72` |
| T07 · verificación ejecutada | ✓ | stdout42/531 |
| T08 · log privado y descarga autorizada | ✓ Conservado | `MailTransportTest:97` |
| T08 · secretos/TLS inválido | ✓ Conservado | `MailTransportTest:148/331` |
| T08 · aceptación no equivale a entrega | ✓ Conservado | `MailTransportTest:273` |
| T08 · requisitos para envío externo | ✓ Conservado; continúa deshabilitado | Ledger T08, sin cambio habilitador |
| T08 · verificación ejecutada | ✓ | Evidencia original y42/531 |
| T09 · matriz/E2E completos | Pendiente declarado | No se cierra con backend verde |
| T09 · revisión sin Critical/Important | ✓ Tras fusión A+B+D | Este informe |

No existe CONSTITUTION ni cobertura porcentual configurada. No aplica generación
interop, OpenAPI ni guía de prosa al diff de implementación/pruebas. No se inventa
porcentaje a partir del número de tests.

## Evidencia y corrección de cita

Ver [fix-1](fix-1.md) y [stdout/planes originales](raw/phase2-feature-fix1.txt).
88 pruebas/957 aserciones, Pint y build verdes no sustituyen QA. La retirada
posterior de `required` en HTML está declarada; se comprobará solo texto desde
el formulario real. Las lentes no ejecutaron suites, fixtures, DML, migraciones,
build ni navegador. B hizo probes PHP puros; D contrastó los planes existentes.

D detectó una cita distinta del archivo final: el JSON posterior preservado
contiene **0,0496152s**, frente a0,0417354s citado al despachar. Se han alineado
ledger y fix-1 después de la revisión, sin alterar el patch ni los raw. El JSON
se regeneró durante la suite completa posterior a la pasada focal; el anterior
conserva0,1240237s. Son mediciones puntuales, sin inferir un benchmark general.
No es defecto nuevo de rendimiento ni dato medido por el revisor.

Ventana2026-10-22T20:51:05Z–20:56:31Z cerrada. Tokens/euros/horas IA reales null.
Siguiente paso: commit/push normal de correcciones y QA en native8082/Docker8083.
