<?php

namespace Laravel\Sail;

use Closure;
use Illuminate\Support\Facades\Facade;

/**
 * @method static self addService(string $service, string $stubPath, bool $persistent = false, ?Closure $afterInstall = null)
 * @method static array availableServices(bool $default = false)
 * @method static string|null stub(string $service)
 * @method static bool isPersistent(string $service)
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
