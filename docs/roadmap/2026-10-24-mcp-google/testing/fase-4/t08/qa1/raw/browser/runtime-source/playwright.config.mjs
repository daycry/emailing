import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests/e2e',
  workers: 1,
  timeout: 60000,
  outputDir: 'storage/mcp-google-phase3/browser-results',
  reporter: 'list',
  use: { baseURL: 'http://127.0.0.1:18967', trace: 'off', screenshot: 'only-on-failure', headless: true, serviceWorkers: 'block',
    launchOptions: { chromiumSandbox: true },
    proxy: { server: 'http://127.0.0.1:18968', bypass: '<-loopback>,127.0.0.1' } },
  globalTeardown: './tests/support/google-browser-teardown.mjs',
  webServer: { command: 'node tests/support/google-browser-launch.mjs', url: 'http://127.0.0.1:18967/login', timeout: 120000, reuseExistingServer: false },
});
