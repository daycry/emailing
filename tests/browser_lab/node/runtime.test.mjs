import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, linkSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fixtureInvocation, runBrowserFixture, loadBrowserFixture, profile } from '../../support/google-browser-runtime.mjs';

const run = 'b5dc463d-f182-4d0a-896f-13337b12ea66';
const environment = { GOOGLE_BROWSER_PROFILE: 'docker', GOOGLE_BROWSER_TEST: '1', GOOGLE_BROWSER_RUN: run,
  GOOGLE_BROWSER_CONTAINER_ID: 'a'.repeat(64), GOOGLE_BROWSER_PHP_IMAGE: 'sha256:' + 'b'.repeat(64) };
const identity = { id: environment.GOOGLE_BROWSER_CONTAINER_ID, image: environment.GOOGLE_BROWSER_PHP_IMAGE,
  run, name: '/emailing-b12-' + run.replaceAll('-', '') + '-php', running: true };

test('import and planning never execute a process; native retains SQL guard overrides', () => {
  assert.equal(profile({}), 'native');
  const value = fixtureInvocation('expire-mcp', { run, tokenId: '12' }, {});
  assert.equal(value.program, 'php');
  assert.equal(value.env.DB_DATABASE, 'emailing_testing');
  assert.equal(value.env.DB_USERNAME, 'emailing_testing');
  assert.equal(value.env.DB_URL, '');
  assert.equal(value.env.GOOGLE_BROWSER_RUN, run);
  assert.equal(value.env.MCP_BROWSER_TOKEN_ID, '12');
  assert.deepEqual(value.args.slice(-2), ['tests/support/google-browser-fixtures.php', 'expire-mcp']);
});

test('Docker verifies the closed identity before executing only its ID as UID33', () => {
  const calls = [];
  const result = runBrowserFixture('expire-mcp', { run, tokenId: '12' }, environment, (program, args, options) => {
    calls.push({ program, args, options });
    return calls.length === 1 ? { status: 0, stdout: JSON.stringify(identity) } : { status: 0, stdout: 'ok' };
  });
  assert.equal(result.status, 0);
  assert.equal(calls.length, 2);
  assert.deepEqual(calls[0].args.slice(0, 2), ['container', 'inspect']);
  assert.equal(calls[0].args.at(-1), identity.id);
  assert(!calls[0].args.join(' ').includes('.Config.Env'));
  assert.equal(calls[1].args[calls[1].args.indexOf('--user') + 1], '33:33');
  assert(calls[1].args.includes(identity.id));
  assert(!calls[1].args.includes(identity.name));
  assert(calls[1].args.includes('MCP_BROWSER_TOKEN_ID=12'));
  assert.equal(calls[1].options.timeout, 120000);
  assert.equal(calls[1].options.windowsHide, true);
});

test('unknown action/profile, bad UUID, missing owner and bearer inputs refuse before processes', () => {
  const invalid = [
    ['erase', { run }, environment],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_PROFILE: 'other' }],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_PROFILE: undefined }],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_PROFILE: 'native' }],
    ['cleanup', { run: run + '\n' }, environment],
    ['cleanup', { run: run.toUpperCase() }, environment],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_RUN: 'c5dc463d-f182-4d0a-896f-13337b12ea66' }],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_CONTAINER_ID: 'container-name' }],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_PHP_IMAGE: 'latest' }],
    ['cleanup', { run }, { ...environment, GOOGLE_BROWSER_TEST: '0' }],
    ['expire-mcp', { run, tokenId: '12|not-a-token' }, environment],
    ['expire-mcp', { run, tokenId: '0' }, environment],
    ['expire-mcp', { run, tokenId: '12\n' }, environment],
    ['cleanup', { run, tokenId: '12' }, environment],
  ];
  for (const [action, options, env] of invalid) {
    let calls = 0;
    assert.throws(() => runBrowserFixture(action, options, env, () => { calls++; }));
    assert.equal(calls, 0);
  }
});

test('mismatched identity fields or extra fields refuse before fixture execution', () => {
  for (const change of [{ id: 'c'.repeat(64) }, { run: 'other' }, { name: '/foreign' },
    { image: 'sha256:' + 'd'.repeat(64) }, { running: false }, { extra: true }]) {
    let calls = 0;
    assert.throws(() => runBrowserFixture('cleanup', { run }, environment, () => {
      calls++;
      return { status: 0, stdout: JSON.stringify({ ...identity, ...change }) };
    }), /browser_identity_mismatch/);
    assert.equal(calls, 1);
  }
});

test('failed or malformed inspect never falls back to a named or host process', () => {
  for (const response of [{ status: 1, stdout: '' }, { status: null, error: new Error('synthetic') },
    { status: 0, stdout: 'not-json' }]) {
    let calls = 0;
    assert.throws(() => runBrowserFixture('setup', { run }, environment, () => { calls++; return response; }));
    assert.equal(calls, 1);
  }
});

test('Docker private DTO stays in the test process and maps only its output directory', () => {
  const root = path.resolve('synthetic-browser-root');
  const fixture = { run, directory: '/var/www/emailing/storage/mcp-google-phase3/browser-' + run, api_key: null };
  let calls = 0;
  const result = loadBrowserFixture(environment, root, () => {
    calls++;
    return { status: 0, stdout: JSON.stringify(calls === 1 ? identity : fixture) };
  });
  assert.equal(calls, 2);
  assert.equal(result.directory, path.join(root, 'storage/mcp-google-docker', run, 'browser'));
  assert.equal(result.api_key, null);
});

test('wrong run/directory, failed child and oversized private DTO are refused', () => {
  for (const response of [
    { status: 0, stdout: JSON.stringify({ run: 'other', directory: '/foreign' }) },
    { status: 0, stdout: JSON.stringify({ run, directory: '/foreign' }) },
    { status: 1, stdout: '' }, { status: 0, stdout: 'x'.repeat(65537) },
  ]) {
    let calls = 0;
    assert.throws(() => loadBrowserFixture(environment, process.cwd(), () => {
      calls++;
      return calls === 1 ? { status: 0, stdout: JSON.stringify(identity) } : response;
    }));
    assert.equal(calls, 2);
  }
});

test('native fixture lookup checks run, canonical ownership and hardlinks', () => {
  const parent = realpathSync(tmpdir());
  const root = mkdtempSync(path.join(parent, 'emailing-browser-runtime-'));
  try {
    const dir = path.join(root, 'storage/mcp-google-phase3');
    mkdirSync(dir, { recursive: true });
    const filename = path.join(dir, 'browser-fixtures.json');
    writeFileSync(filename, JSON.stringify({ run, directory: path.join(dir, 'browser-' + run) }));
    assert.equal(loadBrowserFixture({}, root).run, run);
    writeFileSync(filename, JSON.stringify({ run, directory: '/foreign' }));
    assert.throws(() => loadBrowserFixture({}, root), /owner_mismatch/);
    writeFileSync(filename, JSON.stringify({ run, directory: path.join(dir, 'browser-' + run) }));
    linkSync(filename, path.join(dir, 'synthetic-link.json'));
    assert.throws(() => loadBrowserFixture({}, root), /file_refused/);
  } finally {
    assert.equal(path.dirname(root), parent);
    assert(path.basename(root).startsWith('emailing-browser-runtime-'));
    assert.equal(realpathSync(root), root);
    rmSync(root, { recursive: true });
  }
});
