Lente A · revisión global 10 · intento acotado **2/3 del mismo incremento T-11** · **75 archivos incrementales; tres runtime**. Fallback genérico, runtime heredado; `opus/high` procede del frontmatter. **B-01 corregido; sin gaps nuevos: 0 Critical / 0 Important / 0 Minor.**

Rutas relativas a `storage/phase3-t11-review2/sources/`. Leídas completas las cuatro tablas previas; las aprobaciones indicadas como conservadas no se reabren.

| Criterio T-11 / objetivo | Veredicto y continuidad | Evidencia |
|---|---|---|
| Borradores/revisadas/futuras sin elegibilidad prematura | ✓ componente conservado | `tests/Feature/CampaignTest.php:33`; dominio sin cambios. |
| CA-05: worker literal | **No verificable; sigue pendiente** | `spec.md:171`; `tasks.md:1999`; `due()` y suite focal no acreditan worker. |
| Destinatarios, plantillas y remitentes de la cuenta | ✓ conservado | `app/Services/CampaignService.php:77`; no modificado; `CampaignTest.php:225`. |
| Scopes efectivos y membresía vigente | ✓ conservado y aplicado al control nuevo | `app/Livewire/Campaigns.php:111`; `CampaignTest.php:441` rechaza revocación con 403. |
| UI de composición/resumen/transiciones | ✓ componente; recuperación reevaluada | `resources/views/livewire/campaigns.blade.php:42`; `CampaignTest.php:381` recupera el mismo borrador. |
| Fechas y acciones concurrentes | ✓ conservado | `CampaignTest.php:55`, `:72`; stdout final conserva los cuatro casos `ConcurrentCampaignTest`. |
| CA-03: consentimiento trazable y audiencia elegible | ✓ componente conservado | `CampaignTest.php:80`, `:153`; recuperación termina con destinatario válido en `:418`. |
| CA-13: recursos compartidos y revocación del creador | ✓ componente conservado | `CampaignTest.php:255`; dominio intacto. |
| Revisión/confirmación deliberadas y versión exacta | ✓ conservado y reforzado para vaciado | `Campaigns.php:115`, `:118`, `:135`; `CampaignTest.php:422` conserva revisión y rechaza autorización 409. |
| Snapshots de contenido, audiencia e identidad | ✓ conservado | Servicio/migración/modelos sin cambios; `CampaignTest.php:430` acredita revisión y destinatarios persistidos intactos tras vaciar. |
| Pausa/reanudación: autorización, audiencia y fecha | ✓ componente conservado | `CampaignTest.php:193`, `:213`, `:293`; transporte vivo sigue pendiente de T-13. |
| Privacidad de remitente según `mail.send` | ✓ conservado | `Campaigns.php:88`, `:101`; el control nuevo no añade ni omite `mail.send` en autorización. |
| Audiencia paginada y estado público acotado | ✓ conservado | `Campaigns.php:198`–`:200`; `CampaignTest.php:351`. |
| UTC estricto | ✓ conservado | `CampaignTest.php:55`, `:72`; servicio sin cambios. |
| Renderer: fuente íntegra validada antes de compilar | ✓ conservado | Renderer y pruebas unitarias idénticos al snapshot aprobado. |
| Renderer: escape, límites y cabeceras por expansión | ✓ conservado | Mismas fuentes aprobadas; no se atribuye nueva ejecución del renderer al fix. |
| T-07: preview aislada/sandbox | ✓ componente conservado | `campaigns.blade.php:81`; `CampaignTest.php:341`. |
| Verificación declarada T-11 ejecutada | ✓ evidencia del fix | `tasks.md:1987`; ocho recibos contrastados. Campaign final **28/301**, exit 0. |
| Alcance completo y exclusiones | ✓ | Scope review2: **89/89** acumulativos en alcance, fuera/excluidos/avisos/info vacíos, sin exclusiones de usuario; plan `improvement-plan.md:165`–`:171`, T-11 `tasks.md:530`. |
| Casillas históricas T-10 | ✓ histórico conservado | No cambia su componente ni sus casillas en este incremento; prueba durable continúa abierta. |
| Sin publisher/outbox/SMTP añadido | ✓ conservado | Solo tres runtime modificados; servicio y contratos de entrega intactos. |
| Fallos originales diferenciados del final | ✓ | RED/RED-review y build EPERM conservados; recibos/streams públicos coinciden con originales. |
| Gates/benchmark sin ampliar cobertura | ✓ | `t11-b01-fix.md:42`; Feature **133/1404**, renderer **20/185** y benchmarks siguen asociados al snapshot inicial. |
| E2E-08, M-01 y QA integral fase 3 | **No verificable; siguen pendientes** | `tasks.md:1999`–`:2002`; no se declara navegador ni aceptación humana. |
| ADR-003 y cierre integral T-10/T-11 | **No verificable; siguen pendientes** | Ambas tareas `en-progreso`, total **9/20**, ADR-003 `propuesta`. |
| B-01: retirada explícita de selecciones desaparecidas | **✓ corregido** | `Campaigns.php:109`–`:119`; botón con confirmación `campaigns.blade.php:42`–`:43`; regresión `CampaignTest.php:381`–`:419`. |
| Vaciado sin poda silenciosa ni escrituras persistentes | ✓ | Solo resetea arrays/confirmación del formulario; `CampaignTest.php:403`–`:408` conserva IDs, versión, auditoría y ausencia de snapshots. |
| RED/GREEN significativos sobre mismas expectativas | ✓ | Ambos métodos idénticos en ZIP RED/GREEN/final. RED recuperación **1 failed/9 assertions** → GREEN **1 passed/33**; RED revisión **1 failed/1** → GREEN **1 passed/16**. |

