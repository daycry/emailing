<?php

namespace App\Services;

use App\Models\DeliveryOutbox;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class ConfirmedDeliveryPublisher
{
    public function __construct(private PublicationObserver $observer) {}

    public function publish(DeliveryOutbox $event): PublicationResult
    {
        $connection = null;
        try {
            if (DBTransactionGuard::active()) {
                throw new \LogicException('Publication requires committed SQL work.');
            }
            if (config('delivery.queue') === config('queue.connections.rabbitmq.queue') || in_array(config('delivery.failure_queue'), [config('delivery.queue'), config('queue.connections.rabbitmq.queue')], true)) {
                throw new \LogicException('Domain and Laravel queues must differ.');
            }
            if (in_array((string) config('delivery.failure_routing_key'), [(string) config('delivery.routing_key'), (string) config('delivery.binding_key')], true)) {
                return new PublicationResult(false, 'routing_key_collision');
            }
            if (! extension_loaded('sockets')) {
                // php-amqplib otherwise emits a warning while handling a failed
                // socket; developer error handlers can expose constructor args.
                return new PublicationResult(false, 'runtime_sockets_missing');
            }
            $host = config('queue.connections.rabbitmq.hosts.0');
            $timeout = max(1, min(10, (int) config('delivery.confirm_timeout')));
            $connection = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost'], false, 'AMQPLAIN', null, 'en_US', $timeout, $timeout + 1, null, false, 0, $timeout);
            $channel = $connection->channel();
            $channel->exchange_declare(config('delivery.exchange'), 'direct', false, true, false);
            $channel->queue_declare(config('delivery.queue'), false, true, false, false);
            $channel->queue_bind(config('delivery.queue'), config('delivery.exchange'), config('delivery.binding_key'));
            if ($event->type === 'delivery.failed') {
                $channel->queue_declare(config('delivery.failure_queue'), false, true, false, false);
                $channel->queue_bind(config('delivery.failure_queue'), config('delivery.exchange'), config('delivery.failure_routing_key'));
            }
            $channel->confirm_select();
            $this->observer->observe('confirm_selected', $event);
            $acked = $nacked = $returned = false;
            $channel->set_ack_handler(function () use (&$acked, $event) {
                $acked = true;
                $this->observer->observe('ack', $event);
            });
            $channel->set_nack_handler(function () use (&$nacked, $event) {
                $nacked = true;
                $this->observer->observe('nack', $event);
            });
            $channel->set_return_listener(function ($code, $text, $exchange, $key, AMQPMessage $message) use (&$returned, $event) {
                $returned = true;
                $this->observer->observe('return', $event, ['reply_code' => $code]);
            });
            $this->observer->observe('before_publish', $event);
            $message = new AMQPMessage(json_encode($event->payload(), JSON_THROW_ON_ERROR), ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, 'message_id' => $event->id, 'type' => $event->type]);
            $channel->basic_publish($message, config('delivery.exchange'), $event->type === 'delivery.failed' ? config('delivery.failure_routing_key') : config('delivery.routing_key'), true);
            $this->observer->observe('published_bytes', $event);
            $deadline = microtime(true) + $timeout;
            while (! $acked && ! $nacked) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    return new PublicationResult(false, 'publish_uncertain');
                }
                $channel->wait(null, false, $remaining);
            }
            if ($returned) {
                return new PublicationResult(false, 'unroutable');
            }
            if ($nacked) {
                return new PublicationResult(false, 'broker_nack');
            }
            if (! $acked) {
                return new PublicationResult(false, 'publish_uncertain');
            }
            $this->observer->observe('confirmed', $event);

            return new PublicationResult(true);
        } catch (\LogicException $error) {
            throw $error;
        } catch (Throwable) {
            // Exceptions can include credentials or wire data. Persist only a fixed code.
            return new PublicationResult(false, 'publish_uncertain');
        } finally {
            try {
                $connection?->close();
            } catch (Throwable) {
            }
        }
    }
}
