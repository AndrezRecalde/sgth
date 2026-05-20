<?php

return [
    'api_path' => 'api',

    'api_domain' => null,

    'export_path' => 'storage/app/openapi.yaml',

    'info' => [
        'version' => env('APP_VERSION', '1.5.0'),
        'description' => 'API del Sistema de Gestión
            de Talento Humano — GAD Provincial
            de Esmeraldas',
    ],

    'servers' => null,

    'middleware' => [
        'web',
        \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
    ],

    'ignored_routes' => [
        'telescope*',
        'pulse*',
        'horizon*',
        'sanctum*',
        'up',
    ],

    'extensions' => [],
];
