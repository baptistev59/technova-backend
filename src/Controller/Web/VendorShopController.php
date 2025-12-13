<?php

namespace App\Controller\Web;

use App\Entity\Address;
use App\Entity\AttributeDefinition;
use App\Entity\AttributeValueDefinition;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductAttributeSelection;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductImage;
use App\Entity\ProductBundleItem;
use App\Entity\ProductVariant;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Vendor;
use App\Form\Vendor\ShopType;
use App\Form\Vendor\ProductType;
use App\Repository\AttributeDefinitionRepository;
use App\Repository\ShopRepository;
use App\Repository\UserRepository;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Repository\BrandRepository;
use App\Security\ViewerAccessChecker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Throwable;

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
        private readonly CategoryRepository $categoryRepository,
        private readonly BrandRepository $brandRepository,
        private readonly AttributeDefinitionRepository $attributeDefinitionRepository
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
            return $this->render('vendor/shop/existing.html.twig', [

                // MENU VENDEUR (contient active)
                'vendor_nav' => [
                    ['label' => 'Accueil', 'icon' => '🏠', 'active' => true, 'path' => 'app_vendor_shop_new'],
                    ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => false, 'path' => 'app_vendor_products'],
                    ['label' => 'Attributs', 'icon' => '🎛️', 'active' => false, 'path' => 'app_vendor_attributes'],
                    ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
                    ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
                    ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
                ],

                // STATS DU DASHBOARD (pas d'active ici)
                'stats' => [
                    [
                        'label' => 'Ventes du jour',
                        'value' => '33',
                        'trend' => '+8% cette semaine',
                        'icon'  => '🛒',
                    ],
                    [
                        'label' => 'Revenus',
                        'value' => '1 240 €',
                        'trend' => '+12% cette semaine',
                        'icon'  => '💶',
                    ],
                    [
                        'label' => 'Commandes en cours',
                        'value' => '14',
                        'trend' => '+3% cette semaine',
                        'icon'  => '📦',
                    ],
                    [
                        'label' => 'Produits actifs',
                        'value' => '56',
                        'trend' => 'Stable',
                        'icon'  => '🏬',
                    ],
                ],

                'shop' => $existingShop,
            ]);
        }

        $session = $request->getSession();
        if (!$existingShop && !$vendor && (!$session || !$session->get('vendor_terms_accepted'))) {
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
            if ($vendorAddress->isDefault() === null) {
                $vendorAddress->setIsDefault(false);
            }
            if ($vendorAddress->isBilling() === null) {
                $vendorAddress->setIsBilling(false);
            }
            if ($vendorAddress->isShipping() === null) {
                $vendorAddress->setIsShipping(false);
            }
        }

        if ($existingShop instanceof Shop && $editMode) {
            $shop = $existingShop;
        } else {
            $shop = new Shop();
            $shop->setContactEmail($vendor?->getEmail() ?? $user->getEmail() ?? '');
        }

        if (!$shop->getOwner()) {
            $shop->setOwner($vendor);
        }

        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

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

            if ($isNewShop) {
                $this->entityManager->persist($shop);
            }
            $this->entityManager->flush();

            if ($session) {
                $session->remove('vendor_terms_accepted');
            }

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

        $page = max(1, (int) $request->query->get('page', 1));
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
        if ($statusQuery === null) {
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
            'low_stock' => 'Stock faible (≤10)',
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

        $vendorNav = [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => false, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => true, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => false, 'path' => 'app_vendor_attributes'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];

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
            $request->getSession()?->set('vendor_terms_accepted', true);

            return $this->redirectToRoute('app_vendor_shop_new');
        }

        return $this->render('vendor/shop/terms.html.twig', [
            'form' => $form->createView(),
        ]);
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
            if ($variantAction === 'delete_all_variants') {
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
        $attributeOptions = $this->getAttributeDefinitionsData();
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
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);
            $this->syncProductAttributeSelections($product, $selectionState);
            if ($variantAction === 'generate_variants') {
                $this->syncProductVariantsFromAttributes($product);
            }
            $this->updateVariantDetailsFromRequest($product, $request);
            if ($product->getType() === 'grouped') {
                $this->syncProductBundleItems($product, $bundleState);
                $this->ensureGroupedCategory($product);
            } else {
                $this->clearProductBundleItems($product);
            }

            $this->entityManager->persist($product);
            $this->entityManager->flush();

            if ($variantAction === 'generate_variants') {
                $this->addFlash('success', 'Variantes générées. Tu peux maintenant ajuster chaque variante.');

                return $this->redirectToRoute('app_vendor_product_edit', ['id' => $product->getId()]);
            }

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => false, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => true, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => false, 'path' => 'app_vendor_attributes'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];

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

        if (!$this->isGranted('ROLE_ADMIN') && (!$product->getShop() || $product->getShop()->getOwner()?->getOwner() !== $user)) {
            throw $this->createAccessDeniedException('Accès non autorisé.');
        }

        $form = $this->createForm(ProductType::class, $product);
        $this->prefillPromoPercent($form, $product);
        $form->handleRequest($request);
        $variantAction = (string) $request->request->get('_action', '');
        if ($form->isSubmitted()) {
            if ($variantAction === 'delete_all_variants') {
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
        $attributeOptions = $this->getAttributeDefinitionsData();
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
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);
            $this->syncProductAttributeSelections($product, $selectionState);
            if ($variantAction === 'generate_variants') {
                $this->syncProductVariantsFromAttributes($product);
            }
            $this->updateVariantDetailsFromRequest($product, $request);
            if ($product->getType() === 'grouped') {
                $this->syncProductBundleItems($product, $bundleState);
                $this->ensureGroupedCategory($product);
            } else {
                $this->clearProductBundleItems($product);
            }

            $this->entityManager->flush();

            if ($variantAction === 'generate_variants') {
                $this->addFlash('success', 'Variantes mises à jour.');

                return $this->redirectToRoute('app_vendor_product_edit', ['id' => $product->getId()]);
            }

            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => false, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => true, 'path' => 'app_vendor_products'],
            ['label' => 'Attributs', 'icon' => '🎛️', 'active' => false, 'path' => 'app_vendor_attributes'],
            ['label' => 'Commandes', 'icon' => '📦', 'active' => false],
            ['label' => 'Statistiques', 'icon' => '📊', 'active' => false],
            ['label' => 'Paramètres', 'icon' => '⚙️', 'active' => false],
        ];

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
    public function toggleProductPublication(Product $product, Request $request): RedirectResponse
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_toggle_' . $product->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Le jeton CSRF est invalide.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $product->setIsPublished(!$product->isPublished());
        $this->entityManager->flush();

        $this->addFlash('success', $product->isPublished() ? 'Le produit est publié.' : 'Le produit est repassé en brouillon.');

        return $this->redirectToRoute('app_vendor_products');
    }

    #[Route('/produits/{id}/dupliquer', name: 'app_vendor_product_duplicate', methods: ['POST'])]
    public function duplicateProduct(Product $product, Request $request): RedirectResponse
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_duplicate_' . $product->getId(), $request->request->get('_token'))) {
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
    public function deleteProduct(Product $product, Request $request): RedirectResponse
    {
        if ($response = $this->guardProductAction($product, $request)) {
            return $response;
        }
        if (!$this->isCsrfTokenValid('product_delete_' . $product->getId(), $request->request->get('_token'))) {
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
    private function getAttributeDefinitionsData(): array
    {
        /** @var AttributeDefinition[] $definitions */
        $definitions = $this->attributeDefinitionRepository->findBy([], ['position' => 'ASC', 'name' => 'ASC']);
        $data = [];

        foreach ($definitions as $definition) {
            $values = [];
            foreach ($definition->getValues() as $value) {
                if ($value->getId() === null) {
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
        if ($this->bundleTableExists !== null) {
            return $this->bundleTableExists;
        }

        try {
            $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
            $this->bundleTableExists = $schemaManager->tablesExist(['product_bundle_item']);
        } catch (Throwable) {
            $this->bundleTableExists = false;
        }

        return $this->bundleTableExists;
    }

    private function applyProductFormValidation(FormInterface $form, Product $product): void
    {
        if (!$form->isSubmitted() || $product->getType() === 'grouped') {
            return;
        }

        if ($product->getPrice() === null || $product->getPrice() <= 0) {
            $form->get('price')->addError(new FormError('Indique un prix HT.'));
        }

        if (!$product->getCategory()) {
            $form->get('category')->addError(new FormError('Merci de sélectionner une catégorie.'));
        }
    }

    private function ensureGroupedCategory(Product $product): void
    {
        if ($product->getCategory() || $product->getType() !== 'grouped') {
            return;
        }

        $slug = 'produits-groupes';
        $category = $this->categoryRepository->findOneBy(['slug' => $slug]);
        if (!$category) {
            $category = (new Category())
                ->setName('Produits groupés')
                ->setSlug($this->slugger->slug('Produits groupés')->lower());
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
            if ($type === 'grouped') {
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
            if (!$attribute || $attribute->getId() === null) {
                continue;
            }

            $valueIds = [];
            foreach ($selection->getValues() as $value) {
                if ($value->getId() !== null) {
                    $valueIds[] = $value->getId();
                }
            }
            if ($valueIds === []) {
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
            if (!$component || $component->getId() === null) {
                continue;
            }

            $state[] = [
                'product' => $component->getId(),
                'required' => $item->isRequired(),
                'position' => $item->getPosition(),
            ];
        }

        usort($state, static fn (array $a, array $b) => ($a['position'] ?? 0) <=> ($b['position'] ?? 0));

        return $state;
    }

    /**
     * @return array<int, array{attribute:int, values:array<int>}>
     */
    private function parseAttributeSelectionPayload(Request $request): array
    {
        $raw = $request->request->get('attribute_selections');
        if (!is_string($raw) || trim($raw) === '') {
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

            $valueIds = array_values(array_unique(array_filter(array_map(
                static fn ($value) => is_numeric($value) ? (int) $value : null,
                $item['values'] ?? []
            ), static fn ($value) => $value !== null)));

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
        if (!is_string($raw) || trim($raw) === '') {
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

            $valueIds = $item['values'] ?? [];
            if ($valueIds === []) {
                continue;
            }

            $selection = (new ProductAttributeSelection())
                ->setProduct($product)
                ->setAttribute($attribute);

            foreach ($attribute->getValues() as $value) {
                if ($value->getId() !== null && in_array($value->getId(), $valueIds, true)) {
                    $selection->addValue($value);
                }
            }

            if ($selection->getValues()->count() === 0) {
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

        if ($payload === []) {
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

            if ($component->getType() === 'grouped') {
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
        if ($prices === []) {
            return [
                'min' => null,
                'max' => null,
                'label' => '—',
            ];
        }

        sort($prices, SORT_NUMERIC);
        $min = $prices[0];
        $max = $prices[count($prices) - 1];
        $format = static fn (float $value): string => number_format($value, 2, ',', ' ') . ' €';
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
        if ($productId !== null) {
            if (in_array($productId, $visited, true)) {
                return [];
            }
            $visited[] = $productId;
        }

        if ($product->getType() === 'grouped') {
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
                if ($price === null || $price <= 0) {
                    $price = $variant->getPrice();
                }
                if ($price > 0) {
                    $prices[] = $price;
                }
            }
        } else {
            $price = $product->getPromoPrice();
            if ($price === null || $price <= 0 || $price >= $product->getPrice()) {
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
        if ($product->getType() !== 'grouped' || !$this->bundleTableExists()) {
            return;
        }

        $range = $this->computeProductPriceRange($product);
        if ($range['min'] !== null) {
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
        if ($product->getType() !== 'variable') {
            return;
        }

        $attributeSets = [];
        foreach ($product->getAttributeSelections() as $selection) {
            $attribute = $selection->getAttribute();
            if (!$attribute) {
                continue;
            }

            $values = $selection->getValues()->toArray();
            if ($values === []) {
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

        if ($attributeSets === []) {
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
                $attributeKey = $attribute->getSlug() ?: ('attribute_' . $attribute->getId());
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
     * @return array<int, array<int, array{attribute: AttributeDefinition, value: AttributeValueDefinition}>>
     */
    private function buildVariantCombinations(array $attributeSets): array
    {
        if ($attributeSets === []) {
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
        if (!$configuration || $configuration === []) {
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

        $user = $this->resolveViewer($request);
        if (!$this->isGranted('ROLE_ADMIN')) {
            $owner = $product->getShop()?->getOwner()?->getOwner();
            if (!$owner || $owner !== $user) {
                throw $this->createAccessDeniedException('Accès non autorisé.');
            }
        }

        return null;
    }

    private function duplicateProductEntity(Product $source): Product
    {
        $name = trim(($source->getName() ?? 'Produit') . ' (Copie)');
        $clone = (new Product())
            ->setName($name)
            ->setShortDescription($source->getShortDescription())
            ->setDescription($source->getDescription())
            ->setPrice($source->getPrice())
            ->setPromoPrice($source->getPromoPrice())
            ->setStock($source->getStock())
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
                ->setIsAvailable($variant->isAvailable())
                ->setImagePath($variant->getImagePath())
                ->setConfiguration($variant->getConfiguration())
                ->setMetadata($variant->getMetadata())
                ->setSku($variant->getSku() ? $variant->getSku() . '-copie' : null)
                ->setBarcode($variant->getBarcode() ? $variant->getBarcode() . '-copie' : null);

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
        if (!is_array($payload) || $payload === []) {
            return;
        }

        foreach ($product->getVariants() as $variant) {
            $id = $variant->getId();
            if ($id === null || !isset($payload[$id]) || !is_array($payload[$id])) {
                continue;
            }

            $data = $payload[$id];

            $price = $this->normalizeFloat($data['price'] ?? null);
            if ($price !== null && $price >= 0) {
                $variant->setPrice($price);
            }

            $promo = $this->normalizeFloat($data['promoPrice'] ?? null);
            if ($promo !== null && $promo > 0 && ($price ?? $variant->getPrice()) > $promo) {
                $variant->setPromoPrice($promo);
            } else {
                $variant->setPromoPrice(null);
            }

            $stock = $this->normalizeInt($data['stock'] ?? null);
            if ($stock !== null && $stock >= 0) {
                $variant->setStock($stock);
            }

            if (array_key_exists('sku', $data)) {
                $variant->setSku((string) $data['sku']);
            }

            $variant->setIsAvailable(isset($data['isAvailable']) && (string) $data['isAvailable'] === '1');
        }
    }

    private function normalizeFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
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
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function resolveViewer(Request $request): User
    {
        $current = $this->security->getUser();
        if ($current instanceof User) {
            return $current;
        }

        $recentId = $request->getSession()?->get('recent_user_id');
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
        if ($baseSlug === '') {
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
        if ($percent !== null && $form->has('promoPercent')) {
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

        if ($promoInput === null || $promoInput === '') {
            $product->setPromoPrice(null);
        } elseif (is_numeric($promoInput)) {
            $promoValue = max(0.0, (float) $promoInput);
            if ($price <= 0 || $promoValue >= $price) {
                $product->setPromoPrice(null);
            } else {
                $product->setPromoPrice($promoValue);
            }
        }

        if ($percentInput !== null && $percentInput !== '' && $price > 0) {
            $percent = max(0.0, min(100.0, (float) $percentInput));
            $amount = round($price * (1 - ($percent / 100)), 2);
            $product->setPromoPrice($amount > 0 ? $amount : 0.0);
        } elseif ($product->getPromoPrice() !== null && $price > 0) {
            $computed = $this->calculatePromoPercent($product);
            if ($computed !== null) {
                $form->get('promoPercent')->setData($computed);
            }
        }
    }

    private function calculatePromoPercent(Product $product): ?float
    {
        $price = $product->getPrice();
        $promo = $product->getPromoPrice();

        if ($price <= 0 || $promo === null || $promo >= $price) {
            return null;
        }

        return round((1 - ($promo / $price)) * 100, 2);
    }

    private function removeSelectedProductImages(Product $product, Request $request): void
    {
        $payload = $request->request->all();
        $ids = $payload['remove_images'] ?? [];
        if (!is_array($ids) || $ids === []) {
            return;
        }

        $ids = array_values(array_unique(array_filter(array_map(static fn ($value) => is_numeric($value) ? (int) $value : null, $ids), static fn ($value) => $value !== null)));
        if ($ids === []) {
            return;
        }

        foreach ($product->getImages()->toArray() as $image) {
            $imageId = $image->getId();
            if ($imageId !== null && in_array($imageId, $ids, true)) {
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

        if (!$mainUpload && $galleryUploads === []) {
            return;
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/products';
        $filesystem = new Filesystem();
        if (!$filesystem->exists($uploadDir)) {
            $filesystem->mkdir($uploadDir, 0775);
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

            $image = $this->createProductImageFromFile($product, $mainUpload, $uploadDir);
            $image->setIsMain(true);
            $image->setPosition(0);
            $product->addImage($image);
            $maxPosition++;
        }

        foreach ($galleryUploads as $file) {
            $image = $this->createProductImageFromFile($product, $file, $uploadDir);
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

    private function createProductImageFromFile(Product $product, UploadedFile $file, string $uploadDir): ProductImage
    {
        $mimeType = $file->getMimeType();
        $fileSize = $file->getSize();
        $filename = sprintf('product-%s.%s', uniqid(), $file->guessExtension() ?: 'bin');
        $file->move($uploadDir, $filename);

        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl('uploads/products/' . $filename);
        $image->setMimeType($mimeType ?: null);
        $image->setFileSize($fileSize ?: null);
        $image->setTitle($product->getName());
        $image->setAlt($product->getName());

        return $image;
    }

    private function handleUploads(Shop $shop, mixed $logoFile, mixed $bannerFile): void
    {
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/shops';
        $filesystem = new Filesystem();
        if (!$filesystem->exists($uploadDir)) {
            $filesystem->mkdir($uploadDir, 0775);
        }

        $logoUpload = $this->normalizeUploadedFile($logoFile);
        if ($logoUpload instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getLogo());
            $filename = sprintf('shop-logo-%s.%s', uniqid(), $logoUpload->guessExtension() ?: 'bin');
            $logoUpload->move($uploadDir, $filename);
            $shop->setLogo('uploads/shops/' . $filename);
        }

        $bannerUpload = $this->normalizeUploadedFile($bannerFile);
        if ($bannerUpload instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getBanner());
            $filename = sprintf('shop-banner-%s.%s', uniqid(), $bannerUpload->guessExtension() ?: 'bin');
            $bannerUpload->move($uploadDir, $filename);
            $shop->setBanner('uploads/shops/' . $filename);
        }
    }

    private function deleteUploadFile(?string $relativePath): void
    {
        if (!$relativePath || !str_starts_with($relativePath, 'uploads/')) {
            return;
        }

        $absolute = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($relativePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    private function ensureProductSlug(Product $product): void
    {
        $desired = trim((string) $product->getSlug());
        if ($desired === '') {
            $desired = (string) ($product->getName() ?? 'produit');
        }

        $slug = $this->generateUniqueProductSlug($desired, $product->getId());
        $product->setSlug($slug);
    }

    private function generateUniqueProductSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = strtolower($this->slugger->slug($name)->toString());
        if ($baseSlug === '') {
            $baseSlug = 'produit';
        }

        $slug = $baseSlug;
        $suffix = 1;

        while ($existing = $this->productRepository->findOneBy(['slug' => $slug])) {
            if ($ignoreId !== null && $existing->getId() === $ignoreId) {
                break;
            }
            $slug = sprintf('%s-%d', $baseSlug, ++$suffix);
        }

        return $slug;
    }
}
