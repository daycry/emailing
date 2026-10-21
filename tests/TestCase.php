<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): \Illuminate\Foundation\Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'mysql' || $app['config']->get('database.connections.mysql.database') !== 'emailing_testing' || $app['config']->get('database.connections.mysql.username') !== 'emailing_testing') {
            throw new \RuntimeException('Tests require exclusive MySQL database/user emailing_testing; refusing unsafe database.');
        }
        return $app;
    }
}
