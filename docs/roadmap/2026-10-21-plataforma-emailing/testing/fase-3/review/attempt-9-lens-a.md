Lente A · revisión global 9 · intento acotado 1/3 · base `5a1a52f…` · **18 archivos**. Fallback genérico, runtime heredado. **Sin gaps nuevos: 0 Critical / 0 Important / 0 Minor.**

Las rutas siguientes corresponden a las copias congeladas bajo `storage/phase3-t11-review1/sources/`.

| Criterio | Veredicto | Evidencia leída |
|---|---|---|
| T-11: borradores/revisadas/futuras no son elegibles prematuramente | ✓ componente | `app/Services/CampaignService.php:284`; `tests/Feature/CampaignTest.php:33`: estados distintos, fecha efectiva y ausencia de jobs/mensajes. |
| T-11 / CA-05: comportamiento del worker literal | **no verificable** | Worker pendiente de T-12/T-13/T-16; reconocido en `docs/roadmap/2026-10-21-plataforma-emailing/tasks.md:1928`. No se atribuye esa cobertura a `due()`. |
| T-11: destinatarios, plantillas y remitentes pertenecen a la cuenta | ✓ | `app/Services/CampaignService.php:77`; IDs extranjeros rechazados sin escrituras en `tests/Feature/CampaignTest.php:225`. |
| T-11: scopes efectivos y membresía vigente | ✓ | `app/Services/CampaignService.php:30`, `:40`, `:231`; matriz negativa `tests/Feature/CampaignTest.php:273`; revocación mientras espera mutex en `tests/Feature/ConcurrentCampaignTest.php:102`. |
| T-11: UI de composición, resumen y transiciones | ✓ componente | `app/Livewire/Campaigns.php:70`; `resources/views/livewire/campaigns.blade.php:29`, `:64`, `:96`; prueba error→corrección→programación en `tests/Feature/CampaignTest.php:329`. |
| T-11: fechas y acciones concurrentes | ✓ | `tests/Feature/CampaignTest.php:55`, `:72`; cuatro escenarios completos en `tests/Feature/ConcurrentCampaignTest.php:60`, `:72`, `:90`, `:102`. |
| CA-03: consentimiento trazable y selección elegible | ✓ componente | `app/Services/CampaignService.php:137`; `resources/views/livewire/campaigns.blade.php:47`, `:85`; `tests/Feature/CampaignTest.php:80`, `:153`. |
| CA-13: recursos compartidos sobreviven a revocación del creador | ✓ componente | `tests/Feature/CampaignTest.php:255`; cuenta como propietaria y actores separados en `app/Services/CampaignService.php:115`, `:238`. |
| Revisión/confirmación deliberadas y versión exacta | ✓ | `app/Services/CampaignService.php:60`, `:228`; `tests/Feature/CampaignTest.php:108`, `:179`, `:341`, `:371`. |
| Snapshots de contenido, audiencia e identidad originales | ✓ | `app/Services/CampaignService.php:140`, `:188`; FKs compuestas en `database/migrations/2026_10_23_000008_create_campaigns.php:49`; pruebas `tests/Feature/CampaignTest.php:80`, `:122`, `:141`. |
| Pausa/reanudación conservan autorización, revisión, audiencia y fecha | ✓ componente | `app/Services/CampaignService.php:245`, `:257`; `tests/Feature/CampaignTest.php:193`, `:213`, `:293`. Comprobaciones vivas del transporte siguen contractualmente pendientes de T-13. |
| Privacidad del remitente según `mail.send` | ✓ | `app/Livewire/Campaigns.php:91`, `:180`; `resources/views/livewire/campaigns.blade.php:76`; pruebas `tests/Feature/CampaignTest.php:273`, `:307`, `:316`. |
| Audiencia paginada, sin snapshot completo en estado público Livewire | ✓ | `app/Livewire/Campaigns.php:186`; `tests/Feature/CampaignTest.php:351`: conteo, IDs/página DOM y exclusión del estado público. |
| UTC estricto, sin normalizaciones o envío adelantado | ✓ | `app/Services/CampaignService.php:209`; `tests/Feature/CampaignTest.php:55`, `:72`; `resources/views/livewire/campaigns.blade.php:98`. |
| Renderer: fuente íntegramente validada antes de compilar | ✓ | `app/Support/TemplateRenderer.php:22`; tokens, bytes, encoding y contextos HTML en `tests/Unit/CompiledTemplateTest.php:22`, `:51`. |
| Renderer: cada expansión conserva escape, límites y rechazo de cabeceras | ✓ | `app/Support/TemplateRenderer.php:75`; `tests/Unit/CompiledTemplateTest.php:12`, `:34`, `:51`, `:74`. Compilación una vez por campo, validación de cada destinatario. |
| T-07 afectada: scripts no ejecutables en panel/sandbox | ✓ componente | `resources/views/livewire/campaigns.blade.php:79`; sanitizador congelado `app/Support/TemplatePreview.php:20`; regresión `tests/Feature/CampaignTest.php:341`. |
| Verificación declarada T-11 ejecutada y documentada | ✓ | Ledger congelado `tasks.md:1873`, `:1906`; `campaign-final.receipt.json`: exit 0; stdout real **25 passed / 247 assertions**. |
| Alcance completo, incluidas exclusiones de usuario | ✓ | `scope.stdout.json`: 18 en alcance, fuera `[]`, excluidos `[]`, exclusiones de usuario `{}`, avisos/info `[]`. Plan `improvement-plan.md:165`–`:171` y T-11 `tasks.md:530` justifican código, pruebas y trazabilidad. |
| Tres casillas T-10 marcadas en este diff | ✓ histórico conservado | `tasks.md:1851` explica su procedencia de revisión 8 aprobada; no aparecen cambios nuevos del componente T-10 ni evidencia que justifique reabrirlo. La prueba durable sigue abierta. |
| T-11 sin publisher RabbitMQ, outbox o transporte SMTP añadido | ✓ | Lectura completa de los 18 archivos; `tasks.md:1863`; contrato de handoff distingue responsabilidades T-12/T-13. |
| Fallos originales conservados y diferenciados del estado final | ✓ | Recibos/streams: fatal inicial; Campaign 13/9; Campaign 24/1; build EPERM. Pasadas finales distintas, con manifiestos intermedios/final y sus diferencias contrastadas. |
| Gates y benchmark descritos sin ampliar su alcance | ✓ | `tasks.md:1904`: Feature **133/1404**, Template **20/185**, Pint acotado y build verdes; Pint global sigue pendiente. Benchmark mide `validateBatch`, no SQL/worker/SLA. |
| E2E-08, M-01 y QA integral de fase 3 | **no verificable** | No existe acreditación de navegador/aceptación humana en este paquete; siguen pendientes explícitamente en `tasks.md:1928`. |
| Aceptación de ADR-003 / cierre integral T-10 y T-11 | **no verificable** | ADR-003 congelada `estado: propuesta`; T-10/T-11 continúan `en-progreso`, total **9/20**. No se declara cierre indebido. |

