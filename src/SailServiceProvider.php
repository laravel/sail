<?php

namespace Laravel\Sail;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Sail\Console\AddCommand;
use Laravel\Sail\Console\InstallCommand;
use Laravel\Sail\Console\PublishCommand;

class SailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
        $this->configurePublishing();
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                AddCommand::class,
                PublishCommand::class,
            ]);
        }
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../runtimes' => $this->app->basePath('docker'),
            ], ['sail', 'sail-docker']);

            if (str_starts_with(PHP_OS, 'WIN')) {
                $composerConfig = json_decode(file_get_contents(base_path('composer.json')), true);
                $binPath = $composerConfig['config']['bin-dir'] ?? 'vendor/bin';

                $this->publishes([
                    __DIR__ . '/../bin/sail.ps1' => $binPath . '/sail.ps1',
                ], ['laravel-assets']);

                $this->publishes([
                    __DIR__ . '/../bin/sail.ps1' => $this->app->basePath('sail.ps1'),
                ], ['sail', 'sail-bin']);
            } else {
                $this->publishes([
                    __DIR__ . '/../bin/sail' => $this->app->basePath('sail'),
                ], ['sail', 'sail-bin']);
            }

            $this->publishes([
                __DIR__ . '/../database' => $this->app->basePath('docker'),
            ], ['sail', 'sail-database']);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            InstallCommand::class,
            PublishCommand::class,
        ];
    }
}
