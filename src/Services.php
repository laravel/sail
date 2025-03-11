<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;

class Services
{
    /**
     * The services registered with their stubs, persistence, and hooks.
     *
     * @var array<int|string, string|array{stub: ?string, persistent: ?bool, default: ?bool, dependency: ?bool, configuring_env: ?Closure, after_install: ?Closure}>
     */
    protected array $services = [
        'mysql' => [
            'persistent' => true,
            'default' => true,
        ],
        'pgsql' => [
            'persistent' => true,
        ],
        'mariadb' => [
            'persistent' => true,
        ],
        'mongodb' => [
            'persistent' => true,
        ],
        'redis' => [
            'persistent' => true,
            'default' => true,
        ],
        'valkey' => [
            'persistent' => true,
        ],
        'memcached',
        'meilisearch' => [
            'persistent' => true,
        ],
        'typesense' => [
            'persistent' => true,
        ],
        'minio' => [
            'persistent' => true,
        ],
        'mailpit' => [
            'default' => true,
        ],
        'selenium' => [
            'default' => true,
        ],
        'soketi',
    ];

    /**
     * Path to the base docker compose template
     *
     * @var string
     */
    protected string $composeStub = __DIR__ . '../stubs/docker-compose.stub';

    /**
     * Hooks to be run after all services are configured
     *
     * @var Closure[]
     */
    protected array $afterInstall = [];

    /**
     * Hooks to be run during the publish command
     *
     * @var Closure[]
     */
    protected array $afterPublish = [];

    public function __construct()
    {
        $uncommentDbVars = function (string $environment): string {
            $defaults = [
                '# DB_HOST=127.0.0.1',
                '# DB_PORT=3306',
                '# DB_DATABASE=laravel',
                '# DB_USERNAME=root',
                '# DB_PASSWORD=',
            ];
            foreach ($defaults as $default) {
                $environment = str_replace($default, substr($default, 2), $environment);
            }
            return $environment;
        };

        $this->services['mysql']['configuring_env'] = function (string $environment) use ($uncommentDbVars): string {
            $uncommentDbVars($environment);
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mysql', $environment);
            return str_replace('DB_HOST=127.0.0.1', "DB_HOST=mysql", $environment);
        };

        $this->services['pgsql']['configuring_env'] = function (string $environment) use ($uncommentDbVars): string {
            $uncommentDbVars($environment);
            $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=pgsql', $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=pgsql", $environment);
            return str_replace('DB_PORT=3306', "DB_PORT=5432", $environment);
        };

        $this->services['mariadb']['configuring_env'] = function (string $environment) use ($uncommentDbVars): string {
            $uncommentDbVars($environment);
            if (config()->has('database.connections.mariadb')) {
                $environment = preg_replace('/DB_CONNECTION=.*/', 'DB_CONNECTION=mariadb', $environment);
            }
            return str_replace('DB_HOST=127.0.0.1', "DB_HOST=mariadb", $environment);
        };

        $this->services['mongodb']['configuring_env'] = function (string $environment): string {
            $environment .= "\nMONGODB_URI=mongodb://mongodb:27017";
            $environment .= "\nMONGODB_DATABASE=laravel";
            return $environment;
        };

        $this->services['redis']['configuring_env'] = function (string $environment): string {
            return str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);
        };

        $this->services['valkey']['configuring_env'] = function (string $environment): string {
            return str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=valkey', $environment);
        };

        $this->services['memcached']['configuring_env'] = function (string $environment): string {
            return str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        };

        $this->services['meilisearch']['configuring_env'] = function (string $environment): string {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
            $environment .= "\nMEILISEARCH_NO_ANALYTICS=false\n";
            return $environment;
        };

        $this->services['typesense']['configuring_env'] = function (string $environment): string {
            $environment .= "\nSCOUT_DRIVER=typesense";
            $environment .= "\nTYPESENSE_HOST=typesense";
            $environment .= "\nTYPESENSE_PORT=8108";
            $environment .= "\nTYPESENSE_PROTOCOL=http";
            $environment .= "\nTYPESENSE_API_KEY=xyz\n";
            return $environment;
        };

        $this->services['mailpit']['configuring_env'] = function (string $environment): string {
            $environment = preg_replace("/^MAIL_MAILER=(.*)/m", "MAIL_MAILER=smtp", $environment);
            $environment = preg_replace("/^MAIL_HOST=(.*)/m", "MAIL_HOST=mailpit", $environment);
            return preg_replace("/^MAIL_PORT=(.*)/m", "MAIL_PORT=1025", $environment);
        };

        $this->services['soketi']['configuring_env'] = function (string $environment): string {
            $environment = preg_replace("/^BROADCAST_DRIVER=(.*)/m", "BROADCAST_DRIVER=pusher", $environment);
            $environment = preg_replace("/^PUSHER_APP_ID=(.*)/m", "PUSHER_APP_ID=app-id", $environment);
            $environment = preg_replace("/^PUSHER_APP_KEY=(.*)/m", "PUSHER_APP_KEY=app-key", $environment);
            $environment = preg_replace("/^PUSHER_APP_SECRET=(.*)/m", "PUSHER_APP_SECRET=app-secret", $environment);
            $environment = preg_replace("/^PUSHER_HOST=(.*)/m", "PUSHER_HOST=soketi", $environment);
            $environment = preg_replace("/^PUSHER_PORT=(.*)/m", "PUSHER_PORT=6001", $environment);
            $environment = preg_replace("/^PUSHER_SCHEME=(.*)/m", "PUSHER_SCHEME=http", $environment);
            return preg_replace("/^VITE_PUSHER_HOST=(.*)/m", "VITE_PUSHER_HOST=localhost", $environment);
        };

