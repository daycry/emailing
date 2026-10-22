<?php

namespace Tests\Feature;

use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Tests\TestCase;

class RuntimeIntegrationTest extends TestCase
{
    public function test_guard_rejects_product_database_before_any_migration(): void
    {
        config(['database.connections.mysql.database' => 'emailing']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('refusing unsafe database');
        TestingDatabaseGuard::assertSafe();
    }

    public function test_guard_rejects_url_override_before_any_migration(): void
    {
        config(['database.connections.mysql.url' => 'mysql://example.test/emailing']);
        $this->expectException(\RuntimeException::class);
        TestingDatabaseGuard::assertSafe();
    }

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
        } catch (AMQPConnectionClosedException $error) {
            $this->assertContains($error->getCode(), [403, 530]);
            $this->assertStringContainsString('access to vhost', strtolower($error->getMessage()));
        }
    }
}
