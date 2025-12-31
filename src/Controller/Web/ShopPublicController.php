<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Repository\ProductRepository;
use App\Repository\ProductReviewRepository;
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
    public function index(
        Request $request,
        ShopRepository $shopRepository,
        ProductReviewRepository $productReviewRepository,
    ): Response
    {
        $page = max(1, (int) $request->query->get('page', '1'));
        $limitOptions = [12, 24, 48];
        $limit = (int) $request->query->get('limit', (string) $limitOptions[0]);
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
        $shops = $pagination['items'];
        $shopIds = array_map(static fn ($shop) => $shop->getId(), $shops);
        $reviewSummaries = $productReviewRepository->getSummariesForShops($shopIds);

        return $this->render('shop/list.html.twig', [
            'shops' => $shops,
            'pagination' => $pagination,
            'limit_options' => $limitOptions,
            'filters' => $filterState,
            'review_summaries' => $reviewSummaries,
        ]);
    }

    #[Route('/boutique/{slug}', name: 'shop_show', methods: ['GET'])]
    public function show(
        string $slug,
        ShopRepository $shopRepository,
        ProductRepository $productRepository,
        ProductReviewRepository $productReviewRepository,
    ): Response
    {
        $shop = $shopRepository->findOneBy(['slug' => $slug]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        $latestProducts = $productRepository->findLatestPublishedForShop($shop, 10);
        $featuredProducts = $productRepository->findFeaturedPublishedForShop($shop, 10);
        $productIds = array_values(array_unique(array_merge(
            array_map(static fn ($product) => $product->getId(), $latestProducts),
            array_map(static fn ($product) => $product->getId(), $featuredProducts)
        )));
        $reviewSummaries = $productReviewRepository->getSummariesForProducts($productIds);

        return $this->render('shop/show.html.twig', [
            'shop' => $shop,
            'latestProducts' => $latestProducts,
            'featuredProducts' => $featuredProducts,
            'review_summaries' => $reviewSummaries,
        ]);
    }

    #[Route('/boutique/{slug}/catalogue', name: 'shop_catalog', methods: ['GET'])]
    public function catalog(
        string $slug,
        Request $request,
        ShopRepository $shopRepository,
        ProductRepository $productRepository,
        ProductReviewRepository $productReviewRepository,
    ): Response {
        $shop = $shopRepository->findOneBy(['slug' => $slug]);
        if (!$shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $limitOptions = [12, 24, 48];
        $limit = (int) $request->query->get('limit', (string) $limitOptions[0]);
        if (!in_array($limit, $limitOptions, true)) {
            $limit = $limitOptions[0];
        }

        $pagination = $productRepository->filterByPaginated(['shop' => $shop], $page, $limit);
        $products = $pagination['items'];
        $productIds = array_map(static fn ($product) => $product->getId(), $products);
        $reviewSummaries = $productReviewRepository->getSummariesForProducts($productIds);

        return $this->render('shop/catalog.html.twig', [
            'shop' => $shop,
            'products' => $products,
            'pagination' => $pagination,
            'limit_options' => $limitOptions,
            'review_summaries' => $reviewSummaries,
        ]);
    }
}
