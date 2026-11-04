import { defineConfig } from '@playwright/test';

// The owned Docker controller prepares/settles SQL and the deny proxy. This
// config cannot start the native PHP launcher or invoke its global teardown.
export default defineConfig({
  testDir: '../e2e', testMatch: 'mcp-google.spec.mjs', workers: 1, retries: 0,
  timeout: 60000, outputDir: '../../storage/mcp-google-docker/browser-results', reporter: 'list',
  use: { baseURL: 'http://127.0.0.1:18967', trace: 'off', screenshot: 'only-on-failure', headless: true,
    serviceWorkers: 'block', launchOptions: { chromiumSandbox: true },
    proxy: { server: 'http://127.0.0.1:18968', bypass: '<-loopback>,127.0.0.1' } },
});
