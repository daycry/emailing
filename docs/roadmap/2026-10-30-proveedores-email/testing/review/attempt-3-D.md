# Revisión adversarial — lente D — intento 3 de 3

**Veredicto: sin hallazgos de rendimiento en el delta posterior al intento 2.** Critical: 0; Important: 0; Minor: 0.

Revisor de contexto fresco; subagente genérico con modelo heredado porque el reviewer nativo no está disponible. Aplicados `adversarial-review` 2.3.0 y su prompt literal D. Base recibida: `74970e0af4c5e68bd3b8e139a0988a119208493e`, contra worktree con nuevos públicos. La puerta de alcance comunicada fue exit 0, sin fuera de alcance, criterios alterados ni avisos; no se ejecutó de nuevo ni se atribuye como comprobación propia.

## Traspaso y alcance

Leídos el informe original `docs/roadmap/2026-10-30-proveedores-email/testing/review/attempt-2-D.md` y la fusión del intento 2 en `tasks.md:368`. Se preserva D2 sin hallazgos y sus límites. Las correcciones TLS/AUTH/documentación de las otras lentes ya quedaron juzgadas; no se reabren. Alcance productivo nuevo: el parche completo `storage/providers-email-20261030/review-3-production.patch`, únicamente `DeliveryLifecycle.php` (guardas de feedback previo antes de inicio y finalización). Se siguieron los llamantes y los índices necesarios para evaluar su coste. También se revisaron las dos regresiones añadidas, los cambios E2E contra la copia original QA1 y el ajuste del oráculo de actividad.

## Comprobaciones

| Criterio D | Resultado | Evidencia y escenario de carga |
|---|---|---|
| Consulta de feedback antes del reintento | ✓ Sin hallazgo | `app/Services/DeliveryLifecycle.php:81` añade un `exists()` sólo cuando `attempts > 0`. `:221` filtra por cuenta y entrega; `database/migrations/2026_10_23_000011_add_activity_provenance.php:20` conserva el índice único con prefijo `delivery_id`. No necesita recorrer el historial completo de la cuenta ni materializa todos los mensajes. Para 200 entregas/día y un reintento por entrega son 200 consultas adicionales, una por inicio candidato, sobre los intentos de su entrega; no 200 consultas por entrega. El límite configurado de intentos se acota a 20 en `DeliveryLifecycle.php:195`. No se identifica degradación medible con esta carga a partir de la lectura estática. |
| Consulta al finalizar | ✓ Sin hallazgo | `DeliveryLifecycle.php:187` sustituye el `exists()` sobre mensaje actual por el de la entrega completa; no añade otra consulta. El acceso indexado amplía la comprobación al pequeño conjunto de intentos de una entrega. Incluso con 20 intentos por entrega, no introduce un recorrido por todas las entregas ni consultas por cada intento histórico. `MailMessage.php` no añade scopes globales ni relaciones automáticas a esa lectura. |
| Mutex, bloqueos y E/S | ✓ Sin hallazgo | `AccountMailTransport.php:75`–137 ejecuta el inicio bajo mutex de cuenta y llama a `beforeStart` en `:85`; la E/S externa sigue después del cierre, en `:158`. `finish` abre una transacción posterior en `DeliveryLifecycle.php:181`–216. El webhook usa el mismo mutex en `app/Services/MailProviders/ProviderWebhookService.php:22`, antes de actualizar feedback en `:105`. Con 5–10 workers de una cuenta, el delta sólo agrega la lectura indexada al reintento; no mantiene el mutex durante red, sleeps o espera de backoff. La serialización de cuenta preexistía y no se reabre sin evidencia nueva. |
| N+1 y trabajo repetido | ✓ Sin hallazgo | El parche productivo no añade bucles ni llamadas desde los bucles del webhook. `hasProviderFeedback` se llama una vez por cada guarda aplicable. Se conserva la terminación anticipada que evita otro inicio cuando ya existe feedback; no añade polling ni espera activa. |
| Cambios de pruebas | ✓ Sin hallazgo productivo | `tests/Feature/MailProviderDeliveryTest.php:177` y `:200` añaden dos escenarios finitos con transporte simulado; el segundo sólo envía dos intentos. La comparación directa de `tests/e2e/mail-providers.spec.mjs` con `testing/qa-1/sources/mail-providers.spec.mjs` muestra cambios de selectores y una espera de navegación (`:118`), sin ampliar timeouts, retries ni carga productiva. `tests/Feature/ActivityTest.php:160` exige la fila `api_accepted`; el ajuste de cardinalidad no modifica consultas productivas. |

## Evidencia y límites

Validación propia exclusivamente estática. No se ejecutaron pruebas SQL, E2E, red, proveedores reales ni benchmarks; no se inventaría capacidad de 5–10 workers o de lotes de 1000 referencias. La única escritura fue este informe. No se leyeron entornos privados, solicitudes privadas, archivos de claves, snapshots SQL ni las rutas expresamente prohibidas.

Recibos públicos leídos, sin atribuirlos como ejecuciones propias:

- `storage/providers-email-20261030/late-feedback-green-20261031T001013302259Z/test-summary.json`: exit 0, 3 casos/38 aserciones, cero fallos/errores/omitidos.
- `storage/providers-email-20261030/prior-feedback-green-20261031T001220176603Z/test-summary.json`: exit 0, 1 caso/11 aserciones, cero fallos/errores/omitidos.

Estos recibos no miden throughput ni percentiles. QA1 permanece rojo en su evidencia original; QA2 y la regresión global seguían pendientes/en marcha al recibir el encargo. Esta revisión no les atribuye aprobación. No hay evidencia nueva para reabrir las limitaciones D1/D2 como defecto.

```json
{
  "lens": "D",
  "attempt": 3,
  "status": "complete",
  "verdict": "sin hallazgos",
  "gaps": [],
  "counts": {"Critical": 0, "Important": 0, "Minor": 0},
  "base": "74970e0af4c5e68bd3b8e139a0988a119208493e",
  "target": "worktree including public untracked files",
  "scope": "delta after attempt 2; approved and rebutted findings preserved",
  "reviewer": "generic inherited fallback; native reviewer unavailable",
  "own_tests_executed": [],
  "benchmark_performed": false,
  "global_qa_approved": false,
  "report": "storage/providers-email-20261030/review-D3.md"
}
```
