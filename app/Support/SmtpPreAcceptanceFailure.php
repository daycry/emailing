<?php

namespace App\Support;

// The instrumented adapter proved that the DATA terminator was never attempted.
// A generic SmtpUnconfirmed does not carry this proof.
class SmtpPreAcceptanceFailure extends SmtpUnconfirmed {}
