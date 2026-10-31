<?php

namespace App\Support;

final class SmtpPolicyViolation extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('SMTP security policy requires a valid ESMTP TLS negotiation.');
    }
}
