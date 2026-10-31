# Lente A — T-07 documental — intento 2 de 3

Fecha: 2026-10-31. Contexto fresco; revisor genérico heredado porque el agente nativo reviewer/Opus no está disponible. Se aplicaron el contrato y el prompt literal A de `custom-agents:adversarial-review`, incluido el criterio de prosa del fragmento compartido `docs-style.md`.

**Veredicto: sin gaps nuevos ni pendientes en las correcciones revisadas. 0 Critical / 0 Important / 0 Minor.** A1-01, A1-02 y A1-03 quedan corregidos y revalidados documentalmente. Este resultado no cierra T-07: la QA visual independiente de las 57 páginas sigue pendiente, expresamente declarada.

## Alcance e identidad

Revisión limitada a las correcciones del intento documental 1 y sus efectos. Se leyeron la tabla y los informes previos completos A/B/C/D, su fusión en `tasks.md:482`, los criterios CA-19–CA-22 (`spec.md:163`, `tasks.md:456`) y el plan T-07 (`improvement-plan.md:285`). Los aspectos aprobados anteriormente se conservan sin reabrirlos. T01–T06, revisión de código 3 y QA3 permanecen aceptados.

La fuente y el PDF identificados por los recibos finales y `docs/manual/verificacion.json:2` son:

- Markdown: `0b8d519494af8efecca184a56ed987d671b2a97a4597467f07fa551aad8494fe`.
- PDF: `330446b36205f3e63a21f021e44de8b174a7fd5636d7185ffe60ee1b57d39cd7`; 57 páginas.

Los resultados de exportación/validación citados son evidencia preexistente del orquestador, leída por esta lente; no son ejecuciones nuevas de este revisor. No se instalaron herramientas ni se ejecutaron comandos del procedimiento, SQL, E2E, builds, generadores, renderizadores o verificadores para suplir evidencia pendiente. Solo se escribe este informe.

## T-07 → criterios y revalidación

| Criterio | Resultado | Evidencia y límite |
|---|---|---|
| CA-19 — Docker desde cero, privacidad, persistencia, primer acceso, procesos y separación QA | ✓ conservado | Aprobado en A1; no hay evidencia nueva que justifique reabrirlo. El cambio Laragon remite a los pasos previos existentes y mantiene la separación entre operación y QA. |
| CA-19 — Laragon desde cero y requisitos efectivos; A1-01 | ✓ corregido | `docs/manual/guia-completa.md:380`–454 añade obtención/selección de Laragon y PHP 8.4.1+, terminal con PATH heredado, Composer 2, Node 22.12+, identificación de ejecutables, versiones, ini y módulos. Explica cómo corregir extensiones y exige `check-platform-reqs --lock` antes de instalar. `scripts/composer.ps1:4`–7 y `scripts/php.ps1:6`–9 confirman la carga de sockets/ini y la invocación del ejecutable del PATH. El resultado observable y la prohibición de ocultar requisitos fallidos aparecen en `guia-completa.md:449`. No se exige instalación real para aceptar esta corrección documental. |
| CA-20 — inventario, documentos dueños, 37 MCP, 71 variables, ejemplos, glosario y límites | ✓ conservado | Se conserva el resultado previo; no se pretende que un recuento pruebe por sí solo el significado de todas las opciones. Los cuatro huecos concretos se contrastan en la fila siguiente. |
| CA-20/CA-22 — cobertura autosuficiente concreta; A1-02 | ✓ corregido | `guia-completa.md:678` explica Recordarme, equipo compartido y permisos. `:1043`–1071 explica cada campo y acción SMTP genérica, puerto 587/rango, TLS, blank-preserve, clear prioritario y separación respecto a Brevo/SendGrid. Coincide con `MailSettingsService.php:57`–70 y la vista `mail-settings.blade.php:11`. `guia-completa.md:1907`–1930 explica memoria PHP 256M por proceso y logging de infraestructura json-file/10m/3, distinguiendo volúmenes y logs de aplicación; coincide con `docker/emailing-uploads.ini:6` e `infra/compose.yaml:4`–7. Las guías dueñas están alineadas en `cuentas-y-equipo.md:13`–17 y `plantillas-y-transporte.md:98`–106. |
| CA-21 — fuente común, Mermaid, texto y paginación | ✓ técnico, visual pendiente previsto | Los recibos `fix1-final1/validation.json` y `fix1-final2/validation.json` registran PASS, 57 páginas, 24 capítulos, 8 diagramas, 919/919 bloques y cero etiquetas omitidas, texto fuera, cuerpo pequeño o problemas. La segunda pasada coincide con el hash PDF canónico. El metadato público conserva mapas TOC idénticos (`verificacion.json:15`). Estas mediciones no acreditan ausencia de solapes visuales. |
| CA-21/CA-22 — enlaces utilizables del PDF; A1-03=B1-01 | ✓ corregido | `exportar.mjs:54`–78 transforma los enlaces relativos antes del render, conserva anchors internos, verifica existencia y pertenencia del destino público y produce HTTPS de la rama del repositorio. El recibo final registra 26 referencias estables; por ejemplo, la matriz se dirige a `/blob/feature/plataforma-emailing/docs/manual/cobertura-documentacion.md`. `verificar.py:74`–79 rechaza referencias al origen temporal o referencias estables esperadas ausentes. Ambos recibos finales registran cero enlaces del renderer y cero referencias estables faltantes. `docs/manual/README.md:60`–65 explica conexión/acceso al repositorio. Su publicación sigue siendo posterior al manual, conforme al encargo. |
| CA-21 — revisión visual de todas las páginas | Pendiente previsto | `docs/manual/verificacion.json:19`, `verificar.py:105` y `tasks.md:491` mantienen explícita la QA independiente pendiente. `tasks.md:440` conserva T-07 en progreso. No se afirma cierre ni se convierte esta puerta posterior en gap de este intento. |
| CA-22 — guías/índices y enlaces Markdown coherentes | ✓ en las correcciones | Las guías dueñas incluyen el contenido añadido; la matriz `cobertura-documentacion.md:252` y `:256` queda sustentada en el manual. `links-current.json` acredita 34 páginas, 1015 referencias locales y cero errores en la instantánea final. |
| CA-22 — privacidad, evidencia y commits/push posteriores | ✓ documental / cierre pendiente previsto | El delta leído no incorpora secretos ni datos reales. `guia-completa.md:2230`–2236 conserva el límite entre procedimiento y ensayo realizado. `verificacion.json:20` declara instalación no ejecutada. Se mantienen los pendientes principal 18/20, MCP 11/14 y M-01/M-02 (`cobertura-documentacion.md:262`); no se exige su cierre para el manual. QA visual, cierre del ledger y publicación siguen siendo pasos posteriores. |

