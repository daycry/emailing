# Informe QA independiente — MCP T-09, intento 1

| Dato | Resultado medido |
|---|---|
| Fecha | 2026-10-25, UTC |
| Veredicto | **NO-VERDE**, backend con error; sin aceptación incremental |
| Backend | **144 casos / 3092 aserciones** |
| Fallos / errores / omitidos | **0 / 1 / 0** |
| Ocho E2E originales | **No ejecutados en este intento** |
| Estabilidad E2E-08, tres repeticiones | **No ejecutada en este intento** |
| qa-gate | **No ejecutado**: no existe reporter de navegador de esta QA |
| Estado | T-09 en-progreso; contador 8/14, sin modificación |

[Plan de pruebas](../../../../test-plan.md) · [Ledger](../../../../tasks.md) · [Spec](../../../../spec.md).

## Diagnóstico nativo conservado

El orquestador indicó, después de observar el marcador de error durante el backend, terminar la tanda y priorizar el diagnóstico, informe y devolución del runtime. Por esa instrucción explícita no se iniciaron los ocho E2E ni la tanda de estabilidad sobre un backend rojo. No se sustituyen por resultados de desarrollo ni de otra QA. No hay salida JSON de qa-gate que pegar: no se ejecutó y no se fabricó un reporter.

### test_historical_starts_without_reconstructible_links_and_real_zero_have_a_neutral_page_empty_state

Clase: `Tests\Feature\ActivityTest`. Tipo nativo: `ErrorException`.

```text
Tests\Feature\ActivityTest::test_historical_starts_without_reconstructible_links_and_real_zero_have_a_neutral_page_empty_state
ErrorException: rename(C:\laragon\www\emailing\storage\app\private/migration-window-21ebea03-66c1-4904-82ad-fea71911eecc.json.9fddc123e2e71c60.tmp,C:\laragon\www\emailing\storage\app\private/migration-window-21ebea03-66c1-4904-82ad-fea71911eecc.json): Acceso denegado (code: 5)

C:\laragon\www\emailing\vendor\laravel\framework\src\Illuminate\Foundation\Bootstrap\HandleExceptions.php:265
C:\laragon\www\emailing\tests\support\OutboxProtocolBarrier.php:15
C:\laragon\www\emailing\tests\Concerns\ReplaysMigrationHistory.php:93
C:\laragon\www\emailing\tests\Feature\ActivityTest.php:581
```

El diagnóstico anterior procede del JUnit nativo. Se preservan también stdout, stderr y el recibo natural de run.py, sin modificar sus bytes. No se cambió producto, tests, configuración, umbrales o timeout, ni se reintentó el caso. La causa raíz queda pendiente de investigación; no se atribuye un defecto concreto a partir del marcador parcial ni se convierte el resto de casos en aceptación general.

## Backend ejecutado

Los conteos de esta tabla proceden de testcase en el JUnit final de una única tanda, sin sumar filtros solapados ni resultados históricos:

| Clase | Casos |
|---|---:|
| McpContractArgumentsTest | 24 |
| McpWireInputsTest | 17 |
| ActivityTest | 20 |
| CampaignTest | 26 |
| ConcurrentCampaignTest | 5 |
| McpBusinessToolsTest | 17 |
| McpConsentToolsTest | 9 |
| McpDataToolsTest | 10 |
| SuppressionTest | 16 |

Se ejecutaron las ocho clases requeridas: McpBusinessToolsTest final, McpContractArgumentsTest, McpWireInputsTest, McpDataToolsTest, McpConsentToolsTest, CampaignTest, ActivityTest y SuppressionTest. El filtro CampaignTest también coincidió con ConcurrentCampaignTest: cinco casos adicionales de concurrencia, relevantes para la atomicidad del servicio compartido, ejecutados en esta misma tanda. Fue una coincidencia adicional del filtro, no una suite planificada aparte; no se oculta ni se suma dos veces. En total son nueve clases medidas. Los diecisiete casos finales de negocio usan el fixture contacts_update/revoked. La tanda previa 17/955 y la regresión común 67/746 con captura inestable no son resultados de esta QA.

