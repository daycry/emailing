# Informe de QA — MCP y Google, fase 3 · QA1

| | |
|---|---|
| Fecha | 2026-10-25 · ejecución local de testing |
| Estado | **NO-VERDE: error de arranque del runner E2E; sin aceptación de fase** |
| URL delimitada | `http://127.0.0.1:18967` |
| Alcance | T-04–T-07 y regresión focal de autenticación/equipos; no suite global |
| Ejecutor | Agente QA genérico fresco e independiente, modelo heredado; ninguna aceptación humana atribuida |
| Plan | [Plan](../../../improvement-plan.md) · [Test-plan](../../../test-plan.md) · [Ledger](../../../tasks.md) |

## Veredicto determinista

Salida **exacta** de `qa-gate.py` aplicada al JSON original de Playwright, exit 1:

```json
{
  "verdict": "NO-VERDE",
  "reason": "el runner reportó errores de ejecución",
  "errors": [
    "Error: spawn EPERM"
  ]
}
```

El runner produjo un error superior `Error: spawn EPERM`, `suites=[]` y cero pruebas ejecutadas. **No son seis E2E fallidos ni una batería aprobada.** El gate impide acreditar la UI y la fase. No hubo reintento ni cambios de tests/selectores o relajación del sandbox de Chromium/red. El hijo concreto cuyo spawn fue rechazado no se instrumentó: este fallo no se atribuye a la aplicación ni a Chromium específicamente.

## Resumen de resultados

| Capa | Resultado propio de esta ejecución |
|---|---|
| Backend focal actual | **77 passed, 1.808 assertions, 2048.91 s, exit 0**, 12 clases |
| E2E | **0 ejecutados**, 0 passed/failed/flaky/skipped declarados; 1 error de runner |
| Capturas/UI inspeccionada | **0 capturas nuevas; ninguna UI inspeccionada** por este intento |
| Manuales | M-01–M-04 pendientes; M-02 requiere una persona |
| Perfil | Windows/Laragon, PHP/SQL de testing local; Docker no ejecutado |
| Integraciones externas | Google simulado y cliente de laboratorio previstos; ningún recorrido Google/cliente real aprobado |

El backend procede de una sola captura actual de 352 fuentes. No se suman las baterías históricas 20/213, 17/588 y 4/241: se solapan y tienen manifests propios. No se ejecutó toda la suite del proyecto ni T-12/T-19 global.

## Puertas de entrada y cobertura

- `ledger-lint.py`: **exit 0, 0 incoherencias, 7 avisos de Changelog futuro**, T-08–T-14.
- `coverage-check.py`: **exit 0, 0 referencias rotas, 0 tests sin referencia, 0 GWT sin ID o sin cubrir en el documento**. Defined/referenced: E2E-01–06 y M-01–04. La correspondencia documental no demuestra ejecución.
- Nueve tareas sin `Cubre (tests)`: T-01 fija contrato/plan; T-02, T-04, T-05 y T-06 son backend; T-08–T-10 son catálogo backend pendiente; T-13 es revisión. Esa lista no detecta una tarea UI huérfana. T-07 tiene E2E-04–06 y M-02 declarados, pero **sus E2E no se ejecutaron** en QA1.
- No hay bloques API-xx/A11Y-xx en el test-plan. No se instalaron ni ejecutaron axe/smokes nuevos.
- No existe `.claude/dev.json`: no se activó TDD ni cobertura porcentual opt-in; no se inventa porcentaje unitario o de código.

Guardrail: el Bash original no arrancó, exit **3221225794**, error Win32 5 al crear signal pipe. Se conserva como fallo del mecanismo, sin declararlo verde. El validador nativo comprobó el literal HTTP, host IPv4 loopback y puerto exactos, sin credenciales, ruta, query o fragmento. Root contrastó esa equivalencia con la función del kit y el config revisado. Sólo se autoriza ese destino; no se relajó ningún guard. Evidencia: [puertas](raw/entry-gates.json), [validador nativo](raw/guardrail-fallback.json) y [decisión root](raw/root-guardrail-decision.json).

