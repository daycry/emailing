<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

class DeliveryConsumer
{
    public function __construct(private DeliveryLifecycle $lifecycle, private AccountMailTransport $transport, private DeliveryObserver $observer) {}

    public function process(AMQPMessage $message): void
    {
        $payload = DeliveryEnvelope::decode($message->getBody());
        if ($payload === null || ! $message->has('content_type') || ! $message->has('message_id') || $message->get('content_type') !== 'application/json' || $message->get('message_id') !== $payload['event_id']) {
            $message->reject(false);

            return;
        }
        $claim = $this->lifecycle->claim($payload);
        if ($claim) {
            $this->observer->observe('reserved', $claim);
            $this->transport->sendDelivery($claim);
            if (! $this->lifecycle->readyToSettle($claim)) {
                throw new \RuntimeException('No durable delivery outcome to settle.');
            }
            $this->observer->observe('before_ack', $claim);
        }
        // Duplicate/old-generation work only settles against authoritative SQL.
        // A live claim is durable and the loop reconciles it if its owner dies.
        $message->ack();
        if ($claim) {
            $this->observer->observe('acked', $claim);
        }
    }

    public function run(bool $once = false, int $maxMessages = 0, ?callable $stop = null): int
    {
        if (in_array((string) config('delivery.failure_routing_key'), [(string) config('delivery.routing_key'), (string) config('delivery.binding_key')], true)) {
            throw new \LogicException('Ready and failure routing keys must differ.');
        }
        if (DBTransactionGuard::active() || config('delivery.queue') === config('queue.connections.rabbitmq.queue') || config('delivery.queue') === config('delivery.failure_queue')) {
            throw new \LogicException('Domain consumer requires committed SQL and separate queues.');
        }
        $host = config('queue.connections.rabbitmq.hosts.0');
        $connection = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost'], false, 'AMQPLAIN', null, 'en_US', 5, 10, null, false, 0);
        $count = 0;
        try {
            $channel = $connection->channel();
            $channel->exchange_declare(config('delivery.exchange'), 'direct', false, true, false);
            // Existing queue has no optional arguments. Failure routing is explicit.
            $channel->queue_declare(config('delivery.queue'), false, true, false, false);
            $channel->queue_bind(config('delivery.queue'), config('delivery.exchange'), config('delivery.binding_key'));
            $channel->basic_qos(0, max(1, min(50, (int) config('delivery.prefetch'))), false);
            if ($once) {
                $this->lifecycle->recover();
                $message = $channel->basic_get(config('delivery.queue'), false);
                if ($message) {
                    $this->process($message);
                    $count++;
                }

                return $count;
            }
            $tag = $channel->basic_consume(config('delivery.queue'), '', false, false, false, false, function (AMQPMessage $message) use (&$count) {
                $this->process($message);
                $count++;
            });
            while ($channel->is_consuming() && (! $stop || ! $stop()) && ($maxMessages < 1 || $count < $maxMessages)) {
                $this->lifecycle->recover();
                try {
                    $channel->wait(null, false, 1);
                } catch (AMQPTimeoutException) {
                }
            }
            $channel->basic_cancel($tag);
        } finally {
            try {
                $connection->close();
            } catch (\Throwable) {
            }
        }

        return $count;
    }
}
