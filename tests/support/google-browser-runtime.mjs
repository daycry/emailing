// Test-only fixture transport. Importing this module never starts a process.
import { spawnSync } from 'node:child_process';
import { readFileSync, lstatSync, realpathSync } from 'node:fs';
import path from 'node:path';

const uuid4 = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
const id = /^[0-9a-f]{64}$/;
const image = /^sha256:[0-9a-f]{64}$/;
const label = 'io.emailing.browser.run';
const require = (condition, reason) => { if (!condition) throw new Error(reason); };

export function profile(environment = process.env) {
  const value = environment.GOOGLE_BROWSER_PROFILE ?? 'native';
  require(value === 'native' || value === 'docker', 'browser_profile_refused');
  require(value === 'docker' || !('GOOGLE_BROWSER_CONTAINER_ID' in environment || 'GOOGLE_BROWSER_PHP_IMAGE' in environment),
    'browser_docker_metadata_requires_explicit_profile');
  return value;
}

export function dockerIdentity(environment) {
  const run = environment.GOOGLE_BROWSER_RUN;
  require(typeof run === 'string' && uuid4.test(run), 'browser_run_refused');
  require(typeof environment.GOOGLE_BROWSER_CONTAINER_ID === 'string'
    && id.test(environment.GOOGLE_BROWSER_CONTAINER_ID), 'browser_container_id_refused');
  require(typeof environment.GOOGLE_BROWSER_PHP_IMAGE === 'string'
    && image.test(environment.GOOGLE_BROWSER_PHP_IMAGE), 'browser_image_refused');
  require(environment.GOOGLE_BROWSER_TEST === '1', 'browser_explicit_execution_required');
  return { run, id: environment.GOOGLE_BROWSER_CONTAINER_ID, image: environment.GOOGLE_BROWSER_PHP_IMAGE,
    name: 'emailing-b12-' + run.replaceAll('-', '') + '-php' };
}

export function fixtureInvocation(action, { run, tokenId } = {}, environment = process.env) {
  require(['setup', 'cleanup', 'expire-mcp', 'read-fixture'].includes(action), 'browser_fixture_action_refused');
  require(typeof run === 'string' && uuid4.test(run), 'browser_run_refused');
  require(action === 'expire-mcp' ? typeof tokenId === 'string' && /^[1-9][0-9]{0,15}$/.test(tokenId)
    : tokenId === undefined, 'browser_fixture_token_reference_refused');
  if (profile(environment) === 'native') {
    return { program: 'php', args: ['-d', 'zend.exception_ignore_args=1', 'tests/support/google-browser-fixtures.php', action],
      env: { ...environment, APP_ENV: 'testing', DB_DATABASE: 'emailing_testing', DB_USERNAME: 'emailing_testing',
        DB_URL: '', GOOGLE_BROWSER_TEST: '1', GOOGLE_BROWSER_RUN: run,
        ...(tokenId === undefined ? {} : { MCP_BROWSER_TOKEN_ID: tokenId }) } };
  }
  const owned = dockerIdentity(environment);
  require(owned.run === run, 'browser_fixture_run_mismatch');
  const args = ['container', 'exec', '--user', '33:33', '--workdir', '/var/www/emailing'];
  if (tokenId !== undefined) args.push('--env', 'MCP_BROWSER_TOKEN_ID=' + tokenId);
  args.push(owned.id, 'php', '-d', 'zend.exception_ignore_args=1', 'tests/support/google-browser-fixtures.php', action);
  return { program: 'docker', args, env: environment, owned };
}

export function runBrowserFixture(action, options, environment = process.env, run = spawnSync) {
  const invocation = fixtureInvocation(action, options, environment);
  if (invocation.owned) {
    const owned = invocation.owned;
    const format = '{"id":{{json .Id}},"name":{{json .Name}},"run":{{json (index .Config.Labels "' + label
      + '")}},"image":{{json .Image}},"running":{{json .State.Running}}}';
    const observed = run('docker', ['container', 'inspect', '--format', format, owned.id],
      { encoding: 'utf8', timeout: 15000, maxBuffer: 8192, windowsHide: true });
    require(observed.status === 0 && !observed.error && typeof observed.stdout === 'string', 'browser_identity_unverifiable');
    const value = JSON.parse(observed.stdout);
    require(value && Object.keys(value).sort().join(',') === 'id,image,name,run,running'
      && value.id === owned.id && value.name === '/' + owned.name && value.run === owned.run
      && value.image === owned.image && value.running === true, 'browser_identity_mismatch');
  }
  return run(invocation.program, invocation.args, { cwd: process.cwd(), env: invocation.env, encoding: 'utf8',
    timeout: 120000, maxBuffer: 1048576, windowsHide: true });
}

function regularFile(root, filename) {
  require(path.isAbsolute(root) && path.resolve(filename).startsWith(root + path.sep), 'browser_fixture_path_refused');
  let cursor = root;
  require(!lstatSync(root).isSymbolicLink() && realpathSync(root) === root, 'browser_fixture_path_refused');
  for (const part of path.relative(root, filename).split(path.sep)) {
    cursor = path.join(cursor, part);
    require(!lstatSync(cursor).isSymbolicLink(), 'browser_fixture_link_refused');
  }
  const stat = lstatSync(filename);
  require(stat.isFile() && stat.nlink === 1 && stat.size > 0 && stat.size <= 65536, 'browser_fixture_file_refused');
}

export function loadBrowserFixture(environment = process.env, root = process.cwd(), run = spawnSync) {
  root = path.resolve(root);
  const kind = profile(environment);
  const owned = kind === 'docker' ? dockerIdentity(environment) : null;
  // Normal test bootstrap reads its own synthetic fixture, including its
  // negative API key. The agent must never inspect this runtime body manually.
  let body;
  if (owned) {
    const result = runBrowserFixture('read-fixture', { run: owned.run }, environment, run);
    require(result.status === 0 && !result.error && typeof result.stdout === 'string'
      && Buffer.byteLength(result.stdout, 'utf8') <= 65536, 'browser_fixture_unavailable');
    body = result.stdout;
  } else {
    const filename = path.join(root, 'storage/mcp-google-phase3/browser-fixtures.json');
    regularFile(root, filename);
    body = readFileSync(filename, 'utf8');
  }
  const value = JSON.parse(body);
  require(value && typeof value.run === 'string' && uuid4.test(value.run), 'browser_fixture_run_refused');
  if (owned) {
    require(value.run === owned.run
      && value.directory === '/var/www/emailing/storage/mcp-google-phase3/browser-' + owned.run,
      'browser_fixture_owner_mismatch');
    return { ...value, directory: path.join(root, 'storage/mcp-google-docker', owned.run, 'browser') };
  }
  require(value.directory === path.join(root, 'storage/mcp-google-phase3/browser-' + value.run),
    'browser_fixture_owner_mismatch');
  return value;
}
