'use strict';

// Cronicle plugin for a single finite Emailing action. Credentials live in an
// operator-mounted private JSON file, never in job params, argv or job logs.
const fs = require('node:fs/promises');

const ACTIONS = new Set(['materialize', 'dispatch']);
const fail = (code, status) => ({ ok: false, code, ...(status ? { status } : {}) });

function actionFromJob(job) {
  const params = job && job.params;
  return params && !Array.isArray(params) && Object.keys(params).length === 1 &&
    ACTIONS.has(params.action) ? params.action : null;
}

function validSettings(settings) {
  if (!settings || typeof settings.endpoint !== 'string' || settings.endpoint.length > 2048 ||
      typeof settings.token !== 'string' || !/^[a-f0-9]{64}$/.test(settings.token)) return false;
  try {
    const url = new URL(settings.endpoint);
    return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password &&
      !url.search && !url.hash && url.pathname === '/internal/cronicle/trigger';
  } catch { return false; }
}

async function execute(action, settings, { timeoutMs = 30000 } = {}) {
  if (!ACTIONS.has(action)) return fail('invalid_action');
  if (!validSettings(settings)) return fail('configuration_unavailable');
  const controller = new AbortController();
  const deadline = setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(settings.endpoint, {
      method: 'POST', redirect: 'manual', signal: controller.signal,
      headers: { Authorization: 'Bearer ' + settings.token, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ action }),
    });
    if (response.status !== 200) {
      await response.body?.cancel();
      return fail('http_status', response.status);
    }
    if (!/^application\/json(?:;|$)/i.test(response.headers.get('content-type') || '')) {
      await response.body?.cancel();
      return fail('invalid_response');
    }
    const chunks = [];
    let size = 0;
    for await (const chunk of response.body) {
      size += chunk.byteLength;
      if (size > 4096) {
        // Leaving the async iterator cancels its body. Aborting first can
        // reject iterator cleanup and replace this result with a false timeout.
        return fail('response_too_large');
      }
      chunks.push(Buffer.from(chunk));
    }
    let data;
    try { data = JSON.parse(Buffer.concat(chunks).toString('utf8')); }
    catch { return fail('invalid_response'); }
    const keys = ['status', 'action', 'deliveries_created', 'outbox_claims', 'emails_completed', 'stop_reason', 'elapsed_ms'];
    if (!data || Array.isArray(data) || Object.keys(data).length !== keys.length ||
        !keys.every(key => Object.hasOwn(data, key)) || data.status !== 'processed' ||
        data.action !== action || data.emails_completed !== null ||
        !Number.isInteger(data.deliveries_created) || data.deliveries_created < 0 || data.deliveries_created > 10000 ||
        !Number.isInteger(data.outbox_claims) || data.outbox_claims < 0 || data.outbox_claims > 100 ||
        !Number.isSafeInteger(data.elapsed_ms) || data.elapsed_ms < 0 ||
        !(action === 'materialize' ? ['empty', 'campaign_limit'] : ['empty', 'claim_limit', 'budget']).includes(data.stop_reason) ||
        (data.stop_reason === 'empty' && action === 'materialize' && data.deliveries_created !== 0) ||
        (action === 'materialize' && data.outbox_claims !== 0) ||
        (action === 'dispatch' && data.deliveries_created !== 0)) return fail('invalid_response');
    return { ok: true, action, deliveriesCreated: data.deliveries_created, outboxClaims: data.outbox_claims,
      stopReason: data.stop_reason, elapsedMs: data.elapsed_ms };
  } catch {
    return fail(controller.signal.aborted ? 'deadline_exceeded' : 'transport_unavailable');
  } finally {
    clearTimeout(deadline);
  }
}

function cronicleResult(result) {
  return result.ok ? {
    complete: 1, code: 0,
    description: 'Emailing ' + result.action + ': durable deliveries created=' + result.deliveriesCreated +
      ', outbox claims=' + result.outboxClaims + ', stop=' + result.stopReason +
      ', elapsed ms=' + result.elapsedMs + ', email completion unknown.',
  } : {
    complete: 1, code: 1,
    description: 'Emailing trigger: ' + result.code + (result.status ? ' (' + result.status + ')' : ''),
  };
}

function readJob() {
  return new Promise((resolve, reject) => {
    let body = Buffer.alloc(0);
    const finish = (error, value) => {
      clearTimeout(timer);
      process.stdin.removeListener('data', onData);
      process.stdin.removeListener('end', onEnd);
      process.stdin.destroy();
      error ? reject(error) : resolve(value);
    };
    const parse = line => {
      try { finish(null, JSON.parse(line)); } catch { finish(new Error('invalid_job')); }
    };
    const onData = chunk => {
      body = Buffer.concat([body, chunk]);
      if (body.byteLength > 262144) return finish(new Error('invalid_job'));
      const newline = body.indexOf(10);
      if (newline !== -1) parse(body.subarray(0, newline).toString('utf8'));
    };
    const onEnd = () => parse(body.toString('utf8'));
    const timer = setTimeout(() => finish(new Error('invalid_job')), 10000);
    process.stdin.on('data', onData);
    process.stdin.on('end', onEnd);
  });
}

async function main() {
  let result;
  try {
    const action = actionFromJob(await readJob());
    if (!action) result = fail('invalid_action');
    else {
      let settings;
      try {
        const text = await fs.readFile(process.argv[2], { encoding: 'utf8', signal: AbortSignal.timeout(2000) });
        if (Buffer.byteLength(text) > 4096) throw new Error('invalid_configuration');
        settings = JSON.parse(text);
      } catch { settings = null; }
      result = await execute(action, settings);
    }
  } catch { result = fail('invalid_job'); }
  process.stdout.write(JSON.stringify(cronicleResult(result)) + '\n');
  process.exitCode = result.ok ? 0 : 1;
}

module.exports = { actionFromJob, execute, cronicleResult };
if (require.main === module) main();
