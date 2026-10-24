'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const http = require('node:http');
const { spawn } = require('node:child_process');
const { once } = require('node:events');
const path = require('node:path');
const fs = require('node:fs/promises');
const os = require('node:os');
const { actionFromJob, execute, cronicleResult } = require('../../scripts/cronicle-trigger.cjs');

const token = 'a'.repeat(64); // Public synthetic credential, never a real setting.
const payload = action => ({ status: 'processed', action, deliveries_created: action === 'materialize' ? 2 : 0,
  outbox_claims: action === 'dispatch' ? 20 : 0, emails_completed: null,
  stop_reason: action === 'dispatch' ? 'claim_limit' : 'campaign_limit', elapsed_ms: 25 });

async function serverFor(handler, run) {
  const server = http.createServer(handler);
  server.listen(0, '127.0.0.1');
  await once(server, 'listening');
  const endpoint = 'http://127.0.0.1:' + server.address().port + '/internal/cronicle/trigger';
  try { return await run(endpoint); }
  finally { server.closeAllConnections(); await new Promise(resolve => server.close(resolve)); }
}

test('only a finite action may be supplied in Cronicle job params', () => {
  assert.equal(actionFromJob({ params: { action: 'dispatch' } }), 'dispatch');
  for (const job of [null, {}, { params: [] }, { params: { action: 'shell' } },
    { params: { action: 'dispatch', endpoint: 'http://example.test' } },
    { params: { action: 'dispatch', token } }]) assert.equal(actionFromJob(job), null);
});

test('real HTTP carries the credential only in its header and returns safe counts', async () => {
  await serverFor(async (request, response) => {
    assert.equal(request.method, 'POST');
    assert.equal(request.url, '/internal/cronicle/trigger');
    assert.equal(request.headers.authorization, 'Bearer ' + token);
    const chunks = [];
    for await (const chunk of request) chunks.push(chunk);
    assert.deepEqual(JSON.parse(Buffer.concat(chunks)), { action: 'materialize' });
    response.writeHead(200, { 'Content-Type': 'application/json; charset=UTF-8' });
    response.end(JSON.stringify(payload('materialize')));
  }, async endpoint => {
    const result = await execute('materialize', { endpoint, token });
    assert.deepEqual(result, { ok: true, action: 'materialize', deliveriesCreated: 2, outboxClaims: 0,
      stopReason: 'campaign_limit', elapsedMs: 25 });
    const output = JSON.stringify(cronicleResult(result));
    assert.ok(output.includes('email completion unknown'));
    assert.ok(!output.includes(token));
    assert.ok(!output.includes(endpoint));
  });
});

test('redirects do not forward the credential to another endpoint', async () => {
  let followed = 0;
  await serverFor((request, response) => {
    if (request.url !== '/internal/cronicle/trigger') followed++;
    response.writeHead(302, { Location: '/credential-trap' });
    response.end('private-response-fixture');
  }, async endpoint => {
    assert.deepEqual(await execute('dispatch', { endpoint, token }), { ok: false, code: 'http_status', status: 302 });
    assert.equal(followed, 0);
  });
});

test('HTTP errors and malformed or excessive responses never expose raw content', async () => {
  for (const [status, media, body, expected] of [
    [503, 'text/plain', 'private-response-fixture', 'http_status'],
    [200, 'application/json', '{', 'invalid_response'],
    [200, 'text/html', 'private-response-fixture', 'invalid_response'],
    [200, 'application/json', JSON.stringify({ ...payload('dispatch'), emails_completed: 1 }), 'invalid_response'],
    [200, 'application/json', JSON.stringify({ ...payload('dispatch'), outbox_claims: 101 }), 'invalid_response'],
    [200, 'application/json', JSON.stringify({ ...payload('dispatch'), stop_reason: 'published' }), 'invalid_response'],
    [200, 'application/json', JSON.stringify({ ...payload('dispatch'), elapsed_ms: -1 }), 'invalid_response'],
    [200, 'application/json', JSON.stringify({ ...payload('dispatch'), stop_reason: 'campaign_limit' }), 'invalid_response'],
    [200, 'application/json', ' '.repeat(4097), 'response_too_large'],
  ]) {
    await serverFor((request, response) => { response.writeHead(status, { 'Content-Type': media }); response.end(body); }, async endpoint => {
      const result = await execute('dispatch', { endpoint, token });
      assert.equal(result.ok, false);
      assert.equal(result.code, expected);
      const output = JSON.stringify(cronicleResult(result));
      for (const sensitive of [token, endpoint, 'private-response-fixture']) assert.ok(!output.includes(sensitive));
    });
  }
});

test('an absolute deadline bounds a stalled response', async () => {
  await serverFor(() => {}, async endpoint => {
    assert.deepEqual(await execute('dispatch', { endpoint, token }, { timeoutMs: 100 }), { ok: false, code: 'deadline_exceeded' });
  });
});

test('invalid configuration is refused before network IO', async () => {
  for (const settings of [null, { endpoint: 'http://example.test/internal/cronicle/trigger', token: '' },
    { endpoint: 'http://user:password@example.test/internal/cronicle/trigger', token },
    { endpoint: 'http://example.test/internal/cronicle/trigger?token=fixture', token },
    { endpoint: 'http://example.test/other-path', token }]) {
    assert.deepEqual(await execute('dispatch', settings), { ok: false, code: 'configuration_unavailable' });
  }
});

test('real CLI protocol reads private config and prints only sanitized completion', async () => {
  const directory = await fs.mkdtemp(path.join(os.tmpdir(), 'emailing-cronicle-test-'));
  const config = path.join(directory, 'private.json');
  try {
    await serverFor((request, response) => { response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify(payload('dispatch'))); }, async endpoint => {
      await fs.writeFile(config, JSON.stringify({ endpoint, token }), { mode: 0o600 });
      const child = spawn(process.execPath, [path.resolve(__dirname, '../../scripts/cronicle-trigger.cjs'), config], { stdio: ['pipe', 'pipe', 'pipe'] });
      const out = [], err = [];
      child.stdout.on('data', value => out.push(value));
      child.stderr.on('data', value => err.push(value));
      child.stdin.end(JSON.stringify({ params: { action: 'dispatch' }, username: 'synthetic-operator' }) + '\n');
      const [exit] = await once(child, 'close');
      assert.equal(exit, 0);
      const output = Buffer.concat(out).toString();
      const record = JSON.parse(output);
      assert.equal(record.complete, 1);
      assert.equal(record.code, 0);
      assert.equal(Buffer.concat(err).byteLength, 0);
      for (const sensitive of [token, endpoint, config, 'synthetic-operator']) assert.ok(!output.includes(sensitive));
    });
  } finally {
    await fs.unlink(config).catch(error => { if (error.code !== 'ENOENT') throw error; });
    await fs.rmdir(directory); // Empty owned directory; no recursive deletion.
  }
});
