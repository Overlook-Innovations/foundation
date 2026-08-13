<?php

namespace Overlook\Foundation\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Overlook\Foundation\FoundationServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [FoundationServiceProvider::class];
    }

    /**
     * The widget is injected by middleware in the web group, which encrypts
     * cookies and so needs a key before any of it will run.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
