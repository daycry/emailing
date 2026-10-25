import { spawnSync } from 'node:child_process';

export default async function teardown() {
  const result = spawnSync('php', ['tests/support/google-browser-fixtures.php', 'cleanup'], { cwd: process.cwd(), env: { ...process.env,
    APP_ENV: 'testing', DB_DATABASE: 'emailing_testing', DB_USERNAME: 'emailing_testing', DB_URL: '', GOOGLE_BROWSER_TEST: '1' }, encoding: 'utf8' });
  if (result.status !== 0) throw new Error('Owned Google browser SQL fixture cleanup failed.');
  process.stdout.write(result.stdout);
}
