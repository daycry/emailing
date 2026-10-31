import { chromium } from '@playwright/test';
import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';

const directory = process.env.PROVIDER_BROWSER_DIRECTORY;
const fixture = JSON.parse(readFileSync(path.join(directory, 'fixtures.private.json'), 'utf8')).cases['1'];
const browser = await chromium.launch({ channel: 'chrome', chromiumSandbox: true, proxy: { server: 'http://127.0.0.1:18868', bypass: '<-loopback>,127.0.0.1' } });
try {
  const page = await browser.newPage({ ignoreHTTPSErrors: true });
  await page.goto('https://127.0.0.1:18867/login');
  await page.getByRole('textbox', { name: 'Email' }).fill(fixture.email);
  await page.getByLabel('Contraseña', { exact: true }).fill(fixture.password);
  await page.getByRole('button', { name: /Entrar|Acceder|Iniciar/ }).click();
  await page.waitForURL('**/dashboard');
  await page.goto('https://127.0.0.1:18867/settings/mail');
  await page.getByRole('heading', { name: 'Transporte y remitentes', exact: true }).waitFor();
  const controls = await page.locator('input, select, button, textarea').evaluateAll(items => items.map(el => ({ tag: el.tagName, type: el.type,
    name: el.labels?.[0]?.textContent?.trim() ?? el.textContent?.trim(), options: el.tagName === 'SELECT' ? Array.from(el.options, o => ({ text: o.text, value: o.value })) : undefined })));
  writeFileSync(path.join(directory, 'controls.public.json'), JSON.stringify({ browser: browser.version(), controls }, null, 2));
} finally { await browser.close(); }
