<?php

return [
    'api' => [
        'title' => 'Supermarket API',
        'version' => '1.0.0',
        'description' => 'API de gerenciamento de supermercado - MVP',
    ],

    'routes' => [
        'api' => 'api/documentation',
    ],

    'paths' => [
        'docs' => storage_path('api-docs'),
        'annotations' => base_path('app'),
        'views' => base_path('resources/views/vendor/l5-swagger'),
    ],

    'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false),

    'proxy' => false,

    'additional_config_url' => null,

    'operations_sort' => null,

    'security' => [],

    'swagger_version' => '3.0',

    'defaults' => [
        'swagger' => '3.0',
        'info' => [
            'title' => 'Supermarket API',
            'version' => '1.0.0',
        ],
        'basePath' => '/api',
        'schemes' => ['http', 'https'],
        'consumes' => ['application/json'],
        'produces' => ['application/json'],
    ],
];
