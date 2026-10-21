'use strict';
const http = require('http');
const request = http.get('http://127.0.0.1:3012/api/app/config', response => {
  let body = '';
  response.on('data', chunk => { body += chunk; });
  response.on('end', () => {
    try {
      // Upstream config is JSON wrapped for the browser; never evaluate it.
      const wrapped = body.trim().match(/^app\.receiveConfig\(([\s\S]*)\);$/);
      const config = wrapped && JSON.parse(wrapped[1]);
      process.exit(response.statusCode === 200 && config && config.code === 0 &&
        config.master_hostname === require('os').hostname() ? 0 : 1);
    }
    catch { process.exit(1); }
  });
});
request.setTimeout(3000, () => { request.destroy(); process.exit(1); });
request.on('error', () => process.exit(1));
