<?php

namespace App\Support;

use App\Models\Membership;

/** Explicit, server-verified authority. Never constructed from tool account arguments. */
interface ExecutionContext
{
    public function actorId(): int;

    public function accountId(): int;

    public function authorize(array $scopes = [], bool $lock = false): Membership;

    public function provenance(): array;
}
