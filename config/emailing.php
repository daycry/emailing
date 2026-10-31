<?php

return [
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),
    'google_workspace_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_WORKSPACE_DOMAINS', ''))))),
    // Raising these limits also requires docker/emailing-uploads.ini, nginx.conf and a runtime restart.
    'import_max_bytes' => (int) env('CONTACT_IMPORT_MAX_BYTES', 5 * 1024 * 1024),
    'import_max_rows' => (int) env('CONTACT_IMPORT_MAX_ROWS', 10000),
    'mailpit' => [
        'host' => env('MAILPIT_SMTP_HOST', '127.0.0.1'),
        'port' => (int) env('MAILPIT_SMTP_PORT', 11025),
        'api_url' => env('MAILPIT_API_URL', 'http://127.0.0.1:18025'),
    ],
    'smtp_timeout' => 5,
    'external_enabled' => (bool) env('EMAIL_EXTERNAL_ENABLED', false),
    'provider_api_timeout' => 5,
    'provider_webhook_max_age' => 86400,
];
