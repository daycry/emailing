# Informe de QA — Proveedores de correo

| | |
|---|---|
| **Estado global** | **NO-VERDE — intento 2 de 3** |
| **Inicio nativo UTC** | 2026-10-31T00:21:31.849Z |
| **Origen local** | `https://127.0.0.1:18867` |
| **Plan** | [improvement-plan.md](../../improvement-plan.md) · [test-plan.md](../../test-plan.md) |
| **Rol efectivo** | QA independiente genérico/modelo heredado; no se acredita Sonnet nativo |

## Veredicto (qa-gate)

`qa-gate.py` del bundle custom-agents2.3.0 ejecutado sobre [results.json original](raw/results.json), exit **1**. Sin reconstrucción del reporter ni justificación de flaky.

```json
{
  "verdict": "NO-VERDE",
  "counts": {
    "passed": 5,
    "failed": 1,
    "flaky": 0,
    "skipped": 0,
    "interrupted": 0
  },
  "flaky_sin_justificar": [],
  "flaky_justificados": {},
  "threshold": "verde ⟺ 0 failed, 0 flaky sin justificar, 0 interrumpidos y ≥1 test ejecutado"
}
```

## Resumen

- **5/6 pasan; 1 fallan; 0 flaky, 0 omitidos y 0 interrumpidos.**
- Se conservan seis escenarios originales y una ejecución por escenario, retries0 y timeout180000ms. Correcciones de selectores/navegación se revisaron antes de esta tanda; QA1 rojo queda íntegro y no se suma al conteo.
- M-01 y M-02 permanecen pendientes. Fixtures locales no acreditan proveedores comerciales, entrega real ni aprobación humana.
- Fuentes y auxiliares de informe viven en storage; sólo informe y evidencia necesaria se conservan en este directorio público.

## Puertas de entrada

| Puerta | Exit |
|---|---:|
| ledger-lint | 0 |
| coverage-check | 0 |
| guardrail | 0 |

Cobertura declarada por referencia; no es cobertura porcentual de código ni prueba de que un recorrido fallido alcanzase sus pasos posteriores.

```json
{"applies": true, "defined": ["E2E-01", "E2E-02", "E2E-03", "E2E-04", "E2E-05", "E2E-06", "M-01", "M-02"], "referenced": ["E2E-01", "E2E-02", "E2E-03", "E2E-04", "E2E-05", "E2E-06", "M-01", "M-02"], "broken_refs": 0, "tasks_sin_cobertura": [], "tests_sin_referencia": [], "gwt_cubiertos": ["CA-01", "CA-02", "CA-03", "CA-04", "CA-05", "CA-06", "CA-07", "CA-08", "CA-09", "CA-10", "CA-11", "CA-12", "CA-13", "CA-14", "CA-15", "CA-16"], "gwt_sin_cubrir": [], "gwt_sin_id": 0, "test_plan_na": false, "marcador_no_canonico": null}
```

## Entorno y límites

- Chrome instalado (`channel: chrome`), un worker, viewport1280×900; no se descargan paquetes/navegadores. Controles y sus nombres/opciones se contrastan con el origen local; se conservan selectores accesibles y aserciones web-first.
- Excepción CSS en E2E-05/06: se acotan span/p sin rol interactivo propio al contenedor de actividad identificado por aria-labelledby, evitando la option oculta y comprobando texto/fecha visibles. No se oculta un control inaccesible ni se cambia una aserción visible por existencia en DOM.
- Harness propio JobObject/GATE, HTTPS18867, denyProxy18868, backend privado18869; todos loopback, service workers bloqueados. No se instala CA global. `ignoreHTTPSErrors` admite sólo la CA autofirmada del harness y no acredita TLS público del proveedor.
- Brevo/SendGrid son adaptadores test-only; Mailpit usa SMTP127.0.0.1:11025/API127.0.0.1:18025. SQL se limita a emailing_testing con guarda real de base/usuario/DB_URL/conexión. No se leen manualmente credenciales/configuración privada.
- Pruebas unitarias, wire SMTP/HTTPS, pipeline/contratos y nuevas carreras de feedback son backend separado; no se suman como otros E2E ni sustituyen estos seis recorridos. Sin porcentaje de cobertura de código inventado.
- No hay bloques API-xx/A11Y-xx independientes en el plan. Las comprobaciones de teclado de E2E-06 no equivalen a una auditoría WCAG.
- Reglas QA y mapas pertinentes del bundle2.3.0 aplicados; capacidades compartidas api-contract/backend-practices/delivery-practices/frontend-quality/stack-practices/unit-tests. Laravel/Livewire, sin imponer convenciones CI4/React. Memoria pertinente GOT-001/GOT-012 aceptada; no se escribe memoria.

## Resultados automáticos

