<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\BrokerLab\DockerScope;

require_once __DIR__.'/../../scripts/broker_lab/docker_scope.php';

final class BrokerDockerScopeTest extends TestCase
{
    private string|false $previous;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previous = getenv('BROKER_LAB_REGRESSION_RUN_ID');
        putenv('BROKER_LAB_REGRESSION_RUN_ID');
    }

    protected function tearDown(): void
    {
        putenv($this->previous === false ? 'BROKER_LAB_REGRESSION_RUN_ID' : 'BROKER_LAB_REGRESSION_RUN_ID='.$this->previous);
        parent::tearDown();
    }

    public function test_normal_tests_keep_the_original_docker_arguments(): void
    {
        $command = ['docker', 'run', '--rm', '--name', 'synthetic'];
        $this->assertSame($command, DockerScope::annotate($command));
    }

    public function test_supervised_tests_add_one_run_label_without_changing_the_payload(): void
    {
        $run = '00000000-0000-4000-8000-000000000001';
        putenv('BROKER_LAB_REGRESSION_RUN_ID='.$run);
        $command = ['docker', 'run', '--rm', '--name', 'synthetic', 'image', 'php', 'artisan'];
        $this->assertSame(['docker', 'run', '--label', 'io.emailing.broker-lab.regression='.$run, '--rm', '--name', 'synthetic', 'image', 'php', 'artisan'], DockerScope::annotate($command));
    }

    public function test_invalid_run_identity_is_refused(): void
    {
        putenv('BROKER_LAB_REGRESSION_RUN_ID=invalid');
        $this->expectException(InvalidArgumentException::class);
        DockerScope::annotate(['docker', 'run', 'image']);
    }

    public function test_attribution_cannot_be_applied_to_another_command(): void
    {
        putenv('BROKER_LAB_REGRESSION_RUN_ID=00000000-0000-4000-8000-000000000001');
        $this->expectException(InvalidArgumentException::class);
        DockerScope::annotate(['docker', 'container', 'ls']);
    }
}
