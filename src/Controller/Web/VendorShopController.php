<?php

namespace App\Controller\Web;

use App\Entity\Address;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Form\Vendor\ShopType;
use App\Form\Vendor\ProductType;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

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
        private readonly BrandRepository $brandRepository
    ) {
    }

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

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureProductSlug($product);

            $this->synchronizePromoFields($product, $form);
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);

            $this->entityManager->persist($product);
            $this->entityManager->flush();

            $this->addFlash('success', 'Produit créé avec succès.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => false, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => true, 'path' => 'app_vendor_products'],
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

        if ($form->isSubmitted() && $form->isValid()) {
            $this->ensureProductSlug($product);

            $this->synchronizePromoFields($product, $form);
            $mainImageFile = $form->get('mainImageFile')->getData();
            $galleryFiles = $form->get('galleryFiles')->getData() ?? [];
            $this->handleProductImages($product, $mainImageFile, $galleryFiles);
            $this->removeSelectedProductImages($product, $request);

            $this->entityManager->flush();

            $this->addFlash('success', 'Produit mis à jour.');

            return $this->redirectToRoute('app_vendor_products');
        }

        $vendorNav = [
            ['label' => 'Accueil', 'icon' => '🏠', 'active' => false, 'path' => 'app_vendor_shop_new'],
            ['label' => 'Mes produits', 'icon' => '🗂️', 'active' => true, 'path' => 'app_vendor_products'],
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
        ]);
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
