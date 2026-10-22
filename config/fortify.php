<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'middleware' => ['web'], 'auth_middleware' => 'auth', 'passwords' => 'users', 'username' => 'email', 'email' => 'email',
    'views' => true, 'home' => '/dashboard', 'prefix' => '', 'domain' => null, 'lowercase_usernames' => true, 'limiters' => ['login' => 'login'],
    'features' => array_values(array_filter([config('emailing.registration_enabled') ? Features::registration() : null, Features::resetPasswords()])),
];
