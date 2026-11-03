<?php

declare(strict_types=1);

namespace Tests\BrokerLab;

use InvalidArgumentException;

/** Optional attribution for Docker children of the complete regression supervisor. */
final class DockerScope
{
    public static function annotate(array $command): array
    {
        $run = getenv('BROKER_LAB_REGRESSION_RUN_ID');
        if ($run === false || $run === '') {
            return $command;
        }
        if (! preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $run)
            || array_slice($command, 0, 2) !== ['docker', 'run']) {
            throw new InvalidArgumentException('Regression Docker attribution refused.');
        }
        array_splice($command, 2, 0, ['--label', 'io.emailing.broker-lab.regression='.$run]);

        return $command;
    }
}
