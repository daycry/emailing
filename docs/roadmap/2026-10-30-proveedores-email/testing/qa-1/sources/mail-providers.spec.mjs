import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { sign } from 'node:crypto';
import path from 'node:path';

const origin = 'https://127.0.0.1:18867';
const directory = process.env.PROVIDER_BROWSER_DIRECTORY;
const output = process.env.PROVIDER_BROWSER_OUTPUT;
const fixture = JSON.parse(readFileSync(path.join(directory, 'fixtures.private.json'), 'utf8'));
const modes = ['brevo_smtp', 'brevo_api', 'sendgrid_smtp', 'sendgrid_api'];
const livewirePost = response => response.request().method() === 'POST' && new URL(response.url()).pathname.includes('livewire');
const profile = (page, name) => page.getByRole('region', { name, exact: true });

function action(operation, caseId, mode, outcome = 'accept') {
  const result = spawnSync('php', ['-d', 'zend.exception_ignore_args=1', 'tests/support/mail-provider-browser-fixtures.php', operation, String(caseId), ...(mode ? [mode] : [])],
    { env: { ...process.env, PROVIDER_BROWSER_OUTCOME: outcome }, encoding: 'utf8', timeout: 60000 });
  expect(result.status, 'Owned fixture operation completed').toBe(0);
  return JSON.parse(result.stdout);
}
async function login(page, caseId, reader = false) {
  const state = fixture.cases[String(caseId)];
  await page.goto('/login');
  await page.getByRole('textbox', { name: 'Email', exact: true }).fill(reader ? state.reader_email : state.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(state.password);
  await page.getByRole('button', { name: 'Acceder', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await page.goto('/settings/mail');
  await expect(page.getByRole('heading', { name: 'Transporte y remitentes', exact: true })).toBeVisible();
}
async function submit(page, button) {
  const response = page.waitForResponse(livewirePost);
  await button.click();
  const result = await response;
  expect(result.status()).toBe(200);
  await result.finished();
}
async function saveMode(page, mode) {
  await page.getByRole('combobox', { name: /^Modo/ }).selectOption(mode);
  await submit(page, page.getByRole('button', { name: 'Guardar configuración', exact: true }));
  await page.reload();
  await expect(page.getByRole('combobox', { name: /^Modo/ })).toHaveValue(mode);
}
async function sendTest(page, caseId, label) {
  await page.getByRole('combobox', { name: /^Remitente/ }).selectOption(String(fixture.cases[String(caseId)].sender_id));
  await page.getByLabel('Destinatario', { exact: true }).fill('recipient@example.test');
  await page.getByLabel('Asunto', { exact: true }).fill('provider-browser-'+fixture.run+'-'+label);
  await submit(page, page.getByRole('button', { name: 'Crear prueba', exact: true }));
}
async function shot(page, name) {
  await page.screenshot({ path: path.join(output, 'screenshots', name+'.png'), fullPage: true });
}

test('E2E-01 — Guardar y alternar los cuatro modos sin devolver secretos', async ({ page }) => {
  await login(page, 1);
  await expect(page.getByRole('combobox', { name: /^Modo/ })).toHaveValue('log');
  const secrets = ['synthetic-ui-brevo-api', 'synthetic-ui-brevo-smtp', 'synthetic-ui-sg-api', 'synthetic-ui-sg-smtp'];
  const responses = [];
  page.on('response', response => { if (livewirePost(response)) responses.push(response.text().then(body => secrets.every(secret => !body.includes(secret)))); });
  const brevo = profile(page, 'Brevo');
  await brevo.getByLabel('Clave API nueva de Brevo', { exact: true }).fill(secrets[0]);
  await brevo.getByLabel('Usuario SMTP nuevo', { exact: true }).fill('synthetic-ui-user');
  await brevo.getByLabel(/^Clave SMTP nueva/).fill(secrets[1]);
  await submit(page, brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }));
  await expect(brevo.getByLabel('Clave API nueva de Brevo', { exact: true })).toHaveValue('');
  const sendgrid = profile(page, 'SendGrid');
  await sendgrid.getByLabel('Clave API nueva de SendGrid', { exact: true }).fill(secrets[2]);
  await sendgrid.getByLabel('Clave API nueva para autenticar SMTP', { exact: true }).fill(secrets[3]);
  await submit(page, sendgrid.getByRole('button', { name: 'Guardar SendGrid', exact: true }));
  for (const mode of modes) await saveMode(page, mode);
  await submit(page, brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }));
  await expect(brevo).toContainText('SMTP: preparado');
  await expect(brevo).toContainText('API: preparada');
  const html = await page.content();
  for (const secret of secrets) expect(html).not.toContain(secret);
  expect((await Promise.all(responses)).every(Boolean)).toBe(true);
  expect(action('snapshot', 1).io_count).toBe(0);
  await shot(page, 'E2E-01-profiles');
});

