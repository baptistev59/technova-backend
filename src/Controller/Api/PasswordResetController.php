<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

#[Route('/api/password-reset')]
/**
 * API endpoints pour la réinitialisation de mot de passe (JSON).
 * Parallèle aux routes Twig : /connexion/mot-de-passe-oublie/*
 */
class PasswordResetController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?string $mailerFrom = null,
    ) {
    }

    #[Route('/request', name: 'api_password_reset_request', methods: ['POST'])]
    #[OA\Post(
        summary: 'Demande de réinitialisation de mot de passe',
        description: 'Envoie un lien de réinitialisation par email (valable 5 min).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@test.fr'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Email de réinitialisation envoyé (ou compte non trouvé)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'requested'),
                        new OA\Property(property: 'message', type: 'string', example: 'Si ce compte existe, un lien de réinitialisation a été envoyé.'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Email manquant ou invalide'),
        ]
    )]
    public function request(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $email = $data['email'] ?? null;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(
                ['error' => 'Email manquant ou invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        // Pour la sécurité, on retourne toujours un message générique
        // (évite l'enumération de comptes)
        if (!$user) {
            return $this->json([
                'status' => 'requested',
                'message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.',
            ]);
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json(
                ['error' => 'Impossible de générer le lien de réinitialisation.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $resetUrl = $this->generateUrl(
            'api_password_reset_reset',
            ['token' => $resetToken->getToken()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $fromAddress = $this->mailerFrom
            ? Address::create($this->mailerFrom)
            : new Address('no-reply@technova.local', 'TechNova');

        $email = new Email();
        $email
            ->from($fromAddress)
            ->to((string) $user->getEmail())
            ->subject('TechNova — Réinitialiser votre mot de passe')
            ->html(sprintf(
                '<p>Bonjour %s,</p><p>Cliquez sur ce lien pour réinitialiser votre mot de passe (valable 5 minutes) :</p><p><a href="%s">Réinitialiser mon mot de passe</a></p>',
                htmlspecialchars($user->getFirstname() ?? ''),
                htmlspecialchars($resetUrl)
            ))
            ->text(sprintf(
                "Bonjour %s,\n\nCliquez sur ce lien pour réinitialiser votre mot de passe (valable 5 minutes) :\n%s",
                $user->getFirstname() ?? '',
                $resetUrl
            ));

        $this->mailer->send($email);

        return $this->json([
            'status' => 'requested',
            'message' => 'Si ce compte existe, un lien de réinitialisation a été envoyé.',
        ]);
    }

    #[Route('/check/{token}', name: 'api_password_reset_check', methods: ['GET'])]
    #[OA\Get(
        summary: 'Vérifier la validité d\'un token de réinitialisation',
        description: 'Valide un token sans effectuer la réinitialisation.',
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token valide',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'valid'),
                        new OA\Property(property: 'message', type: 'string', example: 'Token valide.'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Token invalide ou expiré'),
        ]
    )]
    public function check(string $token): JsonResponse
    {
        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json(
                ['error' => 'Token invalide ou expiré.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        return $this->json([
            'status' => 'valid',
            'message' => 'Token valide.',
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/reset', name: 'api_password_reset_reset', methods: ['POST'])]
    #[OA\Post(
        summary: 'Réinitialiser le mot de passe avec token',
        description: 'Valide le token et définit un nouveau mot de passe.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'password'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'abc123...'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'NewP@ss123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mot de passe réinitialisé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'success'),
                        new OA\Property(property: 'message', type: 'string', example: 'Mot de passe réinitialisé avec succès.'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Token invalide ou mot de passe insuffisant'),
        ]
    )]
    public function reset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $token = $data['token'] ?? null;
        $newPassword = $data['password'] ?? null;

        if (!$token || !$newPassword) {
            return $this->json(
                ['error' => 'Token et mot de passe requis.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (strlen($newPassword) < 8) {
            return $this->json(
                ['error' => 'Le mot de passe doit contenir au moins 8 caractères.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            /** @var User $user */
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface $e) {
            return $this->json(
                ['error' => 'Token invalide ou expiré.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Hash et sauvegarde du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $this->resetPasswordHelper->removeResetRequest($token);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'success',
            'message' => 'Mot de passe réinitialisé avec succès.',
        ]);
    }
}
