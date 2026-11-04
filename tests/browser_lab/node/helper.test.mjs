import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, statSync, readdirSync, realpathSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { configure, main } from '../../support/google-docker-helper.mjs';

const run = 'b5dc463d-f182-4d0a-896f-13337b12ea66';
function fixture(t) {
  const parent = realpathSync(tmpdir());
  const root = mkdtempSync(path.join(parent, 'emailing-browser-configuration-'));
  const directory = path.join(root, 'storage/mcp-google-docker', run);
  mkdirSync(directory, { recursive: true });
  t.after(() => {
    assert.equal(path.dirname(root), parent);
    assert(path.basename(root).startsWith('emailing-browser-configuration-'));
    assert.equal(realpathSync(root), root);
    rmSync(root, { recursive: true });
  });
  return { root, directory };
}

test('configuration generates exclusive files and returns metadata without exposing bodies', t => {
  const { root, directory } = fixture(t);
  assert.deepEqual(configure(root, run), { configured: true, existing_configuration_read: false, secrets_returned: false });
  assert.deepEqual(readdirSync(directory).sort(), ['application.private.env', 'browser', 'compose-empty.public.env', 'mysql.private.env']);
  for (const name of ['application.private.env', 'mysql.private.env']) {
    const stat = statSync(path.join(directory, name));
    assert(stat.isFile() && stat.nlink === 1 && stat.size > 0);
  }
  assert.equal(statSync(path.join(directory, 'compose-empty.public.env')).size, 0);
  // Deliberately never read or hash generated key/password file bodies.
});

test('existing generated files refuse replacement without changing metadata', t => {
  const { root, directory } = fixture(t);
  configure(root, run);
  const before = statSync(path.join(directory, 'application.private.env'));
  assert.throws(() => configure(root, run), /configuration_preexists/);
  const after = statSync(path.join(directory, 'application.private.env'));
  assert.equal(after.mtimeMs, before.mtimeMs);
  assert.equal(after.size, before.size);
});

test('a partial existing configuration refuses before any additional file creation', t => {
  const { root, directory } = fixture(t);
  writeFileSync(path.join(directory, 'mysql.private.env'), '# synthetic existing placeholder\n');
  assert.throws(() => configure(root, run), /configuration_preexists/);
  assert.deepEqual(readdirSync(directory), ['mysql.private.env']);
});

test('invalid mode or missing controller gate refuses before processes and files', async () => {
  await assert.rejects(main(['configure', run], {}), /gate_required/);
  await assert.rejects(main(['other', run], { BROWSER_CONTROLLER_GATED: '1' }), /action_refused/);
  await assert.rejects(main(['configure', '../foreign'], { BROWSER_CONTROLLER_GATED: '1' }), /run_refused/);
});
