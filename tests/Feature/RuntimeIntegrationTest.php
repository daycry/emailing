<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

class RuntimeIntegrationTest extends TestCase
{
    public function test_mysql_is_exclusive_and_guarded(): void
    {
        $this->assertSame('emailing_testing', \DB::selectOne('SELECT DATABASE() AS name')->name);
        $this->assertSame('emailing_testing', config('database.connections.mysql.username'));
    }

    public function test_real_rabbitmq_job_survives_release_and_reconnect(): void
    {
        $this->assertSame(0, Artisan::call('app:runtime-smoke'));
        $this->assertStringContainsString('PASS', Artisan::output());
    }

    public function test_application_cannot_access_foreign_vhost(): void
    {
        $host = config('queue.connections.rabbitmq.hosts.0');
        $own = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], $host['vhost']);
        $own->close();
        try {
            $foreign = new AMQPStreamConnection($host['host'], $host['port'], $host['user'], $host['password'], 'emailing-lab');
            $foreign->close();
            $this->fail('Foreign vhost must deny access.');
        } catch (\PhpAmqpLib\Exception\AMQPConnectionClosedException $error) {
            $this->assertContains($error->getCode(), [403, 530]);
            $this->assertStringContainsString('access to vhost', strtolower($error->getMessage()));
        }
    }
}
