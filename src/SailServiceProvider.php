<?php

namespace Laravel\Sail;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
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
            ], 'sail');

            $this->replaceDockerBuildContext();
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

    /**
     * Replace docker build context with exported docker files.
     *
     * @return void
     */
    public function replaceDockerBuildContext()
    {
        $dockerComposeFilePath = $this->app->basePath('docker-compose.yml');

        if (!file_exists($dockerComposeFilePath)) {
            return;
        }

        file_put_contents(
            $dockerComposeFilePath,
            str_replace(
                [
                    './vendor/laravel/sail/runtimes/8.0',
                    './vendor/laravel/sail/runtimes/7.4',
                ],
                [
                    './docker/8.0',
                    './docker/7.4',
                ],
                file_get_contents($dockerComposeFilePath)
            )
        );
    }
}