Esta ejecución es una regresión incremental. No se ejecutó la suite completa. McpTransportTest conserva una expectativa histórica de dos herramientas; su ajuste explícito al catálogo y la regresión completa quedan para T-10/T-12. Este informe no acredita ese test no ejecutado.

No se midió cobertura de líneas ni se inventa un porcentaje. No existe dev.json ni gate de porcentaje configurado. Los casos de Unit son pruebas unitarias y los de Feature integran servicios/transporte/pantallas con MySQL guardado; no hubo E2E real de esta QA.

## Entrada, fuentes y puertas estáticas

La verificación independiente coincidió en los **1991 archivos** de la cápsula congelada, patch.diff y base.zip, y verificó el preflight registrado. Manifest SHA-256 `c619bfcd5c17ccb592733aad3eaa946827de7ac7bfb247eed19b392691507474`, base `0fbc8c7199c3fad4c030e28948bb7a8f57712413`. La única diferencia respecto al árbol vivo era tasks.md, metadata posterior admitida en el handoff. Producto y tests coincidían. No se leyeron razonamientos de revisores para formar este veredicto.

Ledger-lint exit 0: **cero incoherencias y seis avisos** por Changelog futuro. Coverage-check exit 0: **cero referencias rotas y cero GWT sin cubrir**. Los avisos T-01/T-02/T-10/T-13 corresponden a contrato, backend o revisión y no son UI huérfana del incremento. T-09 declara E2E-08; esa cobertura estática no significa que se haya ejecutado. No hay bloques API-xx/A11Y-xx; no se atribuye una auditoría WCAG.

Capability-check seleccionó API, backend, entrega/MCP, frontend, PHP y testing. Se consultaron los criterios pertinentes de contrato/datos, interacción y aislamiento; las particularidades CI4 no se trasladaron al Laravel del repositorio. No se halló constitución ni AGENTS.md aplicable. La consulta de memoria dirigida no dio aciertos; su fallback permitió leer GOT-012, legado aceptado sobre bloqueo exterior y bypass loopback. No se ejecutó el navegador para volver a acreditar esa frontera.

## Guardas y observación de terminalidad

run.py permaneció intacto y fijó APP_ENV testing, DB_DATABASE y DB_USERNAME emailing_testing, DB_URL vacío, sockets y Xdebug apagado. Tests/TestCase y TestingDatabaseGuard verifican DATABASE()/CURRENT_USER() antes de migrar. No se abrió configuración privada, metadatos de procesos, tokens, credenciales ni DSNs. No se consultó la base operativa ni se ejecutó Docker operativo, SMTP externo, scheduler o worker fuera de lo acotado por los tests de laboratorio.

La preparación y el cierre tuvieron errores de tooling: un nombre de helper copiado de otro incremento y errores de codificación CP1252/ASCII en lectura, salida y generación del informe, corregidos con UTF-8 explícito. Sólo se corrigieron helpers privados y entregables de QA, y se usó PYTHONUTF8 para las puertas. Ninguna fuente de producto cambió. Las versiones de PDF rechazadas se conservan privadamente; la entrega final se volvió a renderizar e inspeccionar.

El session_id original del backend no se conservó por un error al presentar el resultado de herramienta. No se inventa su handle ni se infiere terminalidad por ausencia de archivos. Antes de cualquier comprobación dependiente se esperó el **recibo natural de run.py**, que sólo se escribe después de subprocess.wait, y el JUnit final. Los monitores propios fueron observaciones, no handles del backend. Esta limitación queda explícita en evidence.json.

La captura de cada run conserva **398 fuentes** y su ZIP privado con hashes. source_changed_during_run está vacío. Los stdout/stderr, fuentes y recibos públicos son copias con hash idéntico al original. No se atribuye cobertura a fuentes distintas.

