# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: mcp-google.spec.mjs >> Google E2E-01 stable local user, account isolation and server return boundary
- Location: tests\e2e\mcp-google.spec.mjs:168:1

# Error details

```
Error: page.goto: net::ERR_TIMED_OUT at http://blocked.example.test/proxy-check
Call log:
  - navigating to "http://blocked.example.test/proxy-check", waiting until "load"

```

# Test source

```ts
  59  |   const client = await isolatedClient();
  60  |   let resolveCallback;
  61  |   const completed = new Promise(resolve => { resolveCallback = resolve; });
  62  |   callbackHandlers.set(state, async callback => {
  63  |     // Validate the literal issuer and state before consuming a code; never render either.
  64  |     if (callback.searchParams.get('iss') !== 'https://emailing.example.test' || callback.searchParams.get('state') !== state) {
  65  |       resolveCallback({ invalid: true });
  66  |       return { status: 400, body: 'Invalid authorization response' };
  67  |     }
  68  |     if (callback.searchParams.has('error')) {
  69  |       resolveCallback({ denied: callback.searchParams.get('error') === 'access_denied' });
  70  |       return { body: '<h1>Conexión denegada</h1>' };
  71  |     }
  72  |     const exchanged = await client.post('/oauth/token', { maxRedirects: 0, form: { grant_type: 'authorization_code', client_id: data.mcp_client_id, redirect_uri: 'http://127.0.0.1:18743/callback', resource: 'https://emailing.example.test/mcp', code: callback.searchParams.get('code'), code_verifier: verifier } });
  73  |     const status = exchanged.status();
  74  |     const payload = status === 200 ? await exchanged.json() : {};
  75  |     resolveCallback({ status, token: payload.access_token, expires: payload.expires_in, client });
  76  |     return { body: '<h1>Conexión autorizada</h1><p>El cliente completó el canje.</p>' };
  77  |   });
  78  |   const query = new URLSearchParams({ client_id: data.mcp_client_id, redirect_uri: 'http://127.0.0.1:18743/callback', resource: 'https://emailing.example.test/mcp', response_type: 'code', scope, state, code_challenge_method: 'S256', code_challenge: createHash('sha256').update(verifier).digest('base64url') });
  79  |   await page.goto('/oauth/authorize?' + query);
  80  |   return { completed, client };
  81  | }
  82  | 
  83  | async function approve(page, consent, account = 'Browser delegated B') {
  84  |   await page.getByRole('button', { name: 'Autorizar ' + account, exact: true }).click();
  85  |   await expect(page.getByRole('heading', { name: 'Conexión autorizada', exact: true })).toBeVisible();
  86  |   const connection = await consent.completed;
  87  |   expect(connection.status).toBe(200);
  88  |   expect(typeof connection.token).toBe('string');
  89  |   expect((await page.content()).includes(connection.token)).toBe(false);
  90  |   return connection;
  91  | }
  92  | 
  93  | async function mcp(connection, method, params = {}, legacy = null) {
  94  |   const headers = { Accept: 'application/json, text/event-stream', Authorization: 'Bearer ' + connection.token, 'MCP-Protocol-Version': legacy ? '2025-11-25' : '2026-07-28' };
  95  |   if (legacy) headers['MCP-Session-Id'] = legacy;
  96  |   else {
  97  |     params._meta = { 'io.modelcontextprotocol/protocolVersion': '2026-07-28', 'io.modelcontextprotocol/clientCapabilities': {} };
  98  |     headers['Mcp-Method'] = method;
  99  |     if (method === 'tools/call') headers['Mcp-Name'] = params.name;
  100 |   }
  101 |   return connection.client.post('/mcp', { headers, maxRedirects: 0, data: { jsonrpc: '2.0', id: 1, method, params } });
  102 | }
  103 | 
  104 | async function safeError(response, code) {
  105 |   expect(response.status()).toBe(200);
  106 |   const body = await response.json();
  107 |   expect(body.result.isError).toBe(true);
  108 |   expect(Object.hasOwn(body.result, 'structuredContent')).toBe(false);
  109 |   const dto = JSON.parse(body.result.content[0].text);
  110 |   expect(dto.error.code).toBe(code);
  111 |   expect(dto.error.outcome).toBe('not_applied');
  112 | }
  113 | 
  114 | async function provider(page, person, overrides = {}, mutateState = false) {
  115 |   const receipt = {};
  116 |   await page.route('**/*', async route => {
  117 |     const url = new URL(route.request().url());
  118 |     if (url.origin === callbackOrigin && url.pathname === '/callback') return route.continue();
  119 |     if (url.origin !== origin) return route.abort();
  120 |     const isStart = url.pathname.startsWith('/auth/google/redirect') || (url.pathname === '/settings/identities/google' && route.request().method() === 'POST');
  121 |     if (!isStart) return route.continue();
  122 |     const response = await route.fetch({ maxRedirects: 0 });
  123 |     const location = response.headers().location;
  124 |     if (!location || new URL(location, origin).hostname !== 'accounts.google.com') return route.fulfill({ response });
  125 |     const requested = new URL(location);
  126 |     const claims = { nonce: requested.searchParams.get('nonce'), sub: person.sub, email: person.email, synthetic_code_id: randomUUID(), ...overrides };
  127 |     const callback = new URL('/auth/google/callback', origin);
  128 |     callback.searchParams.set('state', mutateState ? 'foreign-state' : requested.searchParams.get('state'));
  129 |     callback.searchParams.set('code', Buffer.from(JSON.stringify(claims)).toString('base64url'));
  130 |     receipt.callback = callback.toString();
  131 |     const headers = { ...response.headers(), location: callback.toString() };
  132 |     delete headers['content-length'];
  133 |     await route.fulfill({ response, status: 302, headers, body: '' });
  134 |   });
  135 |   return receipt;
  136 | }
  137 | 
  138 | async function switchAccount(page, option) {
  139 |   // The native onchange submits a form. Await its navigation before a later goto
  140 |   // can cancel it; selecting an option alone does not prove server-side state.
  141 |   await Promise.all([
  142 |     page.waitForNavigation({ waitUntil: 'load' }),
  143 |     page.getByLabel('Cuenta activa').selectOption(option),
  144 |   ]);
  145 |   const expected = typeof option === 'string' ? option : null;
  146 |   if (expected) await expect(page.getByLabel('Cuenta activa')).toHaveValue(expected);
  147 | }
  148 | 
  149 | async function localLogin(page, email) {
  150 |   await page.goto('/login');
  151 |   await page.getByLabel('Email', { exact: true }).fill(email);
  152 |   await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
  153 |   await page.getByRole('button', { name: 'Acceder', exact: true }).click();
  154 |   await expect(page).toHaveURL(/\/dashboard$/);
  155 | }
  156 | 
  157 | test.beforeEach(async ({ page, context }) => {
  158 |   const probe = await context.newPage();
> 159 |   const blocked = await probe.goto('http://blocked.example.test/proxy-check');
      |                               ^ Error: page.goto: net::ERR_TIMED_OUT at http://blocked.example.test/proxy-check
  160 |   expect(blocked.status()).toBe(502);
  161 |   await expect(probe.goto('https://blocked.example.test/proxy-check')).rejects.toThrow(/TUNNEL|PROXY|ERR_/);
  162 |   await probe.close();
  163 |   await page.goto('/login');
  164 |   await expect(page.getByRole('heading', { name: 'Accede a tu espacio' })).toBeVisible();
  165 |   console.log('Google runner network boundary: HTTP502, HTTPS CONNECT rejected, local navigation OK.');
  166 | });
  167 | 
  168 | test('Google E2E-01 stable local user, account isolation and server return boundary', async ({ page }, testInfo) => {
  169 |   const data = fixture();
  170 |   await provider(page, { sub: 'browser-owner-' + data.run, email: 'changed@example.test' });
  171 |   await page.goto('/login');
  172 |   await page.screenshot({ path: testInfo.outputPath('google-login.png'), fullPage: true });
  173 |   await page.getByRole('link', { name: 'Continuar con Google' }).click();
  174 |   await expect(page).toHaveURL(/\/dashboard$/);
  175 |   await expect(page.getByText('Browser Owner', { exact: true })).toBeVisible();
  176 |   const selector = page.getByLabel('Cuenta activa');
  177 |   await expect(selector).toContainText('Browser second own');
  178 |   await expect(selector).not.toContainText('Browser foreign account');
  179 |   await switchAccount(page, { label: 'Browser second own' });
  180 |   await expect(page.getByText('Solo lectura', { exact: true })).toBeVisible();
  181 |   await page.screenshot({ path: testInfo.outputPath('google-dashboard.png'), fullPage: true });
  182 |   await page.getByRole('button', { name: 'Salir', exact: true }).click();
  183 |   const consent = await startConsent(page, data);
  184 |   await page.getByRole('link', { name: 'Continuar con Google' }).click();
  185 |   await expect(page).toHaveURL(/\/oauth\/authorize\?pending=[a-f0-9-]{36}$/);
  186 |   await expect(page.getByRole('heading', { name: 'Autorizar conexión', exact: true })).toBeVisible();
  187 |   await page.screenshot({ path: testInfo.outputPath('google-real-consent.png'), fullPage: true });
  188 |   const connection = await approve(page, consent);
  189 |   const read = await mcp(connection, 'tools/call', { name: 'lists_get', arguments: { list_id: data.list_b } });
  190 |   expect(read.status()).toBe(200);
  191 |   expect((await read.json()).result.structuredContent.data.list.name).toBe('Browser list B');
  192 |   await connection.client.dispose();
  193 | });
  194 | 
  195 | test('Google E2E-02 invalid callback, no email auto-link and explicit profile lifecycle', async ({ page }, testInfo) => {
  196 |   const data = fixture();
  197 |   const collision = { sub: 'browser-collision-' + data.run, email: data.emails.collision };
  198 |   await provider(page, collision, {}, true);
  199 |   await page.goto('/login');
  200 |   await page.getByRole('link', { name: 'Continuar con Google' }).click();
  201 |   await expect(page).toHaveURL(/\/login$/);
  202 |   await expect(page.getByText('No se pudo completar el acceso con Google.', { exact: false })).toBeVisible();
  203 |   await page.unroute('**/*');
  204 |   const unlinked = await provider(page, collision);
  205 |   await page.getByRole('link', { name: 'Continuar con Google' }).click();
  206 |   await expect(page).toHaveURL(/\/login$/);
  207 |   await page.goto(unlinked.callback);
  208 |   await expect(page).toHaveURL(/\/login$/);
  209 |   for (const invalid of [{ iss: 'https://attacker.example.test' }, { sub: '' }, { scenario: 'bad-code' }]) {
  210 |     await page.unroute('**/*');
  211 |     await provider(page, collision, invalid);
  212 |     await page.getByRole('link', { name: 'Continuar con Google' }).click();
  213 |     await expect(page).toHaveURL(/\/login$/);
  214 |     await expect(page.getByRole('alert')).toContainText('No se pudo completar el acceso con Google.');
  215 |   }
  216 |   await page.unroute('**/*');
  217 |   await provider(page, collision);
  218 |   await localLogin(page, data.emails.collision);
  219 |   await page.getByRole('link', { name: 'Identidad y acceso', exact: true }).click();
  220 |   await page.getByRole('button', { name: 'Vincular Google', exact: true }).click();
  221 |   await expect(page.getByRole('heading', { name: 'Google vinculado', exact: true })).toBeVisible();
  222 |   await page.screenshot({ path: testInfo.outputPath('google-profile.png'), fullPage: true });
  223 |   await page.getByRole('button', { name: 'Salir', exact: true }).click();
  224 |   await page.unroute('**/*');
  225 |   await provider(page, { ...collision, email: 'provider-changed@example.test' });
  226 |   await page.goto('/login');
  227 |   await page.getByRole('link', { name: 'Continuar con Google' }).click();
  228 |   await expect(page.getByText('Browser Collision', { exact: true })).toBeVisible();
  229 |   await page.getByRole('link', { name: 'Identidad y acceso', exact: true }).click();
  230 |   await page.getByRole('button', { name: 'Desvincular Google', exact: true }).click();
  231 |   await expect(page.getByText('Todavía no has vinculado Google.')).toBeVisible();
  232 | });
  233 | 
  234 | test('Google E2E-03 explicit signup, Workspace, admission, invitation and local fallback', async ({ page, context }, testInfo) => {
  235 |   const data = fixture();
  236 |   const signup = { sub: 'browser-signup-' + data.run, email: data.emails.signup };
  237 |   await context.addCookies([{ name: 'google_fixture_mode', value: 'workspace', url: origin }]);
  238 |   await provider(page, signup, { hd: null });
  239 |   await page.goto('/register');
  240 |   await page.getByRole('link', { name: 'Crear cuenta con Google' }).click();
  241 |   await expect(page).toHaveURL(/\/login$/);
  242 |   await page.unroute('**/*');
  243 |   await provider(page, signup, { hd: 'workspace.example.test' });
  244 |   await page.goto('/register');
  245 |   await page.getByRole('link', { name: 'Crear cuenta con Google' }).click();
  246 |   await expect(page.getByRole('heading', { name: 'Confirma tu nueva cuenta' })).toBeVisible();
  247 |   await page.screenshot({ path: testInfo.outputPath('google-confirmation.png'), fullPage: true });
  248 |   await page.getByLabel('Nombre de la cuenta', { exact: true }).fill('Browser signup own');
  249 |   await page.getByLabel('Quiero crear mi cuenta y vincular Google.').check();
  250 |   await page.getByLabel('Contraseña local (opcional)', { exact: true }).fill('Synthetic-password-1234');
  251 |   await page.getByLabel('Repite la contraseña', { exact: true }).fill('Different-password-1234');
  252 |   await page.getByRole('button', { name: 'Crear mi cuenta', exact: true }).click();
  253 |   await expect(page).toHaveURL(/\/auth\/google\/confirm$/);
  254 |   await expect(page.getByRole('alert')).toBeVisible();
  255 |   await expect(page.getByLabel('Nombre de la cuenta', { exact: true })).toHaveValue('Browser signup own');
  256 |   await expect(page.getByLabel('Quiero crear mi cuenta y vincular Google.')).toBeChecked();
  257 |   await expect(page.getByLabel('Contraseña local (opcional)', { exact: true })).toHaveValue('');
  258 |   await expect(page.getByLabel('Repite la contraseña', { exact: true })).toHaveValue('');
  259 |   await page.screenshot({ path: testInfo.outputPath('google-correctable-registration.png'), fullPage: true });
```