test('E2E-02 — Limpiar y corregir configuración de un único proveedor', async ({ page }) => {
  await login(page, 2);
  const brevo = profile(page, 'Brevo');
  await brevo.getByLabel('Bearer privado nuevo del webhook', { exact: true }).fill('short');
  // Bypass only native minlength, to exercise server-side validation through the real action.
  await brevo.getByLabel('Bearer privado nuevo del webhook', { exact: true }).evaluate(el => el.removeAttribute('minlength'));
  await submit(page, brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }));
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(brevo.getByLabel('Bearer privado nuevo del webhook', { exact: true })).toHaveValue('');
  await shot(page, 'E2E-02-error');
  await brevo.getByLabel('Bearer privado nuevo del webhook', { exact: true }).fill('synthetic-corrected-feedback-'.repeat(2));
  await submit(page, brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }));
  await expect(page.getByRole('alert')).toHaveCount(0);
  await brevo.getByLabel(/^Eliminar todas las credenciales/).check();
  await submit(page, brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }));
  await page.reload();
  await expect(brevo).toContainText('SMTP: sin preparar');
  await expect(profile(page, 'SendGrid')).toContainText('API: preparada');
  await saveMode(page, 'brevo_api');
  const before = action('snapshot', 2).io_count;
  await sendTest(page, 2, 'incomplete');
  await expect(page.getByText('Transporte bloqueado.', { exact: false }).first()).toBeVisible();
  expect(action('snapshot', 2).io_count).toBe(before);
  await shot(page, 'E2E-02-cleared');
});

test('E2E-03 — Aislar cuentas y revalidar permisos de ajustes/remitentes', async ({ page, browser }) => {
  await login(page, 3);
  const state = fixture.cases['3'];
  await page.getByRole('combobox', { name: 'Cuenta activa', exact: true }).selectOption(String(state.other_account_id));
  await expect(page).toHaveURL(/\/dashboard$/);
  await page.goto('/settings/mail');
  await expect(profile(page, 'Brevo')).toContainText('Sin credenciales');
  await expect(page.getByText('sender@example.test', { exact: false })).toHaveCount(0);
  expect(await page.content()).not.toContain(state.connections.brevo);
  await shot(page, 'E2E-03-account-B');
  await page.getByRole('combobox', { name: 'Cuenta activa', exact: true }).selectOption(String(state.account_id));
  await page.goto('/settings/mail');
  const brevo = profile(page, 'Brevo');
  await expect(brevo).toContainText('API: preparada');
  expect(action('revoke-writer', 3).revoked).toBe(true);
  await brevo.getByLabel(/^Eliminar todas las credenciales/).check();
  const denied = page.waitForResponse(livewirePost);
  await brevo.getByRole('button', { name: 'Guardar Brevo', exact: true }).click();
  expect((await denied).status()).toBe(403);
  expect(action('snapshot', 3).profiles.brevo).toBe(true);
  const readerContext = await browser.newContext({ ignoreHTTPSErrors: true, serviceWorkers: 'block', proxy: { server: 'http://127.0.0.1:18868', bypass: '<-loopback>,127.0.0.1' } });
  try {
    const reader = await readerContext.newPage();
    await login(reader, 3, true);
    await expect(reader.getByRole('button', { name: 'Guardar Brevo', exact: true })).toHaveCount(0);
    await expect(reader.getByRole('button', { name: 'Nuevo remitente', exact: true })).toHaveCount(0);
    await shot(reader, 'E2E-03-reader');
  } finally { await readerContext.close(); }
});

test('E2E-04 — Enviar por los cuatro canales y distinguir aceptación de entrega', async ({ page }) => {
  await login(page, 4);
  for (const mode of [...modes, 'log', 'mailpit']) {
    await saveMode(page, mode);
    await sendTest(page, 4, mode);
    const expected = mode === 'log' ? 'logged' : mode.endsWith('_api') ? 'api_accepted' : 'smtp_accepted';
    const state = action('snapshot', 4);
    expect(state.messages.at(-1).status).toBe(expected);
    expect(state.messages.at(-1).mode).toBe(mode);
    const durable = action('work', 4, mode);
    expect(durable.status).toBe(expected);
    expect(durable.redelivery_blocked).toBe(true);
    if (mode.endsWith('_api')) await expect(page.getByText('API aceptada por el proveedor.', { exact: false }).first()).toBeVisible();
    await shot(page, 'E2E-04-'+mode);
  }
  expect(action('mailpit-capture', 4).count).toBe(2);
  await page.goto('/activity');
  await expect(page.getByRole('combobox', { name: 'Estado', exact: true })).toContainText('Aceptado por API');
  await expect(page.getByText('Sin feedback del proveedor', { exact: true }).first()).toBeVisible();
  await shot(page, 'E2E-04-activity');
});

