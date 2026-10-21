'use strict';

const fs = require('fs');
const crypto = require('crypto');
const { spawnSync } = require('child_process');
const bcrypt = require('bcryptjs');
process.chdir('/opt/cronicle');

function cli(args, input) {
  const result = spawnSync(process.execPath, ['bin/storage-cli.js', ...args, '--quiet'], {
    input, encoding: 'utf8', stdio: ['pipe', 'pipe', 'pipe']
  });
  if (result.error || result.status !== 0) {
    throw new Error('Cronicle storage initialization failed; inspect local storage permissions.');
  }
}

const password = process.env.LAB_CRONICLE_PASSWORD;
const secret = process.env.LAB_CRONICLE_SECRET;
if (!password || password.length < 24 || !secret || secret.length < 32) {
  throw new Error('Generate local secrets with infra/init.ps1 before starting Cronicle.');
}
const config = JSON.parse(fs.readFileSync('conf/config.json', 'utf8'));
Object.assign(config, {
  secret_key: secret,
  base_app_url: 'http://127.0.0.1:3012',
  foreground: true,
  // This laboratory has one fixed server. Skip the 60-second failover wait.
  master: true,
  debug_level: 3,
  smtp_hostname: 'mailpit',
  smtp_port: 1025,
  job_data_expire_days: 30,
  max_jobs: 10,
  job_log_max_size: 10485760,
  server_comm_use_hostnames: true,
  web_direct_connect: false
});
fs.writeFileSync('conf/config.json', JSON.stringify(config, null, 2), { mode: 0o600 });

// Never overwrite a password changed in the UI after the first bootstrap.
if (!fs.existsSync('data/.lab-initialized')) {
  const existing = spawnSync(process.execPath, ['bin/storage-cli.js', 'get', 'global/users', '--quiet'], { stdio: 'ignore' });
  if (existing.status !== 0) cli(['setup']);
  const salt = crypto.randomBytes(32).toString('hex');
  const now = Math.floor(Date.now() / 1000);
  const admin = {
    username: 'admin', full_name: 'Laboratory Administrator', email: 'admin@localhost',
    active: 1, created: now, modified: now, salt,
    password: bcrypt.hashSync(password + salt, 10), privileges: { admin: 1 }
  };
  cli(['put', 'users/admin'], JSON.stringify(admin) + '\n');
  fs.writeFileSync('data/.lab-initialized', 'Secure initial administrator configured.\n', { mode: 0o600 });
  console.log('Cronicle storage initialized with the local administrator credential.');
} else {
  console.log('Cronicle existing storage preserved.');
}
