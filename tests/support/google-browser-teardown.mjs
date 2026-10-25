import { spawnSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';
import path from 'node:path';

export default async function teardown() {
  const result = spawnSync('php', ['tests/support/google-browser-fixtures.php', 'cleanup'], { cwd: process.cwd(), env: { ...process.env,
    APP_ENV: 'testing', DB_DATABASE: 'emailing_testing', DB_USERNAME: 'emailing_testing', DB_URL: '', GOOGLE_BROWSER_TEST: '1' }, encoding: 'utf8' });
  if (process.env.MCP_EVIDENCE_DIRECTORY) {
    const prefix = path.join(process.env.MCP_EVIDENCE_DIRECTORY, 'browser-teardown-' + randomUUID());
    writeFileSync(prefix + '-stdout.txt', result.stdout ?? '');
    writeFileSync(prefix + '-stderr.txt', result.stderr ?? '');
    writeFileSync(prefix + '-receipt.json', JSON.stringify({ operation: 'owned browser SQL teardown', exit_code: result.status, live_handles: [] }));
  }
  if (result.status !== 0) throw new Error('Owned Google browser SQL fixture cleanup failed.');
  process.stdout.write(result.stdout);
}
