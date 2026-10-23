<?php

namespace App\Support;

// Only an observed SMTP negative reply may establish a safe SMTP retry.
// Never retain the underlying exception, reply, command, debug or argument chain.
class SmtpRejected extends SmtpPreAcceptanceFailure
{
    public function __construct(public readonly string $stage, public readonly int $replyCode)
    {
        parent::__construct();
    }
}