## Backend y trazabilidad de fase

La orden original está en [el recibo](raw/qa1-backend-receipt.json). Filtro exacto: McpAuthorizationTest, McpRevocationConcurrencyTest, TeammateTest, McpMembershipRemovalRegressionTest, McpOAuthTest, McpOAuthConcurrencyTest, McpOAuthRegistrationRegressionTest, McpPruneCapacityRegressionTest, McpTransportTest, McpProtocolMetadataRegressionTest, McpConsentToolsTest y GoogleLoginTest. Las doce clases emitieron PASS.

| Tarea | Evidencia ejecutada | Límite |
|---|---|---|
| T-04 | Autoridad/cuenta/scopes, revocación concurrente, retirada/reinvitación y regresión Teammates | No acepta estados del ledger |
| T-05 | OAuth, concurrencia, registro y capacidad de poda | No acredita scheduler operativo, rendimiento sostenido ni SLA |
| T-06 | Transporte y matriz de metadatos/cabeceras | No acredita clientes reales o despliegue Docker/TLS |
| T-07 | Integración backend de consentimiento, herramientas y auditoría | UI E2E-04–06 sin ejecutar; M-02 humana pendiente |
| T-03 | Regresión focal GoogleLoginTest | No es Google real ni toda la suite de identidad |

El catálogo completo de 37 operaciones de T-08–T-10 no está implementado/ejecutado por esta fase. Las pruebas actuales de herramientas adelantadas se limitan al alcance ya revisado, incluidas `lists_get` y `lists_create`; no se extrapolan a otros módulos.

## E2E previstos: no ejecutados

| ID | Escenario del test-plan | Resultado y evidencia |
|---|---|---|
| E2E-01 | Google: usuario estable y retorno al consentimiento | No ejecutado; arranque EPERM, sin captura |
| E2E-02 | Callback/vínculo sin autoenlace por email | No ejecutado; arranque EPERM, sin captura |
| E2E-03 | Admisión, invitación, registro y desvínculo | No ejecutado; arranque EPERM, sin captura |
| E2E-04 | Consentimiento por cuenta/scopes y CSRF | No ejecutado; arranque EPERM, sin captura |
| E2E-05 | Frontera de cuenta/capacidades y bearer exclusivo | No ejecutado; arranque EPERM, sin captura |
| E2E-06 | Revocación, membresía y reautorización | No ejecutado; arranque EPERM, sin captura |

No hay capturas que inspeccionar ni se reutilizan las de desarrollo o fase 2. La configuración conserva Chromium sandbox, trace off, deny-proxy y bloqueo de service workers; ese diseño no se presenta como recorrido efectivo. [JSON original](raw/playwright-results.json), [recibo del reporter](raw/playwright-json-receipt.json) y [diagnóstico](raw/browser-diagnostic.json).

## Checklist manual para una persona

- [ ] **M-01 — Google real en Laragon y Docker:** acceso, vínculo/desvínculo, política de admisión y retorno OAuth con identidades/callbacks privados. Pendiente; no se solicitan secretos en chat. Cubre T-03/T-11/T-14.
- [ ] **M-02 — Claridad del consentimiento y conexiones:** cliente, cuenta, capacidades, efectos y revocación comprensibles; estados, errores y teclado. **Una persona debe confirmar**; este agente no la sustituye. Cubre T-07/T-14.
- [ ] **M-03 — Clientes reales, expiración y revocación:** Codex, Claude Code y OpenCode con producto/versión, transporte, registro, callback y red documentados; Claude Desktop conserva fila separada. Pendiente. Cubre T-11/T-14.
- [ ] **M-04 — Reinicio, restauración y configuración:** ambos perfiles y restauración aislada, persistencia/revocación y Mailpit/log. Pendiente; no se reiniciaron servicios ni se restauró una base. Cubre T-11/T-14.

## Recursos y cierre atribuido

