<?php

if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    return;
}

require __DIR__ . '/../../vendor/autoload.php';

// Optionally preload common Laravel files
foreach (
    [
        '/app/Providers/AppServiceProvider.php',
        '/routes/web.php',
        '/routes/api.php',
    ] as $path
) {
    $file = base_path($path);
    if (file_exists($file)) {
        require_once $file;
    }
}

// Preload your application files
foreach (glob(__DIR__ . '/../app/**/*.php') as $file) {
    require_once $file;
}
