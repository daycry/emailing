# Revisión 23: API de cuenta y correcciones de T-18

Fecha: 2026-10-25. Evidencia histórica del cierre de la revisión, anterior al QA de navegador. El estado actual se consulta en el ledger; este documento no convierte la revisión en QA ni acredita Google/MCP.

## Revisión de dos lentes — intento 23: Fase 4 (T-18) — correcciones verificadas, sin gaps pendientes

Revisión global23, segundo intento de tres para T-18. Lentes **A+B+C+D** con
contexto fresco y fallback genérico heredado: Agent(reviewer) no disponible;
la configuración Opus/high no acredita ejecución de ese modelo. Las cuatro
lentes verificaron prompt, sello y 765 hashes antes/después, ocho archivos
corregidos y sus tests. El cambio de finales de línea del ledger se comparó
normalizado sin alterar la evidencia original. No se reabren criterios previos
sin evidencia nueva. Ningún revisor ejecutó aplicación, SQL, broker, Docker
ni navegador.

Cápsula inmutable `storage/phase4-t18-review23`, manifiesto SHA-256
`c82c60a4949250bc7e130e8993d7698d9ba698a9bfcfc338bd88b283ba156a87`. Raíz verificó sus 765 hashes y las 278 fuentes actuales antes de
esta anotación. Base `dab4b84c7750f79d859762f991b79587f16a3d91`. Puertas previas: alcance, selección,
tier y ledger exit0; 50 entradas de estado, 49 archivos reales y README sin
cambio de contenido normalizado; sin exclusiones, avisos de alcance ni archivos
fuera de alcance. Se conservan cuatro avisos históricos de ledger.

| Tarea/criterio | Veredicto | Evidencia |
| --- | --- | --- |
| T18: repetición concurrente crea un trabajo; credencial inválida/revocada/sin scope rechazada | ✓ conservado | `app/Services/SendingApiService.php:23`, `AccountKeyService.php:36`; `tests/Concerns/ExercisesSendingApiProcesses.php:201`, `ExercisesSendingApi.php:275`. |
| T18: Read-only/Restricted no elevan permisos; cuenta derivada de credencial | ✓ conservado | `app/Services/AccountKeyService.php:106`,`:163`; `tests/Concerns/ExercisesSendingApi.php:176`, `tests/Feature/SendingApiTest.php:161`. |
| T18: endpoints/middleware/políticas/UI | ✓ | `routes/api.php:7`, `app/Http/Middleware/AccountBearer.php:15`, `resources/views/api-keys.blade.php:1`. Policy:20 conserva cuenta/withTrashed/bloqueo; bound/consentimiento:24. Regresiones direct/bound/replay en traitReviewFixes:94,:146,:202. |
| T18: contrato/documentación/ejemplos | ✓ actualizado | OpenAPI:1104, `docs/api/implementacion.md:1`; `app/Support/StrictApiJson.php:58`,`:65` conserva propiedades hasta conversión completa; SendingApiInput:12 rechaza por esquema cerrado. HTTP en traitReviewFixes:20; tipos/números/NUL/duplicados en ApiJsonNumberTest:50. |
| CA01: autenticación/CSRF | ✓ conservado | `app/Services/AccountKeyService.php:24`, `resources/views/api-keys.blade.php:19`, traitExercisesSendingApi:176. |
| CA12: secretos/errores/autorización de datos | ✓ | Evidencia anterior en AccountKeyService:100, ApiError:15, SendingApiTest:142. Nuevo rechazoNUL sin trabajo parcial en traitReviewFixes:49. |
| CA13: cuenta/scopes | ✓ | Evidencia anterior SendingApiService:82, traitExercisesSendingApi:124. Proyección:88 mantiene filtros cuenta/petición y relaciónID; boundajeno en traitReviewFixes:146. |
| CA19: idempotencia/scopes/revocación | ✓ conservado | SendingApiService:23; traitExercisesSendingApiProcesses:201,:235. |
| CA20: concesión/revocación sin borrar trabajo | ✓ conservado | AccountKeyService:106,:133; SendingApiTest:161, traitExercisesSendingApiProcesses:235. |
| Alcance | ✓ | `docs/roadmap/2026-10-21-plataforma-emailing/improvement-plan.md:160`, `tasks.md:833`,`:836`; scope23receipt exit0 sin exclusiones/avisos/fuera de alcance. |
| Tests/verificación ejecutada | ✓ | `tasks.md:5142`: literal28/767, unit3/244, focal19/282, todos0. Stdout/recibos y262 archivos de ZIP/manifiesto contrastados contra source/. Aserciones nuevas leídas; verde no sustituye análisis. |
| Constitución | N/A | No existe docs/CONSTITUTION.md. |
| E2/E3 generados/interoperabilidad | N/A | No commands/agents/hooks. |
| Cobertura porcentual | N/A | Sin configuración; no se inventa porcentaje. |

