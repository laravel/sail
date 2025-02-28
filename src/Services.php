<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Console\Command;

class Services
{
    protected Collection $stubs;

    /**
     * The custom services registered with their stubs, persistence, and hooks.
     *
     * @var array<string, array{path: string, persistent: bool, after: ?Closure}>
     */
    protected array $stubs = [];

    /**
     * Register a new service with its Docker Compose stub.
     *
     * @param string $service
     * @param string $stubPath
     * @param bool $persistent
     * @param Closure|null $after
     * @return self
     */
    public function addService(string $service, string $stubPath, bool $persistent = false, ?Closure $after = null): self
    {
        $this->stubs[$service] = [
            'path' => $stubPath,
            'persistent' => $persistent,
            'after' => $after,
        ];

        return $this;
    }

    /**
     * Get all available services, including defaults.
     *
     * @param array $defaultServices
     * @return array
     */
    public function availableServices(array $defaultServices = []): array
    {
        return array_unique(array_merge(array_keys($this->stubs), $defaultServices));
    }

    /**
     * Get the stub path for a given service.
     *
     * @param string $service
     * @return string|null
     */
    public function stub(string $service): ?string
    {
        return $this->stubs[$service]['path'];
    }

    /**
     * Check if a service requires a persistent volume.
     *
     * @param string $service
     * @return bool
     */
    public function isPersistent(string $service): bool
    {
        return $this->stubs[$service]['persistent'] ?? false;
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
            if (isset($this->stubs[$service]) && $this->stubs[$service]['after'] !== null) {
                $this->stubs[$service]['after']($command, [$service]);
            }
        }
    }
}
