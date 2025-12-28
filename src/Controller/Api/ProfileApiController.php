<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\UserAnonymizer;
use App\Service\UserProfileService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/profile')]
#[OA\Tag(name: 'Profile')]
/**
 * Endpoints REST autour du profil utilisateur connecté.
 */
class ProfileApiController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly UserProfileService $profileService,
        private readonly UserAnonymizer $userAnonymizer,
    ) {
    }

    /**
     * Retourne le profil du user authentifié.
     */
    #[Route('', name: 'api_profile_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Get(
        summary: 'Récupération du profil utilisateur',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Profil complet',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'firstname', type: 'string'),
                        new OA\Property(property: 'lastname', type: 'string'),
                        new OA\Property(property: 'phone', type: 'string', nullable: true),
                        new OA\Property(property: 'avatarPath', type: 'string', nullable: true),
                        new OA\Property(property: 'newsletterOptIn', type: 'boolean'),
                        new OA\Property(
                            property: 'address',
                            type: 'object',
                            nullable: true,
                            properties: [
                                new OA\Property(property: 'label', type: 'string', nullable: true),
                                new OA\Property(property: 'addressLine1', type: 'string', nullable: true),
                                new OA\Property(property: 'addressLine2', type: 'string', nullable: true),
                                new OA\Property(property: 'postalCode', type: 'string', nullable: true),
                                new OA\Property(property: 'city', type: 'string', nullable: true),
                                new OA\Property(property: 'state', type: 'string', nullable: true),
                                new OA\Property(property: 'country', type: 'string', nullable: true),
                                new OA\Property(property: 'isDefault', type: 'boolean'),
                                new OA\Property(property: 'isShipping', type: 'boolean'),
                                new OA\Property(property: 'isBilling', type: 'boolean'),
                            ]
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function show(): JsonResponse
    {
        $user = $this->getViewer();

        return $this->json($this->profileService->profileToArray($user));
    }

    /**
     * Supprime/anonymise définitivement le compte utilisateur (RGPD).
     */
    #[Route('', name: 'api_profile_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Delete(
        summary: 'Supprimer mon compte (RGPD)',
        responses: [
            new OA\Response(response: 204, description: 'Compte anonymisé avec succès'),
            new OA\Response(response: 401, description: 'JWT manquant ou invalide'),
        ]
    )]
    public function delete(): JsonResponse
    {
        $user = $this->getViewer();
        $this->userAnonymizer->anonymize($user);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Met à jour les informations du profil depuis un JSON envoyé par le front.
     */
    #[Route('', name: 'api_profile_update', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[OA\Post(
        summary: 'Mise à jour du profil utilisateur',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'firstname', type: 'string', nullable: true),
                    new OA\Property(property: 'lastname', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'avatarPath', type: 'string', nullable: true),
                    new OA\Property(property: 'newsletterOptIn', type: 'boolean', nullable: true),
                    new OA\Property(
                        property: 'address',
                        type: 'object',
                        nullable: true,
                        properties: [
                            new OA\Property(property: 'label', type: 'string', nullable: true),
                            new OA\Property(property: 'addressLine1', type: 'string', nullable: true),
                            new OA\Property(property: 'addressLine2', type: 'string', nullable: true),
                            new OA\Property(property: 'postalCode', type: 'string', nullable: true),
                            new OA\Property(property: 'city', type: 'string', nullable: true),
                            new OA\Property(property: 'state', type: 'string', nullable: true),
                            new OA\Property(property: 'country', type: 'string', nullable: true),
                            new OA\Property(property: 'isDefault', type: 'boolean', nullable: true),
                            new OA\Property(property: 'isShipping', type: 'boolean', nullable: true),
                            new OA\Property(property: 'isBilling', type: 'boolean', nullable: true),
                        ]
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour avec succès'),
            new OA\Response(response: 400, description: 'Données de requête invalides'),
        ]
    )]
    public function update(Request $request): JsonResponse
    {
        $user = $this->getViewer();

        try {
            $requestData = $request->toArray();
        } catch (\Throwable) {
            throw new BadRequestHttpException('JSON invalide ou vide.');
        }

        if (array_key_exists('firstname', $requestData)) {
            $user->setFirstname((string) $requestData['firstname']);
        }
        if (array_key_exists('lastname', $requestData)) {
            $user->setLastname((string) $requestData['lastname']);
        }
        if (array_key_exists('phone', $requestData)) {
            $user->setPhone($requestData['phone'] ? (string) $requestData['phone'] : null);
        }
        if (array_key_exists('avatarPath', $requestData)) {
            $user->setAvatarPath($requestData['avatarPath'] ?: null);
        }
        if (array_key_exists('newsletterOptIn', $requestData)) {
            $user->setNewsletterOptIn((bool) $requestData['newsletterOptIn']);
        }

        $addressData = $requestData['address'] ?? null;
        $address = $this->profileService->addressFromArray(is_array($addressData) ? $addressData : null);
        $this->profileService->applyProfileUpdates($user, $address);

        return $this->json($this->profileService->profileToArray($user), Response::HTTP_OK);
    }

    /**
     * Sélectionne l'utilisateur courant ou lève une 403.
     */
    private function getViewer(): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Authentification requise.');
        }

        return $user;
    }
}
