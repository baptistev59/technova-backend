<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Address;
use App\Entity\AttributeDefinition;
use App\Entity\AttributeValueDefinition;
use App\Entity\Category;
use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductAttributeSelection;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductBundleItem;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Entity\CustomerOrderItem;
use App\Entity\OrderDocument;
use App\Enum\DocumentType;
use App\Enum\OrderStatus;
use App\Form\Vendor\ProductType;
use App\Form\Vendor\ShopType;
use App\Image\ImageProfileRegistry;
use App\Image\ImageUploader;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\BrandRepository;
use App\Repository\CategoryRepository;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ProductVariantRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Repository\OrderDocumentRepository;
use App\Security\ViewerAccessChecker;
use App\Security\Voter\OrderVoter;
use App\Security\Voter\ProductVoter;
use App\Service\OrderDocumentGenerator;
use App\Service\OrderFulfillmentManager;
use App\Service\StockAlertService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Psr\Log\LoggerInterface;

#[Route('/mon-espace-vendeur')]
class VendorShopController extends AbstractController
{
    public function __construct(
        private readonly Security $security,
        private readonly ViewerAccessChecker $viewerAccessChecker,
        private readonly ShopRepository $shopRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SluggerInterface $slugger,
        private readonly ProductRepository $productRepository,
        private readonly ProductVariantRepository $productVariantRepository,
        private readonly StockAlertService $stockAlertService,
        private readonly CategoryRepository $categoryRepository,
        private readonly BrandRepository $brandRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly OrderDocumentRepository $documentRepository,
        private readonly OrderDocumentGenerator $documentGenerator,
        private readonly OrderFulfillmentManager $orderFulfillmentManager,
        private readonly ImageUploader $imageUploader,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
        #[Autowire(service: 'state_machine.customer_order')]
        private readonly WorkflowInterface $orderWorkflow,
        private readonly LoggerInterface $logger,
        #[Autowire(service: 'html_sanitizer.sanitizer.rich_text')]
        private readonly HtmlSanitizerInterface $richTextSanitizer,
    ) {
    }

    private ?bool $bundleTableExists = null;

    #[Route('/boutique', name: 'app_vendor_shop_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);

        $vendor = $user->getVendor();
        $existingShop = $vendor ? $this->shopRepository->findOneBy(['owner' => $vendor]) : null;
        $editMode = $request->query->getBoolean('edit');
        if ($request->isMethod('POST') && $existingShop instanceof Shop) {
            $editMode = true;
        }

        if ($existingShop instanceof Shop && !$editMode) {
            $dashboardOrders = $this->orderRepository->paginateForShop($existingShop, 1, 5);
            $shopProductMap = $this->mapShopProductIds($existingShop, $dashboardOrders['orders']);

            $statusCounts = array_merge([
                OrderStatus::Pending->value => 0,
                OrderStatus::Paid->value => 0,
                OrderStatus::Shipped->value => 0,
                OrderStatus::Cancelled->value => 0,
            ], $dashboardOrders['statusCounts']);

            $metrics = [
                'totalOrders' => $dashboardOrders['total'],
                'pendingOrders' => $statusCounts[OrderStatus::Pending->value] ?? 0,
                'paidOrders' => ($statusCounts[OrderStatus::Paid->value] ?? 0) + ($statusCounts[OrderStatus::Shipped->value] ?? 0),
                'revenue' => (float) $dashboardOrders['revenue'],
            ];

            $threshold = $this->stockAlertService->getThreshold();
            $lowStockCount = $this->productRepository->countLowStockForShop($existingShop, $threshold)
                + $this->productVariantRepository->countLowStockForShop($existingShop, $threshold);

            $statCards = [
                ['label' => 'Commandes totales', 'value' => $metrics['totalOrders'], 'trend' => 'Depuis l’ouverture', 'icon' => '🧾'],
                ['label' => 'En attente', 'value' => $metrics['pendingOrders'], 'trend' => 'Statut pending', 'icon' => '⏳'],
                ['label' => 'Payées / expédiées', 'value' => $metrics['paidOrders'], 'trend' => 'Commandes validées', 'icon' => '✅'],
                ['label' => 'Revenus cumulés', 'value' => number_format($metrics['revenue'], 2, ',', ' ').' €', 'trend' => 'Cumul boutique', 'icon' => '💶'],
                ['label' => 'Stock faible', 'value' => $lowStockCount, 'trend' => sprintf('≤ %d unités', $threshold), 'icon' => '⚠️'],
            ];

            $ordersPreview = [];
            foreach ($dashboardOrders['orders'] as $order) {
                $lines = [];
                $lineTotal = 0.0;
                $quantity = 0;
                foreach ($order->getItems() as $item) {
                    if (!isset($shopProductMap[$item->getProductId()])) {
                        continue;
                    }
                    $lineAmount = (float) $item->getLineTotal();
                    $lines[] = [
                        'name' => $item->getProductName(),
                        'quantity' => $item->getQuantity(),
                        'lineTotal' => $lineAmount,
                    ];
                    $lineTotal += $lineAmount;
                    $quantity += $item->getQuantity();
                }
                if ([] === $lines) {
                    continue;
                }
                $ordersPreview[] = [
                    'entity' => $order,
                    'lines' => array_slice($lines, 0, 3),
                    'moreLines' => max(0, count($lines) - 3),
                    'lineTotal' => $lineTotal,
                    'quantity' => $quantity,
                ];
            }

            $today = new \DateTimeImmutable('today');
            $dailyStart = $today->modify('-29 days');
            $monthlyStart = $today->modify('first day of this month')->modify('-11 months');
            $trendStart = $dailyStart < $monthlyStart ? $dailyStart : $monthlyStart;
            $ordersHistory = $this->orderRepository->findShopOrdersSince($existingShop, $trendStart);
            $validatedStatuses = [OrderStatus::Paid->value, OrderStatus::Shipped->value];

            $dailyTrend = [];
            for ($i = 0; $i < 30; ++$i) {
                $date = $dailyStart->modify(sprintf('+%d days', $i));
                $dailyTrend[$date->format('Y-m-d')] = [
                    'label' => $date->format('d/m'),
                    'value' => 0,
                ];
            }

            $monthlyTrend = [];
            for ($i = 0; $i < 12; ++$i) {
                $month = $monthlyStart->modify(sprintf('+%d months', $i));
                $monthlyTrend[$month->format('Y-m')] = [
                    'label' => ucfirst($month->format('M Y')),
                    'value' => 0.0,
                ];
            }

            foreach ($ordersHistory as $historyRow) {
                $createdAt = $historyRow['createdAt'];
                $lineTotal = $historyRow['total'];
                $status = $historyRow['status'];
                $dayKey = $createdAt->format('Y-m-d');
                if (isset($dailyTrend[$dayKey])) {
                    ++$dailyTrend[$dayKey]['value'];
                }
                $monthKey = $createdAt->format('Y-m');
                if (isset($monthlyTrend[$monthKey]) && in_array($status, $validatedStatuses, true)) {
                    $monthlyTrend[$monthKey]['value'] += $lineTotal;
                }
            }

            $dailyMax = max(array_column($dailyTrend, 'value')) ?: 1;
            $monthlyMax = max(array_column($monthlyTrend, 'value')) ?: 1;

            $dailyTrend = array_map(static function (array $point) use ($dailyMax) {
                $point['percent'] = (int) round(($point['value'] / $dailyMax) * 100);

                return $point;
            }, $dailyTrend);
            $monthlyTrend = array_map(static function (array $point) use ($monthlyMax) {
                $point['percent'] = (int) round(($point['value'] / $monthlyMax) * 100);

                return $point;
            }, $monthlyTrend);
            $latestProducts = $this->productRepository->findLatestPublishedForShop(shop: $existingShop, limit: 10);

            return $this->render('vendor/shop/existing.html.twig', [
                'vendor_nav' => $this->buildVendorNav('app_vendor_shop_new'),
                'shop' => $existingShop,
                'attributeStats' => $this->buildAttributeStats($existingShop),
                'stats' => $statCards,
                'orders_preview' => $ordersPreview,
                'orders_metrics' => $metrics,
                'status_badges' => $this->orderStatusBadges(),
                'sales_trend' => array_values($dailyTrend),
                'revenue_trend' => array_values($monthlyTrend),
                'latest_products' => $latestProducts,
            ]);
        }

        $session = $request->getSession();
        if (!$existingShop && !$vendor && !$session->get('vendor_terms_accepted')) {
            $this->addFlash('warning', 'Merci de valider les conditions vendeur avant de créer ta boutique.');

            return $this->redirectToRoute('app_vendor_terms');
        }

        if (!$vendor) {
            $vendor = (new Vendor())
                ->setOwner($user)
                ->setEmail($user->getEmail());
        }

        if (!$vendor->getAddress()) {
            $vendor->setAddress(new Address());
        }

