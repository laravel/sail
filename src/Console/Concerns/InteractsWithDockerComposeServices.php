<?php

namespace Laravel\Sail\Console\Concerns;

use Winter\LaravelConfigWriter\EnvFile;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

trait InteractsWithDockerComposeServices
{
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
        'mailpit',
        'selenium',
        'soketi',
    ];

    /**
     * The default services used when the user chooses non-interactive mode.
     *
     * @var string[]
     */
    protected $defaultServices = ['mysql', 'redis', 'selenium', 'mailpit'];

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
        $composePath = base_path('docker-compose.yml');

        $compose = file_exists($composePath)
            ? Yaml::parseFile($composePath)
            : Yaml::parse(file_get_contents(__DIR__ . '/../../../stubs/docker-compose.stub'));

        // Adds the new services as dependencies of the laravel.test service...
        if (! array_key_exists('laravel.test', $compose['services'])) {
            $this->warn('Couldn\'t find the laravel.test service. Make sure you add ['.implode(',', $services).'] to the depends_on config.');
        } else {
            $compose['services']['laravel.test']['depends_on'] = collect($compose['services']['laravel.test']['depends_on'] ?? [])
                ->merge($services)
                ->unique()
                ->values()
                ->all();
        }

        // Add the services to the docker-compose.yml...
        collect($services)
            ->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['services'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['services'][$service] = Yaml::parseFile(__DIR__ . "/../../../stubs/{$service}.stub")[$service];
            });

        // Merge volumes...
        collect($services)
            ->filter(function ($service) {
                return in_array($service, ['mysql', 'pgsql', 'mariadb', 'redis', 'meilisearch', 'minio']);
            })->filter(function ($service) use ($compose) {
                return ! array_key_exists($service, $compose['volumes'] ?? []);
            })->each(function ($service) use (&$compose) {
                $compose['volumes']["sail-{$service}"] = ['driver' => 'local'];
            });

        // If the list of volumes is empty, we can remove it...
        if (empty($compose['volumes'])) {
            unset($compose['volumes']);
        }

        // Replace Selenium with ARM base container on Apple Silicon...
        if (in_array('selenium', $services) && in_array(php_uname('m'), ['arm64', 'aarch64'])) {
            $compose['services']['selenium']['image'] = 'seleniarm/standalone-chromium';
        }

        file_put_contents($this->laravel->basePath('docker-compose.yml'), Yaml::dump($compose, Yaml::DUMP_OBJECT_AS_MAP));
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param  array  $services
     * @return void
     */
    protected function replaceEnvVariables(array $services)
    {
        $environment = EnvFile::open($this->laravel->basePath('.env'));

        if (in_array('pgsql', $services)) {
            $environment->set('DB_CONNECTION', 'pgsql');
            $environment->set('DB_HOST', 'pgsql');
            $environment->set('DB_PORT', 5432);
        } elseif (in_array('mariadb', $services)) {
            $environment->set('DB_HOST', 'mariadb');
        } else {
            $environment->set('DB_HOST', 'mysql');
        }

        $environment->set('DB_USERNAME', 'sail');
        $environment->set('DB_PASSWORD', 'password');

        if (in_array('memcached', $services)) {
            $environment->set('MEMCACHED_HOST', 'memcached');
        }

        if (in_array('redis', $services)) {
            $environment->set('REDIS_HOST', 'redis');
        }

        if (in_array('meilisearch', $services)) {
            $environment->set('SCOUT_DRIVER', 'meilisearch');
            $environment->set('MEILISEARCH_HOST', 'http://meilisearch:7700');
        }

        if (in_array('soketi', $services)) {
            $environment->set('BROADCAST_DRIVER', 'pusher');
            $environment->set('PUSHER_APP_ID', 'app-id');
            $environment->set('PUSHER_APP_KEY', 'app-key');
            $environment->set('PUSHER_APP_SECRET', 'app-secret');
            $environment->set('PUSHER_HOST', 'soketi');
            $environment->set('PUSHER_PORT', '6001');
            $environment->set('PUSHER_SCHEME', 'http');
            $environment->set('VITE_PUSHER_HOST', 'localhost');
        }

        if (in_array('mailpit', $services)) {
            $environment->set('MAIL_HOST', 'mailpit');
        }

        $environment->write();
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
            file_get_contents(__DIR__.'/../../../stubs/devcontainer.stub')
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
     * Run the given commands.
     *
     * @param  array  $commands
     * @return int
     */
    protected function runCommands($commands)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (\RuntimeException $e) {
                $this->output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) {
            $this->output->write('    '.$line);
        });
    }
}