| Original | Resultado | Duración | Tareas |
|---|---|---:|---|
| E2E-01 — Guardar y alternar los cuatro modos sin devolver secretos | passed | 10.830s | T-01, T-06 |
| E2E-02 — Limpiar y corregir configuración de un único proveedor | passed | 7.738s | T-01, T-06 |
| E2E-03 — Aislar cuentas y revalidar permisos de ajustes/remitentes | passed | 10.154s | T-01, T-06 |
| E2E-04 — Enviar por los cuatro canales y distinguir aceptación de entrega | passed | 33.762s | T-01, T-02, T-03, T-04, T-06 |
| E2E-05 — Mostrar rechazo, rate limit e incertidumbre sin duplicar | passed | 14.299s | T-02, T-04, T-05, T-06 |
| E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado | failed | 12.622s | T-02, T-03, T-04, T-05, T-06 |

### E2E-01 — Guardar y alternar los cuatro modos sin devolver secretos — PASA

**Tareas:** T-01, T-06. **Contrato comprobado:** Cuatro modos persistidos, separación de perfiles, secretos ausentes del HTML y respuestas Livewire, blank conserva y guardar no inicia IO.

![E2E-01 — captura original](screenshots/E2E-01-profiles.png)


### E2E-02 — Limpiar y corregir configuración de un único proveedor — PASA

**Tareas:** T-01, T-06. **Contrato comprobado:** Rechazo visible, corrección del mismo formulario, clear selectivo Brevo, SendGrid intacto y envío incompleto bloqueado antes de IO.

![E2E-02 — captura original](screenshots/E2E-02-cleared.png)

Otras capturas: [E2E-02-error.png](screenshots/E2E-02-error.png).


### E2E-03 — Aislar cuentas y revalidar permisos de ajustes/remitentes — PASA

**Tareas:** T-01, T-06. **Contrato comprobado:** A/B aisladas, vuelta a A, permiso retirado con sesión abierta, 403 sin escritura y controles restringidos.

![E2E-03 — captura original](screenshots/E2E-03-reader.png)

Otras capturas: [E2E-03-account-B.png](screenshots/E2E-03-account-B.png).


### E2E-04 — Enviar por los cuatro canales y distinguir aceptación de entrega — PASA

**Tareas:** T-01, T-02, T-03, T-04, T-06. **Contrato comprobado:** Cuatro canales externos fixture y log/Mailpit, estados y proveedor correctos, trabajo durable y redelivery bloqueado, dos mensajes Mailpit propios.

![E2E-04 — captura original](screenshots/E2E-04-activity.png)

Otras capturas: [E2E-04-brevo_api.png](screenshots/E2E-04-brevo_api.png), [E2E-04-brevo_smtp.png](screenshots/E2E-04-brevo_smtp.png), [E2E-04-log.png](screenshots/E2E-04-log.png), [E2E-04-mailpit.png](screenshots/E2E-04-mailpit.png), [E2E-04-sendgrid_api.png](screenshots/E2E-04-sendgrid_api.png), [E2E-04-sendgrid_smtp.png](screenshots/E2E-04-sendgrid_smtp.png).


### E2E-05 — Mostrar rechazo, rate limit e incertidumbre sin duplicar — PASA

**Tareas:** T-02, T-04, T-05, T-06. **Contrato comprobado:** Negativo permanente, 429 con espera, incertidumbre SMTP, cambio de proveedor, fencing y recarga sin IO adicional.

![E2E-05 — captura original](screenshots/E2E-05-outcomes.png)


### E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado — FALLA

**Tareas:** T-02, T-03, T-04, T-05, T-06. **Contrato previsto:** Firma/Bearer, duplicado/alteración, feedback y supresión conservados, nuevo envío bloqueado, teclado y viewport estrecho.

El escenario falla; los pasos posteriores al error no quedan acreditados. Error completo original en `raw/results.json`.

```text
Error: expect(locator).toBeVisible() failed

Locator: locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ }) with timeout 5000ms
  - waiting for locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })


  212 |   expect(feedback.messages.find(m => m.id === brevo.id).provider_status).toBe('unsubscribed');
  213 |   await page.goto('/activity');
> 214 |   await expect(page.locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })).toBeVisible();
      |                                                                                                                                     ^
  215 |   await shot(page, 'E2E-06-feedback');
  216 |   await page.goto('/suppressions');
  217 |   await expect(page.getByText('Queja comunicada por el proveedor', { exact: false })).toBeVisible();
    at C:\laragon\www\emailing\tests\e2e\mail-providers.spec.mjs:214:133
```

**Límite alcanzado:** los checks backend anteriores al fallo sí pasan (cuatro eventos, una supresión, aceptación API y feedback complained/unsubscribed). No se ejecutan después el envío al destinatario suprimido/cero IO, la navegación con teclado ni el viewport estrecho.

El [error-context nativo original](raw/E2E-06-error-context.md) normaliza el párrafo a `Queja; destinatario suprimido · 2026-10-31 00:22:56.000000 UTC`. No se conservó textContent raw. La fuente vigente `resources/views/livewire/activity.blade.php:48` incluye `UTC @endif</p>` con un espacio final; la expresión `UTC$` no admite ese espacio. Es una hipótesis del localizador respaldada por fuente/snapshot, sin nueva ejecución ni conclusión de ausencia del feedback de producto.

![E2E-06 — captura original](screenshots/E2E-06-failure.png)

