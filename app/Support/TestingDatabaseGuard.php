<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

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
        $identity = DB::selectOne('SELECT DATABASE() AS db, CURRENT_USER() AS db_user');
        if ($identity->db !== 'emailing_testing' || explode('@', $identity->db_user, 2)[0] !== 'emailing_testing') {
            throw new \RuntimeException('Actual MySQL identity is not the exclusive testing database/user; refusing unsafe database.');
        }
    }
}
