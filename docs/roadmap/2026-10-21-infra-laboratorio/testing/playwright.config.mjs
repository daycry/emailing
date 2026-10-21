import { defineConfig } from 'file:///C:/Users/46066917X/.claude/tool-cache/qa/node_modules/@playwright/test/index.mjs';
import { fileURLToPath } from 'node:url';

const out = fileURLToPath(new URL('.', import.meta.url));
export default defineConfig({
  testDir: out,
  testMatch: 'E2E-*.spec.mjs',
  outputDir: `${out}/raw/artifacts`,
  timeout: 45000,
  expect: { timeout: 10000 },
  retries: 2,
  workers: 1,
  forbidOnly: true,
  fullyParallel: false,
  reporter: [['list'], ['json', { outputFile: `${out}/raw/results.json` }]],
  use: {
    headless: true,
    viewport: { width: 1440, height: 1000 },
    screenshot: 'off',
    trace: 'off',
    video: 'off'
  },
  projects: [{ name: 'chromium', use: { browserName: 'chromium' } }]
});