## Checklist manual pendiente

- [ ] **M-01 — Cualificar proveedores reales en el destino privado:** cuatro combinaciones, cuenta/dominio/remitentes, SPF/DKIM/DMARC, claves SMTP/API distintas, callback HTTPS autenticado, envío/evento privados y rotación/revocación. T-03/T-04/T-05/T-06. No bloquea aceptar QA local cuando pase; no se marca realizado.
- [ ] **M-02 — Revisión humana de operación y cierre:** legibilidad, aceptación/entrega, política de ambiguos, resultados privados y retro con datos humanos reales. T-06. Sin aprobación simulada.

## Inmutabilidad y limpieza

UUID propio: `storage/providers-email-20261030/browser-aebf2c86-4e8c-4002-bd03-1cfb5be70246`.

```json
{
  "equal": true,
  "before_count": 507,
  "after_count": 507,
  "changed": [],
  "webhooks_sha256": "00a4023fdaca08a6c85a65363561205bbecb708784a532ccf57b5fc79e9a97ef"
}
```

```json
{
  "expected_ids": [
    "E2E-01",
    "E2E-02",
    "E2E-03",
    "E2E-04",
    "E2E-05",
    "E2E-06"
  ],
  "seen_ids": [
    "E2E-01",
    "E2E-02",
    "E2E-03",
    "E2E-04",
    "E2E-05",
    "E2E-06"
  ],
  "exact_match": true,
  "one_run_each": true
}
```

```json
{
  "exit": 0,
  "sql_clean": true,
  "owned_php_exited": true,
  "owned_listeners_closed": true
}
```

```json
{
  "owned_sql_removed": true,
  "owned_php_exited": true,
  "owned_ports_closed": true
}
```

Los recibos son del UUID emitido por el launcher. Cleanup atribuido a sus fixtures SQL, árbol de procesos y puertos; Mailpit elimina sólo mensajes cuyo asunto empieza por el UUID propio y confirma que no quedan. No se inventarían recursos/procesos globalmente ni se borran recursos compartidos.

| Evidencia | SHA256 |
|---|---|
| [results.json](raw/results.json) | `482cff4dea7d20e50e64604df04a0e37877052825aa45ee416aa310a29bd4970` |
| [qa-gate.json](raw/qa-gate.json) | `b537a874afb3abcc886b734066d29ec9d4aa4c95c4e7a47f861550c77418a890` |
| [cleanup.json](raw/cleanup.json) | `e88fe74224422498b06f23f876832f6a6f7c45d13ed6ec8c4a5d9fba413003ce` |
| [bounded-receipt.public.json](raw/bounded-receipt.public.json) | `4c97f7296a0249bbe7c4100b121f9e85d8ac9e5f312dc1a3960764bcec11dd1d` |
| [browser-settlement.public.json](raw/browser-settlement.public.json) | `dfada0ef21ab206cd3a2fd948bc4cdbc20687c781f2a3afcdd37fd00851331ae` |
| [source-manifest-before.json](raw/source-manifest-before.json) | `5d477cc0172ab01a4f21047cc992815863b9ad0216fc3c0df1f85664a7514195` |
| [source-manifest-after.json](raw/source-manifest-after.json) | `5d477cc0172ab01a4f21047cc992815863b9ad0216fc3c0df1f85664a7514195` |

## Bundle servido y límite de procedencia

Los layouts usan @vite y `public/hot` está ausente: la tanda usó el build Vite existente, sin ejecutar npm build. Sus nombres y hashes se inspeccionaron después de terminar el runner. `public/build` está ignorado y no pertenece al snapshot de 507 fuentes; no existe hash previo de estos assets y no se afirma paridad antes/después del bundle.

[Metadatos del bundle](raw/asset-bundle.json): manifiesto SHA256 `75a86e6636d8c7039a38ea12a649ed3a3831a9166f4fb4335e3eb5b565c4d968`; CSS `assets/app-BzWuR0cw.css` y JS `assets/app-DMsN-rLE.js`.

## Trazabilidad y handoff

| Tarea | Escenarios |
|---|---|
| T-01 | E2E-01/02/03/04 |
| T-02 | E2E-04/05/06 |
| T-03 | E2E-04/06, M-01 |
| T-04 | E2E-04/05/06, M-01 |
| T-05 | E2E-05/06, M-01 |
| T-06 | E2E-01–06, M-01/M-02 |

QA no aceptada. Root coordina diagnóstico/corrección y siguiente intento; no hay handoff a documenter ni cierre de estados.

T12–T14 MCP y T19/T20 de la plataforma principal permanecen fuera del alcance. Informe sólo-local, sin publicación Confluence/Jira ni mensajes externos.

PDF vía skill to-pdf con tema HTML/CSS del bundle, imágenes originales embebidas, Puppeteer ya instalado y Chrome existente. HTML/CSS temporales y copias de fuentes viven en `storage/providers-email-20261030/qa-2-report-tools`. Verificación visual PDF en `raw/pdf-verification.json`. Trazas/vídeo desactivados en la configuración original; no se inventan.
