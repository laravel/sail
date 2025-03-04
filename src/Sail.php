<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static self setBaseTemplate(string $stub)
 * @method static string getBaseTemplate()
 * @method static self addService(string $service, string $stubPath, bool $persistent = false, bool $default = false, bool $dependable = true, ?Closure $configuringEnv = null, ?Closure $afterInstall = null)
 * @method static array availableServices(bool $default = false)
 * @method static string stub(string $service)
 * @method static bool isPersistent(string $service)
 * @method static bool isDependedOn(string $service)
 * @method static void replaceEnvVariables(string $environment, array $services): string
 * @method static void runHooks(mixed $command, array $services)
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
