import { defineConfig } from '@playwright/test';
import path from 'node:path';

const out = process.env.PROVIDER_BROWSER_OUTPUT;
if (!out || process.env.PROVIDER_BROWSER_TEST !== '1') throw new Error('Owned provider run required.');
export default defineConfig({
  testDir: '../e2e', testMatch: 'mail-providers.spec.mjs', workers: 1, retries: 0, timeout: 180000,
  outputDir: path.join(process.env.PROVIDER_BROWSER_DIRECTORY, 'playwright-artifacts'),
  reporter: [['list'], ['json', { outputFile: path.join(out, 'raw/results.json') }]],
  use: { baseURL: 'https://127.0.0.1:18867', headless: true, serviceWorkers: 'block', trace: 'off', screenshot: 'only-on-failure',
    channel: 'chrome', ignoreHTTPSErrors: true, viewport: { width: 1280, height: 900 }, launchOptions: { chromiumSandbox: true },
    proxy: { server: 'http://127.0.0.1:18868', bypass: '<-loopback>,127.0.0.1' } },
});
