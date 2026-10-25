import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';

const origin = 'http://127.0.0.1:18967';
const fixture = () => JSON.parse(readFileSync('storage/mcp-google-phase2/browser-fixtures.json', 'utf8'));

async function provider(page, person, overrides = {}, mutateState = false) {
  const receipt = {};
  await page.route('**/*', async route => {
    const url = new URL(route.request().url());
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
  await selector.selectOption({ label: 'Browser second own' });
  await expect(page.getByText('Solo lectura', { exact: true })).toBeVisible();
  await page.screenshot({ path: testInfo.outputPath('google-dashboard.png'), fullPage: true });
  await page.getByRole('button', { name: 'Salir', exact: true }).click();
  await page.goto('/__google_fixture/mcp-return');
  await page.getByRole('link', { name: 'Continuar con Google' }).click();
  await expect(page).toHaveURL(/\/oauth\/authorize\?pending=[a-f0-9-]{36}$/);
  const runtime = JSON.parse(readFileSync('storage/mcp-google-phase2/browser-runtime.json', 'utf8'));
  expect(new URL(page.url()).searchParams.get('pending')).toBe(runtime.pending);
  // The real consent screen is deliberately still owned by T-05/T-07/T-12.
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
