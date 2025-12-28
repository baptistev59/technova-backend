<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\CartService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints minimalistes pour piloter le panier depuis le front.
 */
#[Route('/api/cart')]
#[OA\Tag(name: 'Cart')]
#[IsGranted('ROLE_USER')]
class CartController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly ProductRepository $productRepository,
    ) {
    }

    #[Route('', name: 'api_cart_get', methods: ['GET'])]
    #[OA\Get(summary: 'Consulter le panier', responses: [new OA\Response(response: 200, description: 'Contenu du panier')])]
    public function show(): JsonResponse
    {
        $summary = $this->cartService->getSummary();

        $items = array_map(static function (array $item) {
            /** @var Product $product */
            $product = $item['product'];

            return [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'price' => $product->getPrice(),
                'quantity' => $item['quantity'],
                'lineTotal' => $item['lineTotal'],
            ];
        }, $summary['items']);

        return $this->json([
            'items' => $items,
            'total' => $summary['total'],
            'totalQuantity' => $summary['totalQuantity'],
        ]);
    }

    #[Route('', name: 'api_cart_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter un produit au panier',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['productId'],
                properties: [
                    new OA\Property(property: 'productId', type: 'integer', example: 42),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Produit ajouté'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ]
    )]
    public function add(Request $request): JsonResponse
    {
        $requestData = json_decode($request->getContent() ?: '[]', true) ?? [];
        $productId = isset($requestData['productId']) ? (int) $requestData['productId'] : 0;
        $quantity = isset($requestData['quantity']) ? (int) $requestData['quantity'] : 1;

        if ($productId <= 0) {
            return $this->json(['error' => 'productId manquant'], Response::HTTP_BAD_REQUEST);
        }

        $product = $this->productRepository->find($productId);
        if (!$product) {
            return $this->json(['error' => 'Produit introuvable'], Response::HTTP_NOT_FOUND);
        }

        $this->cartService->addProduct($product, max(1, $quantity));

        return $this->json(['message' => 'Produit ajouté'], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_cart_remove', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un produit du panier',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Produit retiré'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ]
    )]
    public function remove(Product $product): JsonResponse
    {
        $this->cartService->removeProduct($product);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
