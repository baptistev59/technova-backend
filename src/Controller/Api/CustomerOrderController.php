<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\User;
use App\Enum\DocumentType;
use App\Enum\OrderStatus;
use App\Repository\CustomerOrderRepository;
use App\Repository\OrderDocumentRepository;
use App\Service\OrderDocumentGenerator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/orders')]
#[OA\Tag(name: 'Orders')]
#[IsGranted('ROLE_USER')]
final class CustomerOrderController extends AbstractController
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
        private readonly OrderDocumentRepository $documentRepository,
        private readonly OrderDocumentGenerator $documentGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_orders_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les commandes client',
        parameters: [
            new OA\QueryParameter(name: 'status', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'page', schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'perPage', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = max(1, min(100, (int) $request->query->get('perPage', '20')));
        $status = $request->query->get('status');

        $pagination = $this->orderRepository->paginateForOwner($user, $page, $perPage, $status);
        $items = array_map(fn (CustomerOrder $order) => $this->serializeOrder($order), $pagination['orders']);

        return $this->json([
            'items' => $items,
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $pagination['limit'],
            'status' => $pagination['statusFilter'],
        ]);
    }

    #[Route('/{id}', name: 'api_orders_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d’une commande client',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Commande détaillée'),
            new OA\Response(response: 404, description: 'Commande introuvable'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->findOneBy(['id' => $id, 'owner' => $user]);
        if (!$order instanceof CustomerOrder) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->serializeOrder($order, true));
    }

    #[Route('/{id}/invoice', name: 'api_orders_invoice', methods: ['GET'])]
    #[OA\Get(
        summary: 'Récupérer la facture (lien)',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lien de facture'),
            new OA\Response(response: 400, description: 'Commande non éligible'),
            new OA\Response(response: 404, description: 'Commande introuvable'),
        ]
    )]
    public function invoice(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['message' => 'Authentification requise.'], Response::HTTP_UNAUTHORIZED);
        }

        $order = $this->orderRepository->findOneBy(['id' => $id, 'owner' => $user]);
        if (!$order instanceof CustomerOrder) {
            return $this->json(['message' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };
        if (!$isEligible) {
            return $this->json(['message' => 'Commande non éligible à la facturation.'], Response::HTTP_BAD_REQUEST);
        }

        $document = $this->documentRepository->findOneBy([
            'order' => $order,
            'type' => DocumentType::INVOICE,
        ]);

        if (null === $document) {
            $document = $this->documentGenerator->generate($order, DocumentType::INVOICE, $request->getSchemeAndHttpHost());
            $this->entityManager->persist($document);
            $this->entityManager->flush();
        }

        return $this->json([
            'url' => $document->getUrl(),
            'hash' => $document->getHash(),
            'generatedAt' => $document->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function serializeOrder(CustomerOrder $order, bool $includeDetails = false): array
    {
        $items = array_map(static fn ($item) => [
            'id' => $item->getId(),
            'productId' => $item->getProductId(),
            'name' => $item->getProductName(),
            'quantity' => $item->getQuantity(),
            'unitPrice' => (float) $item->getUnitPrice(),
            'lineTotal' => (float) $item->getLineTotal(),
            'image' => $item->getProductImage(),
            'variantId' => $item->getVariantId(),
            'shopId' => $item->getShopId(),
        ], $order->getItems()->toArray());

        $data = [
            'id' => $order->getId(),
            'reference' => $order->getReference(),
            'status' => $order->getStatus(),
            'total' => (float) $order->getTotalAmount(),
            'itemsTotal' => (float) $order->getItemsTotal(),
            'shippingTotal' => (float) $order->getShippingTotal(),
            'currency' => $order->getCurrency(),
            'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'paidAt' => $order->getPaidAt()?->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ];

        if ($includeDetails) {
            $data['shippingAddress'] = $order->getShippingAddress();
            $data['billingAddress'] = $order->getBillingAddress();
            $data['shippingLines'] = $order->getShippingLines();
        }

        return $data;
    }
}