        $vendorAddress = $vendor->getAddress();
        if ($vendorAddress) {
            if (null === $vendorAddress->isDefault()) {
                $vendorAddress->setIsDefault(false);
            }
            if (null === $vendorAddress->isBilling()) {
                $vendorAddress->setIsBilling(false);
            }
            if (null === $vendorAddress->isShipping()) {
                $vendorAddress->setIsShipping(false);
            }
        }

        if ($existingShop instanceof Shop) {
            $shop = $existingShop;
        } else {
            $shop = new Shop();
            $shop->setContactEmail($vendor->getEmail() ?? $user->getEmail() ?? '');
        }

        if (!$shop->getOwner()) {
            $shop->setOwner($vendor);
        }

        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->logFormErrors($form);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $isNewShop = null === $shop->getId();
            $vendor = $shop->getOwner();
            $isNewVendor = $vendor && null === $vendor->getId();

            if ($isNewVendor) {
                if (!$vendor->getCompanyName()) {
                    $vendor->setCompanyName($shop->getName() ?: sprintf('Boutique de %s', $user->getFirstname()));
                }
                if (!$vendor->getEmail()) {
                    $vendor->setEmail($user->getEmail());
                }
                $this->entityManager->persist($vendor);

                $roles = $user->getRoles();
                if (!in_array('ROLE_VENDOR', $roles, true)) {
                    $roles[] = 'ROLE_VENDOR';
                    $user->setRoles(array_values(array_unique($roles)));
                }
            }

            $shop->setOwner($vendor);
            if ($isNewShop) {
                $shop->setSlug($this->generateUniqueSlug((string) $shop->getName()));
            }

            $logo = $form->get('logoFile')->getData();
            $banner = $form->get('bannerFile')->getData();
            $this->handleUploads($shop, $logo, $banner);
            $this->sanitizeShopContent($shop);

            if ($isNewShop) {
                $this->entityManager->persist($shop);
            }
            $this->entityManager->flush();

            $session->remove('vendor_terms_accepted');

