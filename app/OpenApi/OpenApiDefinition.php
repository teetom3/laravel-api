<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'API REST de gestion de livres avec authentification Sanctum.',
    title: 'Laravel Books API'
)]
#[OA\Server(url: 'http://127.0.0.1:8000/api/v1', description: 'Serveur local')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    bearerFormat: 'Sanctum',
    scheme: 'bearer'
)]
#[OA\Tag(name: 'Auth', description: 'Inscription, connexion et déconnexion')]
#[OA\Tag(name: 'Books', description: 'Gestion des livres')]
class OpenApiDefinition
{
}
