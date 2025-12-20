<?php

namespace App\Controller\Admin;

use App\Controller\Admin\CustomerOrderCrudController;
use App\Controller\Admin\ProductCrudController;
use App\Controller\Admin\VendorCrudController;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\VendorRepository;
use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    public function __construct(
        private readonly CustomerOrderRepository $orderRepository,
        private readonly ProductRepository $productRepository,
        private readonly VendorRepository $vendorRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function __invoke(Request $request): Response
    {
        $statsCards = [
            [
                'label' => 'Commandes totales',
                'value' => $this->orderRepository->countAll(),
                'trend' => 'Toutes boutiques',
                'icon' => '🧾',
            ],
            [
                'label' => 'En attente',
                'value' => $this->orderRepository->countByStatus('pending'),
                'trend' => 'À traiter',
                'icon' => '⏳',
            ],
            [
                'label' => 'Payées / expédiées',
                'value' => $this->orderRepository->countByStatuses(['paid', 'shipped']),
                'trend' => 'Commandes validées',
                'icon' => '✅',
            ],
            [
                'label' => 'Revenus cumulés',
                'value' => number_format($this->orderRepository->sumTotalRevenue(), 2, ',', ' ') . ' €',
                'trend' => 'Marketplace',
                'icon' => '💶',
            ],
        ];

        $latestOrders = $this->orderRepository->findLatest(10);
        $latestProducts = $this->productRepository->findLatestPublished(10);

        $salesTrend = $this->buildFallbackTrend();
        $revenueTrend = $this->buildRevenueTrend();

        $searchReference = trim((string) $request->query->get('order_reference'));
        $searchedOrder = null;
        if ($searchReference !== '') {
            $searchedOrder = $this->orderRepository->findOneByReference($searchReference);
        }

        $links = [
            'orders' => $this->adminUrlGenerator->setController(CustomerOrderCrudController::class)->generateUrl(),
            'vendors' => $this->adminUrlGenerator->setController(VendorCrudController::class)->generateUrl(),
            'products' => $this->adminUrlGenerator->setController(ProductCrudController::class)->generateUrl(),
            'product_new' => $this->adminUrlGenerator->setController(ProductCrudController::class)->setAction('new')->generateUrl(),
        ];

        return $this->render('admin/dashboard_custom.html.twig', [
            'statCards' => $statsCards,
            'latestOrders' => $latestOrders,
            'latestProducts' => $latestProducts,
            'sales_trend' => $salesTrend,
            'revenue_trend' => $revenueTrend,
            'links' => $links,
            'orders_today' => $this->orderRepository->countSince(new DateTimeImmutable('today')),
            'searched_order' => $searchedOrder,
            'search_reference' => $searchReference,
        ]);
    }

    private function buildFallbackTrend(): array
    {
        return [
            ['label' => 'J-6', 'value' => 2],
            ['label' => 'J-5', 'value' => 1],
            ['label' => 'J-4', 'value' => 3],
            ['label' => 'J-3', 'value' => 2],
            ['label' => 'J-2', 'value' => 4],
            ['label' => 'Hier', 'value' => 1],
            ['label' => 'Aujourd’hui', 'value' => 3],
        ];
    }

    private function buildRevenueTrend(): array
    {
        return [
            ['label' => 'Jan', 'value' => 950],
            ['label' => 'Fév', 'value' => 1200],
            ['label' => 'Mar', 'value' => 870],
            ['label' => 'Avr', 'value' => 1430],
            ['label' => 'Mai', 'value' => 1100],
            ['label' => 'Juin', 'value' => 1650],
            ['label' => 'Juil', 'value' => 980],
            ['label' => 'Août', 'value' => 1320],
            ['label' => 'Sept', 'value' => 1180],
            ['label' => 'Oct', 'value' => 1740],
            ['label' => 'Nov', 'value' => 1390],
            ['label' => 'Déc', 'value' => 2100],
        ];
    }
}
