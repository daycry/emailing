<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class DBTransactionGuard
{
    public static function active(): bool
    {
        return DB::transactionLevel() !== 0;
    }
}