test('E2E-05 — Mostrar rechazo, rate limit e incertidumbre sin duplicar', async ({ page }) => {
  await login(page, 5);
  const rate = action('work', 5, 'brevo_api', 'rate');
  expect(rate.status).toBe('pending');
  expect(rate.generation).toBe(2);
  expect(rate.attempts).toBe(1);
  expect(rate.available_at).toBeGreaterThan(Math.floor(Date.now()/1000)+90);
  expect(action('work', 5, 'sendgrid_api', 'permanent').status).toBe('failed');
  expect(action('work', 5, 'brevo_smtp', 'uncertain').status).toBe('ambiguous');
  expect(action('work', 5, 'brevo_api', 'change').status).toBe('api_accepted');
  expect(action('work', 5, 'brevo_api', 'fence').status).toBe('ambiguous');
  const before = action('snapshot', 5);
  expect(before.messages.some(m => m.mode === 'brevo_api' && m.status === 'api_accepted')).toBe(true);
  await page.goto('/activity');
  await expect(page.getByText('Limite temporal del proveedor', { exact: true })).toBeVisible();
  await expect(page.getByText('Aceptación ambigua', { exact: true }).first()).toBeVisible();
  await page.reload();
  expect(action('snapshot', 5).io_count).toBe(before.io_count);
  await shot(page, 'E2E-05-outcomes');
});

test('E2E-06 — Consultar feedback autenticado, supresiones y operar por teclado', async ({ page }) => {
  await login(page, 6);
  await saveMode(page, 'sendgrid_api');
  await sendTest(page, 6, 'sendgrid-feedback');
  await saveMode(page, 'brevo_api');
  await sendTest(page, 6, 'brevo-feedback');
  const state = fixture.cases['6'];
  const messages = action('snapshot', 6).messages;
  const sg = messages.find(m => m.mode === 'sendgrid_api');
  const brevo = messages.find(m => m.mode === 'brevo_api');
  const timestamp = String(Math.floor(Date.now()/1000));
  const event = [{ event: 'spamreport', email: 'recipient@example.test', timestamp: Number(timestamp), sg_event_id: 'synthetic-browser-event', emailing_reference: sg.reference }];
  const body = JSON.stringify(event);
  const headers = { 'Content-Type': 'application/json', 'X-Twilio-Email-Event-Webhook-Timestamp': timestamp,
    'X-Twilio-Email-Event-Webhook-Signature': sign('sha256', Buffer.from(timestamp+body), state.sendgrid_private).toString('base64') };
  const sgUrl = origin+'/webhooks/mail/sendgrid/'+state.connections.sendgrid;
  expect((await page.request.post(sgUrl, { headers, data: body })).status()).toBe(204);
  expect((await page.request.post(sgUrl, { headers, data: body })).status()).toBe(204);
  expect((await page.request.post(sgUrl, { headers, data: body+' ' })).status()).toBe(401);
  const brevoUrl = origin+'/webhooks/mail/brevo/'+state.connections.brevo;
  const brevoHeaders = { Authorization: 'Bearer '+'synthetic-feedback-'.repeat(3) };
  for (const kind of ['hard_bounce', 'unsubscribed', 'delivered']) {
    expect((await page.request.post(brevoUrl, { headers: brevoHeaders, data: { event: kind, email: 'recipient@example.test',
      ts_event: Number(timestamp), 'X-Mailin-custom': 'emailing_reference:'+brevo.reference } })).status()).toBe(204);
  }
  expect((await page.request.post(brevoUrl, { headers: { Authorization: 'Bearer incorrect' }, data: event[0] })).status()).toBe(401);
  const feedback = action('snapshot', 6);
  expect(feedback.events).toBe(4);
  expect(feedback.suppressions).toBe(1);
  expect(feedback.messages.find(m => m.id === sg.id).status).toBe('api_accepted');
  expect(feedback.messages.find(m => m.id === sg.id).provider_status).toBe('complained');
  expect(feedback.messages.find(m => m.id === brevo.id).provider_status).toBe('unsubscribed');
  await page.goto('/activity');
  await expect(page.getByText('Queja; destinatario suprimido', { exact: true })).toBeVisible();
  await shot(page, 'E2E-06-feedback');
  await page.goto('/suppressions');
  await expect(page.getByText('Queja comunicada por el proveedor', { exact: false })).toBeVisible();
  await page.goto('/settings/mail');
  await sendTest(page, 6, 'suppressed');
  const after = action('snapshot', 6);
  expect(after.messages.at(-1).status).toBe('suppressed');
  expect(after.io_count).toBe(feedback.io_count);
  const clear = profile(page, 'SendGrid').getByLabel(/^Eliminar todas las credenciales/);
  await profile(page, 'SendGrid').getByRole('button', { name: 'Guardar SendGrid', exact: true }).focus();
  await page.keyboard.press('Shift+Tab');
  await expect(clear).toBeFocused();
  await page.setViewportSize({ width: 375, height: 812 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await shot(page, 'E2E-06-keyboard-mobile');
});
