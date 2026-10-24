# Revisión 19 — T-16, intento 2 de 3

Fecha: 24-10-2026. **Sin gaps pendientes: 0 Critical, 0 Important y 0 Minor.**
Corrieron A, B, C y D con contexto fresco, mediante el fallback de revisores
genéricos de custom-agents. El runtime fue el heredado; el frontmatter del
plugin no demuestra que se ejecutara un modelo concreto.

## Alcance

Base `0cf2bd5bc42e8090e42f2b20a67b3e143bf7929d`: 87 archivos y 7.365 líneas
de diff. Las cuatro lentes recorrieron el incremento completo. Frente a la
revisión 18 hay 26 archivos idénticos, 20 modificados y 41 nuevos; se conservó
su tabla de veredictos, sin reabrir aprobados sin evidencia nueva.

Scope terminó con código 0, sin archivos fuera de alcance, avisos ni
exclusiones de usuario. C se activó por `.dockerignore`; D, por
SchedulerTriggerTest y WorkerRecoveryTest. No hay constitución, cambios de
interop ni una especificación OpenAPI modificada. No se midió porcentaje de
cobertura de código; la trazabilidad de criterios no se presenta como tal.

Root contrastó los 3.375 hashes de la cápsula privada, cuyo sello SHA256 es
`783dff7a2e93ad0bb49d7a632d787e67ecd5b5a0dfe1cbbb22203e74d9b7d6e7`.
Contiene 1.413 fuentes candidatas y 1.931 archivos de evidencia, además de
preimágenes y metadatos. Se verificaron también las 235 entradas ejecutadas
contra el árbol actual, incluidas las tres traducciones de `lang/`.

## Dictamen por lente

| Lente | Resultado y evidencia |
|---|---|
| A — requisitos | 87/87. Criterios cerrados T-01–T-15 y sus ejecuciones conservados; T-16 y MCP mantienen estados reales. Contraste de recibos, streams, regresiones y relación histórica/final. Sin gaps nuevos. |
| B — corrección | 87/87. Confirmación por request, invalidación por composición o cambios posteriores, traducciones y procedencia revisadas. JSON contrastados; PNG por estructura/CRC y PDF leído, sin aceptación visual humana. Sin defectos confirmados. |
| C — seguridad introducida | 87/87. Permisos/revisión/confirmación preservados, lecturas y montajes delimitados, exclusión de trazas privadas y proyección pública revisadas. Sin vulnerabilidad explotable introducida identificada. No es una auditoría integral. |
| D — rendimiento introducido | 87/87. El cambio Livewire tiene coste constante; los inventarios y esperas pertenecen a fixtures. Sin N+1 o degradación introducida demostrable para 100–200 mensajes/día y 5–10 workers. No se ejecutaron cargas ni se acredita capacidad medida. |

## Criterios y evidencia

| Criterio revisado | Veredicto | Evidencia |
|---|---|---|
| Verificaciones T-01–T-15 cerradas | ✓ conservado | Ledger y dictamen 18: base/ACL, cuentas/auth, Teammates, recursos/CSV, plantillas/transporte, bajas/campañas, outbox/workers, actividad y Cronicle. Ninguna ejecución del revisor se usó para suplir evidencia ausente. |
| Ventanas de fallo y recuperación | ✓ backend; aceptación integral pendiente | Grupo literal `php artisan test --group=delivery`: 139 aprobadas, 2.009 aserciones, exit 0; discovery real 139 IDs únicos/nueve clases. |
| Sin pérdida silenciosa ni promesa exactly-once SMTP | ✓ en el alcance revisado | OutboxIntegrationTest, WorkerRecoveryTest y guía de QA conservan durabilidad, fencing, ACK posterior, límites y aceptación ambigua sin retry ciego. |
| P-02: confirmación explícita y fecha en el mismo batch | ✓ | Campaigns.php:53,164–177; CampaignTest.php:343 prueba ambos órdenes reales del payload. |
| Confirmación falsa, fecha posterior y composición pendiente | ✓ | CampaignTest.php:361; la composición requiere guardar/revisar y la fecha posterior invalida una confirmación anterior. |
| P-01: validación española y recuperación | ✓ | validation.php:13; ActivityTest.php:379 y ContactListTest.php:204; se comprueban mensajes y corrección en la misma instancia. |
| Procedencia de traducciones | ✓ | Recorder, inventarios, comparador, fixtures y ambos montajes Linux incorporan `lang/`; 25 controles nativos y 10 Linux aprobados. |
| Regresión causal ejecutada | ✓ | RED 3 fallos/11 aserciones, GREEN 3 aprobadas/45 aserciones; consentimiento 1/19. El GREEN focal conserva relación histórica con el manifiesto final. El snapshot RED de 232 entradas no acredita bytes antiguos de `lang/`. |
| Perfiles e inventarios finales | ✓ | Mismo manifiesto de 235 fuentes para perfiles, discovery y grupo; 45 documentos runtime contrastados con esperados independientes. |
| D-02: cambio de cuenta del driver | ✓ sólo regresión aislada | Antes: observado A/esperado B, exit 1. Candidato: A→B→A, exit 0. No equivale a nuevo QA de aplicación. |
| Estados y límites del roadmap | ✓ | T-16 abierta, T-17–T-20 pendientes, MCP 0/14; ADR-006 propuesta. QA anterior NO-VERDE conservado. |
| Nuevo E2E-08/E2E-09 y M-01 | Pendiente | Esta revisión no ejecuta navegador ni aprueba el juicio visual de una persona. |

El grupo final conserva duración de runner 4.056,19 s y manifiesto
`10128ad501b6c3f0266896c23b4b87a76f405b284de2a37d6c6a4ec79f242120`.
No cambió ninguna fuente durante su ejecución. Se admitieron 20 runs con sus
ZIP, recibos, salidas y atribuciones; 13 como ejecuciones aprobadas y siete
como diagnósticos/observaciones, sin cambiar sus resultados originales.

Las seis auditorías finales terminaron con código 0. Verificaron 21 recibos
de teardown, 47 nombres de contenedor UUID sin runtime restante, 71
observaciones pasivas de broker ausentes y cinco mensajes atribuidos ausentes
en Mailpit. Se retienen dos tags de imagen como artefactos. SQL comprobó la
identidad de pruebas: `migrations: 0`; las demás tablas comprobadas estaban
ausentes y se mantienen como `null`. La comprobación pasiva de broker cubre
los namespaces registrados de workers/perfiles; outbox conserva su limpieza
síncrona en `finally`, sin atribuirle una auditoría pasiva de nombres no
registrados.

## Cierre y siguiente paso

No hubo gaps que fusionar, correcciones requeridas ni rebates nuevos.
GOT-007 pasa a aceptada por esta revisión; ADR-006 sigue en propuesta porque
la implementación OAuth/MCP y sus clientes reales no se han validado.

Ventana de revisión: 15:07:47Z–15:19:09Z del 24-10-2026, cerrada después de
fix1 y antes de QA. Tokens reales, coste y horas IA permanecen `null` por
falta de transcripciones; el reloj no se convierte en consumo.

**T-16 sigue en progreso, 15/20; MCP 0/14.** El siguiente paso es el primer
intento del nuevo ciclo QA autorizado, con runtime y datos nuevos por caso y
retry. M-01 requiere una persona. No se ejecutaron aplicación, SQL, Docker,
HTTP, SMTP o navegador durante estas lentes, ni se publicó en Jira/Confluence.
