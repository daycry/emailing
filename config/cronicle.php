<?php

return [
    // Dedicated application trigger credential, separate from Cronicle's own
    // server secret and from interactive users. Missing configuration disables it.
    'secret' => env('CRONICLE_TRIGGER_SECRET'),
    // Cooperative checks cannot cancel in-flight SQL/AMQP or undo commits.
    'dispatch_claim_limit' => env('CRONICLE_DISPATCH_CLAIM_LIMIT', 20),
    'dispatch_budget_ms' => env('CRONICLE_DISPATCH_BUDGET_MS', 10000),
];
