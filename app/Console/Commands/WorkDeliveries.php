<?php

namespace App\Console\Commands;

use App\Services\DeliveryConsumer;
use Illuminate\Console\Command;

class WorkDeliveries extends Command
{
    protected $signature = 'deliveries:work {--once} {--max-messages=0}';

    protected $description = 'Consume durable domain deliveries with manual acknowledgement';

    public function handle(DeliveryConsumer $consumer): int
    {
        $stop = false;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, function () use (&$stop) {
                    $stop = true;
                });
            }
        }
        try {
            $count = $consumer->run((bool) $this->option('once'), max(0, (int) $this->option('max-messages')), function () use (&$stop) {
                return $stop;
            });
            $this->info('Processed domain events: '.$count);

            return self::SUCCESS;
        } catch (\Throwable) {
            // No DSN, raw SMTP/broker error, credentials or exception args in CLI logs.
            $this->error('Delivery worker stopped; durable work will be reconciled.');

            return self::FAILURE;
        }
    }
}
