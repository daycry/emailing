<?php

namespace App\Console\Commands;

use App\Services\OutboxDispatcher;
use Illuminate\Console\Command;

class DispatchDeliveryOutbox extends Command
{
    protected $signature = 'app:dispatch-outbox {--limit=20 : Maximum publications in this finite invocation}';

    protected $description = 'Publish due domain delivery events with confirmed, fenced SQL reconciliation';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $requested = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($requested === false) {
            $this->error('El límite debe estar entre 1 y 100.');

            return self::INVALID;
        }
        $count = 0;
        while ($count < $requested && $dispatcher->dispatch()) {
            $count++;
        }
        $this->info('Eventos procesados: '.$count.'. Consulta el estado durable de publicación en SQL.');

        return self::SUCCESS;
    }
}
