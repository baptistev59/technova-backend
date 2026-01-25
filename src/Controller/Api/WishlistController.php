<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Repository\ProductRepository;
use App\Repository\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/wishlists')]
#[IsGranted('ROLE_USER')]
/**
 * Endpoints pour gérer les favoris utilisateur.
 */
class WishlistController extends AbstractController
{
    public function __construct(
        private readonly WishlistRepository $wishlistRepository,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_wishlists_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister mes favoris',
        description: 'Récupère tous les produits ajoutés aux favoris de l\'utilisateur connecté.',
        security: [['BearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des favoris',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'count', type: 'integer', example: 3),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                                    new OA\Property(
                                        property: 'product',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer'),
                                            new OA\Property(property: 'name', type: 'string'),
                                            new OA\Property(property: 'slug', type: 'string'),
                                            new OA\Property(property: 'price', type: 'number'),
                                        ]
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'JWT manquant ou invalide'),
        ]
    )]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $wishlists = $this->wishlistRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        $items = [];
        foreach ($wishlists as $wishlist) {
            $product = $wishlist->getProduct();
            $items[] = [
                'id' => $wishlist->getId(),
                'createdAt' => $wishlist->getCreatedAt()?->format('c'),
                'product' => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'slug' => $product->getSlug(),
                    'price' => $product->getPrice(),
                ],
            ];
        }

        return $this->json([
            'count' => count($items),
            'items' => $items,
        ]);
    }

    #[Route('', name: 'api_wishlists_add', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ajouter un produit aux favoris',
        description: 'Ajoute un produit à la wishlist de l\'utilisateur connecté.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['productId'],
                properties: [
                    new OA\Property(property: 'productId', type: 'integer', example: 42),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Produit ajouté aux favoris',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'added'),
                        new OA\Property(property: 'wishlistId', type: 'integer'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Produit invalide'),
            new OA\Response(response: 409, description: 'Produit déjà dans les favoris'),
        ]
    )]
    public function add(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $productId = $data['productId'] ?? null;

        if (!$productId) {
            return $this->json(['error' => 'productId requis.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        /** @var Product $product */
        $product = $this->productRepository->find($productId);
        if (!$product) {
            return $this->json(['error' => 'Produit non trouvé.'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le produit n'est pas déjà dans les favoris
        $existing = $this->wishlistRepository->findOneBy(['user' => $user, 'product' => $product]);
        if ($existing) {
            return $this->json(
                ['error' => 'Produit déjà dans les favoris.'],
                Response::HTTP_CONFLICT
            );
        }

        $wishlist = new Wishlist();
        $wishlist->setUser($user);
        $wishlist->setProduct($product);

        $this->entityManager->persist($wishlist);
        $this->entityManager->flush();

        return $this->json(
            [
                'status' => 'added',
                'wishlistId' => $wishlist->getId(),
            ],
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'api_wishlists_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Retirer un produit des favoris',
        description: 'Supprime un produit de la wishlist de l\'utilisateur connecté.',
        security: [['BearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                description: 'ID de l\'élément wishlist à supprimer'
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Produit retiré des favoris'),
            new OA\Response(response: 404, description: 'Élément wishlist non trouvé'),
        ]
    )]
    public function delete(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        /** @var Wishlist|null $wishlist */
        $wishlist = $this->wishlistRepository->find($id);

        if (!$wishlist || $wishlist->getUser() !== $user) {
            return $this->json(['error' => 'Non trouvé.'], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($wishlist);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
