// Fixed helper for the gated controller; import has no side effects.
import { generateKeyPairSync, randomBytes } from 'node:crypto';
import { openSync, writeSync, fsyncSync, closeSync, lstatSync, realpathSync, mkdirSync } from 'node:fs';
import { createServer } from 'node:http';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
import { runBrowserFixture } from './google-browser-runtime.mjs';

const require = (condition, code) => { if (!condition) throw new Error(code); };
const validRun = run => typeof run === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/.test(run);

export function ownedDirectory(root, run) {
  require(validRun(run), 'browser_run_refused');
  root = path.resolve(root);
  require(realpathSync(root) === root, 'browser_directory_refused');
  let current = root;
  for (const part of ['storage', 'mcp-google-docker', run]) {
    current = path.join(current, part);
    const stat = lstatSync(current);
    require(stat.isDirectory() && !stat.isSymbolicLink(), 'browser_directory_refused');
  }
  require(realpathSync(current) === current, 'browser_directory_refused');
  return current;
}

export function configure(root, run) {
  const directory = ownedDirectory(root, run);
  const files = ['application.private.env', 'mysql.private.env', 'compose-empty.public.env'];
  const absent = name => {
    try { lstatSync(path.join(directory, name)); return false; }
    catch (error) { if (error.code === 'ENOENT') return true; throw error; }
  };
  require(files.every(absent), 'browser_configuration_preexists');
  const password = randomBytes(32).toString('hex');
  const key = generateKeyPairSync('rsa', { modulusLength: 2048,
    privateKeyEncoding: { type: 'pkcs8', format: 'pem' }, publicKeyEncoding: { type: 'spki', format: 'pem' } });
  const values = {
    'application.private.env': { APP_KEY: 'base64:' + randomBytes(32).toString('base64'), DB_PASSWORD: password,
      GOOGLE_BROWSER_KEY: Buffer.from(key.privateKey).toString('base64') },
    'mysql.private.env': { MYSQL_ROOT_PASSWORD: randomBytes(32).toString('hex'), MYSQL_PASSWORD: password },
    'compose-empty.public.env': {},
  };
  // Generated once, exclusive, durable. Partial files are retained; no existing
  // configuration is opened or copied and no secret is returned or logged.
  for (const name of files) {
    const descriptor = openSync(path.join(directory, name), 'wx', 0o600);
    try {
      const body = Buffer.from(Object.entries(values[name]).map(([k, v]) => k + '=' + v + '\n').join(''), 'ascii');
      let offset = 0;
      while (offset < body.length) {
        const written = writeSync(descriptor, body, offset, body.length - offset);
        require(written > 0, 'browser_configuration_write_failed');
        offset += written;
      }
      fsyncSync(descriptor);
    } finally { closeSync(descriptor); }
  }
  mkdirSync(path.join(directory, 'browser'), { mode: 0o700 });
  return { configured: true, existing_configuration_read: false, secrets_returned: false };
}

export async function denyProxy(root, run) {
  const directory = ownedDirectory(root, run);
  const server = createServer((request, response) => response.writeHead(502).end('External network disabled.'));
  server.on('connect', (request, socket) => socket.end('HTTP/1.1 502 Bad Gateway\r\n\r\n'));
  server.on('clientError', (error, socket) => socket.end('HTTP/1.1 400 Bad Request\r\n\r\n'));
  await new Promise((resolve, reject) => { server.once('error', reject); server.listen(18968, '127.0.0.1', resolve); });
  const descriptor = openSync(path.join(directory, 'proxy-ready.public.json'), 'wx', 0o600);
  try { writeSync(descriptor, JSON.stringify({ run_id: run, bound: true, port: 18968 })); fsyncSync(descriptor); }
  finally { closeSync(descriptor); }
  return new Promise(resolve => {
    let closing = false;
    const stop = () => {
      if (closing) return;
      closing = true;
      server.close(() => resolve());
      server.closeAllConnections?.();
    };
    process.stdin.resume();
    process.stdin.on('end', stop);
    process.stdin.on('data', data => { if (data.toString().trim() === 'stop') stop(); });
    process.once('SIGTERM', stop);
    process.once('SIGINT', stop);
  });
}

export async function main(args = process.argv.slice(2), environment = process.env) {
  require(args.length === 2 && environment.BROWSER_CONTROLLER_GATED === '1', 'browser_controller_gate_required');
  const [mode, run] = args;
  require(validRun(run), 'browser_run_refused');
  if (mode === 'configure') return configure(process.cwd(), run);
  if (mode === 'proxy') { await denyProxy(process.cwd(), run); return { proxy_closed: true }; }
  require(mode === 'setup' || mode === 'cleanup', 'browser_helper_action_refused');
  const result = runBrowserFixture(mode, { run }, environment);
  require(result.status === 0 && !result.error, 'browser_fixture_failed');
  return { action: mode, fixture_complete: true };
}

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  try { console.log(JSON.stringify(await main())); }
  catch (error) { console.log(JSON.stringify({ outcome: 'refused', failure_class: error.constructor.name })); process.exitCode = 2; }
}
