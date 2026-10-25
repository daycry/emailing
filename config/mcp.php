<?php

return [
    // Local resource ceilings; they do not widen OAuth authority or protocol limits.
    'concurrency_per_account' => 8,
    'enabled' => (bool) env('MCP_ENABLED', false),
    'issuer' => env('MCP_ISSUER'),
    'resource' => env('MCP_RESOURCE'),
    'dynamic_registration' => (bool) env('MCP_DYNAMIC_REGISTRATION', false),
    'registration_https_hosts' => array_values(array_filter(explode(',', (string) env('MCP_REGISTRATION_HTTPS_HOSTS', '')))),
    'registration_loopback_profiles' => array_values(array_filter(explode(',', (string) env('MCP_REGISTRATION_LOOPBACK_PROFILES', '')))),
    'allowed_origins' => [],
];
