<?php

declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\BrokerLab\ProtocolEvent;

ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity): never {
    throw new ErrorException('Owned load publication failed.', 0, $severity);
});
$connection = null;
$status = 2;
$phase = 'descriptor';
$attempted = $confirmed = 0;
try {
    $raw = stream_get_contents(STDIN, 16385);
    if (strlen($raw) > 16384) {
        throw new InvalidArgumentException('Descriptor exceeds its bound.');
    }
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (! is_array($input) || ($input['execute'] ?? false) !== true) {
        echo json_encode(['mode' => 'dry-run', 'broker_used' => false, 'sql_used' => false], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    $keys = array_keys($input);
    sort($keys);
    if ($keys !== ['count', 'execute', 'password', 'port', 'rate', 'run_id']
        || ! is_int($input['count']) || $input['count'] < 1 || $input['count'] > 2000
        || ! is_int($input['rate']) || $input['rate'] < 1 || $input['rate'] > 25
        || ! is_string($input['password']) || ! preg_match('/\A[0-9a-f]{64}\z/D', $input['password'])
        || ! is_int($input['port']) || $input['port'] < 1 || $input['port'] > 65535
        || ! is_string($input['run_id']) || ! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $input['run_id'])) {
        throw new InvalidArgumentException('Invalid owned publisher descriptor.');
    }
    if (! extension_loaded('sockets')) {
        throw new RuntimeException('Publisher sockets extension unavailable.');
    }
    require dirname(__DIR__, 2).'/vendor/autoload.php';
    require __DIR__.'/protocol_event.php';
    $phase = 'broker';
    $topology = 'lab.t19.'.$input['run_id'].'.b';
    $connection = new AMQPStreamConnection('127.0.0.1', $input['port'], $topology, $input['password'], $topology, false, 'AMQPLAIN', null, 'en_US', 5, 10, null, false, 0);
    $channel = $connection->channel();
    $channel->confirm_select();
    $ack = $nack = $returned = false;
    $channel->set_ack_handler(static function () use (&$ack): void {
        $ack = true;
    });
    $channel->set_nack_handler(static function () use (&$nack): void {
        $nack = true;
    });
    $channel->set_return_listener(static function () use (&$returned): void {
        $returned = true;
    });
    $next = hrtime(true);
    for ($sequence = 1; $sequence <= $input['count']; $sequence++) {
        while (($wait = $next - hrtime(true)) > 0) {
            usleep(max(1, (int) ceil($wait / 1000)));
        }
        $at = hrtime(true);
        $body = ProtocolEvent::encode($input['run_id'], $sequence, $at);
        $ack = $nack = $returned = false;
        $message = new AMQPMessage($body, ['content_type' => 'application/json', 'delivery_mode' => 2,
            'message_id' => $input['run_id'].':'.$sequence]);
        $attempted++;
        echo json_encode(['phase' => 'attempt', 'sequence' => $sequence, 'published_ns' => $at], JSON_THROW_ON_ERROR)."\n";
        flush();
        $channel->basic_publish($message, $topology.'.events', 'work', true);
        $deadline = hrtime(true) + 5_000_000_000;
        while (! $ack && ! $nack && hrtime(true) < $deadline) {
            $channel->wait(null, false, max(0.001, ($deadline - hrtime(true)) / 1_000_000_000));
        }
        if (! $ack || $nack || $returned) {
            throw new RuntimeException('Owned publication not confirmed to its queue.');
        }
        $confirmed++;
        echo json_encode(['phase' => 'confirmed', 'sequence' => $sequence, 'published_ns' => $at,
            'confirmed_ns' => hrtime(true), 'bytes' => strlen($body)], JSON_THROW_ON_ERROR)."\n";
        flush();
        $next = $at + (int) ceil(1_000_000_000 / $input['rate']);
    }
    $channel->close();
    $connection->close();
    $connection = null;
    echo json_encode(['phase' => 'terminal', 'run_id' => $input['run_id'], 'publication_attempts' => $attempted,
        'confirmed' => $confirmed, 'unconfirmed_attempts' => $attempted - $confirmed,
        'maximum_inflight_confirms' => 1, 'outcome' => 'completed'], JSON_THROW_ON_ERROR)."\n";
    $status = 0;
} catch (Throwable $error) {
    echo json_encode(['outcome' => 'failed', 'phase' => $phase, 'exception_class' => get_class($error),
        'publication_attempts' => $attempted, 'confirmed' => $confirmed, 'unconfirmed_attempts' => $attempted - $confirmed], JSON_THROW_ON_ERROR)."\n";
} finally {
    if ($connection !== null) {
        try {
            $connection->close();
        } catch (Throwable) {
        }
    }
}
exit($status);
