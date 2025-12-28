<?php

declare(strict_types=1);

namespace App\Controller\Api;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Route factice utilisée par le firewall json_login.
 * L'exécution ne devrait jamais atteindre ce contrôleur : si c'est le cas,
 * c'est que la configuration de sécurité n'intercepte pas /api/login.
 */
class AuthController extends AbstractController
{
    #[OA\Post(
        summary: 'Connexion (JWT)',
        description: 'Envoie email + password, le firewall json_login renvoie un JWT utilisable via Bearer.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authentification réussie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'refresh_token', type: 'string', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Identifiants invalides'),
        ]
    )]
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): void
    {
        throw new \LogicException('Handled by Symfony security json_login firewall.');
    }
}
