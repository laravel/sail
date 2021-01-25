<?php

namespace Laravel\Sail;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;

class SailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->configurePublishing();
        }
    }

    /**
     * Register the console commands for the package.
     *
     * @return void
     */
    protected function registerCommands()
    {
        Artisan::command('sail:install', function () {
            $environment = file_get_contents(base_path('.env'));

            if(str_contains($environment, 'DB_CONNECTION=pgsql')){
                copy(__DIR__.'/../stubs/docker-compose-pgsql.yml', base_path('docker-compose.yml'));

                $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=pgsql', $environment);
            }else {
                copy(__DIR__.'/../stubs/docker-compose-mysql.yml', base_path('docker-compose.yml'));

                $environment = str_replace('DB_HOST=127.0.0.1', 'DB_HOST=mysql', $environment);
            }

            $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
            $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);

            file_put_contents(base_path('.env'), $environment);
        })->purpose('Install Laravel Sail\'s default Docker Compose file');

        Artisan::command('sail:publish', function () {
            $this->call('vendor:publish', ['--tag' => 'sail']);

            file_put_contents(base_path('docker-compose.yml'), str_replace(
                './vendor/laravel/sail/runtimes/8.0',
                './docker/8.0',
                file_get_contents(base_path('docker-compose.yml'))
            ));
        })->purpose('Publish the Laravel Sail Docker files');
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        $this->publishes([
            __DIR__.'/../runtimes' => base_path('docker'),
        ], 'sail');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'sail.install-command',
            'sail.publish-command',
        ];
    }
}
