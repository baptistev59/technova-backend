<?php

namespace App\Controller\Admin;

use App\Controller\Admin\CustomerOrderCrudController;
use App\Controller\Admin\DashboardController;
use App\Controller\Admin\ProductCrudController;
use App\Controller\Admin\VendorCrudController;
use App\Entity\CustomerOrder;
use App\Enum\OrderStatus;
use App\Entity\Shop;
use App\Entity\Vendor;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\VendorRepository;
use App\Repository\ShopRepository;
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
        private readonly ShopRepository $shopRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function __invoke(Request $request): Response
    {
        $ordersWindow = (string) $request->query->get('orders_window', 'today');
        $ordersStatus = (string) $request->query->get('orders_status', 'all');

        $windowOptions = [
            'today' => [
                'label' => 'Aujourd’hui',
                'since' => new DateTimeImmutable('today'),
            ],
            '24h' => [
                'label' => '24h glissantes',
                'since' => new DateTimeImmutable('-24 hours'),
            ],
            '3d' => [
                'label' => '3 jours glissants',
                'since' => new DateTimeImmutable('-3 days'),
            ],
            '7d' => [
                'label' => '1 semaine glissante',
                'since' => new DateTimeImmutable('-7 days'),
            ],
        ];

        if (!isset($windowOptions[$ordersWindow])) {
            $ordersWindow = 'today';
        }

        $statusOptions = [
            'all' => [
                'label' => 'Toutes commandes',
                'statuses' => null,
            ],
            'paid' => [
                'label' => 'Payées / expédiées',
                'statuses' => [OrderStatus::Paid, OrderStatus::Shipped],
            ],
        ];

        if (!isset($statusOptions[$ordersStatus])) {
            $ordersStatus = 'all';
        }

        $since = $windowOptions[$ordersWindow]['since'];
        $statusFilter = $statusOptions[$ordersStatus]['statuses'];

        $ordersToday = $statusFilter === null
            ? $this->orderRepository->countSince($since)
            : $this->orderRepository->countSinceWithStatuses($since, $statusFilter);

        $statsCards = [
            [
                'label' => 'Commandes totales',
                'value' => $this->orderRepository->countAll(),
                'trend' => 'Toutes boutiques',
                'icon' => '🧾',
            ],
            [
                'label' => 'En attente',
                'value' => $this->orderRepository->countByStatus(OrderStatus::Pending),
                'trend' => 'À traiter',
                'icon' => '⏳',
            ],
            [
                'label' => 'Payées / expédiées',
                'value' => $this->orderRepository->countByStatuses([OrderStatus::Paid, OrderStatus::Shipped]),
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

        $salesTrend = $this->buildSalesTrend();
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
            'easyadmin' => $this->adminUrlGenerator->setDashboard(DashboardController::class)->generateUrl(),
        ];

        $latestOrderVendors = $this->buildOrdersVendorContext($latestOrders);
        $searchedOrderVendor = $searchedOrder ? ($this->buildOrdersVendorContext([$searchedOrder])[$searchedOrder->getId()] ?? null) : null;

        return $this->render('admin/dashboard_custom.html.twig', [
            'statCards' => $statsCards,
            'latestOrders' => $latestOrders,
            'latestProducts' => $latestProducts,
            'sales_trend' => $salesTrend,
            'revenue_trend' => $revenueTrend,
            'links' => $links,
            'orders_today' => $ordersToday,
            'orders_window' => $ordersWindow,
            'orders_window_label' => $windowOptions[$ordersWindow]['label'],
            'orders_status' => $ordersStatus,
            'orders_status_label' => $statusOptions[$ordersStatus]['label'],
            'searched_order' => $searchedOrder,
            'search_reference' => $searchReference,
            'latest_order_vendors' => $latestOrderVendors,
            'searched_order_vendor' => $searchedOrderVendor,
        ]);
    }

    private function buildSalesTrend(): array
    {
        $today = new DateTimeImmutable('today');
        $start = $today->modify('-6 days');
        $rows = $this->orderRepository->findDailyOrderCountsSince($start);

        $totalsByDay = [];
        foreach ($rows as $row) {
            $totalsByDay[$row['day']->format('Y-m-d')] = $row['total'];
        }

        $trend = [];
        for ($i = 0; $i < 7; ++$i) {
            $date = $start->modify(sprintf('+%d days', $i));
            $label = $i === 6 ? 'Aujourd’hui' : sprintf('J-%d', 6 - $i);
            $trend[] = [
                'label' => $label,
                'value' => $totalsByDay[$date->format('Y-m-d')] ?? 0,
            ];
        }

        return $trend;
    }

    private function buildRevenueTrend(): array
    {
        $start = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
        $rows = $this->orderRepository->findMonthlyRevenueSince($start, [OrderStatus::Paid, OrderStatus::Shipped]);

        $totalsByMonth = [];
        foreach ($rows as $row) {
            $totalsByMonth[$row['month']->format('Y-m')] = $row['total'];
        }

        $labels = [
            1 => 'Jan',
            2 => 'Fév',
            3 => 'Mar',
            4 => 'Avr',
            5 => 'Mai',
            6 => 'Juin',
            7 => 'Juil',
            8 => 'Août',
            9 => 'Sept',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Déc',
        ];

        $trend = [];
        for ($i = 0; $i < 12; ++$i) {
            $month = $start->modify(sprintf('+%d months', $i));
            $monthKey = $month->format('Y-m');
            $monthLabel = $labels[(int) $month->format('n')] ?? $month->format('M');
            $trend[] = [
                'label' => $monthLabel,
                'value' => $totalsByMonth[$monthKey] ?? 0,
            ];
        }

        return $trend;
    }

    /**
     * @param iterable<CustomerOrder> $orders
     *
     * @return array<int, array{shop: Shop|null, vendor: Vendor|null}>
     */
    private function buildOrdersVendorContext(iterable $orders): array
    {
        $shopIds = [];

        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                $shopId = $item->getShopId();

                if ($shopId !== null) {
                    $shopIds[$shopId] = true;
                }
            }
        }

        $shopMap = [];
        if (!empty($shopIds)) {
            foreach ($this->shopRepository->findWithVendorByIds(array_keys($shopIds)) as $shop) {
                $shopMap[$shop->getId()] = $shop;
            }
        }

        $context = [];
        foreach ($orders as $order) {
            $context[$order->getId()] = ['shop' => null, 'vendor' => null];

            foreach ($order->getItems() as $item) {
                $shopId = $item->getShopId();

                if ($shopId && isset($shopMap[$shopId])) {
                    $context[$order->getId()] = [
                        'shop' => $shopMap[$shopId],
                        'vendor' => $shopMap[$shopId]->getOwner(),
                    ];

                    break;
                }
            }
        }

        return $context;
    }
}
