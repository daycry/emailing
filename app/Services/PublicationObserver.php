<?php

namespace App\Services;

use App\Models\DeliveryOutbox;

// Inert in production. Tests bind an observer to coordinate real protocol barriers.
class PublicationObserver
{
    public function observe(string $stage, DeliveryOutbox $event, array $facts = []): void {}
}
