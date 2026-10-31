# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: mail-providers.spec.mjs >> E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado
- Location: tests\e2e\mail-providers.spec.mjs:181:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ }) with timeout 5000ms
  - waiting for locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })

```

```yaml
- complementary:
  - link "↗ correo .":
    - /url: /dashboard
  - paragraph: ESPACIO DE TRABAJO
  - navigation:
    - link "◫ Inicio":
      - /url: https://127.0.0.1:18867/dashboard
    - link "♧ Teammates":
      - /url: /team
    - link "Contactos y listas":
      - /url: https://127.0.0.1:18867/contacts
    - link "Transporte y remitentes":
      - /url: https://127.0.0.1:18867/settings/mail
    - link "Plantillas":
      - /url: https://127.0.0.1:18867/templates
    - link "Campañas":
      - /url: https://127.0.0.1:18867/campaigns
    - link "Bajas y supresiones":
      - /url: https://127.0.0.1:18867/suppressions
    - link "Actividad":
      - /url: /activity
    - link "Llaves API":
      - /url: https://127.0.0.1:18867/api-keys
    - link "⇄ Mis cuentas":
      - /url: https://127.0.0.1:18867/accounts
    - link "Identidad y acceso":
      - /url: https://127.0.0.1:18867/settings/identities
    - link "Conexiones MCP":
      - /url: https://127.0.0.1:18867/settings/connections
  - text: Un equipo. Un espacio compartido.
- banner:
  - text: Cuenta activa
  - combobox "Cuenta activa":
    - option "Provider fixture 6" [selected]
    - option "Isolated fixture 6"
  - text: Provider browser 6
  - button "Salir"
