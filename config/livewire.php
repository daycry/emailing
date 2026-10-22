<?php

use App\Http\Middleware\AuthorizeContactUpload;

return [
    'temporary_file_upload' => [
        'disk' => 'contact-imports',
        'directory' => 'temporary',
        'preview_mimes' => [],
        'rules' => ['required', 'file', 'max:'.(int) ceil((int) env('CONTACT_IMPORT_MAX_BYTES', 5 * 1024 * 1024) / 1024), 'extensions:csv'],
        'middleware' => ['auth', AuthorizeContactUpload::class, 'throttle:30,1'],
    ],
];