## Limpieza y navegador no ejecutado

Tras la terminalidad del backend, el chequeo read-only guardado verificó nivel transaccional cero y ausencia de las seis tablas de dominio comprobadas tras DatabaseMigrations. El backend-state sólo acredita el teardown: no prueba que la ventana histórica completase todos los pasos up ni que se restaurase el esquema. El orquestador informó una lectura dirigida del trace identificado en el error: diez pasos down completados y sólo tres up publicados, hasta create_sending_api, sin flags restored/primary_failure_class. QA no abrió ese trace y atribuye esta observación al orquestador. La evidencia de restauración es incompleta y mantiene NO-VERDE; la investigación causal queda pendiente.

Los binds exclusivos sobre 18967, 18968 y 18743 acreditaron que esos sockets estaban libres; no se infiere ausencia de procesos.

No se creó un UUID de fixtures de navegador, ni se leyeron sus manifiestos o metadatos. Por tanto la limpieza SQL por 28 categorías, los recibos de callback/teardown, las 17 capturas previstas y las nueve de estabilidad son **no aplicables/no ejecutados en QA1**, no resultados verdes. No hay capturas o trazas nuevas; no se reutilizan las de desarrollo. El destino local previsto se validó nativamente como literal exacto HTTP loopback con puerto fijo y sin credenciales/path/query/fragment; no se afirma ejecución de Bash ni comprobación de navegación real.

## Manuales pendientes y alcance de aceptación

- [ ] **M-01 — Google real en Laragon y Docker**: acceso, vínculo, admisión y retorno con identidad autorizada; el proveedor simulado no sustituye el servicio real.
- [ ] **M-02 — Claridad del consentimiento y conexiones**: juicio humano de cuenta, cliente, capacidades, errores y teclado.
- [ ] **M-03 — Clientes reales**: Codex, Claude Code y OpenCode por versión, expiración y revocación; Claude Desktop separado. Los tests protocolarios no acreditan esos productos.
- [ ] **M-04 — Reinicio, restauración y configuración**: perfiles, persistencia/revocación, recuperación y Mailpit/log.

T-09 continúa en-progreso y el contador permanece 8/14. No se modifica ledger, spec o documentación de producto, no se invoca documenter ni se envían mensajes externos. Un error backend impide aceptar este incremento; las puertas estáticas no lo compensan. La investigación y una nueva ejecución de las pruebas pertinentes sobre las fuentes finales corresponden al orquestador. También siguen pendientes T-10–T-14 y las verificaciones reales/manuales.

## Evidencia y PDF

El PDF se generó mediante la skill to-pdf y una adaptación privada del motor existente: sandbox conservado, red bloqueada, sin instalaciones. Se renderizaron e inspeccionaron todas sus páginas; la mera existencia del fichero no se presenta como verificación visual. Se ejecutó el checker existente de privacidad sólo sobre esta entrega, con extracción de texto del PDF y sin leer valores secretos de configuración.

- [Evidencia consolidada y hashes](evidence.json) · [PDF](report.pdf).
- [JUnit final original](raw/backend/backend-junit.xml) · [stdout original](raw/backend/stdout.txt) · [recibo natural](raw/backend/receipt.json).
- [Estado SQL guardado](raw/backend-state/stdout.txt) · [Binds exclusivos](raw/backend-sockets/stdout.txt).
- [Ledger-lint](raw/ledger-lint.stdout.txt) · [Coverage-check](raw/coverage-check.stdout.txt) · [Entrada verificada](raw/entry-verification.json).
- [PDF inspeccionado](raw/pdf-visual-review.json) · [Privacidad](raw/privacy-check.json).

Handoff **NO-VERDE**: conservar el diagnóstico y las fuentes exactas, investigar la causa y repetir las pruebas pertinentes. El runtime queda devuelto explícitamente al orquestador al finalizar los checks y recibos aquí enlazados; no hay aceptación incremental ni general.
