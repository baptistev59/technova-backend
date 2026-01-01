<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\User;
use App\Service\CartService;
use App\Service\CheckoutService;
use App\Service\ShippingSelectionResolver;
use App\Service\StripePaymentService;
use App\Service\UserProfileService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/checkout')]
#[OA\Tag(name: 'Checkout')]
#[IsGranted('ROLE_USER')]
final class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly UserProfileService $profileService,
        private readonly CheckoutService $checkoutService,
        private readonly StripePaymentService $stripePaymentService,
        private readonly ShippingSelectionResolver $shippingSelectionResolver,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_checkout_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Créer une session de paiement',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['shipping', 'successUrl', 'cancelUrl'],
                properties: [
                    new OA\Property(
                        property: 'shipping',
                        type: 'object',
                        additionalProperties: new OA\AdditionalProperties(type: 'integer'),
                        example: ['12' => 3]
                    ),
                    new OA\Property(property: 'addressId', type: 'integer', nullable: true),
                    new OA\Property(property: 'successUrl', type: 'string', example: 'https://front/success?session_id={CHECKOUT_SESSION_ID}'),
                    new OA\Property(property: 'cancelUrl', type: 'string', example: 'https://front/cancel'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Session Stripe créée'),
            new OA\Response(response: 400, description: 'Données invalides'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $summary = $this->cartService->getSummary();
        if (empty($summary['items'])) {
            return $this->json(['message' => 'Panier vide.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = json_decode($request->getContent() ?: '[]', true) ?? [];
        $selection = $payload['shipping'] ?? null;
        if (!is_array($selection) || [] === $selection) {
            return $this->json(['message' => 'Choix de livraison requis.'], Response::HTTP_BAD_REQUEST);
        }

        $address = $this->resolveAddress($payload['addressId'] ?? null, $user);
        if (!$address instanceof Address) {
            return $this->json(['message' => 'Adresse introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        $shippingSelection = $this->shippingSelectionResolver->resolveSelection(
            $summary['items'],
            $address,
            $selection
        );
        if (!$shippingSelection['valid']) {
            return $this->json(['message' => $shippingSelection['message']], Response::HTTP_BAD_REQUEST);
        }

        try {
            $order = $this->checkoutService->createOrder(
                $user,
                $address,
                $shippingSelection['lines'],
                $shippingSelection['shippingTotal']
            );
        } catch (\RuntimeException $exception) {
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $successUrl = $this->normalizeUrl((string) ($payload['successUrl'] ?? ''), $order->getReference(), true);
        $cancelUrl = $this->normalizeUrl((string) ($payload['cancelUrl'] ?? ''), $order->getReference(), false);

        try {
            $session = $this->stripePaymentService->createCheckoutSession($order, $successUrl, $cancelUrl);
            $this->checkoutService->attachPaymentSession($order, $session['id']);
        } catch (\Throwable $exception) {
            $this->checkoutService->cancelOrder($order);
            return $this->json(['message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'checkoutUrl' => $session['url'],
            'order' => [
                'id' => $order->getId(),
                'reference' => $order->getReference(),
                'total' => (float) $order->getTotalAmount(),
            ],
        ]);
    }

    private function resolveAddress(?int $addressId, User $user): ?Address
    {
        if ($addressId) {
            $address = $this->entityManager->getRepository(Address::class)->find($addressId);
            if ($address instanceof Address && $address->getOwner()?->getId() === $user->getId()) {
                return $address;
            }
        }

        return $this->profileService->guessPrimaryAddress($user);
    }

    private function normalizeUrl(string $url, ?string $reference, bool $isSuccess): string
    {
        if ('' !== trim($url)) {
            return $url;
        }

        if (!$reference) {
            return $this->urlGenerator->generate('app_cart_show', [], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        if ($isSuccess) {
            return $this->urlGenerator->generate(
                'app_checkout_success',
                ['reference' => $reference],
                UrlGeneratorInterface::ABSOLUTE_URL
            ).'?session_id={CHECKOUT_SESSION_ID}';
        }

        return $this->urlGenerator->generate(
            'app_checkout_cancel',
            ['reference' => $reference],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
