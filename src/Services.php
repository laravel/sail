<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;

class Services
{
    /**
     * The services registered with their stubs, persistence, and hooks.
     *
     * @var array<int|string, string|array{stub: ?string, persistent: ?bool, default: ?bool, dependable: ?bool, configuring_env: ?Closure, afterInstall: ?Closure}>
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

    protected string $composeStub = __DIR__ . '../stubs/docker-compose.stub';

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
     * @param string $stubPath
     * @param bool $persistent
     * @param bool $default
     * @param bool $dependable
     * @param Closure|null $configuringEnv
     * @param Closure|null $afterInstall
     * @return self
     */
    public function addService(string   $service,
                               string   $stubPath,
                               bool     $persistent = false,
                               bool     $default = false,
                               bool     $dependable = true,
                               ?Closure $configuringEnv = null,
                               ?Closure $afterInstall = null): self
    {
        $this->services[$service] = [
            'stub' => $stubPath,
            'persistent' => $persistent,
            'default' => $default,
            'dependable' => $dependable,
            'configuring_env' => $configuringEnv,
            'after_install' => $afterInstall,
        ];

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
        return $this->services[$service]['dependable'] ?? true;
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
     * Execute hooks for the requested services.
     *
     * @param Command $command
     * @param array $services
     * @return void
     */
    public function runHooks(Command $command, array $services): void
    {
        foreach ($services as $service) {
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['after_install'] ?? null) !== null) {
                $this->services[$service]['after_install']($command, $services);
            }
        }
    }
}
