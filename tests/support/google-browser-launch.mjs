import { generateKeyPairSync, randomUUID } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import http from 'node:http';

const root = process.cwd();
const run = randomUUID();
const directory = path.join(root, 'storage/mcp-google-phase2/browser-' + run);
mkdirSync(directory, { recursive: true });
// Bind the deny boundary before creating SQL fixtures or starting PHP.
const denyProxy = http.createServer((request, response) => { response.writeHead(502); response.end('External network disabled for Google fixtures.'); });
denyProxy.on('connect', (request, socket) => socket.end('HTTP/1.1 502 Bad Gateway\r\n\r\n'));
try {
  await new Promise((resolve, reject) => { denyProxy.once('error', reject); denyProxy.listen(18968, '127.0.0.1', resolve); });
} catch {
  writeFileSync(path.join(directory, 'settlement.json'), JSON.stringify({ run, launcher_pid: process.pid, reason: 'proxy-bind-refused-before-fixtures', exit_code: 1, sql_started: false, php_started: false }));
  process.stderr.write('Owned deny-proxy port unavailable; refusing before fixtures or PHP.\n');
  process.exit(1);
}
const key = generateKeyPairSync('rsa', { modulusLength: 2048, privateKeyEncoding: { type: 'pkcs8', format: 'pem' }, publicKeyEncoding: { type: 'spki', format: 'pem' } });
// This synthetic signing key exists only in process memory/environment, never in an artifact.
const env = { ...process.env, APP_ENV: 'testing', DB_DATABASE: 'emailing_testing', DB_USERNAME: 'emailing_testing', DB_URL: '',
  APP_URL: 'http://127.0.0.1:18967', GOOGLE_BROWSER_TEST: '1', GOOGLE_BROWSER_RUN: run, GOOGLE_BROWSER_DIRECTORY: directory,
  GOOGLE_BROWSER_KEY: Buffer.from(key.privateKey).toString('base64'), GOOGLE_BROWSER_PENDING: randomUUID(),
  PHP_INI_SCAN_DIR: path.join(root, 'storage/php-conf'), MAIL_MAILER: 'log' };
const setup = spawnSync('php', ['tests/support/google-browser-fixtures.php', 'setup'], { cwd: root, env, encoding: 'utf8' });
if (setup.status !== 0) {
  denyProxy.close();
  process.stderr.write(setup.stderr || 'Guarded Google fixture setup failed.');
  process.exit(setup.status || 1);
}
writeFileSync(path.join(root, 'storage/mcp-google-phase2/browser-runtime.json'), JSON.stringify({ run, directory, pending: env.GOOGLE_BROWSER_PENDING }));
const server = spawn('php', ['-S', '127.0.0.1:18967', '-t', 'public', 'tests/support/google-browser-server.php'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'] });
writeFileSync(path.join(directory, 'process.json'), JSON.stringify({ launcher_pid: process.pid, php_pid: server.pid, port: 18967, deny_proxy_port: 18968 }));
const sanitize = data => data.toString().replace(/(\/auth\/google\/callback)\?[^\s]*/g, '$1 [query redacted]');
server.stdout.on('data', data => process.stdout.write(sanitize(data)));
server.stderr.on('data', data => process.stderr.write(sanitize(data)));
let reason = 'php-exit';
let settled = false;
const settle = code => {
  if (settled) return;
  settled = true;
  const cleanup = spawnSync('php', ['tests/support/google-browser-fixtures.php', 'cleanup'], { cwd: root, env, encoding: 'utf8' });
  denyProxy.close();
  const exit = cleanup.status === 0 ? (code || 0) : 1;
  writeFileSync(path.join(directory, 'settlement.json'), JSON.stringify({ run, launcher_pid: process.pid, php_pid: server.pid,
    reason, exit_code: exit, sql_cleanup_exit: cleanup.status, ports: [18967, 18968], php_exited: true }));
  process.exit(exit);
};
for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => { reason = signal; denyProxy.close(); server.kill(); });
server.on('error', () => { reason = 'php-spawn-failed'; settle(1); });
server.on('exit', code => settle(code));
