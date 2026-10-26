import { test, expect, request as requestFactory } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import { randomUUID, randomBytes, createHash } from 'node:crypto';
import { spawnSync } from 'node:child_process';
import { createServer } from 'node:http';

const origin = 'http://127.0.0.1:18967';
const callbackOrigin = 'http://127.0.0.1:18743';
const callbackHandlers = new Map();
let callbackServer;

test.beforeAll(async () => {
  callbackServer = createServer(async (request, response) => {
    const url = new URL(request.url, callbackOrigin);
    const state = url.searchParams.get('state');
    const handler = callbackHandlers.get(state);
    callbackHandlers.delete(state);
    response.setHeader('Content-Type', 'text/html; charset=utf-8');
    response.setHeader('Referrer-Policy', 'no-referrer');
    response.setHeader('Cache-Control', 'no-store');
    if (request.method !== 'GET' || url.pathname !== '/callback' || !handler) {
      response.writeHead(400).end('Invalid authorization response');
      return;
    }
    try {
      const result = await handler(url);
      response.writeHead(result.status ?? 200).end('<script>history.replaceState(null,"","/callback")</script>' + result.body);
    } catch {
      response.writeHead(500).end('Authorization response could not be completed');
    }
  });
  await new Promise((resolve, reject) => {
    callbackServer.once('error', reject);
    callbackServer.listen(18743, '127.0.0.1', resolve);
  });
});

test.afterAll(async () => {
  callbackHandlers.clear();
  if (callbackServer?.listening) {
    await new Promise((resolve, reject) => {
      callbackServer.close(error => error ? reject(error) : resolve());
      callbackServer.closeIdleConnections();
    });
  }
  if (process.env.MCP_EVIDENCE_DIRECTORY) {
    writeFileSync(process.env.MCP_EVIDENCE_DIRECTORY + '/callback-server-receipt.json', JSON.stringify({ port: 18743, closed: !callbackServer?.listening, live_handles: [] }));
  }
});
const fixture = () => JSON.parse(readFileSync('storage/mcp-google-phase3/browser-fixtures.json', 'utf8'));

async function isolatedClient() {
  return requestFactory.newContext({ baseURL: origin, proxy: { server: 'http://127.0.0.1:18968', bypass: '127.0.0.1' } });
}

async function startConsent(page, data, scope = 'contacts.read contacts.write') {
  const verifier = randomBytes(48).toString('base64url');
  const state = randomUUID();
  const client = await isolatedClient();
  let resolveCallback;
  const completed = new Promise(resolve => { resolveCallback = resolve; });
  callbackHandlers.set(state, async callback => {
    // Validate the literal issuer and state before consuming a code; never render either.
    if (callback.searchParams.get('iss') !== 'https://emailing.example.test' || callback.searchParams.get('state') !== state) {
      resolveCallback({ invalid: true });
      return { status: 400, body: 'Invalid authorization response' };
    }
    if (callback.searchParams.has('error')) {
      resolveCallback({ denied: callback.searchParams.get('error') === 'access_denied' });
      return { body: '<h1>Conexión denegada</h1>' };
    }
    const exchanged = await client.post('/oauth/token', { maxRedirects: 0, form: { grant_type: 'authorization_code', client_id: data.mcp_client_id, redirect_uri: 'http://127.0.0.1:18743/callback', resource: 'https://emailing.example.test/mcp', code: callback.searchParams.get('code'), code_verifier: verifier } });
    const status = exchanged.status();
    const payload = status === 200 ? await exchanged.json() : {};
    resolveCallback({ status, token: payload.access_token, expires: payload.expires_in, client });
    return { body: '<h1>Conexión autorizada</h1><p>El cliente completó el canje.</p>' };
  });
  const query = new URLSearchParams({ client_id: data.mcp_client_id, redirect_uri: 'http://127.0.0.1:18743/callback', resource: 'https://emailing.example.test/mcp', response_type: 'code', scope, state, code_challenge_method: 'S256', code_challenge: createHash('sha256').update(verifier).digest('base64url') });
  await page.goto('/oauth/authorize?' + query);
  return { completed, client };
}

