<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\UserRegistrationService;
use App\Service\RecaptchaValidator;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/register')]
/**
 * Endpoint JSON pour l'inscription côté front SPA / mobile.
 */
class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly UserRegistrationService $registrationService,
        private readonly RateLimiterFactory $registrationLimiter,
        private readonly RecaptchaValidator $recaptchaValidator,
    ) {
    }

    /**
     * Crée un compte et renvoie un JWT utilisable immédiatement.
     */
    #[Route('', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        summary: 'Inscription d’un client',
        description: 'Crée un compte client et renvoie directement un JWT utilisable.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'firstname', 'lastname'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'client@test.fr'),
                    new OA\Property(property: 'password', type: 'string', example: 'P@ssword123'),
                    new OA\Property(property: 'firstname', type: 'string', example: 'Alex'),
                    new OA\Property(property: 'lastname', type: 'string', example: 'Martin'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé + JWT retourné',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(
                            property: 'user',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'email', type: 'string', format: 'email'),
                                new OA\Property(property: 'firstname', type: 'string'),
                                new OA\Property(property: 'lastname', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 409, description: 'Email déjà utilisé'),
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        // Rate limiting: max 5 inscriptions par IP par jour
        $limiter = $this->registrationLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return $this->json(
                ['error' => 'Trop de tentatives d\'inscription. Veuillez réessayer demain.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        $requestData = json_decode($request->getContent() ?: '[]', true) ?? [];
        $recaptchaToken = $requestData['recaptchaToken'] ?? null;

        // reCAPTCHA v3 optionnel (mais recommandé en prod)
        if ($recaptchaToken && !$this->recaptchaValidator->isValid($recaptchaToken)) {
            return $this->json(
                ['error' => 'Validation reCAPTCHA échouée. Vous êtes peut-être un bot.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $result = $this->registrationService->register($requestData);

        if (Response::HTTP_CREATED !== $result['status']) {
            return $this->json(
                $result['errors'] ?? ['error' => 'Requête invalide'],
                $result['status']
            );
        }

        return $this->json($result['data'], Response::HTTP_CREATED);
    }
}
