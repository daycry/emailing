<?php

declare(strict_types=1);

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Tests\BrokerLab\ProtocolEvent;

ini_set('zend.exception_ignore_args', '1');
set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException('Laboratory worker operation failed.', 0, $severity);
});
$connection = null;
$worker = null;
$run = null;
$status = 2;
try {
    $raw = stream_get_contents(STDIN, 16385);
    if (strlen($raw) > 16384) {
        throw new InvalidArgumentException('Descriptor exceeds its bound.');
    }
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    $keys = is_array($input) ? array_keys($input) : [];
    sort($keys);
    if ($keys !== ['directory', 'maximum', 'password', 'port', 'run_id', 'seconds', 'worker']
        || ! is_string($input['worker']) || ! preg_match('/\Ab-[0-9]{1,2}\z/D', $input['worker'])
        || ! is_string($input['run_id']) || ! is_int($input['port']) || $input['port'] < 1 || $input['port'] > 65535
        || ! is_int($input['seconds']) || $input['seconds'] < 1 || $input['seconds'] > 1800
        || ! is_int($input['maximum']) || $input['maximum'] < 1 || $input['maximum'] > 10000
        || ! is_string($input['password']) || strlen($input['password']) < 32 || strlen($input['password']) > 256
        || ! is_string($input['directory'])) {
        throw new InvalidArgumentException('Invalid owned worker descriptor.');
    }
    $root = dirname(__DIR__, 2);
    require $root.'/vendor/autoload.php';
    require __DIR__.'/protocol_event.php';
    $worker = $input['worker'];
    $run = $input['run_id'];
    ProtocolEvent::decode(ProtocolEvent::encode($run, 1, 1), $run, $input['maximum']);
    $directory = realpath($input['directory']);
    $expected = realpath($root.'/storage/broker-lab-'.$run.'/b');
    if ($expected === false || $directory !== $expected) {
        throw new InvalidArgumentException('Worker directory is not the owned fixture.');
    }
    $topology = 'lab.t19.'.$run.'.b';
    $connection = new AMQPStreamConnection('127.0.0.1', $input['port'], $topology, $input['password'], $topology, false, 'AMQPLAIN', null, 'en_US', 5, 10, null, false, 0);
    $channel = $connection->channel();
    $channel->exchange_declare($topology.'.events', 'direct', false, true, false);
    $channel->queue_declare($topology.'.ready', false, true, false, false);
    $channel->queue_bind($topology.'.ready', $topology.'.events', 'work');
    $channel->basic_qos(0, 1, false);
    $counts = ['effects_created' => 0, 'duplicate_effects_prevented' => 0, 'ack_frames_sent' => 0, 'rejected_packets' => 0];
    $tag = $channel->basic_consume($topology.'.ready', 'lab.t19.'.$run.'.'.$worker, false, false, false, false,
        static function (AMQPMessage $message) use (&$counts, $directory, $input, $run, $worker): void {
            try {
                $event = ProtocolEvent::decode($message->getBody(), $run, $input['maximum']);
                if (! $message->has('content_type') || $message->get('content_type') !== 'application/json'
                    || ! $message->has('message_id') || $message->get('message_id') !== $run.':'.$event['sequence']) {
                    throw new InvalidArgumentException('Invalid protocol headers.');
                }
            } catch (InvalidArgumentException) {
                $message->reject(false);
                $counts['rejected_packets']++;

                return;
            }
            // Disk/conflict failures escape without ACK, so they cannot silently lose work.
            $created = ProtocolEvent::recordOnce($directory, $message->getBody(), $run, $input['maximum']);
            $counts[$created ? 'effects_created' : 'duplicate_effects_prevented']++;
            $trace = $directory.'/'.$worker.'-events.jsonl';
            clearstatcache(true, $trace);
            $line = json_encode(['worker' => $worker, 'sequence' => $event['sequence'], 'created' => $created,
                'published_ns' => $event['published_ns'], 'effect_recorded_ns' => hrtime(true)], JSON_THROW_ON_ERROR)."\n";
            if (is_link($trace) || (is_file($trace) && filesize($trace) > 8 * 1048576)
                || file_put_contents($trace, $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
                throw new RuntimeException('Owned protocol trace unavailable.');
            }
            $message->ack();
            $counts['ack_frames_sent']++;
        });
    echo json_encode(['phase' => 'consumer_ready', 'worker' => $worker, 'run_id' => $run, 'consumer_tag' => $tag, 'monotonic_ns' => hrtime(true)], JSON_THROW_ON_ERROR)."\n";
    flush();
    $deadline = hrtime(true) + $input['seconds'] * 1_000_000_000;
    while ($channel->is_consuming() && ! is_file($directory.'/stop') && hrtime(true) < $deadline) {
        try {
            $channel->wait(null, false, 1);
        } catch (AMQPTimeoutException) {
        }
    }
    $stopped = is_file($directory.'/stop');
    $channel->basic_cancel($tag);
    $channel->close();
    $connection->close();
    $connection = null;
    echo json_encode(['phase' => 'terminal', 'worker' => $worker, 'run_id' => $run, 'outcome' => $stopped ? 'stopped' : 'deadline'] + $counts, JSON_THROW_ON_ERROR)."\n";
    $status = $stopped ? 0 : 2;
} catch (Throwable $error) {
    // Neither broker errors, credentials, descriptors nor exception arguments leave this process.
    echo json_encode(['phase' => 'terminal', 'worker' => $worker, 'run_id' => $run, 'outcome' => 'failed', 'exception_class' => get_class($error)], JSON_THROW_ON_ERROR)."\n";
    $status = 2;
} finally {
    if ($connection !== null) {
        try {
            $connection->close();
        } catch (Throwable) {
        }
    }
}
exit($status);
