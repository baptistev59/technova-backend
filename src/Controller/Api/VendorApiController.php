<?php

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\Product;
use App\Entity\Shop;
use App\Entity\Vendor;
use App\Form\Vendor\ProductType;
use App\Form\Vendor\ShopProfileType;
use App\Form\Vendor\VendorProfileType;
use App\Image\ImageProfileRegistry;
use App\Image\ImageUploader;
use App\Repository\CustomerOrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ShopRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/vendor', name: 'api_vendor_')]
#[OA\Tag(name: 'Vendor')]
#[Security(name: 'Bearer')]
final class VendorApiController extends AbstractController
{
    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly ProductRepository $productRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly ImageUploader $imageUploader,
        private readonly SluggerInterface $slugger,
    ) {
    }

    private function getShop(): Shop
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Connexion requise');
        }
        $shop = $this->shopRepository->findOneBy(['owner' => $user->getVendor()]);
        if (!$shop) {
            throw $this->createNotFoundException('Aucune boutique enregistrée.');
        }
        return $shop;
    }

    #[Route('/shop', name: 'shop_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Retourne la boutique du vendeur connecté',
        responses: [
            new OA\Response(response: 200, description: 'Boutique trouvée'),
            new OA\Response(response: 404, description: 'Pas de boutique')
        ]
    )]
    public function fetchShop(): JsonResponse
    {
        $shop = $this->getShop();
        return $this->json($this->serializeShop($shop));
    }

    #[Route('/shop', name: 'shop_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Création de la boutique vendor',
        responses: [
            new OA\Response(response: 201, description: 'Boutique créée'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new Schema(
                required: ['name', 'contactEmail'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'policies', type: 'string'),
                    new OA\Property(property: 'contactEmail', type: 'string', format: 'email'),
                    new OA\Property(property: 'logoFile', type: 'string', format: 'binary'),
                    new OA\Property(property: 'bannerFile', type: 'string', format: 'binary'),
                ]
            )
        )
    )]
    public function createShop(Request $request): JsonResponse
    {
        $vendor = $this->requireVendor();
        if ($this->shopRepository->findOneBy(['owner' => $vendor])) {
            return $this->json(['message' => 'Une boutique existe déjà.'], JsonResponse::HTTP_CONFLICT);
        }

        $shop = new Shop();
        $shop->setOwner($vendor);
        $form = $this->formFactory->create(ShopProfileType::class, $shop);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateShopSlug($shop);
        $this->handleShopUploads(
            $shop,
            $form->get('logoFile')->getData(),
            $form->get('bannerFile')->getData()
        );

        $this->entityManager->persist($shop);
        $this->entityManager->flush();

        return $this->json($this->serializeShop($shop), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_vendor_shop_get'),
        ]);
    }

    #[Route('/shop', name: 'shop_update', methods: ['PUT', 'PATCH'])]
    #[OA\Put(
        summary: 'Mise à jour de la boutique vendor',
        responses: [
            new OA\Response(response: 200, description: 'Boutique mise à jour'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    #[OA\RequestBody(
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new Schema(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'policies', type: 'string'),
                    new OA\Property(property: 'contactEmail', type: 'string', format: 'email'),
                    new OA\Property(property: 'logoFile', type: 'string', format: 'binary'),
                    new OA\Property(property: 'bannerFile', type: 'string', format: 'binary'),
                ]
            )
        )
    )]
    public function updateShop(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $form = $this->formFactory->create(ShopProfileType::class, $shop);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateShopSlug($shop);
        $this->handleShopUploads(
            $shop,
            $form->get('logoFile')->getData(),
            $form->get('bannerFile')->getData()
        );

        $this->entityManager->flush();

        return $this->json($this->serializeShop($shop));
    }

    #[Route('/profile', name: 'profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        $vendor = $this->requireVendor();
        return $this->json($this->serializeVendor($vendor));
    }

    #[Route('/profile', name: 'profile_update', methods: ['PUT', 'PATCH'])]
    #[OA\Patch(
        summary: 'Met à jour le profil vendeur',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'companyName', type: 'string'),
                    new OA\Property(property: 'businessId', type: 'string'),
                    new OA\Property(property: 'businessIdType', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'website', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profil mis à jour'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    public function updateProfile(Request $request): JsonResponse
    {
        $vendor = $this->requireVendor();
        $form = $this->formFactory->create(VendorProfileType::class, $vendor);
        $form->submit($request->toArray(), false);
        if (!$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeVendor($vendor));
    }

    #[Route('/products', name: 'products_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste paginée des produits du vendeur',
        parameters: [
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
        ]
    )]
    public function listProducts(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(50, (int) $request->query->get('perPage', 10)));

        $filters = ['shop' => $shop];
        if ($status = $request->query->get('status')) {
            $filters['status'] = $status;
        }

        $pagination = $this->productRepository->filterByPaginated($filters, $page, $limit);
        return $this->json([
            'items' => array_map(static fn ($product) => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'slug' => $product->getSlug(),
                'status' => $product->isPublished() ? 'published' : 'draft',
                'price' => $product->getPrice(),
            ], $pagination['items']),
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'perPage' => $pagination['per_page'],
        ]);
    }

    #[Route('/products', name: 'products_create', methods: ['POST'])]
    #[OA\Post(
        summary: 'Création d’un produit vendeur',
        responses: [
            new OA\Response(response: 201, description: 'Produit créé'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'price', 'isPublished'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'shortDescription', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'number', format: 'float'),
                new OA\Property(property: 'isPublished', type: 'boolean')
            ]
        )
    )]
    public function createProduct(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $product = new Product();
        $product->setShop($shop);

        $form = $this->formFactory->create(ProductType::class, $product);
        $form->submit($request->toArray());
        if (!$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateProductSlug($product);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json(['id' => $product->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/products/{id}', name: 'products_get', methods: ['GET'])]
    #[OA\Get(
        summary: 'Détail d’un produit vendeur',
        responses: [
            new OA\Response(response: 200, description: 'Produit trouvé'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
        ]
    )]
    public function getProduct(int $id): JsonResponse
    {
        $shop = $this->getShop();
        $product = $this->productRepository->findOneBy(['id' => $id, 'shop' => $shop]);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->json([
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice(),
            'isPublished' => $product->isPublished(),
        ]);
    }

    #[Route('/products/{id}', name: 'products_update', methods: ['PUT', 'PATCH'])]
    #[OA\Patch(
        summary: 'Mise à jour d’un produit vendeur',
        responses: [
            new OA\Response(response: 200, description: 'Produit mis à jour'),
            new OA\Response(response: 404, description: 'Produit introuvable'),
            new OA\Response(response: 422, description: 'Données invalides'),
        ]
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'shortDescription', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'number', format: 'float'),
                new OA\Property(property: 'isPublished', type: 'boolean')
            ]
        )
    )]
    public function updateProduct(int $id, Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $product = $this->productRepository->findOneBy(['id' => $id, 'shop' => $shop]);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $form = $this->formFactory->create(ProductType::class, $product);
        $form->submit($request->toArray(), false);
        if (!$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateProductSlug($product);
        $this->entityManager->flush();

        return $this->json(['id' => $product->getId()]);
    }

    #[Route('/products/{id}', name: 'products_delete', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Supprimer un produit',
        responses: [
            new OA\Response(response: 204, description: 'Produit supprimé'),
            new OA\Response(response: 404, description: 'Introuvable'),
        ]
    )]
    public function deleteProduct(int $id): JsonResponse
    {
        $shop = $this->getShop();
        $product = $this->productRepository->findOneBy(['id' => $id, 'shop' => $shop]);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/media', name: 'media_upload', methods: ['POST'])]
    #[OA\Post(
        summary: 'Upload d’un media (logo, bannière, visuel produit)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new MediaType(
                mediaType: 'multipart/form-data',
                schema: new Schema(
                    required: ['file', 'profile'],
                    properties: [
                        new OA\Property(property: 'profile', type: 'string', enum: ['shop_banner', 'shop_logo', 'product_image', 'avatar']),
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Upload réussi'),
            new OA\Response(response: 422, description: 'Profil ou fichier invalide'),
        ]
    )]
    public function uploadMedia(Request $request): JsonResponse
    {
        $this->getShop(); // vérifie que le vendeur dispose d’une boutique
        $profileKey = (string) ($request->request->get('profile') ?? '');
        if ($profileKey === '') {
            return $this->json(['error' => 'Profil requis (shop_banner, shop_logo, product_image, avatar).'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $profile = ImageProfileRegistry::get($profileKey);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['error' => 'Fichier manquant ou invalide.'], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $relativePath = $this->imageUploader->upload($file, $profile);

        return $this->json([
            'path' => $relativePath,
            'url' => '/' . ltrim($relativePath, '/'),
            'width' => $profile->width,
            'height' => $profile->height,
            'mimeType' => 'image/webp',
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/orders', name: 'orders_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste les commandes du vendeur',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: [CustomerOrder::STATUS_PENDING, CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED, CustomerOrder::STATUS_CANCELLED])),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new OA\Schema(type: 'integer')),
        ]
    )]
    public function listOrders(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(50, (int) $request->query->get('perPage', 10)));
        $status = $request->query->get('status');

        $pagination = $this->orderRepository->paginateForShop($shop, $page, $limit, $status ?: null);
        $productMap = $this->mapShopProductIds($shop, $pagination['orders']);

        $items = array_map(function (CustomerOrder $order) use ($productMap): array {
            return $this->serializeOrder($order, $productMap);
        }, $pagination['orders']);

        return $this->json([
            'items' => $items,
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'perPage' => $pagination['limit'],
            'statusCounts' => $pagination['statusCounts'],
            'revenue' => $pagination['revenue'],
        ]);
    }

    #[Route('/orders/{id}', name: 'orders_get', methods: ['GET'])]
    public function getOrder(int $id): JsonResponse
    {
        $shop = $this->getShop();
        $order = $this->findOrderForShop($shop, $id);
        $productMap = $this->mapShopProductIds($shop, [$order]);

        return $this->json($this->serializeOrder($order, $productMap, true));
    }

    #[Route('/orders/{id}/status', name: 'orders_status', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Change le statut d’une commande',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: [CustomerOrder::STATUS_PENDING, CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED, CustomerOrder::STATUS_CANCELLED]),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Statut mis à jour'),
            new OA\Response(response: 400, description: 'Transition non autorisée'),
        ]
    )]
    public function changeOrderStatus(int $id, Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $order = $this->findOrderForShop($shop, $id);
        $payload = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($payload)) {
            $payload = [];
        }
        $target = (string) ($payload['status'] ?? '');
        if (!in_array($target, [CustomerOrder::STATUS_PENDING, CustomerOrder::STATUS_PAID, CustomerOrder::STATUS_SHIPPED, CustomerOrder::STATUS_CANCELLED], true)) {
            return $this->json(['error' => 'Statut cible invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $current = $order->getStatus();
        $allowed = match ($target) {
            CustomerOrder::STATUS_PAID => $current === CustomerOrder::STATUS_PENDING,
            CustomerOrder::STATUS_SHIPPED => $current === CustomerOrder::STATUS_PAID,
            CustomerOrder::STATUS_CANCELLED => in_array($current, [CustomerOrder::STATUS_PENDING, CustomerOrder::STATUS_PAID], true),
            CustomerOrder::STATUS_PENDING => false,
            default => false,
        };

        if (!$allowed) {
            return $this->json(['error' => 'Transition de statut non autorisée.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ($target === CustomerOrder::STATUS_PAID) {
            $order
                ->setStatus(CustomerOrder::STATUS_PAID)
                ->setPaidAt($order->getPaidAt() ?? new \DateTimeImmutable());
        } elseif ($target === CustomerOrder::STATUS_SHIPPED) {
            $order->setStatus(CustomerOrder::STATUS_SHIPPED);
        } elseif ($target === CustomerOrder::STATUS_CANCELLED) {
            $order->setStatus(CustomerOrder::STATUS_CANCELLED);
        }

        $this->entityManager->flush();

        return $this->json($this->serializeOrder($order, $this->mapShopProductIds($shop, [$order]), true));
    }

    #[Route('/orders/{id}/documents', name: 'orders_documents', methods: ['POST'])]
    #[OA\Post(
        summary: 'Génère un document de commande (invoice/delivery)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new MediaType(
                mediaType: 'multipart/form-data',
                schema: new Schema(
                    required: ['type'],
                    properties: [
                        new OA\Property(property: 'type', type: 'string', enum: ['invoice', 'delivery']),
                    ]
                )
            )
        )
    )]
    public function generateDocument(int $id, Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $order = $this->findOrderForShop($shop, $id);
        $type = (string) $request->request->get('type', 'invoice');
        if (!in_array($type, ['invoice', 'delivery'], true)) {
            return $this->json(['error' => 'Type de document invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $content = sprintf(
            "Document: %s\nCommande #%s\nMontant: %s %s\nStatut: %s\nGénéré le: %s",
            strtoupper($type),
            $order->getReference() ?? $order->getId(),
            $order->getTotalAmount(),
            $order->getCurrency(),
            $order->getStatus(),
            (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        );

        return $this->json([
            'id' => $order->getId(),
            'reference' => $order->getReference(),
            'type' => $type,
            'filename' => sprintf('%s-%s.txt', $type, $order->getReference() ?? $order->getId()),
            'mimeType' => 'text/plain',
            'base64' => base64_encode($content),
        ]);
    }

    private function normalizeErrors(FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        return array_values(array_unique($errors));
    }

    private function generateProductSlug(Product $product): void
    {
        $base = trim((string) ($product->getSlug() ?: $product->getName() ?: 'produit'));
        $slugBase = (string) $this->slugger->slug($base)->lower();
        $candidate = $slugBase;
        $suffix = 0;

        while (true) {
            $existing = $this->productRepository->findOneBy(['slug' => $candidate]);
            if (!$existing || $existing === $product) {
                $product->setSlug($candidate);
                break;
            }
            ++$suffix;
            $candidate = sprintf('%s-%d', $slugBase, $suffix);
        }
    }

    private function generateShopSlug(Shop $shop): void
    {
        $base = trim((string) ($shop->getSlug() ?: $shop->getName() ?: 'boutique'));
        $slugBase = (string) $this->slugger->slug($base)->lower();
        $candidate = $slugBase;
        $suffix = 0;

        while (true) {
            $existing = $this->shopRepository->findOneBy(['slug' => $candidate]);
            if (!$existing || $existing === $shop) {
                $shop->setSlug($candidate);
                break;
            }
            ++$suffix;
            $candidate = sprintf('%s-%d', $slugBase, $suffix);
        }
    }

    private function findOrderForShop(Shop $shop, int $orderId): CustomerOrder
    {
        $order = $this->orderRepository->findOneForShop($shop, $orderId);
        if (!$order) {
            throw $this->createNotFoundException('Commande introuvable pour cette boutique.');
        }

        return $order;
    }

    /**
     * @param array<int, CustomerOrder> $orders
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
        if ($productIds === []) {
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
     * @param array<int, true> $shopProductMap
     */
    private function serializeOrder(CustomerOrder $order, array $shopProductMap, bool $includeAddresses = false): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $productId = $item->getProductId();
            if (!isset($shopProductMap[$productId])) {
                continue;
            }

            $items[] = [
                'id' => $item->getId(),
                'productId' => $productId,
                'name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => (float) $item->getUnitPrice(),
                'lineTotal' => (float) $item->getLineTotal(),
                'image' => $item->getProductImage(),
            ];
        }

        $data = [
            'id' => $order->getId(),
            'reference' => $order->getReference(),
            'status' => $order->getStatus(),
            'total' => (float) $order->getTotalAmount(),
            'currency' => $order->getCurrency(),
            'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $order->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ];

        if ($includeAddresses) {
            $data['shippingAddress'] = $order->getShippingAddress();
            $data['billingAddress'] = $order->getBillingAddress();
        }

        return $data;
    }

    private function serializeShop(Shop $shop): array
    {
        return [
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'slug' => $shop->getSlug(),
            'description' => $shop->getDescription(),
            'policies' => $shop->getPolicies(),
            'contactEmail' => $shop->getContactEmail(),
            'logo' => $shop->getLogo(),
            'banner' => $shop->getBanner(),
        ];
    }

    private function serializeVendor(Vendor $vendor): array
    {
        return [
            'companyName' => $vendor->getCompanyName(),
            'businessId' => $vendor->getBusinessId(),
            'businessIdType' => $vendor->getBusinessIdType(),
            'phone' => $vendor->getPhone(),
            'email' => $vendor->getEmail(),
            'website' => $vendor->getWebsite(),
        ];
    }

    private function requireVendor(): Vendor
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Connexion requise.');
        }
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createNotFoundException('Aucun vendeur trouvé.');
        }

        return $vendor;
    }

    private function handleShopUploads(Shop $shop, ?UploadedFile $logo, ?UploadedFile $banner): void
    {
        if ($logo instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getLogo());
            $shop->setLogo($this->imageUploader->upload($logo, ImageProfileRegistry::get('shop_logo')));
        }

        if ($banner instanceof UploadedFile) {
            $this->deleteUploadFile($shop->getBanner());
            $shop->setBanner($this->imageUploader->upload($banner, ImageProfileRegistry::get('shop_banner')));
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
}
