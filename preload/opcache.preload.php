<?php

if (! file_exists(__DIR__.'/../../vendor/autoload.php')) {
    return;
}

// Only preload the Composer autoloader
// This is safe because it doesn't depend on Laravel being bootstrapped
require __DIR__.'/../../vendor/autoload.php';

// NOTE: Do NOT preload application files (app/, routes/, etc.) as they depend on
// Laravel's service container, facades, and service providers which aren't
// available during the preload phase. This causes issues with packages like
// Spatie Permission that register policies, gates, and other services.
//
// OPcache will still cache these files when they're loaded normally during
// request handling, so you'll still get performance benefits without the
// bootstrapping conflicts.
