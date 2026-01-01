<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Shop;
use App\Repository\ProductReviewRepository;
use App\Repository\ShopRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Route('/api/shops')]
#[OA\Tag(name: 'Shops')]
final class ShopController extends AbstractController
{
    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly ProductReviewRepository $reviewRepository,
    ) {
    }

    #[Route('', name: 'api_shops_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les boutiques publiques',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des boutiques',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'slug', type: 'string'),
                            new OA\Property(property: 'description', type: 'string', nullable: true),
                            new OA\Property(property: 'logo', type: 'string', nullable: true),
                            new OA\Property(property: 'banner', type: 'string', nullable: true),
                            new OA\Property(property: 'rating', type: 'number', format: 'float'),
                            new OA\Property(property: 'reviews', type: 'integer'),
                        ]
                    )
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $shops = $this->shopRepository->findBy([], ['name' => 'ASC']);
        $summaries = $this->reviewRepository->getSummariesForShops($shops);

        $payload = array_map(static fn (Shop $shop) => [
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'description' => $shop->getDescription(),
            'logo' => $shop->getLogo(),
            'banner' => $shop->getBanner(),
            'rating' => $summaries[$shop->getId()]['average'] ?? 0.0,
            'reviews' => $summaries[$shop->getId()]['count'] ?? 0,
        ], $shops);

        return $this->json($payload);
    }

    #[Route('/{slug}', name: 'api_shops_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d’une boutique',
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Boutique'),
            new OA\Response(response: 404, description: 'Boutique introuvable'),
        ]
    )]
    public function show(string $slug): JsonResponse
    {
        $shop = $this->shopRepository->findOneBy(['slug' => $slug]);
        if (!$shop instanceof Shop) {
            throw new NotFoundHttpException('Boutique introuvable');
        }

        $summaries = $this->reviewRepository->getSummariesForShops([$shop]);

        return $this->json([
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'description' => $shop->getDescription(),
            'logo' => $shop->getLogo(),
            'banner' => $shop->getBanner(),
            'contactEmail' => $shop->getContactEmail(),
            'policies' => $shop->getPolicies(),
            'rating' => $summaries[$shop->getId()]['average'] ?? 0.0,
            'reviews' => $summaries[$shop->getId()]['count'] ?? 0,
        ]);
    }
}
