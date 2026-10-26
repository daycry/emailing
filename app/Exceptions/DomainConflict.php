<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class DomainConflict extends HttpException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct(409, $message);
    }
}
