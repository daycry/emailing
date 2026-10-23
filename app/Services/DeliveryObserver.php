<?php

namespace App\Services;

use App\Models\Delivery;

// Inert production observer. Process fixtures bind an identity-constrained observer.
class DeliveryObserver
{
    public function observe(string $stage, Delivery $delivery, array $facts = []): void {}
}