async function approve(page, consent, account = 'Browser delegated B') {
  await page.getByRole('button', { name: 'Autorizar ' + account, exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Conexión autorizada', exact: true })).toBeVisible();
  const connection = await consent.completed;
  expect(connection.status).toBe(200);
  expect(typeof connection.token).toBe('string');
  expect((await page.content()).includes(connection.token)).toBe(false);
  return connection;
}

async function mcp(connection, method, params = {}, legacy = null) {
  const headers = { Accept: 'application/json, text/event-stream', Authorization: 'Bearer ' + connection.token, 'MCP-Protocol-Version': legacy ? '2025-11-25' : '2026-07-28' };
  if (legacy) headers['MCP-Session-Id'] = legacy;
  else {
    params._meta = { 'io.modelcontextprotocol/protocolVersion': '2026-07-28', 'io.modelcontextprotocol/clientCapabilities': {} };
    headers['Mcp-Method'] = method;
    if (method === 'tools/call') headers['Mcp-Name'] = params.name;
  }
  return connection.client.post('/mcp', { headers, maxRedirects: 0, data: { jsonrpc: '2.0', id: 1, method, params } });
}

async function safeError(response, code) {
  expect(response.status()).toBe(200);
  const body = await response.json();
  expect(body.result.isError).toBe(true);
  expect(Object.hasOwn(body.result, 'structuredContent')).toBe(false);
  const dto = JSON.parse(body.result.content[0].text);
  expect(dto.error.code).toBe(code);
  expect(dto.error.outcome).toBe('not_applied');
}

async function provider(page, person, overrides = {}, mutateState = false) {
  const receipt = {};
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
    if (url.origin === callbackOrigin && url.pathname === '/callback') return route.continue();
    if (url.origin !== origin) return route.abort();
    const isStart = url.pathname.startsWith('/auth/google/redirect') || (url.pathname === '/settings/identities/google' && route.request().method() === 'POST');
    if (!isStart) return route.continue();
    const response = await route.fetch({ maxRedirects: 0 });
    const location = response.headers().location;
    if (!location || new URL(location, origin).hostname !== 'accounts.google.com') return route.fulfill({ response });
    const requested = new URL(location);
    const claims = { nonce: requested.searchParams.get('nonce'), sub: person.sub, email: person.email, synthetic_code_id: randomUUID(), ...overrides };
    const callback = new URL('/auth/google/callback', origin);
    callback.searchParams.set('state', mutateState ? 'foreign-state' : requested.searchParams.get('state'));
    callback.searchParams.set('code', Buffer.from(JSON.stringify(claims)).toString('base64url'));
    receipt.callback = callback.toString();
    const headers = { ...response.headers(), location: callback.toString() };
    delete headers['content-length'];
    await route.fulfill({ response, status: 302, headers, body: '' });
  });
  return receipt;
}

async function switchAccount(page, option) {
  // The native onchange submits a form. Await its navigation before a later goto
  // can cancel it; selecting an option alone does not prove server-side state.
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'load' }),
    page.getByLabel('Cuenta activa').selectOption(option),
  ]);
  const expected = typeof option === 'string' ? option : null;
  if (expected) await expect(page.getByLabel('Cuenta activa')).toHaveValue(expected);
}

