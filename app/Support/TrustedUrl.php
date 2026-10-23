<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

class TrustedUrl
{
    public static function signedRoute(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').URL::signedRoute($name, $parameters, absolute: false);
    }

    public static function route(string $name, array $parameters = []): string
    {
        return rtrim(config('app.url'), '/').route($name, $parameters, false);
    }
}
