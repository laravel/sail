<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;

class Services
{
    /**
     * The services registered with their stubs, persistence, and hooks.
     *
     * @var array<int|string, string|array{stub: ?string, persistent: ?bool, default: ?bool, after: ?Closure}>
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
     * Register a new service with its Docker Compose stub.
     *
     * @param string $service
     * @param string $stubPath
     * @param bool $persistent
     * @param bool|null $default
     * @param Closure|null $after
     * @return self
     */
    public function addService(string $service,
                               string $stubPath,
                               bool $persistent = false,
                               ?bool $default = false,
                               ?Closure $after = null
    ): self {
        $this->services[$service] = [
            'stub' => $stubPath,
            'persistent' => $persistent,
            'default' => $default,
            'after' => $after,
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
     * Execute hooks for the requested services.
     *
     * @param mixed $command
     * @param array $services
     * @return void
     */
    public function runHooks(Command $command, array $services): void
    {
        foreach ($services as $service) {
            if (isset($this->services[$service]) && is_array($this->services[$service]) && ($this->services[$service]['after'] ?? null) !== null) {
                $this->services[$service]['after']($command, [$service]);
            }
        }
    }
}
