<?php

namespace App\Services;

// Only bounded stage, numeric code, flags and elapsed time; never wire contents.
class SmtpProtocolObserver
{
    public function observe(string $stage, array $facts): void {}
}
