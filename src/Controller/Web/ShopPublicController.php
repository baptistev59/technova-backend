<?php

namespace App\Controller\Web;

use App\Repository\ProductRepository;
use App\Repository\ShopRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages publiques d’une boutique : vitrine et catalogue.
 */
class ShopPublicController extends AbstractController
{
    #[Route('/boutiques', name: 'shop_index', methods: ['GET'])]
    public function index(Request $request, ShopRepository $shopRepository): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limitOptions = [12, 24, 48];
        $limit = (int) $request->query->get('limit', $limitOptions[0]);
        if (!in_array($limit, $limitOptions, true)) {
            $limit = $limitOptions[0];
        }

        $filterState = [
            'search' => trim((string) $request->query->get('search', '')),
            'vendor' => trim((string) $request->query->get('vendor', '')),
        ];

        $pagination = $shopRepository->paginate($page, $limit, [
            'search' => $filterState['search'],
            'vendor' => $filterState['vendor'],
        ]);

        return $this->render('shop/list.html.twig', [
            'shops' => $pagination['items'],
            'pagination' => $pagination,
            'limit_options' => $limitOptions,
            'filters' => $filterState,
        ]);
    }

    #[Route('/boutique/{slug}', name: 'shop_show', methods: ['GET'])]
    public function show(string $slug, ShopRepository $shopRepository, ProductRepository $productRepository): Response
    {
        $shop = $shopRepository->findOneBy(['slug' => $slug]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        return $this->render('shop/show.html.twig', [
            'shop' => $shop,
            'latestProducts' => $productRepository->findLatestPublishedForShop($shop, 10),
            'featuredProducts' => $productRepository->findFeaturedPublishedForShop($shop, 10),
        ]);
    }

    #[Route('/boutique/{slug}/catalogue', name: 'shop_catalog', methods: ['GET'])]
    public function catalog(
        string $slug,
        Request $request,
        ShopRepository $shopRepository,
        ProductRepository $productRepository
    ): Response {
        $shop = $shopRepository->findOneBy(['slug' => $slug]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limitOptions = [12, 24, 48];
        $limit = (int) $request->query->get('limit', $limitOptions[0]);
        if (!in_array($limit, $limitOptions, true)) {
            $limit = $limitOptions[0];
        }

        $pagination = $productRepository->filterByPaginated(['shop' => $shop], $page, $limit);

        return $this->render('shop/catalog.html.twig', [
            'shop' => $shop,
            'products' => $pagination['items'],
            'pagination' => $pagination,
            'limit_options' => $limitOptions,
        ]);
    }
}
