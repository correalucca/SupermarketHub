<?php

namespace App;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SupermarketHub API',
    description: 'API de gerenciamento de supermercado - MVP em Laravel 12.',
)]
#[OA\Server(
    url: '/api',
    description: 'Base URL da API',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'plainTextToken',
)]
class OpenApi
{
}
