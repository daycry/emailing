import { homedir } from 'node:os';
import { resolve } from 'node:path';
import { pathToFileURL } from 'node:url';
const qaCache = process.env.QA_CACHE_DIR || resolve(homedir(), '.claude/tool-cache/qa');
const { defineConfig } = await import(pathToFileURL(resolve(qaCache, 'node_modules/@playwright/test/index.mjs')).href);
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