| # | Grado previo | Tarea | Disposición | Evidencia y escenario |
|---|---|---|---|---|
| B-01 | Important | T-11 | **Corregido** | Tras eliminar lista/contacto seleccionados, la UI permite vaciar explícitamente, elegir alternativas, guardar y revisar **la misma campaña**. Se conservan cuenta/scopes/versión/estado, confirmación y bloqueo de cambios sin guardar. |

**Gaps nuevos:** ninguno. **Conteos: Critical 0 / Important 0 / Minor 0.**

**Integridad y evidencia:** comprobación inicial sin discrepancias y comprobación final **299/299** —178 fuentes, 41 evidencias, 75 entradas del diff solapadas, cuatro preimágenes y patch—, **224 rutas únicas**. SHA256 del patch antes/después: `6331c8711dc88d90935e5f17da780d14fd2f57d530ffbd31fd86e016556a62db`. Recorridos y reconstruidos en memoria los **75/75** bloques: cero diferencias de contenido; cinco normalizaciones de LF final previstas por el formato del patch.

Contrastados tres ZIP de **16 fuentes más manifest**, los dos métodos exactos en sus tres snapshots, **38 artefactos**, ocho recibos/16 streams nuevos y **60 streams públicos** exactos. Los **30 recibos públicos** —22 históricos y ocho del fix— conservan resultados, tiempos y hashes. El patch público de revisión 9 coincide con el original; su manifest conserva todos los campos anteriores y añade únicamente trazabilidad administrativa.

**Condicionales:** constitución ausente; interop y OpenAPI no aplican. Sin configuración TDD/cobertura mínima ni porcentaje inventado. Consulta de memoria mediante `knowledge-find.py --no-index --root <snapshot>`: ADR-001/002/004 aceptadas, ADR-003 propuesta; sin contradicción introducida.

**Lo ejecutado:** lecturas, hashes, comparación de manifests/ZIP, reconstrucción del patch y consulta de memoria sin índice. No ejecuté tests, Git, DB, servicios ni escrituras. Fuera de mi lente: robustez adicional y rendimiento corresponden a B/D. No emito QA integral ni cierre de T-10/T-11.