La suite backend terminó y liberó el hijo del runner; `live_handles=[]`. Los tests MCP emplean DatabaseMigrations; la última clase TeammateTest usa RefreshDatabase y conserva esquema tras rollback de sus datos. El verificador heredado `qa1-backend-settlement` exigía ausencia de tablas MCP y terminó **exit 1**, RuntimeException sanitizada. Se conserva ese fallo de su supuesto, separado del backend 77/1808 verde. No se ocultó ni se arregló borrando o recreando esquema.

Los comprobadores **sólo de lectura** `qa1-backend-state` y `qa1-browser-state` terminaron exit 0: TestingDatabaseGuard contrastó configuración y conexión MySQL real del usuario/base `emailing_testing`, sin DB_URL; nivel transaccional 0; cero filas en las 16 tablas verificadas. No hubo mutaciones por esos checks ni consultas a la base operativa. El esquema existente no se describe como borrado selectivo.

El intento browser no produjo directorios nuevos de launcher, no actualizó su puntero runtime y no dejó recibos de setup/callback/teardown nuevos: no se atribuye un arranque de web, proxy, callback o fixtures que no ocurrió. No se ejecutó un teardown adicional. Antes y después, los binds locales de 18967/18968/18743 fueron posibles y se cerraron en contexto. No se buscó ningún proceso/PID ni se intervino en recursos ajenos. SMTP externo sigue fuera del alcance; sólo se usaron las fronteras de laboratorio del plan.

Recibos y datos: [cierre heredado exit1](raw/qa1-backend-settlement-receipt.json), [estado backend](raw/backend-sql-state.json), [estado posterior browser](raw/post-browser-sql-state.json), [binds previos](raw/pre-browser-ports.json) y [posteriores](raw/post-browser-ports.json).

## Procedencia y límites de aceptación

Cápsula revisada SHA-256 `ac48477292f63d6055b30b9d767a2d5a662970629e1d79986871a30e787c70a1`: 1.347 archivos verificados, sin discrepancias, y hashes de ocho originales A/B/C/D contrastados. Su revisión estática incremental está limpia; no es ejecución por los revisores ni aprobación humana. Root consolidó 17 criterios y seis cierres previos. QA conserva esa procedencia y no realiza otra auditoría global de seguridad.

Los cinco runs de backend/comprobadores/browser tienen 352 fuentes verificadas contra su ZIP, tree revisado y checkout actual, con `source_changed_during_run=[]`. Backend/browser comparten manifest `276f853d903367ff2150e2f84807dba153743cdfa71bfb1f36940a8da1ade10e`. Los productores exactos del runner/adaptador coinciden con producer-hashes.json. Los originales permanecen en la caché privada dedicada; las copias públicas de recibos y reporter son byte a byte. Sólo las referencias de comandos de preflight se sanitizaron. [Procedencia](raw/provenance.json) · [fuentes](raw/source-manifest.json) · [verificación de entrada](raw/input-verification.json).

No se modificó producto, tests, configuración, contratos, migraciones, ledger ni Git. No hubo instalaciones, servicios externos, publicación ni delegación. Capacidades pertinentes: backend/API, frontend, delivery/MCP y testing; GOT-012 legacy aceptada orientó la lectura del límite de red existente, sin convertir un mock en Google real. Root decidirá la corrección del entorno y un despacho QA2. **QA1 queda NO-VERDE y no propone cierre ni handoff de documentación verde.**

## Edición y PDF

Se corrigió una degradación editorial de Unicode causada por el transporte de texto del shell; los hechos y los resultados originales no cambiaron. Se preservaron el Markdown/PDF previos y todos los recibos privados. El primer conversor PDF local falló con `spawn EPERM`; root autorizó ejecutar únicamente el conversor fuera del sandbox de herramientas, manteniendo el sandbox de Chromium, sin instalar dependencias ni añadir activos remotos. Esa conversión documental no repite los E2E ni vuelve verde QA1. El PDF final corresponde a este Markdown corregido; sus recibos y verificación se incluyen en el handoff.