async function localLogin(page, email) {
  await page.goto('/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
  await page.getByRole('button', { name: 'Acceder', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}

test.beforeEach(async ({ page, context }) => {
  const probe = await context.newPage();
  const blocked = await probe.goto('http://blocked.example.test/proxy-check');
  expect(blocked.status()).toBe(502);
  await expect(probe.goto('https://blocked.example.test/proxy-check')).rejects.toThrow(/TUNNEL|PROXY|ERR_/);
  await probe.close();
  await page.goto('/login');
  await expect(page.getByRole('heading', { name: 'Accede a tu espacio' })).toBeVisible();
  console.log('Google runner network boundary: HTTP502, HTTPS CONNECT rejected, local navigation OK.');
});

test('Google E2E-01 stable local user, account isolation and server return boundary', async ({ page }, testInfo) => {
  const data = fixture();
  await provider(page, { sub: 'browser-owner-' + data.run, email: 'changed@example.test' });
  await page.goto('/login');
  await page.screenshot({ path: testInfo.outputPath('google-login.png'), fullPage: true });
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByText('Browser Owner', { exact: true })).toBeVisible();
  const selector = page.getByLabel('Cuenta activa');
  await expect(selector).toContainText('Browser second own');
  await expect(selector).not.toContainText('Browser foreign account');
  await switchAccount(page, { label: 'Browser second own' });
  await expect(page.getByText('Solo lectura', { exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('google-dashboard.png'), fullPage: true });
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  const consent = await startConsent(page, data);
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL(/\/oauth\/authorize\?pending=[a-f0-9-]{36}$/);
  await expect(page.getByRole('heading', { name: 'Autorizar conexión', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('google-real-consent.png'), fullPage: true });
  const connection = await approve(page, consent);
  const read = await mcp(connection, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_b } });
  expect(read.status()).toBe(200);
  expect((await read.json()).result.structuredContent.data.list.name).toBe('Browser list B');
  await connection.client.dispose();
});

test('Google E2E-02 invalid callback, no email auto-link and explicit profile lifecycle', async ({ page }, testInfo) => {
  const data = fixture();
  const collision = { sub: 'browser-collision-' + data.run, email: data.emails.collision };
  await provider(page, collision, {}, true);
  await page.goto('/login');
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByText('No se pudo completar el acceso con Google.', { exact: false })).toBeVisible();
  await page.unroute('**/*');
  const unlinked = await provider(page, collision);
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goto(unlinked.callback);
  await expect(page).toHaveURL(/\/login$/);
  for (const invalid of [{ iss: 'https://attacker.example.test' }, { sub: '' }, { scenario: 'bad-code' }]) {
    await page.unroute('**/*');
    await provider(page, collision, invalid);
    await page.getByRole('link', { name: 'Continuar con Google' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await expect(page.getByRole('alert')).toContainText('No se pudo completar el acceso con Google.');
  }
  await page.unroute('**/*');
  await provider(page, collision);
  await localLogin(page, data.emails.collision);
  await page.getByRole('link', { name: 'Identidad y acceso', exact: true }).click();
  await page.getByRole('button', { name: 'Vincular Google', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Google vinculado', exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('google-profile.png'), fullPage: true });
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  await page.unroute('**/*');
  await provider(page, { ...collision, email: 'provider-changed@example.test' });
  await page.goto('/login');
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page.getByText('Browser Collision', { exact: true })).toBeVisible();
  await page.getByRole('link', { name: 'Identidad y acceso', exact: true }).click();
  await page.getByRole('button', { name: 'Desvincular Google', exact: true }).click();
  await expect(page.getByText('Todavía no has vinculado Google.')).toBeVisible();
});

test('Google E2E-03 explicit signup, Workspace, admission, invitation and local fallback', async ({ page, context }, testInfo) => {
  const data = fixture();
  const signup = { sub: 'browser-signup-' + data.run, email: data.emails.signup };
  await context.addCookies([{ name: 'google_fixture_mode', value: 'workspace', url: origin }]);
  await provider(page, signup, { hd: null });
  await page.goto('/register');
  await page.getByRole('link', { name: 'Crear cuenta con Google' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.unroute('**/*');
  await provider(page, signup, { hd: 'workspace.example.test' });
  await page.goto('/register');
  await page.getByRole('link', { name: 'Crear cuenta con Google' }).click();
  await expect(page.getByRole('heading', { name: 'Confirma tu nueva cuenta' })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('google-confirmation.png'), fullPage: true });
  await page.getByLabel('Nombre de la cuenta', { exact: true }).fill('Browser signup own');
  await page.getByLabel('Quiero crear mi cuenta y vincular Google.').check();
  await page.getByLabel('Contraseña local (opcional)', { exact: true }).fill('Synthetic-password-1234');
  await page.getByLabel('Repite la contraseña', { exact: true }).fill('Different-password-1234');
  await page.getByRole('button', { name: 'Crear mi cuenta', exact: true }).click();
  await expect(page).toHaveURL(/\/auth\/google\/confirm$/);
  await expect(page.getByRole('alert')).toBeVisible();
  await expect(page.getByLabel('Nombre de la cuenta', { exact: true })).toHaveValue('Browser signup own');
  await expect(page.getByLabel('Quiero crear mi cuenta y vincular Google.')).toBeChecked();
  await expect(page.getByLabel('Contraseña local (opcional)', { exact: true })).toHaveValue('');
  await expect(page.getByLabel('Repite la contraseña', { exact: true })).toHaveValue('');
  await page.screenshot({ path: testInfo.outputPath('google-correctable-registration.png'), fullPage: true });
  await page.getByRole('button', { name: 'Crear mi cuenta', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await page.getByRole('link', { name: 'Identidad y acceso', exact: true }).click();
  await expect(page.getByText('antes de desvincular Google.', { exact: false })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Desvincular Google', exact: true })).toHaveCount(0);
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  await context.addCookies([{ name: 'google_fixture_mode', value: 'closed', url: origin }]);
  await page.goto('/auth/google/redirect/register');
  await expect(page).toHaveURL(/\/login$/);
  await localLogin(page, data.emails.owner);
  const deniedInvitation = await page.goto('/invitations/' + data.invitation);
  expect(deniedInvitation.status()).toBe(403);
  await page.goto('/dashboard');
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  await page.unroute('**/*');
  await provider(page, { sub: 'browser-recipient-' + data.run, email: data.emails.recipient });
  await page.goto('/invitations/' + data.invitation);
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL('/invitations/' + data.invitation);
  await page.getByRole('button', { name: /Aceptar/ }).click();
  await expect(page).toHaveURL(/\/team$/);
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  await context.addCookies([{ name: 'google_fixture_mode', value: 'disabled', url: origin }]);
  await page.goto('/login');
  await expect(page.getByRole('status')).toContainText('Google no está configurado');
  await expect(page.getByRole('link', { name: 'Continuar con Google' })).toHaveCount(0);
  await localLogin(page, data.emails.owner);
});

test('MCP E2E-04 real consent revalidates account scopes and CSRF before automatic client exchange', async ({ page }, testInfo) => {
  const data = fixture();
  await localLogin(page, data.emails.owner);
  const denied = await startConsent(page, data);
  await expect(page.getByRole('heading', { name: 'Autorizar conexión', exact: true })).toBeVisible();
  await expect(page.getByText('Browser foreign account', { exact: true })).toHaveCount(0);
  const form = page.getByRole('form', { name: 'Autorizar Browser delegated B', exact: true });
  await expect(form.locator('input[value="contacts.read"]')).toBeChecked();
  await expect(form.locator('input[value="contacts.write"]')).toBeDisabled();
  await form.locator('input[value="contacts.read"]').focus();
  await page.keyboard.press('Space');
  await expect(form.locator('input[value="contacts.read"]')).not.toBeChecked();
  await page.keyboard.press('Space');
  await expect(form.locator('input[value="contacts.read"]')).toBeChecked();
  await page.keyboard.press('Tab');
  await expect(form.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true })).toBeFocused();
  await page.setViewportSize({ width: 390, height: 844 });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  await form.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true }).focus();
  await expect(form.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true })).toBeFocused();
  await page.screenshot({ path: testInfo.outputPath('mcp-limited-consent.png'), fullPage: true });
  const requestId = await form.locator('input[name="request_id"]').inputValue();
  const csrf = await form.locator('input[name="_token"]').inputValue();
  const withoutCsrf = await page.request.post('/oauth/authorize/decision', { maxRedirects: 0, form: { request_id: requestId, decision: 'allow', account_id: data.account_b, 'scopes[]': 'contacts.read' } });
  expect(withoutCsrf.status()).toBe(419);
  const foreign = await page.request.post('/oauth/authorize/decision', { maxRedirects: 0, form: { _token: csrf, request_id: requestId, decision: 'allow', account_id: data.foreign_account_id, 'scopes[]': 'contacts.read' } });
  expect(foreign.status()).toBe(403);
  const elevation = await page.request.post('/oauth/authorize/decision', { maxRedirects: 0, form: { _token: csrf, request_id: requestId, decision: 'allow', account_id: data.account_b, 'scopes[]': 'contacts.write' } });
  expect(elevation.status()).toBe(403);
  await form.locator('input[name="account_id"]').evaluate((input, value) => { input.value = String(value); }, data.foreign_account_id);
  await form.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('No se pudo autorizar');
  await page.screenshot({ path: testInfo.outputPath('mcp-consent-safe-error.png'), fullPage: true });
  await page.goto('/oauth/authorize?pending=' + requestId);
  await page.getByRole('button', { name: 'Denegar conexión', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Conexión denegada', exact: true })).toBeVisible();
  expect((await denied.completed).denied).toBe(true);
  await page.screenshot({ path: testInfo.outputPath('mcp-denied.png'), fullPage: true });
  await denied.client.dispose();
  const fresh = await startConsent(page, data);
  const connection = await approve(page, fresh);
  const response = await mcp(connection, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_b } });
  expect(response.status()).toBe(200);
  expect((await response.json()).result.structuredContent.data.list.name).toBe('Browser list B');
  await page.screenshot({ path: testInfo.outputPath('mcp-client-connected.png'), fullPage: true });
  await connection.client.dispose();
});

test('MCP E2E-05 bearer-only client keeps B authority across web account switch and rejects other credentials', async ({ page }, testInfo) => {
  const data = fixture();
  await localLogin(page, data.emails.owner);
  const connection = await approve(page, await startConsent(page, data));
  await page.goto('/dashboard');
  await switchAccount(page, String(data.account_a));
  const listed = await mcp(connection, 'tools/list');
  expect(listed.status()).toBe(200);
  expect((await listed.json()).result.tools.map(tool => tool.name)).toEqual(['contacts_get', 'contacts_list', 'imports_get', 'imports_list', 'lists_get', 'lists_list']);
  const allowed = await mcp(connection, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_b } });
  expect((await allowed.json()).result.structuredContent.data.list.name).toBe('Browser list B');
  await safeError(await mcp(connection, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_a } }), 'not_found');
  await safeError(await mcp(connection, 'tools/call', { name: 'lists_create', arguments: { list: { name: 'Must not exist', description: '' } } }), 'forbidden');
  for (const token of [data.api_key, 'synthetic-google-access-token']) {
    const rejected = await mcp({ ...connection, token }, 'tools/list');
    expect(rejected.status()).toBe(401);
  }
  const cookieOnly = await page.request.post('/mcp', { maxRedirects: 0, data: { jsonrpc: '2.0', id: 1, method: 'tools/list' } });
  expect(cookieOnly.status()).toBe(401);
  await switchAccount(page, String(data.account_b));
  await page.goto('/contacts');
  await expect(page.getByText('Must not exist', { exact: true })).toHaveCount(0);
  await page.goto('/settings/connections');
  await expect(page.getByRole('heading', { name: 'Conexiones MCP', exact: true })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Tu actividad MCP reciente', exact: true })).toBeVisible();
  await expect(page.getByText('lists_get · succeeded', { exact: false }).first()).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('mcp-connections-and-audit.png'), fullPage: true });
  await connection.client.dispose();
});

