<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\BrokerLab\ProtocolEvent;

require_once __DIR__.'/../../scripts/broker_lab/protocol_event.php';

#[Group('broker-isolation')]
final class BrokerProtocolTest extends TestCase
{
    private string $run;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $hex = bin2hex(random_bytes(16));
        $this->run = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-8'.substr($hex, 17, 3).'-'.substr($hex, 20);
        $this->directory = dirname(__DIR__, 2).'/storage/broker-lab-'.$this->run.'/b';
        $this->assertDirectoryDoesNotExist(dirname($this->directory));
        $this->assertTrue(mkdir($this->directory, 0700, true));
    }

    protected function tearDown(): void
    {
        // Remove only exact files this test can create inside its fresh UUID directory.
        $expected = realpath(dirname(__DIR__, 2).'/storage').DIRECTORY_SEPARATOR.'broker-lab-'.$this->run.DIRECTORY_SEPARATOR.'b';
        if (realpath($this->directory) !== $expected) {
            throw new RuntimeException('Owned protocol test directory changed.');
        }
        foreach (['000001.json', '000001.json.lock'] as $name) {
            $path = $this->directory.'/'.$name;
            if (is_file($path) && ! is_link($path)) {
                unlink($path);
            }
        }
        rmdir($this->directory);
        rmdir(dirname($this->directory));
        parent::tearDown();
    }

    public function test_wire_size_and_identity_are_preserved(): void
    {
        $body = ProtocolEvent::encode($this->run, 1, 123456789);
        $this->assertSame(2048, strlen($body));
        $event = ProtocolEvent::decode($body, $this->run, 1);
        $this->assertSame($this->run, $event['run_id']);
        $this->assertSame(1, $event['sequence']);
        $this->assertSame(123456789, $event['published_ns']);
    }

    public function test_repeated_delivery_preserves_exactly_one_durable_effect(): void
    {
        $body = ProtocolEvent::encode($this->run, 1, 123);
        $this->assertTrue(ProtocolEvent::recordOnce($this->directory, $body, $this->run, 1));
        $path = $this->directory.'/000001.json';
        $original = file_get_contents($path);
        $this->assertFalse(ProtocolEvent::recordOnce($this->directory, $body, $this->run, 1));
        $this->assertSame($original, file_get_contents($path));
        $effect = json_decode($original, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(hash('sha256', $body), $effect['payload_sha256']);
        $this->assertSame($this->run, $effect['run_id']);
        $this->assertSame(1, $effect['sequence']);
        $this->assertCount(1, glob($this->directory.'/*.json'));
    }

    public function test_reused_sequence_with_different_content_is_refused_without_overwrite(): void
    {
        $this->assertTrue(ProtocolEvent::recordOnce($this->directory, ProtocolEvent::encode($this->run, 1, 123), $this->run, 1));
        $original = file_get_contents($this->directory.'/000001.json');
        try {
            ProtocolEvent::recordOnce($this->directory, ProtocolEvent::encode($this->run, 1, 124), $this->run, 1);
            $this->fail('Changed content must not be accepted as a duplicate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Protocol sequence content conflict.', $error->getMessage());
        }
        $this->assertSame($original, file_get_contents($this->directory.'/000001.json'));
    }

    public function test_foreign_run_is_rejected_before_persisting(): void
    {
        $body = ProtocolEvent::encode('00000000-0000-4000-8000-000000000001', 1, 123);
        $this->expectException(InvalidArgumentException::class);
        ProtocolEvent::recordOnce($this->directory, $body, $this->run, 1);
    }

    public function test_duplicate_json_keys_are_rejected(): void
    {
        $body = ProtocolEvent::encode($this->run, 1, 123);
        $this->expectException(InvalidArgumentException::class);
        ProtocolEvent::decode(str_replace('"schema":1,', '"schema":1,"schema":1,', $body), $this->run, 1);
    }

    public function test_effects_outside_owned_laboratory_directory_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProtocolEvent::recordOnce(dirname(__DIR__, 2).'/storage', ProtocolEvent::encode($this->run, 1, 123), $this->run, 1);
    }
}
