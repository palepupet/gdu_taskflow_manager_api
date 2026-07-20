<?php

declare(strict_types=1);

namespace App\Controller\Auth;

use OpenApi\Attributes as OA;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Auth')]
class AuthController
{
    #[Route('/auth/login', name:'auth_login', methods: ['POST'])]
    #[OA\Post(
        path: '/auth/login',
        summary: 'Connexion',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'manager@taskflow.fr'),
                    new OA\Property(property: 'password', type: 'string', example: 'TaskFlowManager123'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'JWT retourné'),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
        ],
    )]
    public function login(): void
    {
    }
}
