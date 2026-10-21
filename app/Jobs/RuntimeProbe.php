<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RuntimeProbe implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $marker) {}

    public function handle(): void
    {
        file_put_contents(storage_path('app/private/probe-'.$this->marker), 'completed');
    }
}
