<?php

namespace App\Controller\Web;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
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
        CategoryRepository $categoryRepository
    ): Response {
        // Derniers produits mis en avant (fixtures)
        $latestProducts = $productRepository->findLatestPublished(10);
        $featuredProducts = $productRepository->findFeaturedPublished(10);
        // Quelques catégories pour alimenter les cartes
        $popularCategories = $categoryRepository->findBy([], ['name' => 'ASC'], 6);
        return $this->render('catalog/homepage.html.twig', [
            'latestProducts' => $latestProducts,
            'featuredProducts' => $featuredProducts,
            'categories' => $popularCategories,
        ]);
    }
}
