<?php

return [
    'registration_enabled' => (bool) env('REGISTRATION_ENABLED', true),
    // Raising these limits also requires docker/emailing-uploads.ini, nginx.conf and a runtime restart.
    'import_max_bytes' => (int) env('CONTACT_IMPORT_MAX_BYTES', 5 * 1024 * 1024),
    'import_max_rows' => (int) env('CONTACT_IMPORT_MAX_ROWS', 10000),
];
