<?php

namespace App\Console\Commands;

use App\Jobs\RuntimeProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Connectors\RabbitMQConnector;

class RuntimeSmoke extends Command
{
    protected $signature = 'app:runtime-smoke';

    protected $description = 'Verify RabbitMQ publishing, release, recovery and acknowledgement without SMTP';

    public function handle(): int
    {
        $marker = (string) Str::uuid();
        $name = 'emailing.smoke.'.$marker;
        $queue = Queue::connection('rabbitmq');
        $queue->push(new RuntimeProbe($marker), '', $name);
        $job = $queue->pop($name);
        if (! $job) {
            throw new \RuntimeException('Published probe missing.');
        }
        $job->release(0);
        $queue->close();
        $connector = new RabbitMQConnector(app('events'));
        $queue = $connector->connect(config('queue.connections.rabbitmq'));
        $queue->setContainer(app());
        $queue->setConnectionName('rabbitmq');
        $job = $queue->pop($name);
        if (! $job) {
            throw new \RuntimeException('Released probe missing after reconnect.');
        }
        $job->fire();
        $path = storage_path('app/private/probe-'.$marker);
        if (! is_file($path) || file_get_contents($path) !== 'completed' || $queue->size($name) !== 0) {
            throw new \RuntimeException('Probe execution or acknowledgement failed.');
        }
        unlink($path);
        $queue->getChannel()->queue_delete($name);
        $this->info('RabbitMQ publish/release/reconnect/consume/ack: PASS (application identity).');

        return self::SUCCESS;
    }
}
