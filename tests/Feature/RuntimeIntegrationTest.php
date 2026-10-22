<?php

namespace Tests\Feature;

use App\Jobs\RuntimeProbe;
use App\Support\TestingDatabaseGuard;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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

    public function test_worker_consumes_the_configured_default_queue_without_an_explicit_queue_argument(): void
    {
        $marker = (string) Str::uuid();
        $name = 'emailing.smoke.'.$marker;
        config(['queue.connections.rabbitmq.queue' => $name]);
        $queue = Queue::connection('rabbitmq');
        $path = storage_path('app/private/probe-'.$marker);

        try {
            $queue->push(new RuntimeProbe($marker));
            $this->assertSame(1, $queue->size($name));
            $this->assertSame(0, Artisan::call('queue:work', ['connection' => 'rabbitmq', '--once' => true, '--sleep' => 0, '--tries' => 1, '--timeout' => 10]));
            $this->assertFileExists($path);
            $this->assertSame('completed', file_get_contents($path));
            $this->assertSame(0, $queue->size($name));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
            $queue->getChannel()->queue_delete($name);
            $queue->close();
        }
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