test('MCP E2E-06 own revocation cuts a live legacy session and membership removal blocks reauthorization', async ({ page, browser }, testInfo) => {
  const data = fixture();
  await localLogin(page, data.emails.owner);
  await page.goto('/settings/connections');
  const existingActions = new Set(await page.locator('article form').evaluateAll(forms => forms.map(form => form.action)));
  const connectionA = await approve(page, await startConsent(page, data, 'contacts.read'), 'Browser owner own');
  const connectionB = await approve(page, await startConsent(page, data, 'contacts.read'));
  const initialize = await connectionB.client.post('/mcp', { maxRedirects: 0, headers: { Authorization: 'Bearer ' + connectionB.token }, data: { jsonrpc: '2.0', id: 1, method: 'initialize', params: { protocolVersion: '2025-11-25', capabilities: {}, clientInfo: { name: 'Synthetic browser client', version: '1' } } } });
  expect(initialize.status()).toBe(200);
  const session = initialize.headers()['mcp-session-id'];
  expect(typeof session).toBe('string');
  const initialized = await connectionB.client.post('/mcp', { maxRedirects: 0, headers: { Authorization: 'Bearer ' + connectionB.token, 'MCP-Protocol-Version': '2025-11-25', 'MCP-Session-Id': session }, data: { jsonrpc: '2.0', method: 'notifications/initialized' } });
  expect(initialized.status()).toBe(202);
  const legacyRead = await mcp(connectionB, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_b } }, session);
  expect(legacyRead.status()).toBe(200);
  expect((await legacyRead.json()).result.structuredContent.data.list.name).toBe('Browser list B');
  await page.goto('/settings/connections');
  const newActions = (await page.locator('article').filter({ hasText: 'Browser delegated B' }).filter({ has: page.getByText('Estado: Activa', { exact: true }) }).locator('form').evaluateAll(forms => forms.map(form => form.action))).filter(action => !existingActions.has(action));
  expect(newActions).toHaveLength(1);
  const card = page.locator('article').filter({ has: page.locator('form[action="' + newActions[0] + '"]') });
  const revokeForm = card.locator('form');
  const action = await revokeForm.getAttribute('action');
  expect((await page.request.get(action, { maxRedirects: 0 })).status()).toBe(405);
  expect((await page.request.post(action, { maxRedirects: 0, form: {} })).status()).toBe(419);
  expect((await mcp(connectionB, 'tools/list', {}, session)).status()).toBe(200);
  await card.locator('summary').focus();
  await page.keyboard.press('Enter');
  await page.keyboard.press('Tab');
  await expect(card.getByRole('button', { name: 'Confirmar revocación', exact: true })).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.getByText('Conexión revocada. El cliente ya no puede utilizarla.', { exact: true })).toBeVisible();
  expect((await mcp(connectionB, 'tools/list', {}, session)).status()).toBe(401);
  expect((await mcp(connectionA, 'tools/list')).status()).toBe(200);
  const refresh = await connectionB.client.post('/oauth/token', { maxRedirects: 0, form: { grant_type: 'refresh_token', client_id: data.mcp_client_id, resource: 'https://emailing.example.test/mcp', refresh_token: 'not-issued' } });
  expect(refresh.status()).toBe(400);
  expect((await refresh.json()).error).toBe('unsupported_grant_type');
  await page.screenshot({ path: testInfo.outputPath('mcp-revoked.png'), fullPage: true });
  const expiring = await approve(page, await startConsent(page, data, 'contacts.read'));
  expect((await mcp(expiring, 'tools/list')).status()).toBe(200);
  const clock = spawnSync('php', ['tests/support/google-browser-fixtures.php', 'expire-mcp'], { cwd: process.cwd(), env: { ...process.env, APP_ENV: 'testing', DB_DATABASE: 'emailing_testing', DB_USERNAME: 'emailing_testing', DB_URL: '', GOOGLE_BROWSER_TEST: '1', GOOGLE_BROWSER_RUN: data.run, MCP_BROWSER_TOKEN_ID: expiring.token.split('|')[0] }, encoding: 'utf8' });
  const clockPrefix = data.directory + '/clock-' + randomUUID();
  writeFileSync(clockPrefix + '-stdout.txt', clock.stdout ?? '');
  writeFileSync(clockPrefix + '-stderr.txt', clock.stderr ?? '');
  writeFileSync(clockPrefix + '-receipt.json', JSON.stringify({ operation: 'owned clock fixture', exit_code: clock.status, stdout_sha256: createHash('sha256').update(clock.stdout ?? '').digest('hex'), stderr_sha256: createHash('sha256').update(clock.stderr ?? '').digest('hex'), live_handles: [] }));
  expect(clock.status).toBe(0);
  expect((await mcp(expiring, 'tools/list')).status()).toBe(401);
  const renewed = await approve(page, await startConsent(page, data, 'contacts.read'));
  expect((await mcp(renewed, 'tools/list')).status()).toBe(200);
  const adminContext = await browser.newContext({ baseURL: origin, serviceWorkers: 'block', proxy: { server: 'http://127.0.0.1:18968', bypass: '<-loopback>,127.0.0.1' } });
  const adminPage = await adminContext.newPage();
  await adminPage.route('**/*', route => new URL(route.request().url()).origin === origin ? route.continue() : route.abort());
  await localLogin(adminPage, data.emails.other);
  await switchAccount(adminPage, String(data.account_b));
  await adminPage.goto('/team');
  adminPage.once('dialog', dialog => dialog.accept());
  await adminPage.getByRole('row').filter({ hasText: data.emails.owner }).getByRole('button', { name: 'Retirar acceso', exact: true }).click();
  await expect(adminPage.getByRole('row').filter({ hasText: data.emails.owner })).toHaveCount(0);
  expect((await mcp(renewed, 'tools/list')).status()).toBe(401);
  expect((await mcp(connectionA, 'tools/list')).status()).toBe(200);
  const removed = await startConsent(page, data, 'contacts.read');
  await expect(page.getByRole('button', { name: 'Autorizar Browser delegated B', exact: true })).toHaveCount(0);
  await page.getByRole('button', { name: 'Denegar conexión', exact: true }).click();
  expect((await removed.completed).denied).toBe(true);
  await adminContext.close();
  await Promise.all([connectionA.client.dispose(), connectionB.client.dispose(), expiring.client.dispose(), renewed.client.dispose(), removed.client.dispose()]);
});

