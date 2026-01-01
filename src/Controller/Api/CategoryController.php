<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/categories')]
#[OA\Tag(name: 'Categories')]
final class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('', name: 'api_categories_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Lister les catégories',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des catégories',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'slug', type: 'string'),
                            new OA\Property(property: 'description', type: 'string', nullable: true),
                            new OA\Property(property: 'icon', type: 'string', nullable: true),
                            new OA\Property(property: 'parentId', type: 'integer', nullable: true),
                        ]
                    )
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);

        $payload = array_map(static fn (Category $category) => [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
            'icon' => $category->getIconPath(),
            'parentId' => $category->getParent()?->getId(),
        ], $categories);

        return $this->json($payload);
    }
}
