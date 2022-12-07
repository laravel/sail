<?php

namespace Laravel\Sail\Console;

use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\Process;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sail:install
                {--with= : The services that should be included in the installation}
                {--devcontainer : Create a .devcontainer configuration directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Laravel Sail\'s default Docker Compose file';

    /**
     * The available services that may be installed.
     *
     * @var array<string>
     */
    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'redis',
        'memcached',
        'meilisearch',
        'minio',
        'mailhog',
        'selenium',
        'caddy',
    ];

    /**
     * Execute the console command.
     *
     * @return int|null
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

        if ($invalidServices = array_diff($services, $this->services)) {
            $this->error('Invalid services ['.implode(',', $invalidServices).'].');

            return 1;
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configureCaddy($services);
        $this->configurePhpUnit();

        if ($this->option('devcontainer')) {
            $this->installDevContainer();
        }

        $this->info('Sail scaffolding installed successfully.');

        $this->configureRoutes($services);
        $this->prepareInstallation($services);
    }

    /**
     * Gather the desired Sail services using a Symfony menu.
     *
     * @return array
     */
    protected function gatherServicesWithSymfonyMenu()
    {
        return $this->choice('Which services would you like to install?', $this->services, 0, null, true);
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
                return !in_array($service, ['caddy']);  // laravel.test container does not depend on Caddy when enabled
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
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'meilisearch', 'minio', 'caddy']);
            })->map(function ($service) {
                return "    sail-{$service}:\n        driver: local";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('volumes:');
            })->implode("\n");

        $dockerCompose = file_get_contents(__DIR__ . '/../../stubs/docker-compose.stub');

        // Caddy requires a few changes to the default docker-compose.yml file...
        $dockerCompose = $this->modifyDockerComposeForCaddy($dockerCompose, $services);

        $dockerCompose = str_replace('{{depends}}', empty($depends) ? '' : '        '.$depends, $dockerCompose);
        $dockerCompose = str_replace('{{services}}', $stubs, $dockerCompose);
        $dockerCompose = str_replace('{{volumes}}', $volumes, $dockerCompose);

        // Replace Selenium with ARM base container on Apple Silicon...
        if (in_array('selenium', $services) && php_uname('m') === 'arm64') {
            $dockerCompose = str_replace('selenium/standalone-chrome', 'seleniarm/standalone-chromium', $dockerCompose);
        }

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

        if (in_array('pgsql', $services)) {
            $environment = str_replace('DB_CONNECTION=mysql', "DB_CONNECTION=pgsql", $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=pgsql", $environment);
            $environment = str_replace('DB_PORT=3306', "DB_PORT=5432", $environment);
        } elseif (in_array('mariadb', $services)) {
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mariadb", $environment);
        } else {
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mysql", $environment);
        }

        $environment = str_replace('DB_USERNAME=root', "DB_USERNAME=sail", $environment);
        $environment = preg_replace("/DB_PASSWORD=(.*)/", "DB_PASSWORD=password", $environment);

        $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);

        if (in_array('meilisearch', $services)) {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
        }

        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        if (in_array('caddy', $services)) {
            $environment = str_replace('APP_URL=http://', 'APP_URL=https://', $environment);

            $environment = (false === strpos($environment, 'APP_SERVICE='))
                ? preg_replace('/APP_URL=(.*)/', '\0'."\nAPP_SERVICE=".$domain, $environment)
                : preg_replace('/APP_SERVICE=(.*)/', 'APP_SERVICE='.$domain, $environment);
        }

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    /**
     * Modifies the default docker-compose.yml configuration when Caddy is used.
     *
     * @param  string  $dockerCompose
     * @param  array  $services
     * @return string
     */
    protected function modifyDockerComposeForCaddy(string $dockerCompose, array $services): string
    {
        if (!in_array('caddy', $services)) return $dockerCompose;

        // Port 80 will be exposed on the Caddy service instead of the default Laravel service.
        $dockerCompose = str_replace('- \'${APP_PORT:-80}:80\'', '# - \'${APP_PORT:-80}:80\'', $dockerCompose);

        // The default docker service name must match the Caddy host
        $domain = parse_url(config('app.url'), PHP_URL_HOST);
        $dockerCompose = str_replace('laravel.test', $domain, $dockerCompose);

        return $dockerCompose;
    }

    /**
     * Configure Caddy using the default Caddyfile.
     *
     * @param  array  $services
     * @return void
     */
    protected function configureCaddy(array $services)
    {
        if (in_array('caddy', $services)) {
            $path = $this->laravel->basePath('config/app.php');
            $appConfig = file_get_contents($path);

            $appConfig = str_replace('];', file_get_contents(__DIR__ . '/../../sslproxy/appconfig.stub'), $appConfig);

            file_put_contents($path, $appConfig);

            file_put_contents(
                $this->laravel->basePath('app/Http/Controllers/CaddyProxyController.php'),
                file_get_contents(__DIR__ . '/../../sslproxy/CaddyProxyController.php')
            );

            $domain = parse_url(config('app.url'), PHP_URL_HOST);

            file_put_contents(
                $this->laravel->basePath('Caddyfile'),
                str_replace('localhost', $domain, file_get_contents(__DIR__ . '/../../sslproxy/Caddyfile'))
            );
        }
    }

    /**
     * Configure PHPUnit to use the dedicated testing database.
     *
     * @return void
     */
    protected function configurePhpUnit()
    {
        if (! file_exists($path = $this->laravel->basePath('phpunit.xml'))) {
            $path = $this->laravel->basePath('phpunit.xml.dist');
        }

        $phpunit = file_get_contents($path);

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace('<!-- <env name="DB_DATABASE" value=":memory:"/> -->', '<env name="DB_DATABASE" value="testing"/>', $phpunit);

        file_put_contents($this->laravel->basePath('phpunit.xml'), $phpunit);
    }

    /**
     * Install the devcontainer.json configuration file.
     *
     * @return void
     */
    protected function installDevContainer()
    {
        if (! is_dir($this->laravel->basePath('.devcontainer'))) {
            mkdir($this->laravel->basePath('.devcontainer'), 0755, true);
        }

        file_put_contents(
            $this->laravel->basePath('.devcontainer/devcontainer.json'),
            file_get_contents(__DIR__.'/../../stubs/devcontainer.stub')
        );

        $environment = file_get_contents($this->laravel->basePath('.env'));

        $environment .= "\nWWWGROUP=1000";
        $environment .= "\nWWWUSER=1000\n";

        file_put_contents($this->laravel->basePath('.env'), $environment);
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     *
     * @param  array  $services
     * @return void
     */
    protected function prepareInstallation($services)
    {
        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1']) !== 0) {
            return;
        }

        if (count($services) > 0) {
            $status = $this->runCommands([
                './vendor/bin/sail pull '.implode(' ', $services),
            ]);

            if ($status === 0) {
                $this->info('Sail images installed successfully.');
            }
        }

        $status = $this->runCommands([
            './vendor/bin/sail build',
        ]);

        if ($status === 0) {
            $this->info('Sail build successful.');
        }
    }

    /**
     * Adds Caddy routes to web.php
     *
     * @param  array  $services
     * @return void
     */
    protected function configureRoutes($services)
    {
        if (in_array('caddy', $services)) {
            $routes = file_get_contents($this->laravel->basePath('routes/web.php'));

            $routes .= "\nRoute::get('/domain-verify', [App\Http\Controllers\CaddyProxyController::class, 'verifyDomain']);\n";

            file_put_contents($this->laravel->basePath('routes/web.php'), $routes);
        }
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @return int
     */
    protected function runCommands($commands): int
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });
    }
}
