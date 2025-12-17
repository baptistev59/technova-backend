<?php

namespace App\Controller\Web;

use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Liste des produits avec filtres simples (catégorie + marque) côté Twig.
 */
class CatalogController extends AbstractController
{
    #[Route('/catalogue', name: 'catalog_index')]
    public function index(
        Request $request,
        ProductRepository $productRepository,
        CategoryRepository $categoryRepository,
        BrandRepository $brandRepository
    ): Response {
        $filters = [
            'category' => $request->query->get('category'),
            'brand' => $request->query->get('brand'),
            'minPrice' => $request->query->get('minPrice'),
            'maxPrice' => $request->query->get('maxPrice'),
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort'),
        ];

        // Le repository connaît déjà la logique de filtres → réutilisation côté API/Twig
        $page = max(1, (int) $request->query->get('page', 1));
        $rowsOptions = [5, 10, 20];
        $selectedRows = (int) $request->query->get('rows', $rowsOptions[1]);
        if (!in_array($selectedRows, $rowsOptions, true)) {
            $selectedRows = $rowsOptions[1];
        }

        $defaultColumns = 4;
        $limit = (int) $request->query->get('limit', 0);
        if ($limit <= 0) {
            $limit = $selectedRows * $defaultColumns;
        }
        $pagination = $productRepository->filterByPaginated($filters, $page, $limit);
        $products = $pagination['items'];
        $categories = $categoryRepository->findAll();
        $brands = $brandRepository->findAll();
        return $this->render('catalog/index.html.twig', [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'activeFilters' => $filters,
            'resultsCount' => $pagination['total'],
            'pagination' => $pagination,
            'rows_options' => $rowsOptions,
            'selected_rows' => $selectedRows,
        ]);
    }

    #[Route('/catalogue/suggestions', name: 'catalog_product_suggestions', methods: ['GET'])]
    public function suggest(Request $request, ProductRepository $productRepository): JsonResponse
    {
        $query = (string) $request->query->get('query', '');
        if (mb_strlen($query) < 3) {
            return $this->json([]);
        }

        $names = $productRepository->findNamesContaining($query, 40);
        return $this->json($names);
    }
}
