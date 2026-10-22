<?php

namespace App\Support;

class TrustedUrl
{
    public static function route(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }
}
