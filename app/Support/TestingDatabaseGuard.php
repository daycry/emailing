<?php

namespace App\Support;

class TestingDatabaseGuard
{
    public static function assertSafe(): void
    {
        if (config('database.default') !== 'mysql'
            || config('database.connections.mysql.database') !== 'emailing_testing'
            || config('database.connections.mysql.username') !== 'emailing_testing'
            || filled(config('database.connections.mysql.url'))) {
            throw new \RuntimeException('Tests require exclusive MySQL database/user emailing_testing without DB_URL; refusing unsafe database.');
        }
    }
}
