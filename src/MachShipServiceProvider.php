<?php

namespace WebdevsComAu\MachShip;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use WebdevsComAu\MachShip\Console\PingCommand;

class MachShipServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/machship.php', 'machship');

        $this->app->singleton(Client::class, function (Application $app) {
            $config = $app['config']['machship'];

            return new Client(
                $app->make(HttpFactory::class),
                $config['token'] ?? null,
                $config['base_url'] ?? 'https://live.machship.com',
                [
                    'timeout' => $config['timeout'] ?? 30,
                    'connect_timeout' => $config['connect_timeout'] ?? 10,
                    'retry' => $config['retry'] ?? [],
                ],
            );
        });

        $this->app->singleton(MachShip::class, function (Application $app) {
            $config = $app['config']['machship'];
            $companyId = $config['company_id'] ?? null;

            return new MachShip(
                $app->make(Client::class),
                is_numeric($companyId) ? (int) $companyId : null,
                (bool) ($config['labels']['print_a4'] ?? false),
            );
        });

        $this->app->alias(MachShip::class, 'machship');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/machship.php' => config_path('machship.php'),
            ], 'machship-config');

            $this->commands([PingCommand::class]);
        }
    }
}
