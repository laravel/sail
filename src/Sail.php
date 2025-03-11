<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static self setBaseTemplate(string $stub)
 * @method static self addService(string $service, ?string $stubPath = null, ?bool $persistent = null, ?bool $default = null, ?bool $dependable = null, ?Closure $configuringEnv = null, ?Closure $afterInstall = null)
 * @method static self registerPublishHook(Closure $closure)
 * @method static string getBaseTemplate()
 * @method static array availableServices(bool $default = false)
 * @method static string stub(string $service)
 * @method static bool isPersistent(string $service)
 * @method static bool isDependedOn(string $service)
 * @method static void replaceEnvVariables(string $environment, array $services)
 * @method static void runInstallHooks(mixed $command, array $services)
 * @method static void runPublishingHooks(mixed $command)
 */
class Sail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return Services::class;
    }
}
