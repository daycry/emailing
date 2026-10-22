<?php

namespace App\Support;

use RuntimeException;

class SmtpUnconfirmed extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No se ha confirmado la aceptación SMTP. No se debe reenviar automáticamente.');
    }
}
