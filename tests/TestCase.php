<?php

namespace WebdevsComAu\MachShip\Tests;

use WebdevsComAu\MachShip\MachShipServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MachShipServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['MachShip' => \WebdevsComAu\MachShip\Facades\MachShip::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('machship.token', 'test-token');
        $app['config']->set('machship.retry.sleep_ms', 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/Fixtures/{$name}.json"), true, flags: JSON_THROW_ON_ERROR);
    }
}
