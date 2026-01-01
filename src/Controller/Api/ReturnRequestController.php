<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\ReturnRequest;
use App\Entity\User;
use App\Enum\OrderStatus;
use App\Repository\CustomerOrderRepository;
use App\Repository\ReturnRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/returns')]
#[OA\Tag(name: 'Returns')]
#[IsGranted('ROLE_USER')]
final class ReturnRequestController extends AbstractController
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
        private readonly ReturnRequestRepository $returnRequestRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_returns_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Demander un retour',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['orderId', 'reason'],
                properties: [
                    new OA\Property(property: 'orderId', type: 'integer', example: 120),
                    new OA\Property(property: 'reason', type: 'string', example: 'Produit non conforme'),
                    new OA\Property(property: 'details', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Demande enregistrée'),
            new OA\Response(response: 400, description: 'Données invalides'),
            new OA\Response(response: 404, description: 'Commande introuvable'),
        ]
    )]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent() ?: '[]', true) ?? [];
        $orderId = isset($payload['orderId']) ? (int) $payload['orderId'] : 0;
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($orderId <= 0 || '' === $reason) {
            return $this->json(['message' => 'orderId et reason requis.'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->findOneBy(['id' => $orderId, 'owner' => $user]);
        if (!$order instanceof CustomerOrder) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };
        if (!$isEligible) {
            return $this->json(['message' => 'Commande non éligible au retour.'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->returnRequestRepository->findOneBy([
            'order' => $order,
            'requester' => $user,
        ]);
        if ($existing instanceof ReturnRequest) {
            return $this->json(['message' => 'Une demande existe déjà.'], Response::HTTP_BAD_REQUEST);
        }

        $returnRequest = (new ReturnRequest())
            ->setOrder($order)
            ->setRequester($user)
            ->setReason($reason)
            ->setDetails(isset($payload['details']) ? trim((string) $payload['details']) : null);

        $this->entityManager->persist($returnRequest);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'submitted',
            'return' => [
                'id' => $returnRequest->getId(),
                'orderId' => $order->getId(),
                'reason' => $returnRequest->getReason(),
                'details' => $returnRequest->getDetails(),
                'status' => $returnRequest->getStatus()->value,
            ],
        ], Response::HTTP_CREATED);
    }
}
