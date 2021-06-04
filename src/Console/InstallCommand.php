<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:install {--with= : The services that should be included in the installation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Sail\'s default Docker Compose file';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->option('with')) {
            $services = $this->option('with') == 'none' ? [] : explode(',', $this->option('with'));
        } elseif ($this->option('no-interaction')) {
            $services = ['mysql', 'redis', 'selenium', 'mailhog'];
        } else {
            $services = $this->gatherServicesWithSymfonyMenu();
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);

        $this->info('Sail scaffolding installed successfully.');
    }

    /**
     * Gather the desired Sail services using a Symfony menu.
     *
     * @return array
     */
    protected function gatherServicesWithSymfonyMenu()
    {
        return $this->choice('Which services would you like to install?', [
             'mysql',
             'pgsql',
             'mariadb',
             'redis',
             'memcached',
             'meilisearch',
             'minio',
             'mailhog',
             'selenium',
         ], 0, null, true);
    }

    /**
     * Build the Docker Compose file.
     *
     * @param  array  $services
     * @return void
     */
    protected function buildDockerCompose(array $services)
    {
        $depends = collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'selenium']);
            })->map(function ($service) {
                return "            - {$service}";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('depends_on:');
            })->implode("\n");

        $stubs = rtrim(collect($services)->map(function ($service) {
            return file_get_contents(__DIR__ . "/../../stubs/{$service}.stub");
        })->implode(''));

        $volumes = collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'meilisearch', 'minio']);
            })->map(function ($service) {
                return "    sail{$service}:\n        driver: local";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('volumes:');
            })->implode("\n");

        $dockerCompose = file_get_contents(__DIR__ . '/../../stubs/docker-compose.stub');

        $dockerCompose = str_replace('{{depends}}', empty($depends) ? '' : '        '.$depends, $dockerCompose);
        $dockerCompose = str_replace('{{services}}', $stubs, $dockerCompose);
        $dockerCompose = str_replace('{{volumes}}', $volumes, $dockerCompose);

        // Remove empty lines...
        $dockerCompose = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $dockerCompose);

        file_put_contents($this->laravel->basePath('docker-compose.yml'), $dockerCompose);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param  array  $services
     * @return void
     */
    protected function replaceEnvVariables(array $services)
    {
        $environment = file_get_contents($this->laravel->basePath('.env'));

        foreach (static::sailEnvVariables($services) as $key => $value) {
            if (strstr($environment, $key.'=')) {
                $environment = preg_replace("/$key=(.*)/", "$key=$value", $environment);
            } else {
                $environment .= "\n$key=$value\n";
            }
        }

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    protected static function sailEnvVariables(array $services): array
    {
        $variables = [];
        $defaults = [
            'mailhog' => [
                'MAIL_HOST' => 'mailhog',
                'MAIL_PORT' => '1025',
            ],
            'mariadb' => [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => 'mariadb',
                'DB_PORT' => '3306',
                'DB_USERNAME' => 'sail',
                'DB_PASSWORD' => 'password',
            ],
            'meilisearch' => [
                'SCOUT_DRIVER' => 'meilisearch',
                'MEILISEARCH_HOST' => 'http://meilisearch:7700',
            ],
            'memcached' => [
                'MEMCACHED_HOST' => 'memcached',
            ],
            'minio' => [
                'FILESYSTEM_DRIVER' => 's3',
                'AWS_ACCESS_KEY_ID' => 'sail',
                'AWS_SECRET_ACCESS_KEY' => 'password',
                'AWS_DEFAULT_REGION' => 'us-east-1',
                'AWS_BUCKET' => 'local',
                'AWS_ENDPOINT' => 'http://minio:9000',
                'AWS_USE_PATH_STYLE_ENDPOINT' => 'true',
            ],
            'mysql' => [
                'DB_CONNECTION' => 'mysql',
                'DB_HOST' => 'mysql',
                'DB_PORT' => '3306',
                'DB_USERNAME' => 'sail',
                'DB_PASSWORD' => 'password',
            ],
            'pgsql' => [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => 'pgsql',
                'DB_PORT' => '5432',
                'DB_USERNAME' => 'sail',
                'DB_PASSWORD' => 'password',
            ],
            'redis' => [
                'REDIS_HOST' => 'redis',
            ],
            'selenium' => [

            ],
        ];

        foreach ($services as $service) {
            $variables += $defaults[$service] ?? [];
        }

        return $variables;
    }
}