| # | Grado | Gap | Tarea | Corrección | Evidencia |
| --- | --- | --- | --- | --- | --- |
| AB-01 | Important histórico, resuelto | Propiedad JSON válida iniciada por NUL producía503 | T-18 | Corregido: acumular propiedades antes de convertir a objeto; el esquema cerrado conserva su rechazo | `app/Support/StrictApiJson.php:58`, `tests/Concerns/ExercisesSendingApiReviewFixes.php:20`, `tests/Unit/ApiJsonNumberTest.php:50`; seis422, dos400, cero trabajo parcial |
| D-01 | Important histórico, resuelto | Status hidrataba cuerpos completos para utilizar email | T-18 | Corregido: proyección id/email, sin cambiar respuesta | `app/Services/SendingApiService.php:88`, traitReviewFixes:57; strings hidratados40.011.392→5.792 bytes, respuesta17.115 bytes idéntica |
| D-02 | Important histórico, resuelto | Búsqueda histórica repetía escaneos dentro del bucle | T-18 | Corregido: índice no único(account_id,email) y consultas email/PK separadas bajo el mutex; merge por PK | `app/Services/ApiRecipientPolicy.php:20`, migración000014:13, traitReviewFixes:94,:146,:202; directos1.000.301→301 lecturas, seis escenarios bound5–7; probe índice+OR permanece rojo |

**Recepción y fusión:** los tres gaps previos están corregidos, sin rebate ni
deuda aceptada. No hay gaps nuevos/reabiertos. Total pendiente: **0 Critical,
0 Important, 0 Minor**. Root ya admitió 11 cápsulas conservando seis rojos y
cinco verdes: literal API28/767, unit3/244, focal19/282 y runtime/settlement0,
con manifiesto final común de262 fuentes. Las métricas están ligadas a sus
fixtures, recibos y fuentes; no se afirma latencia, capacidad máxima u OOM.
Delivery24 sigue siendo regresión anterior al fix, no evidencia del fix actual.

GOT-008/009/010/011 se promueven con traza de esta revisión y su índice en el
mismo cambio; solo cambia metadata de aceptación. **T-18 sigue en-progreso**:
esta revisión no acredita E2E-10 ni QA. Principal17/20 y Google/MCP0/14
conservan todo su alcance; M01/M02 siguen pendientes humanas. Jira no está
activado; no se publica nada externo.

Worklog `[revisión]`: medidor review23 cerrado antes de QA. Tokens, coste y
horas IA reales siguen desconocidos; no se convierten minutos de reloj en
horas IA. Recibos privados de puertas, prompts, veredictos completos y cierre
en `storage/phase4-t18-review23-control/`, ignorado por Git.

Siguiente paso: QA formal E2E-10 en Laragon y Docker con fuentes calificadas,
guardrail local, evidencia sin secretos, qa-gate real e informe/PDF. Solo tras
QA se podrá cerrar T-18 y realizar commit/push del incremento validado.