## Estado completo de los gaps previos

| ID previo | Severidad previa | Estado en DOC2 | Evidencia principal |
|---|---|---|---|
| A1-01 | Important | Corregido y revalidado | Manual:380–454; wrappers Composer/PHP. |
| A1-02 | Important | Corregido y revalidado | Manual:678, :1043–1071, :1907–1930; guías dueñas y fuentes públicas. |
| A1-03 / B1-01 | Important, duplicado fusionado | Corregido y revalidado | Exportador:54–78; verificador:74–79; ambos recibos finales. |
| C1 / D1 | Sin hallazgos | Conservado, sin reapertura | No corresponde ampliar la lente A a auditoría de seguridad o rendimiento. |

No hay gaps adicionales.

## Comprobaciones transversales de la lente A

| Comprobación | Resultado |
|---|---|
| Tarea hecha frente a implementación/verificación | T-07 sigue en progreso; hay evidencia preexistente para exportación/paridad/enlaces. No se afirma ejecutada la QA visual, instalación ni publicación. No existe verificación cerrada sin evidencia en este delta. |
| Alcance y criterios | La corrección cae en `docs/manual/**` y guías declaradas por `tasks.md:448`. `scope-review2.json` registra base `74970e0`, 343 cambios, cero fuera de alcance, criterios alterados o avisos; incluye el alcance anterior aceptado. No se reabre ese runtime. |
| Constitución | No existe `docs/CONSTITUTION.md` en la ruta comprobada. No se inventan principios. |
| Generado y documentación | Los recibos finales y el metadato público corresponden a la fuente corregida y PDF de 57 páginas. No hay cambios T07 en `commands/`, `agents/` o `hooks/`; el check interop no aplica. |
| Contrato OpenAPI | El delta de corrección documental no cambia specs OpenAPI. No aplica reejecutar lint/diff ni reabrir T01–T06. |
| Puertas relajadas | No se observan tests/assertions/umbrales debilitados por estas correcciones. El verificador incorpora guardas de enlaces; conserva la exigencia de QA visual. |
| Prosa | Fragmento `docs-style.md` leído. Los añadidos usan acciones concretas, tablas para campos y valores, comandos existentes y límites explícitos. Sin violaciones nuevas citables que reportar; no se introducen preferencias de estilo como gaps. |

Siguiente puerta prevista: QA visual independiente del artefacto completo, seguida de cierre documental y commits/push según el orden autorizado.
