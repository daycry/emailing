<?php

return [
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),
    // Raising these limits also requires docker/emailing-uploads.ini, nginx.conf and a runtime restart.
    'import_max_bytes' => (int) env('CONTACT_IMPORT_MAX_BYTES', 5 * 1024 * 1024),
    'import_max_rows' => (int) env('CONTACT_IMPORT_MAX_ROWS', 10000),
    'mailpit' => [
        'host' => env('MAILPIT_SMTP_HOST', '127.0.0.1'),
        'port' => (int) env('MAILPIT_SMTP_PORT', 11025),
        'api_url' => env('MAILPIT_API_URL', 'http://127.0.0.1:18025'),
    ],
    'smtp_timeout' => 5,
];
