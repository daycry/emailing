<?php

namespace App\Services;

final readonly class PublicationResult
{
    public function __construct(public bool $confirmed, public ?string $error = null) {}
}
