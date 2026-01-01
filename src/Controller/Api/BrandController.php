<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Brand;
use App\Repository\BrandRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/brands')]
#[OA\Tag(name: 'Brands')]
final class BrandController extends AbstractController
{
    public function __construct(
        private readonly BrandRepository $brandRepository,
    ) {
    }

    #[Route('', name: 'api_brands_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les marques',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des marques',
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
                        ]
                    )
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $brands = $this->brandRepository->findBy([], ['name' => 'ASC']);

        $payload = array_map(static fn (Brand $brand) => [
            'id' => $brand->getId(),
            'name' => $brand->getName(),
            'slug' => $brand->getSlug(),
            'description' => $brand->getDescription(),
            'logo' => $brand->getLogoPath(),
        ], $brands);

        return $this->json($payload);
    }
}