- main:
  - paragraph: RESULTADOS OBSERVADOS
  - heading "Actividad de la cuenta" [level=1]
  - paragraph: Aceptado por SMTP o API indica la aceptación del relay o proveedor. No acredita entrega, apertura ni clic. Un archivo log no conecta SMTP. Los resultados ambiguos no se reenvían desde este panel.
  - region "Filtrar actividad":
    - heading "Filtrar actividad" [level=2]
    - text: Desde (UTC)
    - textbox "Desde (UTC)": 2026-10-02
    - text: Hasta, incluido (UTC)
    - textbox "Hasta, incluido (UTC)": 2026-10-31
    - text: Población
    - combobox "Población":
      - option "Todas, con contadores separados" [selected]
      - option "Entregas lógicas de API"
      - option "Entregas lógicas de campañas"
      - option "Pruebas explícitas"
      - option "Históricos de procedencia desconocida"
      - option "Entregas lógicas de MCP"
    - text: Estado
    - combobox "Estado":
      - option "Todos los estados" [selected]
      - option "Pendiente"
      - option "Reservada"
      - option "En proceso"
      - option "Aceptado por SMTP"
      - option "Aceptado por API"
      - option "Archivo privado (log)"
      - option "Fallido"
      - option "Suprimido"
      - option "Aceptación ambigua"
      - option "Bloqueado"
    - text: Destinatario o asunto
    - searchbox "Destinatario o asunto"
    - text: ID de campaña (opcional)
    - spinbutton "ID de campaña (opcional)"
    - paragraph: La ventana usa la fecha de creación de la entrega o del registro independiente. Inicio incluido, fin al comienzo del día siguiente excluido; zona UTC. Filtros y estado se aplican también a los contadores y al CSV.
    - link "Descargar CSV de esta selección":
      - /url: https://127.0.0.1:18867/activity/export?from=2026-10-02&to=2026-10-31&population=&status=&search=&campaign=
  - region "Métricas por población":
    - article:
      - heading "Entregas lógicas de API" [level=2]
      - paragraph: "0"
      - paragraph: "Aceptadas SMTP: 0 · API: 0 / 0 (sin muestra)"
      - list
    - article:
      - heading "Entregas lógicas de campañas" [level=2]
      - paragraph: "0"
      - paragraph: "Aceptadas SMTP: 0 · API: 0 / 0 (sin muestra)"
      - list
    - article:
      - heading "Pruebas explícitas" [level=2]
      - paragraph: "2"
      - paragraph: "Aceptadas SMTP: 0 · API: 2 / 2 (100 %)"
      - list:
        - listitem: "Aceptado por API: 2"
    - article:
      - heading "Históricos de procedencia desconocida" [level=2]
      - paragraph: "0"
      - paragraph: "Aceptadas SMTP: 0 · API: 0 / 0 (sin muestra)"
      - list
    - article:
      - heading "Entregas lógicas de MCP" [level=2]
      - paragraph: "0"
      - paragraph: "Aceptadas SMTP: 0 · API: 0 / 0 (sin muestra)"
      - list
  - region "Feedback y alertas":
    - heading "Feedback y alertas" [level=2]
    - paragraph: "Fuente de los contadores: SQL local, estados actuales de la cohorte filtrada. Cada entrega lógica cuenta una vez, aunque tenga varios intentos. Pruebas explícitas e históricos sin procedencia son poblaciones independientes; no constituyen emails únicos de campaña. Un histórico desconocido podría ser un intento anterior sin vínculo reconstruible."
    - paragraph: Los estados Fallido, Bloqueado y Aceptación ambigua muestran incidencias locales comprobadas. Una espera por cuota o pausa sigue pendiente; no es un fallo final. Las incidencias de intentos anteriores se consultan en el detalle.
    - paragraph: La aceptacion del transporte y el feedback posterior del proveedor se muestran por separado.
    - paragraph: Brevo y SendGrid pueden comunicar entrega, rebote, queja y baja mediante webhooks autenticados. Aperturas, clics y listas de bloqueo no se miden aqui. Sin eventos, los resultados son desconocidos. La ausencia de datos no equivale a 0 % de incidencias ni demuestra reputación saludable. Estos porcentajes describen estados locales, no tasas de entrega ni un SLA.
  - region "Entregas y registros independientes":
    - heading "Entregas y registros independientes" [level=2]
    - list:
      - listitem:
        - paragraph: recipient@example.test
        - text: Aceptado por API
        - paragraph: provider-browser-aebf2c86-4e8c-4002-bd03-1cfb5be70246-brevo-feedback
        - paragraph: 2026-10-31 00:22:55 UTC · Pruebas explícitas · Provider browser 6
        - paragraph: Baja; destinatario suprimido · 2026-10-31 00:22:56.000000 UTC
        - button "Ver detalle e intentos"
      - listitem:
        - paragraph: recipient@example.test
        - text: Aceptado por API
        - paragraph: provider-browser-aebf2c86-4e8c-4002-bd03-1cfb5be70246-sendgrid-feedback
        - paragraph: 2026-10-31 00:22:54 UTC · Pruebas explícitas · Provider browser 6
        - paragraph: Queja; destinatario suprimido · 2026-10-31 00:22:56.000000 UTC
        - button "Ver detalle e intentos"
