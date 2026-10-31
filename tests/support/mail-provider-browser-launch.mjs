import { spawn, spawnSync } from 'node:child_process';
import { createServer as httpsServer } from 'node:https';
import { createServer as httpServer, request as httpRequest } from 'node:http';
import { createServer as tcpServer } from 'node:net';
import { readFileSync, writeFileSync, createWriteStream } from 'node:fs';
import { randomBytes } from 'node:crypto';
import path from 'node:path';

if (process.env.PROVIDER_BROWSER_TEST !== '1' || process.env.APP_ENV !== 'testing') process.exit(2);
const directory = process.env.PROVIDER_BROWSER_DIRECTORY;
const env = { ...process.env, PROVIDER_BROWSER_PROXY_TOKEN: randomBytes(32).toString('hex') };
const servers = [];
let php;
let stopping = false;
let setupDone = false;
const listen = (server, port) => new Promise((resolve, reject) => { server.once('error', reject); server.listen(port, '127.0.0.1', resolve); });
const close = server => new Promise(resolve => { if (!server.listening) return resolve(); server.close(resolve); server.closeAllConnections?.(); });
const fixture = action => {
  const result = spawnSync('php', ['-d', 'zend.exception_ignore_args=1', 'tests/support/mail-provider-browser-fixtures.php', action], { env, encoding: 'utf8', timeout: 120000 });
  writeFileSync(path.join(directory, action + '-stdout.private.txt'), result.stdout ?? '');
  writeFileSync(path.join(directory, action + '-stderr.private.txt'), result.stderr ?? '');
  return result.status === 0;
};
async function stop(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const server of servers) await close(server);
  let phpExited = !php;
  if (php && php.exitCode === null) {
    const exited = new Promise(resolve => php.once('exit', resolve));
    php.kill();
    await exited;
    phpExited = true;
  } else phpExited = true;
  const sqlClean = !setupDone || fixture('cleanup');
  const exit = code || (!sqlClean || !phpExited ? 1 : 0);
  writeFileSync(path.join(directory, 'browser-settlement.public.json'), JSON.stringify({ exit, sql_clean: sqlClean, owned_php_exited: phpExited, owned_listeners_closed: servers.every(s => !s.listening) }));
  process.exit(exit);
}
try {
  if (!fixture('pki')) throw new Error('PKI refused');
  const proxy = httpsServer({ key: readFileSync(path.join(directory, 'key.pem')), cert: readFileSync(path.join(directory, 'certificate.pem')) }, (request, response) => {
    const headers = { ...request.headers, 'x-provider-fixture-proxy': env.PROVIDER_BROWSER_PROXY_TOKEN };
    const upstream = httpRequest({ host: '127.0.0.1', port: 18869, method: request.method, path: request.url, headers }, result => {
      response.writeHead(result.statusCode, result.headers); result.pipe(response);
    });
    upstream.on('error', () => { if (!response.headersSent) response.writeHead(503); response.end(); });
    request.pipe(upstream);
  });
  servers.push(proxy);
  await listen(proxy, 18867);
  const deny = httpServer((request, response) => response.writeHead(502).end());
  deny.on('connect', (request, socket) => socket.end('HTTP/1.1 502 Bad Gateway\r\n\r\n'));
  servers.push(deny);
  await listen(deny, 18868);
  const reservation = tcpServer();
  servers.push(reservation);
  await listen(reservation, 18869);
  if (!fixture('setup')) throw new Error('Setup refused');
  setupDone = true;
  await close(reservation);
  php = spawn('php', ['-d', 'zend.exception_ignore_args=1', '-S', '127.0.0.1:18869', '-t', 'public', 'tests/support/mail-provider-browser-server.php'], { env, stdio: ['ignore', 'pipe', 'pipe'] });
  const output = createWriteStream(path.join(directory, 'php-server.private.txt'));
  php.stdout.pipe(output); php.stderr.pipe(output);
  php.on('error', () => stop(1));
  php.on('exit', () => { if (!stopping) stop(1); });
  writeFileSync(path.join(directory, 'browser-ready.public.json'), JSON.stringify({ listeners_owned: true, setup_completed: true }));
  process.stdin.setEncoding('utf8');
  process.stdin.on('data', text => { if (text.trim() === 'stop') stop(); });
  process.stdin.on('end', () => stop());
  for (const signal of ['SIGTERM', 'SIGINT']) process.on(signal, () => stop());
} catch {
  await stop(1);
}
