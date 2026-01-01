<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Product;
use App\Repository\ProductRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API publique qui expose la liste de produits prêts pour le front (React/Twig).
 * Chaque méthode renvoie un JSON épuré pour ne pas exposer d'informations inutiles.
 */
#[Route('/api/products')]
#[OA\Tag(name: 'Products')]
class ProductApiController extends AbstractController
{
    public function __construct(private ProductRepository $productRepository)
    {
    }

    #[Route('', name: 'api_products_index', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste paginable des produits publiés',
        description: 'Filtrez par catégorie, marque, prix ou mot-clé. Les résultats sont ceux exposés sur la boutique publique.',
        parameters: [
            new OA\QueryParameter(name: 'category', description: 'Slug de la catégorie', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'brand', description: 'Slug de la marque', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'minPrice', description: 'Prix minimum', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\QueryParameter(name: 'maxPrice', description: 'Prix maximum', schema: new OA\Schema(type: 'number', format: 'float')),
            new OA\QueryParameter(name: 'search', description: 'Terme recherché dans le nom ou le résumé', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'sort', description: 'Tri (newest, oldest, price_asc, price_desc)', schema: new OA\Schema(type: 'string')),
            new OA\QueryParameter(name: 'page', description: 'Page', schema: new OA\Schema(type: 'integer')),
            new OA\QueryParameter(name: 'perPage', description: 'Résultats par page', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste de produits',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'name', type: 'string'),
                                    new OA\Property(property: 'slug', type: 'string'),
                                    new OA\Property(property: 'shortDescription', type: 'string', nullable: true),
                                    new OA\Property(property: 'price', type: 'number', format: 'float'),
                                    new OA\Property(property: 'brand', type: 'string', nullable: true),
                                    new OA\Property(property: 'brandSlug', type: 'string', nullable: true),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'categorySlug', type: 'string'),
                                    new OA\Property(property: 'thumbnail', type: 'string', nullable: true),
                                ]
                            )
                        ),
                        new OA\Property(property: 'total', type: 'integer'),
                        new OA\Property(property: 'page', type: 'integer'),
                        new OA\Property(property: 'pages', type: 'integer'),
                        new OA\Property(property: 'perPage', type: 'integer'),
                    ]
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'category' => $request->query->get('category'),
            'brand' => $request->query->get('brand'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort'),
        ];

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPage = max(1, min(100, (int) $request->query->get('perPage', '20')));
        $pagination = $this->productRepository->filterByPaginated($filters, $page, $perPage);
        $items = array_map(fn (Product $product) => $this->serializeProduct($product), $pagination['items']);

        return $this->json([
            'items' => $items,
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'pages' => $pagination['pages'],
            'perPage' => $pagination['per_page'],
        ]);
    }

    #[Route('/{slug}', name: 'api_products_show', methods: ['GET'])]
    #[OA\Get(
        summary: 'Fiche produit détaillée',
        parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Produit enrichi',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'slug', type: 'string'),
                        new OA\Property(property: 'shortDescription', type: 'string', nullable: true),
                        new OA\Property(property: 'description', type: 'string', nullable: true),
                        new OA\Property(property: 'price', type: 'number', format: 'float'),
                        new OA\Property(property: 'stock', type: 'integer'),
                        new OA\Property(property: 'brand', type: 'string', nullable: true),
                        new OA\Property(property: 'brandSlug', type: 'string', nullable: true),
                        new OA\Property(property: 'category', type: 'string'),
                        new OA\Property(property: 'categorySlug', type: 'string'),
                        new OA\Property(
                            property: 'images',
                            type: 'array',
                            items: new OA\Items(type: 'object', properties: [
                                new OA\Property(property: 'url', type: 'string'),
                                new OA\Property(property: 'alt', type: 'string', nullable: true),
                                new OA\Property(property: 'title', type: 'string', nullable: true),
                            ])
                        ),
                        new OA\Property(
                            property: 'attributes',
                            type: 'array',
                            items: new OA\Items(type: 'object', properties: [
                                new OA\Property(property: 'slug', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'type', type: 'string'),
                                new OA\Property(
                                    property: 'values',
                                    type: 'array',
                                    items: new OA\Items(type: 'object', properties: [
                                        new OA\Property(property: 'slug', type: 'string'),
                                        new OA\Property(property: 'label', type: 'string'),
                                        new OA\Property(property: 'color', type: 'string', nullable: true),
                                    ])
                                ),
                            ])
                        ),
                        new OA\Property(
                            property: 'variants',
                            type: 'array',
                            items: new OA\Items(type: 'object', properties: [
                                new OA\Property(property: 'id', type: 'integer'),
                                new OA\Property(property: 'price', type: 'number', format: 'float'),
                                new OA\Property(property: 'promoPrice', type: 'number', format: 'float', nullable: true),
                                new OA\Property(property: 'stock', type: 'integer'),
                                new OA\Property(property: 'isAvailable', type: 'boolean'),
                                new OA\Property(property: 'configuration', type: 'object', nullable: true),
                                new OA\Property(property: 'metadata', type: 'object', nullable: true),
                            ])
                        ),
                        new OA\Property(
                            property: 'reviews',
                            type: 'array',
                            items: new OA\Items(type: 'object', properties: [
                                new OA\Property(property: 'rating', type: 'integer'),
                                new OA\Property(property: 'comment', type: 'string'),
                            ])
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ]
    )]
    public function show(string $slug): JsonResponse
    {
        $product = $this->productRepository->findOneBy(['slug' => $slug, 'isPublished' => true]);

        if (!$product) {
            throw new NotFoundHttpException('Produit introuvable');
        }

        return $this->json($this->serializeProduct($product, true));
    }

    private function serializeProduct(Product $product, bool $includeDetails = false): array
    {
        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'shortDescription' => $product->getShortDescription(),
            'price' => (float) $product->getPrice(),
            'brand' => $product->getBrand()?->getName(),
            'brandSlug' => $product->getBrand()?->getSlug(),
            'category' => $product->getCategory()->getName(),
            'categorySlug' => $product->getCategory()->getSlug(),
            'thumbnail' => ($product->getImages()->first() ?: null)?->getUrl(),
        ];

        if ($includeDetails) {
            $data['description'] = $product->getDescription();
            $data['stock'] = $product->getStock();
            $data['weight'] = $product->getWeight();
            $data['images'] = array_map(fn ($img) => [
                'url' => $img->getUrl(),
                'alt' => $img->getAlt(),
                'title' => $img->getTitle(),
            ], $product->getImages()->toArray());

            $data['attributes'] = array_map(function ($attribute) {
                return [
                    'slug' => $attribute->getSlug(),
                    'name' => $attribute->getName(),
                    'type' => $attribute->getInputType(),
                    'values' => array_map(fn ($value) => [
                        'slug' => $value->getSlug(),
                        'label' => $value->getValue(),
                        'color' => $value->getColorHex(),
                    ], $attribute->getValues()->toArray()),
                ];
            }, $product->getAttributes()->toArray());

            $data['variants'] = array_map(fn ($variant) => [
                'id' => $variant->getId(),
                'price' => $variant->getPrice(),
                'promoPrice' => $variant->getPromoPrice(),
                'stock' => $variant->getStock(),
                'weight' => $variant->getWeight(),
                'isAvailable' => $variant->isAvailable(),
                'configuration' => $variant->getConfiguration(),
                'metadata' => $variant->getMetadata(),
            ], $product->getVariants()->toArray());

            $reviews = array_filter(
                $product->getReviews()->toArray(),
                static fn ($review) => $review->isApproved()
            );
            $data['reviews'] = array_map(fn ($review) => [
                'rating' => $review->getRating(),
                'comment' => $review->getComment(),
            ], $reviews);
        }

        return $data;
    }
}