```

# Test source

```ts
  114 |   await expect(page.getByText('sender@example.test', { exact: false })).toHaveCount(0);
  115 |   expect(await page.content()).not.toContain(state.connections.brevo);
  116 |   await shot(page, 'E2E-03-account-B');
  117 |   await page.getByRole('combobox', { name: 'Cuenta activa', exact: true }).selectOption(String(state.account_id));
  118 |   await expect(page).toHaveURL(/\/dashboard$/);
  119 |   await page.goto('/settings/mail');
  120 |   const brevo = profile(page, 'Brevo');
  121 |   await expect(brevo).toContainText('API: preparada');
  122 |   expect(action('revoke-writer', 3).revoked).toBe(true);
  123 |   await brevo.getByRole('checkbox', { name: /^Eliminar todas las credenciales/ }).check();
  124 |   const denied = page.waitForResponse(livewirePost);
  125 |   await brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }).click();
  126 |   expect((await denied).status()).toBe(403);
  127 |   expect(action('snapshot', 3).profiles.brevo).toBe(true);
  128 |   const readerContext = await browser.newContext({ ignoreHTTPSErrors: true, serviceWorkers: 'block', proxy: { server: 'http://127.0.0.1:18868', bypass: '<-loopback>,127.0.0.1' } });
  129 |   try {
  130 |     const reader = await readerContext.newPage();
  131 |     await login(reader, 3, true);
  132 |     await expect(reader.getByRole('button', { name: 'Guardar Brevo', exact: true })).toHaveCount(0);
  133 |     await expect(reader.getByRole('button', { name: 'Nuevo remitente', exact: true })).toHaveCount(0);
  134 |     await shot(reader, 'E2E-03-reader');
  135 |   } finally { await readerContext.close(); }
  136 | });
  137 | 
  138 | test('E2E-04 — Enviar por los cuatro canales y distinguir aceptación de entrega', async ({ page }) => {
  139 |   await login(page, 4);
  140 |   for (const mode of [...modes, 'log', 'mailpit']) {
  141 |     await saveMode(page, mode);
  142 |     await sendTest(page, 4, mode);
  143 |     const expected = mode === 'log' ? 'logged' : mode.endsWith('_api') ? 'api_accepted' : 'smtp_accepted';
  144 |     const state = action('snapshot', 4);
  145 |     expect(state.messages.at(-1).status).toBe(expected);
  146 |     expect(state.messages.at(-1).mode).toBe(mode);
  147 |     const durable = action('work', 4, mode);
  148 |     expect(durable.status).toBe(expected);
  149 |     expect(durable.redelivery_blocked).toBe(true);
  150 |     if (mode.endsWith('_api')) await expect(page.getByText('API aceptada por el proveedor.', { exact: false }).first()).toBeVisible();
  151 |     await shot(page, 'E2E-04-'+mode);
  152 |   }
  153 |   expect(action('mailpit-capture', 4).count).toBe(2);
  154 |   await page.goto('/activity');
  155 |   await expect(page.getByRole('combobox', { name: 'Estado', exact: true })).toContainText('Aceptado por API');
  156 |   await expect(page.getByText('Sin feedback del proveedor', { exact: true }).first()).toBeVisible();
  157 |   await shot(page, 'E2E-04-activity');
  158 | });
  159 | 
  160 | test('E2E-05 — Mostrar rechazo, rate limit e incertidumbre sin duplicar', async ({ page }) => {
  161 |   await login(page, 5);
  162 |   const rate = action('work', 5, 'brevo_api', 'rate');
  163 |   expect(rate.status).toBe('pending');
  164 |   expect(rate.generation).toBe(2);
  165 |   expect(rate.attempts).toBe(1);
  166 |   expect(rate.available_at).toBeGreaterThan(Math.floor(Date.now()/1000)+90);
  167 |   expect(action('work', 5, 'sendgrid_api', 'permanent').status).toBe('failed');
  168 |   expect(action('work', 5, 'brevo_smtp', 'uncertain').status).toBe('ambiguous');
  169 |   expect(action('work', 5, 'brevo_api', 'change').status).toBe('api_accepted');
  170 |   expect(action('work', 5, 'brevo_api', 'fence').status).toBe('ambiguous');
  171 |   const before = action('snapshot', 5);
  172 |   expect(before.messages.some(m => m.mode === 'brevo_api' && m.status === 'api_accepted')).toBe(true);
  173 |   await page.goto('/activity');
  174 |   await expect(page.getByText('Limite temporal del proveedor', { exact: true })).toBeVisible();
  175 |   await expect(page.locator('[aria-labelledby="activity-list"] span').filter({ hasText: /^Aceptación ambigua$/ }).first()).toBeVisible();
  176 |   await page.reload();
  177 |   expect(action('snapshot', 5).io_count).toBe(before.io_count);
  178 |   await shot(page, 'E2E-05-outcomes');
  179 | });
  180 | 
  181 | test('E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado', async ({ page }) => {
  182 |   await login(page, 6);
  183 |   await saveMode(page, 'sendgrid_api');
  184 |   await sendTest(page, 6, 'sendgrid-feedback');
  185 |   await saveMode(page, 'brevo_api');
  186 |   await sendTest(page, 6, 'brevo-feedback');
  187 |   const state = fixture.cases['6'];
  188 |   const messages = action('snapshot', 6).messages;
  189 |   const sg = messages.find(m => m.mode === 'sendgrid_api');
  190 |   const brevo = messages.find(m => m.mode === 'brevo_api');
  191 |   const timestamp = String(Math.floor(Date.now()/1000));
  192 |   const event = [{ event: 'spamreport', email: 'recipient@example.test', timestamp: Number(timestamp), sg_event_id: 'synthetic-browser-event', emailing_reference: sg.reference }];
  193 |   const body = JSON.stringify(event);
  194 |   const headers = { 'Content-Type': 'application/json', 'X-Twilio-Email-Event-Webhook-Timestamp': timestamp,
  195 |     'X-Twilio-Email-Event-Webhook-Signature': sign('sha256', Buffer.from(timestamp+body), state.sendgrid_private).toString('base64') };
  196 |   const sgUrl = origin+'/webhooks/mail/sendgrid/'+state.connections.sendgrid;
  197 |   expect((await page.request.post(sgUrl, { headers, data: body })).status()).toBe(204);
  198 |   expect((await page.request.post(sgUrl, { headers, data: body })).status()).toBe(204);
  199 |   expect((await page.request.post(sgUrl, { headers, data: body+' ' })).status()).toBe(401);
  200 |   const brevoUrl = origin+'/webhooks/mail/brevo/'+state.connections.brevo;
  201 |   const brevoHeaders = { Authorization: 'Bearer '+'synthetic-feedback-'.repeat(3) };
  202 |   for (const kind of ['hard_bounce', 'unsubscribed', 'delivered']) {
  203 |     expect((await page.request.post(brevoUrl, { headers: brevoHeaders, data: { event: kind, email: 'recipient@example.test',
  204 |       ts_event: Number(timestamp), 'X-Mailin-custom': 'emailing_reference:'+brevo.reference } })).status()).toBe(204);
  205 |   }
  206 |   expect((await page.request.post(brevoUrl, { headers: { Authorization: 'Bearer incorrect' }, data: event[0] })).status()).toBe(401);
  207 |   const feedback = action('snapshot', 6);
  208 |   expect(feedback.events).toBe(4);
  209 |   expect(feedback.suppressions).toBe(1);
  210 |   expect(feedback.messages.find(m => m.id === sg.id).status).toBe('api_accepted');
  211 |   expect(feedback.messages.find(m => m.id === sg.id).provider_status).toBe('complained');
  212 |   expect(feedback.messages.find(m => m.id === brevo.id).provider_status).toBe('unsubscribed');
  213 |   await page.goto('/activity');
> 214 |   await expect(page.locator('[aria-labelledby="activity-list"] p').filter({ hasText: /^Queja; destinatario suprimido · .* UTC$/ })).toBeVisible();
      |                                                                                                                                     ^ Error: expect(locator).toBeVisible() failed
  215 |   await shot(page, 'E2E-06-feedback');
  216 |   await page.goto('/suppressions');
  217 |   await expect(page.getByText('Queja comunicada por el proveedor', { exact: false })).toBeVisible();
  218 |   await page.goto('/settings/mail');
  219 |   await sendTest(page, 6, 'suppressed');
  220 |   const after = action('snapshot', 6);
  221 |   expect(after.messages.at(-1).status).toBe('suppressed');
  222 |   expect(after.io_count).toBe(feedback.io_count);
  223 |   const clear = profile(page, 'SendGrid').getByRole('checkbox', { name: /^Eliminar todas las credenciales/ });
  224 |   await profile(page, 'SendGrid').getByRole('button', { name: 'Guardar SendGrid', exact: true }).focus();
  225 |   await page.keyboard.press('Shift+Tab');
  226 |   await expect(clear).toBeFocused();
  227 |   await page.setViewportSize({ width: 375, height: 812 });
  228 |   expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  229 |   await shot(page, 'E2E-06-keyboard-mobile');
  230 | });
  231 | 
```