test('MCP E2E-07 consent authorizes data tools, keyset reads and safe template preview', async ({ page }, testInfo) => {
  const data = fixture();
  await page.route('**/*', route => [origin, callbackOrigin].includes(new URL(route.request().url()).origin) ? route.continue() : route.abort());
  await localLogin(page, data.emails.owner);
  const consent = await startConsent(page, data, 'contacts.read contacts.write templates.read templates.write');
  const form = page.getByRole('form', { name: 'Autorizar Browser owner own', exact: true });
  await expect(form.getByRole('checkbox', { name: 'Consultar plantillas', exact: true })).toBeChecked();
  await expect(form.getByRole('checkbox', { name: 'Editar plantillas', exact: true })).toBeChecked();
  await page.screenshot({ path: testInfo.outputPath('mcp-data-consent.png'), fullPage: true });
  const connection = await approve(page, consent, 'Browser owner own');
  const contacts = [];
  let templateId;
  const call = async (name, args = {}) => {
    const response = await mcp(connection, 'tools/call', { name, arguments: args });
    expect(response.status(), name).toBe(200);
    const result = (await response.json()).result;
    expect(result.isError, name).toBe(false);
    return result.structuredContent;
  };
  try {
    const listed = await mcp(connection, 'tools/list');
    expect(listed.status()).toBe(200);
    expect((await listed.json()).result.tools).toHaveLength(20);
    for (const suffix of ['a', 'b']) {
      const created = await call('contacts_create', { contact: { email: `mcp-data-${data.run}-${suffix}@example.test`, name: 'Browser data fixture', consent_status: 'unknown' } });
      contacts.push(created.data.contact.id);
    }
    const first = await call('contacts_list', { search: `mcp-data-${data.run}`, limit: 1 });
    expect(first.data.items.map(item => item.id)).toEqual([contacts[1]]);
    expect(first.page.has_more).toBe(true);
    const second = await call('contacts_list', { search: `mcp-data-${data.run}`, cursor: first.page.next_cursor, limit: 100 });
    expect(second.data.items.map(item => item.id)).toEqual([contacts[0]]);
    expect(second.page.has_more).toBe(false);
    const created = await call('templates_create', { template: { name: 'Browser MCP data template', subject: 'Hello {{contact.name}}', html: '<p>Hello {{contact.name}}</p><img src="https://example.invalid/pixel"><script>alert(1)</script>' } });
    templateId = created.data.template.id;
    const preview = await call('templates_preview', { source: { type: 'stored', template_id: templateId }, sample: { name: '<Browser fixture>', email: 'preview@example.test' } });
    expect(preview.data.preview.subject).toBe('Hello <Browser fixture>');
    expect(preview.data.preview.html).toContain('&lt;Browser fixture&gt;');
    expect(preview.data.preview.document).not.toContain('<script');
    expect(preview.data.preview.document).not.toContain('example.invalid/pixel');
    await safeError(await mcp(connection, 'tools/call', { name: 'templates_get', arguments: { template_id: templateId, account_id: data.account_b } }), 'validation_failed');
    await page.goto('/settings/connections');
    await expect(page.getByText('templates_preview · succeeded', { exact: false }).first()).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('mcp-data-audit.png'), fullPage: true });
  } finally {
    // These are created by this test; the SQL owner also cascades only its fixture accounts on failure.
    try {
      if (templateId) await call('templates_delete', { template_id: templateId });
      for (const id of contacts) await call('contacts_delete', { contact_id: id });
    } finally {
      await connection.client.dispose();
    }
  }
});

