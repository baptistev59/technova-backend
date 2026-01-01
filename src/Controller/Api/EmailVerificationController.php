<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/email')]
#[OA\Tag(name: 'Auth')]
final class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailVerificationService $emailVerificationService,
    ) {
    }

    #[Route('/verify/{token}', name: 'api_email_verify', methods: ['GET'])]
    #[OA\Get(
        summary: 'Confirmer un email',
        parameters: [
            new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Email confirmé'),
            new OA\Response(response: 400, description: 'Lien invalide ou expiré'),
        ]
    )]
    public function verify(string $token): JsonResponse
    {
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $token]);
        if (!$user) {
            return $this->json([
                'status' => 'invalid',
                'message' => 'Lien de confirmation invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($user->isEmailVerified()) {
            return $this->json([
                'status' => 'already_verified',
                'message' => 'Adresse email déjà confirmée.',
            ]);
        }

        $expiresAt = $user->getEmailVerificationExpiresAt();
        if ($expiresAt && $expiresAt < new \DateTimeImmutable()) {
            return $this->json([
                'status' => 'expired',
                'message' => 'Lien de confirmation expiré.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->clearEmailVerification();
        $this->entityManager->flush();

        return $this->json([
            'status' => 'verified',
            'message' => 'Adresse email confirmée.',
        ]);
    }

    #[Route('/verify/resend', name: 'api_email_verify_resend', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[OA\Post(
        summary: 'Renvoyer le lien de confirmation',
        responses: [
            new OA\Response(response: 200, description: 'Email envoyé'),
        ]
    )]
    public function resend(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isEmailVerified()) {
            return $this->json([
                'status' => 'already_verified',
                'message' => 'Adresse email déjà confirmée.',
            ]);
        }

        $token = $this->emailVerificationService->prepareVerification($user);
        $this->entityManager->flush();
        $this->emailVerificationService->sendVerification($user, $token);

        return $this->json([
            'status' => 'sent',
            'message' => 'Email de confirmation renvoyé.',
        ]);
    }
}
