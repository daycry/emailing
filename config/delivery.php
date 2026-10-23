<?php

return [
    // Domain JSON is deliberately separate from Laravel's serialized job queue.
    'exchange' => env('DELIVERY_EXCHANGE', 'emailing.deliveries'),
    'queue' => env('DELIVERY_QUEUE', 'emailing.deliveries'),
    'routing_key' => env('DELIVERY_ROUTING_KEY', 'delivery.ready'),
    'binding_key' => env('DELIVERY_BINDING_KEY', 'delivery.ready'),
    'confirm_timeout' => 3,
    'lease_seconds' => 30,
    'retry_seconds' => 10,
    'failure_queue' => env('DELIVERY_FAILURE_QUEUE', 'emailing.deliveries.failed'),
    'failure_routing_key' => 'delivery.failed',
    'prefetch' => (int) env('DELIVERY_PREFETCH', 2),
    'worker_lease_seconds' => (int) env('DELIVERY_WORKER_LEASE_SECONDS', 30),
    'pause_poll_seconds' => (int) env('DELIVERY_PAUSE_POLL_SECONDS', 30),
    'max_attempts' => (int) env('DELIVERY_MAX_ATTEMPTS', 3),
    'backoff_seconds' => (int) env('DELIVERY_BACKOFF_SECONDS', 10),
    'backoff_max_seconds' => (int) env('DELIVERY_BACKOFF_MAX_SECONDS', 3600),
    // Server-owned explicit pool, shared across accounts. Never supplied by clients.
    'relay_pool' => env('DELIVERY_RELAY_POOL', 'mailpit-lab'),
    'quotas' => [
        'account' => ['minute' => (int) env('DELIVERY_ACCOUNT_PER_MINUTE', 10), 'day' => (int) env('DELIVERY_ACCOUNT_PER_DAY', 200)],
        'pool' => ['minute' => (int) env('DELIVERY_POOL_PER_MINUTE', 20), 'day' => (int) env('DELIVERY_POOL_PER_DAY', 500)],
    ],
];
