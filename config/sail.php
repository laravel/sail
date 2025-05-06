<?php

return [
    'domain' => env('SAIL_DOMAIN', 'localhost'),
    'build' => [
        'environments' => env('SAIL_BUILD_ENVIRONMENT'),
        'architectures' => env('SAIL_BUILD_ARCHITECTURES'),
        'repository' => env('SAIL_BUILD_REPOSITORY'),
        'push' => env('SAIL_BUILD_PUSH'),
        'organization' => env('SAIL_BUILD_ORGANIZATION'),
        'version' => env('SAIL_BUILD_VERSION'),
    ],
];