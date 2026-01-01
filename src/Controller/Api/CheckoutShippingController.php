<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\User;
use App\Service\CartService;
use App\Service\ShippingSelectionResolver;
use App\Service\UserProfileService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/checkout')]
#[OA\Tag(name: 'Checkout')]
#[IsGranted('ROLE_USER')]
final class CheckoutShippingController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ShippingSelectionResolver $shippingSelectionResolver,
        private readonly UserProfileService $profileService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/shipping-options', name: 'api_checkout_shipping_options', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les options de livraison par boutique',
        parameters: [
            new OA\QueryParameter(name: 'addressId', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Options de livraison'),
            new OA\Response(response: 400, description: 'Adresse manquante'),
        ]
    )]
    public function shippingOptions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            return $this->json(['message' => 'Panier vide.'], Response::HTTP_BAD_REQUEST);
        }

        $address = $this->resolveAddress($request, $user);
        if (!$address instanceof Address) {
            return $this->json(['message' => 'Adresse introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $options = $this->shippingSelectionResolver->buildOptions($summary['items'], $address);
        $payload = array_values(array_map(static fn (array $entry): array => [
            'shop' => [
                'id' => $entry['shop']->getId(),
                'name' => $entry['shop']->getName(),
                'slug' => $entry['shop']->getSlug(),
            ],
            'weight' => $entry['weight'],
            'options' => $entry['options'],
            'defaultId' => $entry['defaultId'],
        ], $options));

        return $this->json($payload);
    }

    private function resolveAddress(Request $request, User $user): ?Address
    {
        $addressId = $request->query->get('addressId');
        if ($addressId) {
            $address = $this->entityManager->getRepository(Address::class)->find((int) $addressId);
            if ($address instanceof Address && $address->getOwner()?->getId() === $user->getId()) {
                return $address;
            }
        }

        return $this->profileService->guessPrimaryAddress($user);
    }
}
