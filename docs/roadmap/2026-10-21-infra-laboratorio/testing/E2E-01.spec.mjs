import { homedir } from 'node:os';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
const qaCache = process.env.QA_CACHE_DIR || resolve(homedir(), '.claude/tool-cache/qa');
const { test, expect } = await import(pathToFileURL(resolve(qaCache, 'node_modules/@playwright/test/index.mjs')).href);
import { readFileSync, mkdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const out = fileURLToPath(new URL('.', import.meta.url));
const env = Object.fromEntries(readFileSync(new URL('../../../../infra/.env', import.meta.url), 'utf8').replace(/^\uFEFF/, '')
  .split(/\r?\n/).filter(line => line && !line.startsWith('#')).map(line => {
    const split = line.indexOf('=');
    return [line.slice(0, split), line.slice(split + 1)];
  }));
const state = JSON.parse(readFileSync(new URL('../../../../infra/.runtime/smoke-state.json', import.meta.url), 'utf8').replace(/^\uFEFF/, ''));
mkdirSync(`${out}/screenshots`, { recursive: true });
test.afterEach(async ({}, testInfo) => {
  for (const error of testInfo.errors) {
    for (const key of ['message', 'stack', 'snippet']) {
      if (typeof error[key] === 'string') {
        for (const value of Object.values(env).filter(value => value.length >= 12)) {
          error[key] = error[key].split(value).join('[REDACTED]');
        }
      }
    }
  }
});
const target = port => {
  const url = new URL(`http://127.0.0.1:${port}`);
  if (url.hostname !== '127.0.0.1') throw new Error('Only local lab targets are allowed');
  return url.href;
};

test('E2E-01 RabbitMQ: authenticated broker overview', async ({ page }) => {
  await page.goto(target(15672));
  await page.locator('input[name="username"]').fill(env.RABBITMQ_ADMIN_USER);
  await page.locator('input[name="password"]').fill(env.RABBITMQ_ADMIN_PASSWORD);
  await page.locator('input[type="submit"]').click();
  await expect(page.getByRole('link', { name: 'Overview', exact: true })).toBeVisible();
  await expect(page.locator('body')).toContainText('rabbit@lab-rabbitmq');
  await page.screenshot({ path: `${out}/screenshots/E2E-01-rabbitmq.png`, fullPage: true });
});

test('E2E-01 Cronicle: authenticated completed smoke job', async ({ page }) => {
  await page.goto(target(3012));
  await page.locator('#fe_login_username').fill('admin');
  await page.locator('#fe_login_password').fill(env.CRONICLE_ADMIN_PASSWORD);
  await page.locator('#fe_login_password').press('Enter');
  await expect(page.getByText('Logout', { exact: true })).toBeVisible();
  await page.goto(`${target(3012)}#JobDetails?id=${state.jobId}`);
  await expect(page.locator('body')).toContainText('Laboratory infrastructure smoke');
  await expect(page.locator('body')).toContainText(/success/i);
  await page.screenshot({ path: `${out}/screenshots/E2E-01-cronicle.png`, fullPage: true });
});

test('E2E-01 Mailpit: SMTP recipient subject and body', async ({ page }) => {
  await page.goto(target(18025));
  await page.getByText(`Laboratory smoke ${state.marker}`, { exact: true }).first().click();
  await expect(page.locator('body')).toContainText('inbox@lab.invalid');
  await expect(page.locator('body')).toContainText(`Laboratory smoke ${state.marker}`);
  await expect(page.locator('body')).toContainText(`Infrastructure test ${state.marker}`);
  await page.screenshot({ path: `${out}/screenshots/E2E-01-mailpit.png`, fullPage: true });
});
