<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vue d’accueil marketing : on y injecte uniquement ce qu’il faut pour la maquette.
 */
class HomepageController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function __invoke(
        ProductRepository $productRepository,
        ProductReviewRepository $productReviewRepository,
        CategoryRepository $categoryRepository,
    ): Response {
        // Derniers produits mis en avant (fixtures)
        $latestProducts = $productRepository->findLatestPublished(10);
        $featuredProducts = $productRepository->findFeaturedPublished(10);
        // Quelques catégories pour alimenter les cartes
        $popularCategories = $categoryRepository->findBy([], ['name' => 'ASC'], 6);
        $productIds = array_values(array_unique(array_merge(
            array_map(static fn ($product) => $product->getId(), $latestProducts),
            array_map(static fn ($product) => $product->getId(), $featuredProducts)
        )));
        $reviewSummaries = $productReviewRepository->getSummariesForProducts($productIds);

        return $this->render('catalog/homepage.html.twig', [
            'latestProducts' => $latestProducts,
            'featuredProducts' => $featuredProducts,
            'categories' => $popularCategories,
            'review_summaries' => $reviewSummaries,
        ]);
    }
}