        $this->afterPublish[] = function (Command $command) {
            file_put_contents(
                app()->basePath('docker-compose.yml'),
                str_replace(
                    [
                        './vendor/laravel/sail/runtimes/8.4',
                        './vendor/laravel/sail/runtimes/8.3',
                        './vendor/laravel/sail/runtimes/8.2',
                        './vendor/laravel/sail/runtimes/8.1',
                        './vendor/laravel/sail/runtimes/8.0',
                        './vendor/laravel/sail/database/mysql',
                        './vendor/laravel/sail/database/pgsql'
                    ],
                    [
                        './docker/8.4',
                        './docker/8.3',
                        './docker/8.2',
                        './docker/8.1',
                        './docker/8.0',
                        './docker/mysql',
                        './docker/pgsql'
                    ],
                    file_get_contents(app()->basePath('docker-compose.yml'))
                )
            );
        };
    }

    /**
     * Set the base Docker Compose template containing a php service named as 'APP_SERVICE'
     *
     * @param string $stub Path to the base Docker Compose stub
     * @return $this
     */
    public function setBaseTemplate(string $stub): self
    {
        $this->composeStub = $stub;

        return $this;
    }

    /**
     * Get the path to the base Docker Compose template
     *
     * @return string
     */
    public function getBaseTemplate(): string
    {
        return $this->composeStub;
    }

    /**
     * Register a new service with its Docker Compose stub.
     *
     * @param string $service
     * @param string|null $stubPath
     * @param bool|null $persistent
     * @param bool|null $default
     * @param bool|null $dependency
     * @param Closure|null $configuringEnv
     * @param Closure|null $afterInstall
     * @return self
     */
    public function addService(string   $service,
                               ?string   $stubPath = null,
                               ?bool     $persistent = null,
                               ?bool     $default = null,
                               ?bool     $dependency = null,
                               ?Closure $configuringEnv = null,
                               ?Closure $afterInstall = null): self
    {
        $this->services[$service] = [
            'stub' => $stubPath ?? $this->services[$service]['stub'] ?? null,
            'persistent' => $persistent ?? $this->services[$service]['persistent'] ?? null,
            'default' => $default ?? $this->services[$service]['default'] ?? null,
            'dependency' => $dependency ?? $this->services[$service]['dependency'] ?? null,
            'configuring_env' => $configuringEnv ?? $this->services[$service]['configuring_env'] ?? null,
            'after_install' => $afterInstall ?? $this->services[$service]['after_install'] ?? null,
        ];

        return $this;
    }

    /**
     * Add a hook to the pipeline executed during the installation command.
     *
     * @param Closure $closure
     * @return $this
     */
    public function registerInstallHook(Closure $closure): self
    {
        $this->afterInstall[] = $closure;

        return $this;
    }

    /**
     * Add a hook to the pipeline executed during the publish command.
     *
     * @param Closure $closure
     * @return $this
     */
    public function registerPublishHook(Closure $closure): self
    {
        $this->afterPublish[] = $closure;

        return $this;
    }

    /**
     * Get all available services, including defaults.
     *
     * @param bool $default If true, returns only default services
     * @return array
     */
    public function availableServices(bool $default = false): array
    {
        $services = [];
        foreach ($this->services as $key => $value) {
            $services[] = is_string($value) ? $value : $key;
        }

        if ($default) {
            $defaults = [];
            foreach ($this->services as $key => $value) {
                if (is_array($value) && ($value['default'] ?? false)) {
                    $defaults[] = $key;
                }
            }
            return $defaults;
        }

        return $services;
    }

    /**
     * Get the stub path for a given service.
     *
     * @param string $service
     * @return string
     */
    public function stub(string $service): string
    {
        return $this->services[$service]['stub'] ?? __DIR__ . '/../stubs/'.$service.'.stub';
    }

    /**
     * Check if a service requires a persistent volume.
     *
     * @param string $service
     * @return bool
     */
    public function isPersistent(string $service): bool
    {
        return $this->services[$service]['persistent'] ?? false;
    }

    /**
     * Check if a service is required by APP_SERVICE
     *
     * @param string $service
     * @return bool
     */
    public function isDependedOn(string $service): bool
    {
        return $this->services[$service]['dependency'] ?? true;
    }

    /**
     * Execute environment variable hooks for the requested services.
     *
     * @param string $environment
     * @param array $services
     * @return string
     */
    public function replaceEnvVariables(string $environment, array $services): string
    {
        foreach ($services as $service) {
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['configuring_env'] ?? null) !== null) {
                $environment = $this->services[$service]['configuring_env']($environment);
            }
        }

        return $environment;
    }

    /**
     * Execute hooks set for the installation command.
     *
     * @param Command $command
     * @param array $services
     * @param string $appService
     * @return void
     */
    public function runInstallHooks(Command $command, array $services, string $appService = 'laravel.test'): void
    {
        foreach ($services as $service) {
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['after_install'] ?? null) !== null) {
                $this->services[$service]['after_install']($command, $services, $appService);
            }
        }
        foreach ($this->afterInstall as $hook) {
            $hook($command, $services, $appService);
        }
    }

    /**
     * Execute hooks set for the publish command
     *
     * @param Command $command
     * @return void
     */
    public function runPublishHooks(Command $command): void
    {
        foreach ($this->afterPublish as $hook) {
            $hook($command);
        }
    }
}
