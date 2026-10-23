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
];