            $this->addFlash('success', $isNewShop ? 'Ta boutique est créée ! Tu peux maintenant ajouter tes produits.' : 'Ta boutique a été mise à jour.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        return $this->render('vendor/shop/new.html.twig', [
            'form' => $form->createView(),
            'shop' => $shop,
            'is_edit' => $editMode,
        ]);
    }

    #[Route('/produits', name: 'app_vendor_products', methods: ['GET'])]
    public function products(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();

        if (!$vendor) {
            $this->addFlash('warning', 'Tu dois d’abord créer ta boutique avant de gérer tes produits.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            $this->addFlash('warning', 'Tu dois publier ta boutique avant d’ajouter des produits.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $sort = (string) $request->query->get('sort', 'updated_desc');
        $statusQuery = $request->query->has('status') ? $request->query->get('status') : null;

        $filters = [
            'search' => $request->query->get('q'),
            'category' => $request->query->get('category'),
            'brand' => $request->query->get('brand'),
            'stock' => $request->query->get('stock'),
            'type' => $request->query->get('type'),
            'status' => $statusQuery,
        ];

        $queryFilters = $filters;
        if (null === $statusQuery) {
            $queryFilters['status'] = '1';
        }

        $perPage = 10;
        $result = $this->productRepository->filterForVendor($shop, $queryFilters, $page, $perPage, $sort);
        $products = $result['items'];
        $totalProducts = $result['total'];
        $totalPages = max(1, (int) ceil($totalProducts / $perPage));

        $categories = $this->categoryRepository->findBy([], ['name' => 'ASC']);
        $brands = $this->brandRepository->findBy([], ['name' => 'ASC']);

        $productTypes = [
            'simple' => 'Produit simple',
            'variable' => 'Produit variable',
            'grouped' => 'Produit groupé',
        ];

        $stockFilters = [
            'in_stock' => 'En stock',
            'low_stock' => 'Stock faible (seuil configuré)',
            'out_of_stock' => 'Rupture de stock',
        ];

        $statusFilters = [
            '1' => 'Publié',
            '0' => 'Brouillon',
        ];

        $sortOptions = [
            'updated_desc' => 'Dernière mise à jour (récent)',
            'updated_asc' => 'Dernière mise à jour (ancien)',
            'price_asc' => 'Prix croissant',
            'price_desc' => 'Prix décroissant',
            'name_asc' => 'Nom (A-Z)',
            'name_desc' => 'Nom (Z-A)',
        ];

        $vendorNav = $this->buildVendorNav('app_vendor_products');

        $productIds = array_map(static fn (Product $product) => $product->getId(), $products);

        return $this->render('vendor/product/index.html.twig', [
            'shop' => $shop,
            'products' => $products,
            'product_ids' => $productIds,
            'filters' => $filters,
            'current_sort' => $sort,
            'categories' => $categories,
            'brands' => $brands,
            'product_types' => $productTypes,
            'stock_filters' => $stockFilters,
            'status_filters' => $statusFilters,
            'sort_options' => $sortOptions,
            'pagination' => [
                'page' => $page,
                'pages' => $totalPages,
                'total' => $totalProducts,
                'per_page' => $perPage,
            ],
            'vendor_nav' => $vendorNav,
        ]);
    }

    #[Route('/mon-espace-vendeur/conditions', name: 'app_vendor_terms', methods: ['GET', 'POST'])]
    public function terms(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if ($vendor && $this->shopRepository->findOneBy(['owner' => $vendor])) {
            $this->addFlash('info', 'Tu as déjà une boutique active.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $form = $this->createFormBuilder()
            ->add('accept', \Symfony\Component\Form\Extension\Core\Type\CheckboxType::class, [
                'label' => 'J’accepte les conditions générales vendeur TechNova.',
                'mapped' => false,
                'required' => true,
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $request->getSession()->set('vendor_terms_accepted', true);

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        return $this->render('vendor/shop/terms.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/commandes', name: 'app_vendor_orders', methods: ['GET'])]
    public function orders(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();

        if (!$vendor) {
            $this->addFlash('warning', 'Crée d’abord ta boutique pour consulter tes commandes.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            $this->addFlash('warning', 'Publie ta boutique pour commencer à recevoir des commandes.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPageOptions = [10, 25, 50];
        $perPage = (int) $request->query->get('limit', '10');
        if (!in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }
        $statusParam = (string) $request->query->get('status', '');
        $allowedStatusKeys = array_keys($this->orderStatusBadges());
        $statusFilter = in_array($statusParam, $allowedStatusKeys, true) ? $statusParam : null;

        $result = $this->orderRepository->paginateForShop($shop, $page, $perPage, $statusFilter);
        $orders = $result['orders'];

        $orderViews = [];
        $pageRevenue = 0.0;
        foreach ($orders as $order) {
            $lines = [];
            $subtotal = 0.0;
            $quantity = 0;
            $items = $this->orderFulfillmentManager->getShopItems($shop, $order);
            foreach ($items as $item) {
                $lineAmount = (float) $item->getLineTotal();
                $lines[] = [
                    'name' => $item->getProductName(),
                    'quantity' => $item->getQuantity(),
                    'lineTotal' => $lineAmount,
                ];
                $subtotal += $lineAmount;
                $quantity += $item->getQuantity();
            }

            $pageRevenue += $subtotal;

            $orderViews[] = [
                'entity' => $order,
                'lines' => $lines,
                'lineTotal' => $subtotal,
                'quantity' => $quantity,
                'customer' => $this->resolveOrderCustomerName($order),
                'items' => $items,
                'shop_status' => $this->orderFulfillmentManager->describeShopStatus($shop, $order),
            ];
        }

        $statusCounts = array_merge([
            OrderStatus::Pending->value => 0,
            OrderStatus::Paid->value => 0,
            OrderStatus::Shipped->value => 0,
            OrderStatus::Cancelled->value => 0,
        ], $result['statusCounts']);

        $metrics = [
            'totalOrders' => $result['overallTotal'],
            'pendingOrders' => $statusCounts[OrderStatus::Pending->value] ?? 0,
            'paidOrders' => ($statusCounts[OrderStatus::Paid->value] ?? 0) + ($statusCounts[OrderStatus::Shipped->value] ?? 0),
            'revenue' => (float) $result['revenue'],
            'pageRevenue' => $pageRevenue,
        ];

        return $this->render('vendor/order/index.html.twig', [
            'shop' => $shop,
            'orders' => $orderViews,
            'metrics' => $metrics,
            'pagination' => [
                'page' => $result['page'],
                'pages' => $result['pages'],
                'total' => $result['total'],
                'per_page' => $perPage,
            ],
            'status_badges' => $this->orderStatusBadges(),
            'status_filter' => $statusFilter,
            'per_page' => $perPage,
            'per_page_options' => $perPageOptions,
            'vendor_nav' => $this->buildVendorNav('app_vendor_orders'),
        ]);
    }

    private function streamOrderDocument(CustomerOrder $order, DocumentType $type, string $filenamePrefix, Request $request, array $context = []): Response
    {
        $document = $this->findOrGenerateDocument($order, $type, $request->getSchemeAndHttpHost(), $context);

        $absolutePath = $this->getDocumentAbsolutePath($document);
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('Le document est introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath, 200, [
            'Content-Type' => 'application/pdf',
        ]);

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('%s-%s.pdf', $filenamePrefix, $order->getReference() ?? 'commande')
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function findOrGenerateDocument(CustomerOrder $order, DocumentType $type, string $baseUrl, array $context = []): OrderDocument
    {
        $document = null;
        if ([] === $context) {
            $document = $this->documentRepository->findOneBy([
                'order' => $order,
                'type' => $type,
            ]);

            if ($document instanceof OrderDocument) {
                $absolutePath = $this->getDocumentAbsolutePath($document);
                if (is_file($absolutePath)) {
                    return $document;
                }

                $this->deleteDocumentFile($document);
                $this->entityManager->remove($document);
                $this->entityManager->flush();
            }
        }

        $this->cleanupExistingDocuments($order, $type);

        $document = $this->documentGenerator->generate($order, $type, $baseUrl, $context);
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $document;
    }

    private function cleanupExistingDocuments(CustomerOrder $order, DocumentType $type): void
    {
        $documents = $this->documentRepository->findBy([
            'order' => $order,
            'type' => $type,
        ]);

        if ([] === $documents) {
            return;
        }

        foreach ($documents as $document) {
            $this->deleteDocumentFile($document);
            $this->entityManager->remove($document);
        }

        $this->entityManager->flush();
    }

    private function deleteDocumentFile(OrderDocument $document): void
    {
        $path = $this->getDocumentAbsolutePath($document);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function getDocumentAbsolutePath(OrderDocument $document): string
    {
        return $this->projectDir.'/public/'.ltrim($document->getPath(), '/');
    }

    private function resolveVendorShop(Request $request): Shop
    {
        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();

        if (!$vendor) {
            throw $this->createAccessDeniedException('Vendeur requis.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            throw $this->createNotFoundException('Boutique introuvable.');
        }

        return $shop;
    }

    #[Route('/commandes/{id}/facture', name: 'app_vendor_orders_invoice', methods: ['GET'])]
    public function invoice(Request $request, int $id): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->find($id);
        if (!$order instanceof CustomerOrder) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $this->denyAccessUnlessGranted(OrderVoter::MANAGE, $order);

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };

        if (!$isEligible) {
            $this->addFlash('error', 'Facture disponible uniquement après paiement.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        return $this->streamOrderDocument($order, DocumentType::INVOICE, 'facture', $request);
    }

    #[Route('/commandes/{id}/bon-de-livraison', name: 'app_vendor_orders_delivery', methods: ['GET'])]
    public function deliveryNote(Request $request, int $id): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $order = $this->orderRepository->find($id);
        if (!$order instanceof CustomerOrder) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $this->denyAccessUnlessGranted(OrderVoter::MANAGE, $order);

        $status = $order->getStatusEnum();
        $isEligible = match ($status) {
            OrderStatus::Paid, OrderStatus::Shipped => true,
            default => false,
        };

        if (!$isEligible) {
            $this->addFlash('error', 'Bon de livraison disponible uniquement après paiement.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        $shop = $this->resolveVendorShop($request);
        $vendorItems = $this->orderFulfillmentManager->getShopItems($shop, $order, true);

        if ([] === $vendorItems) {
            $this->addFlash('warning', 'Aucun article expédié pour ta boutique sur cette commande.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        return $this->streamOrderDocument(
            $order,
            DocumentType::DELIVERY,
            'bon-de-livraison',
            $request,
            ['vendor_items' => $vendorItems]
        );
    }

    #[Route('/statistiques', name: 'app_vendor_stats', methods: ['GET'])]
    public function stats(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();

        if (!$vendor) {
            $this->addFlash('warning', 'Crée ta boutique pour consulter tes statistiques.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            $this->addFlash('warning', 'Publie ta boutique pour accéder aux statistiques.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $statusMeta = [
            OrderStatus::Pending->value => ['label' => 'En attente', 'color' => '#f97316', 'fill' => 'rgba(249,115,22,0.12)'],
            OrderStatus::Paid->value => ['label' => 'Payee', 'color' => '#10b981', 'fill' => 'rgba(16,185,129,0.12)'],
            OrderStatus::Shipped->value => ['label' => 'Expediee', 'color' => '#0ea5e9', 'fill' => 'rgba(14,165,233,0.12)'],
            OrderStatus::Cancelled->value => ['label' => 'Annulee', 'color' => '#ef4444', 'fill' => 'rgba(239,68,68,0.12)'],
        ];
        $realStatuses = [OrderStatus::Paid->value, OrderStatus::Shipped->value];

        $today = new \DateTimeImmutable('today');
        $dailyStart = $today->modify('-29 days');
        $monthlyStart = $today->modify('first day of this month')->modify('-11 months');
        $trendStart = $dailyStart < $monthlyStart ? $dailyStart : $monthlyStart;
        $ordersHistory = $this->orderRepository->findShopOrdersSince($shop, $trendStart);

        $dailyLabels = [];
        $dailyKeyIndex = [];
        for ($i = 0; $i < 30; ++$i) {
            $date = $dailyStart->modify(sprintf('+%d days', $i));
            $dailyLabels[] = $date->format('d/m');
            $dailyKeyIndex[$date->format('Y-m-d')] = $i;
        }

        $monthlyLabels = [];
        $monthlyKeyIndex = [];
        for ($i = 0; $i < 12; ++$i) {
            $month = $monthlyStart->modify(sprintf('+%d months', $i));
            $monthlyLabels[] = $month->format('m/Y');
            $monthlyKeyIndex[$month->format('Y-m')] = $i;
        }

        $dailyStatusSeries = [];
        foreach ($statusMeta as $code => $meta) {
            $dailyStatusSeries[$code] = array_fill(0, count($dailyLabels), 0);
        }
        $dailyRevenueReal = array_fill(0, count($dailyLabels), 0.0);
        $dailyRevenueCancelled = array_fill(0, count($dailyLabels), 0.0);
        $monthlySalesCounts = array_fill(0, count($monthlyLabels), 0);
        $monthlyRevenueTotals = array_fill(0, count($monthlyLabels), 0.0);

        foreach ($ordersHistory as $historyRow) {
            $createdAt = $historyRow['createdAt'];
            $total = $historyRow['total'];
            $status = $historyRow['status'];

            $dayKey = $createdAt->format('Y-m-d');
            if (isset($dailyKeyIndex[$dayKey], $dailyStatusSeries[$status])) {
                ++$dailyStatusSeries[$status][$dailyKeyIndex[$dayKey]];
            }

            $monthKey = $createdAt->format('Y-m');
            if (isset($monthlyKeyIndex[$monthKey]) && in_array($status, $realStatuses, true)) {
                $monthIndex = $monthlyKeyIndex[$monthKey];
                ++$monthlySalesCounts[$monthIndex];
                $monthlyRevenueTotals[$monthIndex] += $total;
            }

            if (isset($dailyKeyIndex[$dayKey])) {
                $dayIndex = $dailyKeyIndex[$dayKey];
                if (in_array($status, $realStatuses, true)) {
                    $dailyRevenueReal[$dayIndex] += $total;
                } elseif ($status === OrderStatus::Cancelled->value) {
                    $dailyRevenueCancelled[$dayIndex] += $total;
                }
            }
        }

        $dailyStatusChart = [
            'labels' => $dailyLabels,
            'datasets' => [],
        ];
        foreach ($statusMeta as $code => $meta) {
            $dailyStatusChart['datasets'][] = [
                'label' => $meta['label'],
                'data' => $dailyStatusSeries[$code],
                'borderColor' => $meta['color'],
                'backgroundColor' => $meta['fill'],
                'borderWidth' => 2,
                'tension' => 0.35,
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        $monthlySalesChart = [
            'labels' => $monthlyLabels,
            'datasets' => [[
                'label' => 'Ventes confirmées',
                'data' => $monthlySalesCounts,
                'backgroundColor' => 'rgba(59,130,246,0.75)',
                'borderRadius' => 6,
                'borderSkipped' => false,
            ]],
        ];

        $dailyRevenueChart = [
            'labels' => $dailyLabels,
            'datasets' => [
                [
                    'label' => 'Revenus validés',
                    'data' => $dailyRevenueReal,
                    'backgroundColor' => 'rgba(16,185,129,0.85)',
                    'borderRadius' => 6,
                    'stack' => 'revenue',
                ],
                [
                    'label' => 'Commandes annulées',
                    'data' => $dailyRevenueCancelled,
                    'backgroundColor' => 'rgba(248,113,113,0.7)',
                    'borderRadius' => 6,
                    'stack' => 'revenue',
                ],
            ],
        ];

        $monthlyRevenueChart = [
            'labels' => $monthlyLabels,
            'datasets' => [[
                'label' => 'Revenus validés',
                'data' => $monthlyRevenueTotals,
                'backgroundColor' => 'rgba(99,102,241,0.85)',
                'borderRadius' => 6,
                'borderSkipped' => false,
            ]],
        ];

        return $this->render('vendor/stats/index.html.twig', [
            'shop' => $shop,
            'vendor_nav' => $this->buildVendorNav('app_vendor_stats'),
            'daily_status_chart' => $dailyStatusChart,
            'monthly_sales_chart' => $monthlySalesChart,
            'daily_revenue_chart' => $dailyRevenueChart,
            'monthly_revenue_chart' => $monthlyRevenueChart,
        ]);
    }

    #[Route('/commandes/{id}/statut', name: 'app_vendor_orders_update', methods: ['POST'])]
    public function updateOrderStatus(CustomerOrder $order, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createAccessDeniedException('Crée d’abord ta boutique.');
        }
        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop) {
            throw $this->createAccessDeniedException('Publie ta boutique pour gérer les commandes.');
        }

        $this->denyAccessUnlessGranted(OrderVoter::MANAGE, $order);

        if (!$this->orderBelongsToShop($order, $shop)) {
            throw $this->createAccessDeniedException('Commande introuvable pour cette boutique.');
        }

        if (!$this->isCsrfTokenValid('vendor_order_status_'.$order->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        $target = (string) $request->request->get('status');
        $transition = match ($target) {
            OrderStatus::Paid->value => 'pay',
            OrderStatus::Shipped->value => 'ship',
            OrderStatus::Cancelled->value => 'cancel',
            default => null,
        };

        if (!$transition || !$this->orderWorkflow->can($order, $transition)) {
            $this->addFlash('error', 'Transition de statut non autorisée.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        if ('ship' === $transition) {
            $this->orderFulfillmentManager->markShopItemsAsShipped($shop, $order);
            $this->addFlash('success', 'Les articles de ta boutique sont désormais marqués comme expédiés.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        if ('pay' === $transition) {
            $order->setPaidAt($order->getPaidAt() ?? new \DateTimeImmutable());
        }

        $this->orderWorkflow->apply($order, $transition, [
            'triggered_by' => sprintf('vendor_dashboard:%s', $user->getUserIdentifier()),
            'payload' => ['shop_id' => $shop->getId()],
        ]);

        $successMessage = match ($transition) {
            'pay' => 'Commande marquée comme payée.',
            'cancel' => 'Commande annulée.',
            default => 'Commande mise à jour.',
        };

        $this->entityManager->flush();
        $this->addFlash('success', $successMessage);

        return $this->redirectToRoute('app_vendor_orders');
    }

    #[Route('/commandes/{order}/articles/{item}/annuler-expedition', name: 'app_vendor_order_item_cancel_delivery', methods: ['POST'])]
    public function cancelOrderItemDelivery(CustomerOrder $order, CustomerOrderItem $item, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createAccessDeniedException('Crée d’abord ta boutique.');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            throw $this->createAccessDeniedException('Publie ta boutique pour gérer les commandes.');
        }

        $this->denyAccessUnlessGranted(OrderVoter::MANAGE, $order);

        if ($item->getCustomerOrder()?->getId() !== $order->getId()) {
            throw $this->createNotFoundException('Article introuvable pour cette commande.');
        }

        $shopItems = $this->orderFulfillmentManager->getShopItems($shop, $order);
        if (!in_array($item, $shopItems, true)) {
            throw $this->createAccessDeniedException('Cet article ne fait pas partie de ta boutique.');
        }

        if (!$this->isCsrfTokenValid('vendor_order_item_cancel_delivery_'.$item->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');

            return $this->redirectToRoute('app_vendor_orders');
        }

        $this->orderFulfillmentManager->markItemAsPending($item);
        $this->addFlash('success', 'L’expédition de cet article a été annulée.');

        return $this->redirectToRoute('app_vendor_orders');
    }

    #[Route('/produits/nouveau', name: 'app_vendor_product_new', methods: ['GET', 'POST'])]
    public function createProduct(Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);
        $vendor = $user->getVendor();

        if (!$vendor) {
            $this->addFlash('warning', 'Tu dois d’abord créer ta boutique avant d’ajouter des produits.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
        if (!$shop instanceof Shop) {
            $this->addFlash('warning', 'Tu dois publier ta boutique avant d’ajouter des produits.');

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        $product = new Product();
        $product->setShop($shop);
        $product->setType('simple');
        $product->setIsPublished(false);

        $form = $this->createForm(ProductType::class, $product);
        $this->prefillPromoPercent($form, $product);
        $form->handleRequest($request);
        $variantAction = (string) $request->request->get('_action', '');
        if ($form->isSubmitted()) {
            if ('delete_all_variants' === $variantAction) {
                $this->deleteAllVariants($product);
                $this->entityManager->flush();
                $this->addFlash('success', 'Toutes les variantes ont été supprimées.');

                return $this->redirectAfterVariantAction($product);
            }
            if (str_starts_with($variantAction, 'delete_variant_')) {
                $variantId = (int) substr($variantAction, strlen('delete_variant_'));
                if ($variantId > 0) {
                    $this->deleteVariantById($product, $variantId);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'La variante a été supprimée.');
                }

                return $this->redirectAfterVariantAction($product);
            }
        }
        $attributeOptions = $this->getAttributeDefinitionsData($shop);
        $selectionState = $form->isSubmitted()
            ? $this->parseAttributeSelectionPayload($request)
            : $this->getProductAttributeSelectionState($product);
        $bundleCandidates = $this->getBundleCandidatesData($shop, $product);
        $bundleState = $form->isSubmitted()
            ? $this->parseBundleItemsPayload($request)
            : $this->getProductBundleState($product);
        $this->applyProductFormValidation($form, $product);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureProductSlug($product);

            $this->synchronizePromoFields($product, $form);
            $this->sanitizeProductContent($product);
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);
            $this->syncProductAttributeSelections($product, $selectionState);
            if ('generate_variants' === $variantAction) {
                $this->syncProductVariantsFromAttributes($product);
            }
            $this->updateVariantDetailsFromRequest($product, $request);
            if ('grouped' === $product->getType()) {
                $this->syncProductBundleItems($product, $bundleState);
                $this->ensureGroupedCategory($product);
                $product->setLowStockThreshold(null);
            } else {
                $this->clearProductBundleItems($product);
            }

            if (null !== $product->getTaxZone()) {
                $product->setTaxClass($product->getTaxZone()->getTaxClass());
            }

            $this->entityManager->persist($product);
            $this->entityManager->flush();

            if ('generate_variants' === $variantAction) {
                $this->addFlash('success', 'Variantes générées. Tu peux maintenant ajuster chaque variante.');

                return $this->redirectToRoute('app_vendor_product_edit', ['id' => $product->getId()]);
            }

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = $this->buildVendorNav('app_vendor_products');

        return $this->render('vendor/product/form.html.twig', [
            'form' => $form->createView(),
            'shop' => $shop,
            'product' => $product,
            'is_edit' => false,
            'vendor_nav' => $vendorNav,
            'attribute_options' => $attributeOptions,
            'attribute_selection_state' => $selectionState,
            'bundle_candidates' => $bundleCandidates,
            'bundle_selection_state' => $bundleState,
        ]);
    }

    #[Route('/produits/{id}/modifier', name: 'app_vendor_product_edit', methods: ['GET', 'POST'])]
    public function editProduct(Product $product, Request $request): Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $user = $this->resolveViewer($request);

        $this->denyAccessUnlessGranted(ProductVoter::MANAGE, $product);

        $form = $this->createForm(ProductType::class, $product);
        $this->prefillPromoPercent($form, $product);
        $form->handleRequest($request);
        $variantAction = (string) $request->request->get('_action', '');
        if ($form->isSubmitted()) {
            if ('delete_all_variants' === $variantAction) {
                $this->deleteAllVariants($product);
                $this->entityManager->flush();
                $this->addFlash('success', 'Toutes les variantes ont été supprimées.');

                return $this->redirectAfterVariantAction($product);
            }
            if (str_starts_with($variantAction, 'delete_variant_')) {
                $variantId = (int) substr($variantAction, strlen('delete_variant_'));
                if ($variantId > 0) {
                    $this->deleteVariantById($product, $variantId);
                    $this->entityManager->flush();
                    $this->addFlash('success', 'La variante a été supprimée.');
                }

                return $this->redirectAfterVariantAction($product);
            }
        }
        $attributeOptions = $this->getAttributeDefinitionsData($product->getShop());
        $selectionState = $form->isSubmitted()
            ? $this->parseAttributeSelectionPayload($request)
            : $this->getProductAttributeSelectionState($product);
        $bundleCandidates = $this->getBundleCandidatesData($product->getShop(), $product);
        $bundleState = $form->isSubmitted()
            ? $this->parseBundleItemsPayload($request)
            : $this->getProductBundleState($product);
        $this->applyProductFormValidation($form, $product);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureProductSlug($product);

            $this->synchronizePromoFields($product, $form);
            $this->sanitizeProductContent($product);
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);
            $this->syncProductAttributeSelections($product, $selectionState);
            if ('generate_variants' === $variantAction) {
                $this->syncProductVariantsFromAttributes($product);
            }
            $this->updateVariantDetailsFromRequest($product, $request);
            if ('grouped' === $product->getType()) {
                $this->syncProductBundleItems($product, $bundleState);
                $this->ensureGroupedCategory($product);
                $product->setLowStockThreshold(null);
            } else {
                $this->clearProductBundleItems($product);
            }

            if (null !== $product->getTaxZone()) {
                $product->setTaxClass($product->getTaxZone()->getTaxClass());
            }

            $this->entityManager->flush();

            if ('generate_variants' === $variantAction) {
                $this->addFlash('success', 'Variantes mises à jour.');

                return $this->redirectToRoute('app_vendor_product_edit', ['id' => $product->getId()]);
            }

            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = $this->buildVendorNav('app_vendor_products');

        return $this->render('vendor/product/form.html.twig', [
            'form' => $form->createView(),
            'shop' => $product->getShop(),
            'product' => $product,
            'is_edit' => true,
            'vendor_nav' => $vendorNav,
            'attribute_options' => $attributeOptions,
            'attribute_selection_state' => $selectionState,
            'bundle_candidates' => $bundleCandidates,
            'bundle_selection_state' => $bundleState,
        ]);
    }

    #[Route('/produits/{id}/toggle-publication', name: 'app_vendor_product_toggle_publish', methods: ['POST'])]
    public function toggleProductPublication(Product $product, Request $request): Response
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_toggle_'.$product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $product->setIsPublished(!$product->isPublished());
        $this->entityManager->flush();

        $this->addFlash('success', $product->isPublished() ? 'Le produit est publié.' : 'Le produit est repassé en brouillon.');

        return $this->redirectToRoute('app_vendor_products');
    }

    #[Route('/produits/{id}/dupliquer', name: 'app_vendor_product_duplicate', methods: ['POST'])]
    public function duplicateProduct(Product $product, Request $request): Response
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_duplicate_'.$product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $duplicate = $this->duplicateProductEntity($product);
        $this->entityManager->persist($duplicate);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Le produit "%s" a été dupliqué.', $product->getName()));

        return $this->redirectToRoute('app_vendor_product_edit', ['id' => $duplicate->getId()]);
    }

    #[Route('/produits/{id}/supprimer', name: 'app_vendor_product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request): Response
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_delete_'.$product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->addFlash('success', 'Le produit a été supprimé.');

        return $this->redirectToRoute('app_vendor_products');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getAttributeDefinitionsData(?Shop $shop): array
    {
        if (!$shop) {
            return [];
        }

        /** @var AttributeDefinition[] $definitions */
        $definitions = $this->attributeDefinitionRepository->findBy(
            ['shop' => $shop],
            ['position' => 'ASC', 'name' => 'ASC']
        );
        $data = [];

        foreach ($definitions as $definition) {
            $values = [];
            foreach ($definition->getValues() as $value) {
                if (null === $value->getId()) {
                    continue;
                }
                $values[] = [
                    'id' => $value->getId(),
                    'label' => $value->getLabel(),
                    'value' => $value->getValue(),
                    'position' => $value->getPosition(),
                ];
            }
            usort($values, static fn (array $a, array $b) => [$a['position'], $a['label']] <=> [$b['position'], $b['label']]);

            $data[] = [
                'id' => $definition->getId(),
                'name' => $definition->getName(),
                'slug' => $definition->getSlug(),
                'inputType' => $definition->getInputType(),
                'position' => $definition->getPosition(),
                'values' => $values,
            ];
        }

        return $data;
    }

    private function bundleTableExists(): bool
    {
        if (null !== $this->bundleTableExists) {
            return $this->bundleTableExists;
        }

        try {
            $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
            $this->bundleTableExists = $schemaManager->tablesExist(['product_bundle_item']);
        } catch (\Throwable) {
            $this->bundleTableExists = false;
        }

        return $this->bundleTableExists;
    }

    private function applyProductFormValidation(FormInterface $form, Product $product): void
    {
        if (!$form->isSubmitted() || 'grouped' === $product->getType()) {
            return;
        }

        if ($product->getPrice() <= 0) {
            $form->get('price')->addError(new FormError('Indique un prix HT.'));
        }

        if (!$product->getCategory()) {
            $form->get('category')->addError(new FormError('Merci de sélectionner une catégorie.'));
        }
    }

    private function ensureGroupedCategory(Product $product): void
    {
        if ($product->getCategory() || 'grouped' !== $product->getType()) {
            return;
        }

        $slug = 'produits-groupes';
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category) {
            $category = (new Category())
                ->setName('Produits groupés')
                ->setSlug((string) $this->slugger->slug('Produits groupés')->lower());
            $this->entityManager->persist($category);
        }

        $product->setCategory($category);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getBundleCandidatesData(?Shop $shop, ?Product $current = null): array
    {
        if (!$shop || !$this->bundleTableExists()) {
            return [];
        }

        $candidates = $this->productRepository->findBy(['shop' => $shop], ['name' => 'ASC']);
        $data = [];

        foreach ($candidates as $candidate) {
            if (!$candidate instanceof Product || !$candidate->getId()) {
                continue;
            }

            if ($current && $candidate->getId() === $current->getId()) {
                continue;
            }

            $type = $candidate->getType() ?: 'simple';
            if ('grouped' === $type) {
                continue;
            }

            $range = $this->computeProductPriceRange($candidate);

            $data[] = [
                'id' => $candidate->getId(),
                'name' => $candidate->getName(),
                'sku' => $candidate->getSku(),
                'type' => $type,
                'typeLabel' => $this->humanizeProductType($type),
                'priceMin' => $range['min'],
                'priceMax' => $range['max'],
                'priceLabel' => $range['label'],
            ];
        }

        return $data;
    }

    /**
     * @return array<int, array{attribute:int, values:array<int>}>
     */
    private function getProductAttributeSelectionState(Product $product): array
    {
        $state = [];
        foreach ($product->getAttributeSelections() as $selection) {
            $attribute = $selection->getAttribute();
            if (!$attribute || null === $attribute->getId()) {
                continue;
            }

            $valueIds = [];
            foreach ($selection->getValues() as $value) {
                if (null !== $value->getId()) {
                    $valueIds[] = $value->getId();
                }
            }
            if ([] === $valueIds) {
                continue;
            }

            $state[] = [
                'attribute' => $attribute->getId(),
                'values' => array_values(array_unique($valueIds)),
            ];
        }

        return $state;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getProductBundleState(Product $product): array
    {
        if (!$this->bundleTableExists()) {
            return [];
        }

        $state = [];
        foreach ($product->getBundleItems() as $item) {
            $component = $item->getComponent();
            if (!$component || null === $component->getId()) {
                continue;
            }

            $state[] = [
                'product' => $component->getId(),
                'required' => $item->isRequired(),
                'position' => $item->getPosition(),
            ];
        }

        usort($state, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return $state;
    }

    /**
     * @return array<int, array{attribute:int, values:array<int>}>
     */
    private function parseAttributeSelectionPayload(Request $request): array
    {
        $raw = $request->request->get('attribute_selections');
        if (!is_string($raw) || '' === trim($raw)) {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $item) {
            if (!is_array($item) || !isset($item['attribute'])) {
                continue;
            }

            $attributeId = (int) $item['attribute'];
            if ($attributeId <= 0) {
                continue;
            }

            $rawValues = $item['values'];
            if (!is_array($rawValues)) {
                $rawValues = [];
            }
            $valueIds = array_values(array_unique(array_filter(array_map(
                static fn ($value) => is_numeric($value) ? (int) $value : null,
                $rawValues
            ), static fn ($value) => null !== $value)));

            $results[] = [
                'attribute' => $attributeId,
                'values' => $valueIds,
            ];
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseBundleItemsPayload(Request $request): array
    {
        $raw = $request->request->get('bundle_items');
        if (!is_string($raw) || '' === trim($raw)) {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach ($data as $index => $entry) {
            if (!is_array($entry) || !isset($entry['product'])) {
                continue;
            }

            $productId = (int) $entry['product'];
            if ($productId <= 0) {
                continue;
            }

            $results[] = [
                'product' => $productId,
                'required' => isset($entry['required']) ? (bool) $entry['required'] : false,
                'position' => (int) ($entry['position'] ?? $index),
            ];
        }

        usort($results, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return $results;
    }

    /**
     * @param array<int, array{attribute:int, values:array<int>}> $payload
     */
    private function syncProductAttributeSelections(Product $product, array $payload): void
    {
        foreach ($product->getAttributeSelections()->toArray() as $existing) {
            $product->removeAttributeSelection($existing);
            $this->entityManager->remove($existing);
        }

        foreach ($payload as $item) {
            $attribute = $this->attributeDefinitionRepository->find($item['attribute']);
            if (!$attribute) {
                continue;
            }

            $valueIds = $item['values'];
            if ([] === $valueIds) {
                continue;
            }

            $selection = (new ProductAttributeSelection())
                ->setProduct($product)
                ->setAttribute($attribute);

            foreach ($attribute->getValues() as $value) {
                if (null !== $value->getId() && in_array($value->getId(), $valueIds, true)) {
                    $selection->addValue($value);
                }
            }

            if (0 === $selection->getValues()->count()) {
                continue;
            }

            $product->addAttributeSelection($selection);
            $this->entityManager->persist($selection);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $payload
     */
    private function syncProductBundleItems(Product $product, array $payload): void
    {
        if (!$this->bundleTableExists()) {
            return;
        }

        $this->clearProductBundleItems($product);

        if ([] === $payload) {
            $this->refreshGroupedProductPrice($product);

            return;
        }

        $seen = [];
        $position = 0;

        foreach ($payload as $entry) {
            $componentId = (int) ($entry['product'] ?? 0);
            if ($componentId <= 0 || isset($seen[$componentId])) {
                continue;
            }

            if ($componentId === $product->getId()) {
                continue;
            }

            $component = $this->productRepository->find($componentId);
            if (!$component) {
                continue;
            }

            if ('grouped' === $component->getType()) {
                continue;
            }

            if ($component->getShop()?->getId() !== $product->getShop()?->getId()) {
                continue;
            }

            $item = (new ProductBundleItem())
                ->setBundle($product)
                ->setComponent($component)
                ->setPosition($position++)
                ->setIsRequired((bool) ($entry['required'] ?? false));

            $product->addBundleItem($item);
            $this->entityManager->persist($item);
            $seen[$componentId] = true;
        }

        $this->refreshGroupedProductPrice($product);
    }

    private function clearProductBundleItems(Product $product): void
    {
        if (!$this->bundleTableExists()) {
            return;
        }

        foreach ($product->getBundleItems()->toArray() as $item) {
            $product->removeBundleItem($item);
            $this->entityManager->remove($item);
        }

        $this->refreshGroupedProductPrice($product);
    }

    /**
     * @return array{min:float|null, max:float|null, label:string}
     */
    private function computeProductPriceRange(Product $product, array $visited = []): array
    {
        $prices = $this->collectEffectivePrices($product, $visited);
        if ([] === $prices) {
            return [
                'min' => null,
                'max' => null,
                'label' => '—',
            ];
        }

        sort($prices, SORT_NUMERIC);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $format = static fn (float $value): string => number_format($value, 2, ',', ' ').' €';
        $label = $min === $max ? $format($min) : sprintf('%s – %s', $format($min), $format($max));

        return [
            'min' => $min,
            'max' => $max,
            'label' => $label,
        ];
    }

    /**
     * @return float[]
     */
    private function collectEffectivePrices(Product $product, array $visited = []): array
    {
        $prices = [];
        $productId = $product->getId();
        if (null !== $productId) {
            if (in_array($productId, $visited, true)) {
                return [];
            }
            $visited[] = $productId;
        }

        if ('grouped' === $product->getType()) {
            foreach ($product->getBundleItems() as $item) {
                $component = $item->getComponent();
                if ($component) {
                    $prices = array_merge($prices, $this->collectEffectivePrices($component, $visited));
                }
            }

            return $prices;
        }

        if ($product->getVariants()->count() > 0) {
            foreach ($product->getVariants() as $variant) {
                $price = $variant->getPromoPrice();
                if (null === $price || $price <= 0) {
                    $price = $variant->getPrice();
                }
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
        } else {
            $price = $product->getPromoPrice();
            if (null === $price || $price <= 0 || $price >= $product->getPrice()) {
                $price = $product->getPrice();
            }
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        return $prices;
    }

    private function refreshGroupedProductPrice(Product $product): void
    {
        if ('grouped' !== $product->getType() || !$this->bundleTableExists()) {
            return;
        }

        $range = $this->computeProductPriceRange($product);
        if (null !== $range['min']) {
            $product->setPrice($range['min']);
        }
        $product->setPromoPrice(null);
    }

    private function humanizeProductType(?string $type): string
    {
        return match ($type) {
            'variable' => 'Produit variable',
            'grouped' => 'Produit groupé',
            default => 'Produit simple',
        };
    }

    private function syncProductVariantsFromAttributes(Product $product): void
    {
        if ('variable' !== $product->getType()) {
            return;
        }

        $attributeSets = [];
        foreach ($product->getAttributeSelections() as $selection) {
            $attribute = $selection->getAttribute();
            if (!$attribute) {
                continue;
            }

            $values = $selection->getValues()->toArray();
            if ([] === $values) {
                continue;
            }

            usort($values, static function ($a, $b) {
                return [$a->getPosition(), $a->getLabel()] <=> [$b->getPosition(), $b->getLabel()];
            });

            $attributeSets[] = [
                'attribute' => $attribute,
                'values' => $values,
            ];
        }

        if ([] === $attributeSets) {
            foreach ($product->getVariants()->toArray() as $variant) {
                $product->removeVariant($variant);
                $this->entityManager->remove($variant);
            }

            return;
        }

        $combinations = $this->buildVariantCombinations($attributeSets);

        $existing = [];
        foreach ($product->getVariants() as $variant) {
            $key = $this->buildVariantKey($variant->getConfiguration());
            if ($key) {
                $existing[$key] = $variant;
            }
        }

        $seen = [];
        foreach ($combinations as $combination) {
            $configuration = [];
            $metadata = [];
            foreach ($combination as $entry) {
                $attribute = $entry['attribute'];
                $value = $entry['value'];
                $attributeKey = $attribute->getSlug() ?: ('attribute_'.$attribute->getId());
                $configuration[$attributeKey] = $value->getValue();
                $metadata[$attribute->getName() ?? $attributeKey] = $value->getLabel();
            }

            ksort($configuration);
            $key = $this->buildVariantKey($configuration);
            if (!$key) {
                continue;
            }

            $seen[] = $key;

            if (isset($existing[$key])) {
                $variant = $existing[$key];
                $variant->setConfiguration($configuration);
                $variant->setMetadata($metadata);
                continue;
            }

            $variant = (new ProductVariant())
                ->setProduct($product)
                ->setPrice($product->getPrice())
                ->setPromoPrice(null)
                ->setStock($product->getStock())
                ->setLowStockThreshold($product->getLowStockThreshold())
                ->setIsAvailable(true)
                ->setConfiguration($configuration)
                ->setMetadata($metadata)
                ->setSku($this->generateVariantSku($product, $configuration));

            $mainImage = $this->resolveProductMainImage($product);
            if ($mainImage) {
                $variant->setImagePath($mainImage->getUrl());
            }

            $product->addVariant($variant);
            $this->entityManager->persist($variant);
        }

        foreach ($product->getVariants()->toArray() as $variant) {
            $key = $this->buildVariantKey($variant->getConfiguration());
            if ($key && !in_array($key, $seen, true)) {
                $product->removeVariant($variant);
                $this->entityManager->remove($variant);
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $attributeSets
     *
     * @return array<int, array<int, array{attribute: AttributeDefinition, value: AttributeValueDefinition}>>
     */
    private function buildVariantCombinations(array $attributeSets): array
    {
        if ([] === $attributeSets) {
            return [];
        }

        $result = [[]];

        foreach ($attributeSets as $set) {
            $next = [];
            foreach ($result as $partial) {
                foreach ($set['values'] as $value) {
                    $combo = $partial;
                    $combo[] = [
                        'attribute' => $set['attribute'],
                        'value' => $value,
                    ];
                    $next[] = $combo;
                }
            }
            $result = $next;
        }

        return $result;
    }

    private function buildVariantKey(?array $configuration): ?string
    {
        if (empty($configuration)) {
            return null;
        }

        ksort($configuration);

        return implode('|', array_map(
            static fn ($attribute, $value) => sprintf('%s=%s', $attribute, $value),
            array_keys($configuration),
            $configuration
        ));
    }

    private function generateVariantSku(Product $product, array $configuration): string
    {
        $base = strtoupper(substr((string) ($product->getSku() ?: $product->getSlug() ?: 'VAR'), 0, 6));
        $hash = substr(md5(json_encode($configuration, JSON_THROW_ON_ERROR)), 0, 6);

        return sprintf('%s-%s', $base, $hash);
    }

    private function resolveProductMainImage(Product $product): ?ProductImage
    {
        foreach ($product->getImages() as $image) {
            if ($image->isMain()) {
                return $image;
            }
        }

        return $product->getImages()->first() ?: null;
    }

    private function deleteAllVariants(Product $product): void
    {
        foreach ($product->getVariants()->toArray() as $variant) {
            $product->removeVariant($variant);
            $this->entityManager->remove($variant);
        }
    }

    private function deleteVariantById(Product $product, int $variantId): void
    {
        foreach ($product->getVariants() as $variant) {
            if ($variant->getId() === $variantId) {
                $product->removeVariant($variant);
                $this->entityManager->remove($variant);
                break;
            }
        }
    }

    private function redirectAfterVariantAction(Product $product): RedirectResponse
    {
        if ($product->getId()) {
            return $this->redirectToRoute('app_vendor_product_edit', ['id' => $product->getId()]);
        }

        return $this->redirectToRoute('app_vendor_product_new');
    }

    /**
     * @return RedirectResponse|Response|null
     */
    private function guardProductAction(Product $product, Request $request): ?Response
    {
        if ($response = $this->viewerAccessChecker->requireViewer($this->security->getUser(), $request->getSession())) {
            return $response;
        }

        $this->denyAccessUnlessGranted(ProductVoter::MANAGE, $product);

        return null;
    }

    private function duplicateProductEntity(Product $source): Product
    {
        $name = trim(($source->getName() ?? 'Produit').' (Copie)');
        $clone = (new Product())
            ->setName($name)
            ->setShortDescription($source->getShortDescription())
            ->setDescription($source->getDescription())
            ->setPrice($source->getPrice())
            ->setPromoPrice($source->getPromoPrice())
            ->setStock($source->getStock())
            ->setLowStockThreshold($source->getLowStockThreshold())
            ->setSku(null)
            ->setBarcode(null)
            ->setKeywords($source->getKeywords())
            ->setType($source->getType())
            ->setIsFeatured($source->isFeatured())
            ->setIsPublished(false)
            ->setCategory($source->getCategory())
            ->setBrand($source->getBrand())
            ->setShop($source->getShop());

        $clone->setSlug($this->generateUniqueProductSlug($clone->getName() ?? 'produit'));

        foreach ($source->getImages() as $image) {
            $copyImage = (new ProductImage())
                ->setUrl((string) $image->getUrl())
                ->setAlt($image->getAlt())
                ->setTitle($image->getTitle())
                ->setCaption($image->getCaption())
                ->setPosition($image->getPosition())
                ->setIsMain($image->isMain())
                ->setFileSize($image->getFileSize())
                ->setMimeType($image->getMimeType());

            $clone->addImage($copyImage);
        }

        foreach ($source->getAttributes() as $attribute) {
            $copyAttribute = (new ProductAttribute())
                ->setName((string) $attribute->getName())
                ->setSlug($attribute->getSlug() ?: (string) $this->slugger->slug((string) $attribute->getName())->lower())
                ->setInputType($attribute->getInputType())
                ->setPosition($attribute->getPosition());

            foreach ($attribute->getValues() as $value) {
                $copyValue = (new ProductAttributeValue())
                    ->setValue((string) $value->getValue())
                    ->setSlug($value->getSlug() ?: (string) $this->slugger->slug((string) $value->getValue())->lower())
                    ->setColorHex($value->getColorHex());

                $copyAttribute->addValue($copyValue);
            }

            $clone->addAttribute($copyAttribute);
        }

        foreach ($source->getAttributeSelections() as $selection) {
            $copySelection = (new ProductAttributeSelection())
                ->setAttribute($selection->getAttribute());

            foreach ($selection->getValues() as $value) {
                $copySelection->addValue($value);
            }

            $clone->addAttributeSelection($copySelection);
        }

        foreach ($source->getVariants() as $variant) {
            $copyVariant = (new ProductVariant())
                ->setPrice($variant->getPrice())
                ->setPromoPrice($variant->getPromoPrice())
                ->setStock($variant->getStock())
                ->setWeight($variant->getWeight())
                ->setLowStockThreshold($variant->getLowStockThreshold())
                ->setIsAvailable($variant->isAvailable())
                ->setImagePath($variant->getImagePath())
                ->setConfiguration($variant->getConfiguration())
                ->setMetadata($variant->getMetadata())
                ->setSku($variant->getSku() ? $variant->getSku().'-copie' : null)
                ->setBarcode($variant->getBarcode() ? $variant->getBarcode().'-copie' : null);

            $clone->addVariant($copyVariant);
        }

        foreach ($source->getBundleItems() as $item) {
            $component = $item->getComponent();
            if (!$component) {
                continue;
            }
            $copyItem = (new ProductBundleItem())
                ->setComponent($component)
                ->setPosition($item->getPosition())
                ->setIsRequired($item->isRequired());

            $clone->addBundleItem($copyItem);
        }

        return $clone;
    }

    private function updateVariantDetailsFromRequest(Product $product, Request $request): void
    {
        if ($product->getVariants()->isEmpty()) {
            return;
        }

        $payload = $request->request->all('variants');
        if (!is_array($payload) || [] === $payload) {
            return;
        }

        foreach ($product->getVariants() as $variant) {
            $id = $variant->getId();
            if (null === $id || !isset($payload[$id]) || !is_array($payload[$id])) {
                continue;
            }

            $data = $payload[$id];

            $price = $this->normalizeFloat($data['price'] ?? null);
            if (null !== $price && $price >= 0) {
                $variant->setPrice($price);
            }

            $promo = $this->normalizeFloat($data['promoPrice'] ?? null);
            if (null !== $promo && $promo > 0 && ($price ?? $variant->getPrice()) > $promo) {
                $variant->setPromoPrice($promo);
            } else {
                $variant->setPromoPrice(null);
            }

            $stock = $this->normalizeInt($data['stock'] ?? null);
            if (null !== $stock && $stock >= 0) {
                $variant->setStock($stock);
            }

            $weight = $this->normalizeFloat($data['weight'] ?? null);
            if (null !== $weight && $weight >= 0) {
                $variant->setWeight($weight);
            } elseif (array_key_exists('weight', $data)) {
                $variant->setWeight(null);
            }

            $lowStock = $this->normalizeInt($data['lowStockThreshold'] ?? null);
            if (null !== $lowStock && $lowStock >= 0) {
                $variant->setLowStockThreshold($lowStock);
            } elseif (array_key_exists('lowStockThreshold', $data)) {
                $variant->setLowStockThreshold(null);
            }

            if (array_key_exists('sku', $data)) {
                $variant->setSku((string) $data['sku']);
            }

            $variant->setIsAvailable(isset($data['isAvailable']) && '1' === (string) $data['isAvailable']);
        }
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeInt(mixed $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildVendorNav(string $activeRoute): array
    {
        return [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => 'app_vendor_shop_new' === $activeRoute, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => 'app_vendor_products' === $activeRoute, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => 'app_vendor_attributes' === $activeRoute, 'path' => 'app_vendor_attributes'],
            ['label' => 'Taux TVA', 'icon' => '💱', 'active' => 'app_vendor_vatrates' === $activeRoute, 'path' => 'app_vendor_vatrates'],
            ['label' => 'Zones TVA', 'icon' => '🌍', 'active' => 'app_vendor_taxzones' === $activeRoute, 'path' => 'app_vendor_taxzones'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => 'app_vendor_orders' === $activeRoute, 'path' => 'app_vendor_orders'],
            ['label' => 'Retours', 'icon' => '↩️', 'active' => 'app_vendor_returns' === $activeRoute, 'path' => 'app_vendor_returns'],
            ['label' => 'Livraison', 'icon' => '🚚', 'active' => 'app_vendor_shipping_index' === $activeRoute, 'path' => 'app_vendor_shipping_index'],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => 'app_vendor_stats' === $activeRoute, 'path' => 'app_vendor_stats'],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];
    }

    /**
     * @param array<int, CustomerOrder> $orders
     *
     * @return array<int, true>
     */
    private function mapShopProductIds(Shop $shop, array $orders): array
    {
        $productIds = [];
        foreach ($orders as $order) {
            foreach ($order->getItems() as $item) {
                $productIds[] = $item->getProductId();
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds, static fn ($id) => is_int($id) || ctype_digit((string) $id))));
        if ([] === $productIds) {
            return [];
        }

        $rows = $this->productRepository->createQueryBuilder('p')
            ->select('p.id')
            ->where('p.shop = :shop')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('shop', $shop)
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getScalarResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['id']] = true;
        }

        return $map;
    }

    /**
     * @return array<string, array{label:string, class:string}>
     */
    private function orderStatusBadges(): array
    {
        return [
            OrderStatus::Pending->value => [
                'label' => 'En attente',
                'class' => 'bg-amber-50 text-amber-700 border border-amber-200',
            ],
            OrderStatus::Paid->value => [
                'label' => 'Payee',
                'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            ],
            OrderStatus::Shipped->value => [
                'label' => 'Expediee',
                'class' => 'bg-sky-50 text-sky-700 border border-sky-200',
            ],
            OrderStatus::Cancelled->value => [
                'label' => 'Annulee',
                'class' => 'bg-rose-50 text-rose-700 border border-rose-200',
            ],
        ];
    }

    /**
     * @return array{attributes:int, values:int, variants:int}
     */
    private function buildAttributeStats(?Shop $shop): array
    {
        if (!$shop) {
            return [
                'attributes' => 0,
                'values' => 0,
                'variants' => 0,
            ];
        }

        $attributeCount = (int) $this->attributeDefinitionRepository
            ->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.shop = :shop')
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getSingleScalarResult();

        $valueCount = (int) $this->entityManager
            ->createQuery('SELECT COUNT(v.id) FROM App\Entity\AttributeValueDefinition v JOIN v.attribute attr WHERE attr.shop = :shop')
            ->setParameter('shop', $shop)
            ->getSingleScalarResult();

        $variantCount = (int) $this->entityManager
            ->createQuery('SELECT COUNT(variant.id) FROM App\Entity\ProductVariant variant JOIN variant.product p WHERE p.shop = :shop')
            ->setParameter('shop', $shop)
            ->getSingleScalarResult();

        return [
            'attributes' => $attributeCount,
            'values' => $valueCount,
            'variants' => $variantCount,
        ];
    }

    private function resolveOrderCustomerName(CustomerOrder $order): string
    {
        $owner = $order->getOwner();
        if ($owner instanceof User) {
            $fullName = trim(sprintf('%s %s', $owner->getFirstname() ?? '', $owner->getLastname() ?? ''));
            if ('' !== $fullName) {
                return $fullName;
            }

            if ($owner->getEmail()) {
                return (string) $owner->getEmail();
            }
        }

        $shipping = $order->getShippingAddress();
        if (!empty($shipping['label'])) {
            return (string) $shipping['label'];
        }

        if (!empty($shipping['addressLine1'])) {
            return (string) $shipping['addressLine1'];
        }

        return 'Client TechNova';
    }

    private function orderBelongsToShop(CustomerOrder $order, Shop $shop): bool
    {
        $productIds = [];
        foreach ($order->getItems() as $item) {
            $productIds[] = $item->getProductId();
        }
        if ([] === $productIds) {
            return false;
        }

        $productIds = array_values(array_unique(array_filter($productIds, static fn ($id) => is_int($id) || ctype_digit((string) $id))));
        if ([] === $productIds) {
            return false;
        }

        $count = (int) $this->productRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.shop = :shop')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('shop', $shop)
            ->setParameter('ids', $productIds)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    private function resolveViewer(Request $request): User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $recentId = $request->getSession()->get('recent_user_id');
        if ($recentId) {
            $user = $this->userRepository->find((int) $recentId);
            if ($user instanceof User) {
                return $user;
            }
        }

        throw $this->createAccessDeniedException('Utilisateur requis.');
    }

    private function generateUniqueSlug(string $name): string
    {
        $baseSlug = strtolower($this->slugger->slug($name)->toString());
        if ('' === $baseSlug) {
            $baseSlug = 'boutique';
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($this->shopRepository->findOneBy(['slug' => $slug])) {
            $slug = sprintf('%s-%d', $baseSlug, ++$suffix);
        }

        return $slug;
    }

    private function prefillPromoPercent(FormInterface $form, Product $product): void
    {
        $percent = $this->calculatePromoPercent($product);
        if (null !== $percent && $form->has('promoPercent')) {
            $form->get('promoPercent')->setData($percent);
        }
    }

    private function synchronizePromoFields(Product $product, FormInterface $form): void
    {
        if (!$form->has('promoPercent')) {
            return;
        }

        $price = $product->getPrice();
        $percentInput = $form->get('promoPercent')->getData();
        $promoInput = $form->has('promoPrice') ? $form->get('promoPrice')->getData() : null;

        if (null === $promoInput || '' === $promoInput) {
            $product->setPromoPrice(null);
        } elseif (is_numeric($promoInput)) {
            $promoValue = max(0.0, (float) $promoInput);
            if ($price <= 0 || $promoValue >= $price) {
                $product->setPromoPrice(null);
            } else {
                $product->setPromoPrice($promoValue);
            }
        }

        if (null !== $percentInput && '' !== $percentInput && $price > 0) {
            $percent = max(0.0, min(100.0, (float) $percentInput));
            $amount = round($price * (1 - ($percent / 100)), 2);
            $product->setPromoPrice($amount > 0 ? $amount : 0.0);
        } elseif (null !== $product->getPromoPrice() && $price > 0) {
            $computed = $this->calculatePromoPercent($product);
            if (null !== $computed) {
                $form->get('promoPercent')->setData($computed);
            }
        }
    }

    private function calculatePromoPercent(Product $product): ?float
    {
        $price = $product->getPrice();
        $promo = $product->getPromoPrice();

        if ($price <= 0 || null === $promo || $promo >= $price) {
            return null;
        }

        return round((1 - ($promo / $price)) * 100, 2);
    }

    private function removeSelectedProductImages(Product $product, Request $request): void
    {
        $payload = $request->request->all();
        $ids = $payload['remove_images'] ?? [];
        if (!is_array($ids) || [] === $ids) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(static fn ($value) => is_numeric($value) ? (int) $value : null, $ids), static fn ($value) => null !== $value)));
        if ([] === $ids) {
            return;
        }

        foreach ($product->getImages()->toArray() as $image) {
            $imageId = $image->getId();
            if (null !== $imageId && in_array($imageId, $ids, true)) {
                $this->deleteUploadFile($image->getUrl());
                $product->removeImage($image);
            }
        }
    }

    private function handleProductImages(Product $product, mixed $mainImageFile, mixed $galleryFiles): void
    {
        $mainUpload = $this->normalizeUploadedFile($mainImageFile);
        $galleryUploads = [];
        if (is_iterable($galleryFiles)) {
            foreach ($galleryFiles as $file) {
                $valid = $this->normalizeUploadedFile($file);
                if ($valid) {
                    $galleryUploads[] = $valid;
                }
            }
        }

        if (!$mainUpload && [] === $galleryUploads) {
            return;
        }

        $maxPosition = 0;
        foreach ($product->getImages() as $image) {
            $maxPosition = max($maxPosition, $image->getPosition());
        }

        if ($mainUpload instanceof UploadedFile) {
            foreach ($product->getImages() as $existingImage) {
                if ($existingImage->isMain()) {
                    $existingImage->setIsMain(false);
                }
            }

            $image = $this->createProductImageFromFile($product, $mainUpload);
            $image->setIsMain(true);
            $image->setPosition(0);
            $product->addImage($image);
            ++$maxPosition;
        }

        foreach ($galleryUploads as $file) {
            $image = $this->createProductImageFromFile($product, $file);
            $image->setPosition(++$maxPosition);
            $product->addImage($image);
        }
    }

    private function normalizeUploadedFile(mixed $file): ?UploadedFile
    {
        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            $originalName = $file->getClientOriginalName() ?: 'Fichier';
            $this->addFlash('error', sprintf('%s n’a pas pu être téléversé (taille ou configuration).', $originalName));

            return null;
        }

        return $file;
    }

    private function createProductImageFromFile(Product $product, UploadedFile $file): ProductImage
    {
        $relativePath = $this->imageUploader->upload($file, ImageProfileRegistry::get('product_image'));
        $absolutePath = $this->projectDir.'/public/'.ltrim($relativePath, '/');
        $fileSize = is_file($absolutePath) ? filesize($absolutePath) : null;

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl($relativePath);
        $image->setMimeType('image/webp');
        $image->setFileSize($fileSize ?: null);
        $image->setTitle($product->getName());
        $image->setAlt($product->getName());

        return $image;
    }

    private function handleUploads(Shop $shop, mixed $logoFile, mixed $bannerFile): void
    {
        $logoUpload = $this->normalizeUploadedFile($logoFile);
        if ($logoUpload instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getLogo());
            $shop->setLogo($this->imageUploader->upload($logoUpload, ImageProfileRegistry::get('shop_logo')));
        }

        $bannerUpload = $this->normalizeUploadedFile($bannerFile);
        if ($bannerUpload instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getBanner());
            $shop->setBanner($this->imageUploader->upload($bannerUpload, ImageProfileRegistry::get('shop_banner')));
        }
    }

    private function logFormErrors(FormInterface $form): void
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $origin = $error->getOrigin();
            $errors[] = [
                'field' => $origin ? $origin->getName() : null,
                'message' => $error->getMessage(),
            ];
        }

        if ([] === $errors) {
            return;
        }

        $this->logger->info('Shop form validation failed', [
            'errors' => $errors,
            'route' => 'app_vendor_shop_new',
        ]);
    }

    private function deleteUploadFile(?string $relativePath): void
    {
        if (!$relativePath || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $absolute = $this->getParameter('kernel.project_dir').'/public/'.ltrim($relativePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function ensureProductSlug(Product $product): void
    {
        $desired = trim((string) $product->getSlug());
        if ('' === $desired) {
            $desired = (string) ($product->getName() ?? 'produit');
        }

        $slug = $this->generateUniqueProductSlug($desired, $product->getId());
        $product->setSlug($slug);
    }

    private function generateUniqueProductSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = strtolower($this->slugger->slug($name)->toString());
        if ('' === $baseSlug) {
            $baseSlug = 'produit';
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($existing = $this->productRepository->findOneBy(['slug' => $slug])) {
            if (null !== $ignoreId && $existing->getId() === $ignoreId) {
                break;
            }
            $slug = sprintf('%s-%d', $baseSlug, ++$suffix);
        }

        return $slug;
    }

    private function sanitizeShopContent(Shop $shop): void
    {
        $shop->setDescription($this->sanitizeRichText($shop->getDescription()));
        $shop->setPolicies($this->sanitizeRichText($shop->getPolicies()));
    }

    private function sanitizeProductContent(Product $product): void
    {
        $product->setDescription($this->sanitizeRichText($product->getDescription()));
    }

    private function sanitizeRichText(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $sanitized = trim($this->richTextSanitizer->sanitize($value));

        return '' === $sanitized ? null : $sanitized;
    }
}
