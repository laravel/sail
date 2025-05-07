<?php

return [
    'domain' => env('SAIL_DOMAIN', 'localhost'),
    'build' => [
        'environments' => env('SAIL_BUILD_ENVIRONMENT', 'production'),
        'architectures' => env('SAIL_BUILD_ARCHITECTURES', 'linux/amd64,linux/arm64'),
        'repository' => env('SAIL_BUILD_REPOSITORY', 'sail'),
        'push' => env('SAIL_BUILD_PUSH', false),
        'organization' => env('SAIL_BUILD_ORGANIZATION', 'reyemtech'),
        'version' => env('SAIL_BUILD_VERSION', "1.0.0"),
    ],
    'deploy' => [
        'domains' => env('SAIL_DEPLOY_DOMAINS', '.reyemtech.com'),
    ],
];