test('MCP E2E-08 business consent and reviewed campaigns share the web domain', async ({ page }, testInfo) => {
  const data = fixture();
  const scenarioRun = `${data.run}-${testInfo.repeatEachIndex}`;
  await page.route('**/*', route => [origin, callbackOrigin].includes(new URL(route.request().url()).origin) ? route.continue() : route.abort());
  await localLogin(page, data.emails.owner);
  await switchAccount(page, String(data.account_a));
  await page.goto('/settings/mail');
  await page.getByRole('button', { name: 'Nuevo remitente', exact: true }).click();
  const senderForm = page.locator('form').filter({ has: page.getByRole('button', { name: 'Guardar remitente', exact: true }) });
  const senderEmail = `business-sender-${scenarioRun}@example.test`;
  await senderForm.getByLabel('Email', { exact: true }).fill(senderEmail);
  await senderForm.getByLabel('Nombre', { exact: true }).fill('Synthetic business sender');
  await senderForm.getByRole('checkbox', { name: 'Autorizo este remitente para log y Mailpit', exact: true }).check();
  await senderForm.getByRole('button', { name: 'Guardar remitente', exact: true }).click();
  const senderRow = page.locator('li').filter({ hasText: senderEmail });
  await expect(senderRow).toContainText('Autorizado para laboratorio');
  const senderKey = await senderRow.getAttribute('wire:key');
  expect(senderKey).toMatch(/^sender-[1-9][0-9]*$/);
  const senderId = Number(senderKey.slice('sender-'.length));
  const consent = await startConsent(page, data, 'contacts.read contacts.write templates.read templates.write campaigns.read campaigns.write mail.send suppressions.read suppressions.write activity.read');
  await expect(page.getByRole('form', { name: 'Autorizar Browser owner own', exact: true }).getByRole('checkbox', { checked: true })).toHaveCount(10);
  await page.screenshot({ path: testInfo.outputPath('mcp-business-consent.png'), fullPage: true });
  const connection = await approve(page, consent, 'Browser owner own');
  let reader;
  const call = async (name, args = {}) => {
    const response = await mcp(connection, 'tools/call', { name, arguments: args });
    expect(response.status(), name).toBe(200);
    const result = (await response.json()).result;
    expect(result.isError, name).toBe(false);
    expect(JSON.parse(result.content[0].text)).toEqual(result.structuredContent);
    return result.structuredContent;
  };
  try {
    expect((await (await mcp(connection, 'tools/list', { per_page: 100 })).json()).result.tools).toHaveLength(34);
    const contact = (await call('contacts_create', { contact: { email: `business-recipient-${scenarioRun}@example.test`, name: '<Browser business fixture>', consent_status: 'granted', consent_source: 'Synthetic browser form', consented_at: new Date(Date.now() - 60000).toISOString().slice(0, 16) } })).data.contact;
    const template = (await call('templates_create', { template: { name: `Business template ${scenarioRun}`, subject: 'Hello {{ contact.name }}', html: '<p>Hello {{ contact.name }}</p>', text: 'Hello {{ contact.email }}' } })).data.template;
    const campaignName = `Browser MCP business ${scenarioRun}`;
    const fields = { name: campaignName, template_id: template.id, sender_id: senderId, list_ids: [], contact_ids: [contact.id] };
    const created = (await call('campaigns_create', { campaign: fields })).data.campaign;
    const updated = (await call('campaigns_update', { campaign_id: created.id, expected_version: created.version, campaign: fields })).data.campaign;
    const review = (await call('campaigns_review', { campaign_id: created.id, expected_version: updated.version })).data;
    expect(review.campaign.status).toBe('reviewed');
    expect(review.revision.audience.recipient_count).toBe(1);
    expect(review.revision.preview.html).toContain('&lt;Browser business fixture&gt;');
    expect(review.revision).not.toHaveProperty('sender');
    const detail = (await call('campaigns_get', { campaign_id: created.id, include: { composition: true, content: true, sender: true, preview: true, recipients: {} } })).data;
    expect(detail.revision.sender.id).toBe(senderId);
    expect(detail.revision.audience.items.map(item => item.contact_id)).toEqual([contact.id]);
    const authorized = (await call('campaigns_authorize', { campaign_id: created.id, expected_version: review.campaign.version, revision_id: review.revision.id, confirmed: true })).data.campaign;
    const paused = (await call('campaigns_pause', { campaign_id: created.id, expected_version: authorized.version })).data.campaign;
    await page.goto('/campaigns');
    await expect(page.locator('li').filter({ hasText: campaignName })).toContainText('Pausada');
    await page.screenshot({ path: testInfo.outputPath('mcp-business-campaigns.png'), fullPage: true });
    const resumed = (await call('campaigns_resume', { campaign_id: created.id, expected_version: paused.version })).data.campaign;
    expect(resumed.status).toBe('authorized');
    expect(resumed.reviewed_revision_id).toBe(review.revision.id);
    expect((await call('campaigns_list')).data.items.some(item => item.id === created.id)).toBe(true);
    await safeError(await mcp(connection, 'tools/call', { name: 'campaigns_pause', arguments: { campaign_id: created.id, expected_version: paused.version } }), 'version_conflict');
    const suppression = (await call('suppressions_add', { email: contact.email })).data.suppression;
    expect((await call('suppressions_list')).data.items.some(item => item.id === suppression.id)).toBe(true);
    expect((await call('suppressions_remove', { suppression_id: suppression.id })).data.deleted).toBe(true);
    expect((await call('activity_metrics')).data.delivery_feedback).toBe('not_available');
    expect((await call('activity_list')).data.items).toEqual([]);
    await safeError(await mcp(connection, 'tools/call', { name: 'activity_attempts', arguments: { record_key: 'message:9007199254740991' } }), 'not_found');
    reader = await approve(page, await startConsent(page, data, 'campaigns.read'), 'Browser owner own');
    const listed = await mcp(reader, 'tools/list', { per_page: 100 });
    expect((await listed.json()).result.tools.map(tool => tool.name).sort()).toEqual(['campaigns_get', 'campaigns_list']);
    const summary = await mcp(reader, 'tools/call', { name: 'campaigns_get', arguments: { campaign_id: created.id } });
    expect((await summary.json()).result.structuredContent.data.revision).toEqual({ id: review.revision.id, version: review.revision.version, created_at: review.revision.created_at });
    await safeError(await mcp(reader, 'tools/call', { name: 'campaigns_get', arguments: { campaign_id: created.id, include: { content: true } } }), 'forbidden');
    await safeError(await mcp(reader, 'tools/call', { name: 'campaigns_pause', arguments: { campaign_id: created.id, expected_version: resumed.version } }), 'forbidden');
    await page.goto('/settings/connections');
    await expect(page.getByText('campaigns_resume · succeeded', { exact: false }).first()).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('mcp-business-audit.png'), fullPage: true });
  } finally {
    // Existing fixture-account teardown owns the campaign and its immutable dependencies.
    await connection.client.dispose();
    await reader?.client.dispose();
  }
});