**Gaps:** ninguno.

**Condicionales:** constitución ausente; no cambios en `commands/`, `agents/` o `hooks/`, por lo que interop no aplica; ninguna spec OpenAPI tocada. Sin configuración TDD ni cobertura mínima; no se inventa porcentaje. Consulta de memoria ejecutada con `knowledge-find.py --no-index --root <snapshot>`: ADR-001/002/004 aceptadas y ADR-003 propuesta; sin contradicción identificada.

**Integridad y ejecución:** comprobaciones SHA256 iniciales y finales: **220/220 coincidencias** —18 archivos cambiados, 107 fuentes/contexto, cinco anteriores, 89 evidencias y patch—; **202 rutas únicas**. Patch `921f5f99495aa2f41a656111bd70b8f57e5856672219e2036be7e9eab9627ca2`. Reconstrucción del patch en memoria: **18/18** coinciden exactamente con las fuentes congeladas. Verificados **22 recibos, 44 streams y 16 fuentes finales contra snapshot/ZIP**, sin discrepancias. Solo lectura y comprobaciones en memoria; no ejecuté tests, DB, servicios ni escrituras.

**Archivos del incremento leídos completos:** `app/Livewire/Campaigns.php`; los tres modelos Campaign; `app/Services/CampaignService.php`; `app/Support/TemplateRenderer.php`; migración `000008_create_campaigns`; `tasks.md` —todos los hunks y criterios afectados—; `docs/roadmap/README.md`; las tres vistas/componentes modificados; `routes/web.php`; `InteractsWithCampaigns.php`; `CampaignTest.php`; `ConcurrentCampaignTest.php`; `CompiledTemplateTest.php`; `campaign-action.php`. Además, spec, diseño, plan, test-plan, ADR y contexto necesario, contrato de handoff, manifiestos, recibos y salidas originales/finales.

Fuera de mi lente: rendimiento SQL/locks y robustez adicional corresponden a B/D. No emito aprobación de QA ni cierre de tarea.
