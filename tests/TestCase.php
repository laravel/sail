<?php

namespace Laravel\Sail\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Sail\SailServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default config
        $app['config']->set('app.name', 'TestApp');
        $app['config']->set('sail.domain', 'localhost');
        $app['config']->set('sail.build.environments', 'local');
        $app['config']->set('sail.build.architectures', 'linux/amd64');
        $app['config']->set('sail.build.repository', 'none');
        $app['config']->set('sail.build.push', false);
        $app['config']->set('sail.build.organization', 'testorg');
        $app['config']->set('sail.build.version', '1.0.0');
        $app['config']->set('sail.deploy.domains', 'test.local');
        $app['config']->set('sail.secret.path', 'secret/test');
        $app['config']->set('sail.secret.store', 'vault-backend');
        $app['config']->set('mail.from.address', 'test@example.com');
    }
}
