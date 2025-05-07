<?php

namespace Laravel\Sail;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Application as LaravelApplication;
use Laravel\Sail\Console\AddCommand;
use Laravel\Sail\Console\BuildCommand;
use Laravel\Sail\Console\InstallCommand;
use Laravel\Sail\Console\PublishCommand;
use Reyemtech\Sail\Http\Middleware\ForceHttps;

class SailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->environment('production')) {
            $kernel = $this->app->make(Kernel::class);
            $kernel->pushMiddleware(ForceHttps::class);
        }

        $this->registerCommands();
        $this->configurePublishing();
        $this->setupConfig();
    }

    /**
     * Setup the config.
     *
     * @return void
     */
    private function setupConfig(): void
    {
        $source = realpath($raw = __DIR__ . '/../config/sail.php') ?: $raw;

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('sail.php')], 'config');
        }

        $this->mergeConfigFrom($source, 'sail');
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
                BuildCommand::class
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

            $this->publishes([
                __DIR__ . '/../bin/sail' => $this->app->basePath('sail'),
                __DIR__ . '/../bin/sail-setup' => $this->app->basePath('sail-setup'),
            ], ['sail', 'sail-bin']);
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
            BuildCommand::class,
        ];
    }
}
