<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/** Preserve the API's closed Cache-Control value through Symfony preparation. */
final class ApiNoStoreHeaders extends ResponseHeaderBag
{
    public function __construct(array $headers = [])
    {
        $headers['Cache-Control'] = 'no-store';
        parent::__construct($headers);
    }

    protected function computeCacheControlValue(): string
    {
        return 'no-store';
    }
}
