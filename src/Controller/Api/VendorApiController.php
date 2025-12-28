<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CustomerOrder;
use App\Entity\Media;
use App\Entity\OrderDocument;
use App\Entity\Product;
use App\Entity\ProductAttribute;
use App\Entity\ProductAttributeValue;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;
use App\Entity\Shop;
use App\Entity\User;
use App\Entity\Vendor;
use App\Enum\DocumentType;
use App\Enum\OrderStatus;
use App\Form\Vendor\ProductType;
use App\Form\Vendor\ShopProfileType;
use App\Form\Vendor\VendorProfileType;
use App\Image\ImageProfileRegistry;
use App\Image\ImageUploader;
use App\Repository\CustomerOrderRepository;
use App\Repository\OrderDocumentRepository;
use App\Repository\ProductRepository;
use App\Repository\ShopRepository;
use App\Service\OrderDocumentGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\MediaType;
use OpenApi\Attributes\Schema;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/api/vendor', name: 'api_vendor_')]
#[OA\Tag(name: 'Vendor')]
#[Security(name: 'Bearer')]
final class VendorApiController extends AbstractController
{
    public function __construct(
        private readonly ShopRepository $shopRepository,
        private readonly ProductRepository $productRepository,
        private readonly CustomerOrderRepository $orderRepository,
        private readonly OrderDocumentRepository $documentRepository,
        private readonly OrderDocumentGenerator $documentGenerator,
        private readonly EntityManagerInterface $entityManager,
        private readonly FormFactoryInterface $formFactory,
        private readonly ImageUploader $imageUploader,
        private readonly SluggerInterface $slugger,
        #[Autowire(service: 'state_machine.customer_order')]
        private readonly WorkflowInterface $orderWorkflow,
    ) {
    }

    private function getShop(): Shop
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Connexion requise');
        }
        $vendor = $user->getVendor();
        if (!$vendor) {
            throw $this->createNotFoundException('Aucun vendeur trouvé.');
        }
        $shop = $this->shopRepository->findOneBy(['owner' => $vendor]);
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
            new OA\Response(response: 404, description: 'Pas de boutique'),
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
        content: new MediaType(
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
        $form->submit($request->request->all(), true);
        if (!$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateShopSlug($shop);
        $this->handleShopUploads(
            $shop,
            $request->files->get('logoFile'),
            $request->files->get('bannerFile')
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
        content: new MediaType(
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
        $form->submit($request->request->all(), false);
        if (!$form->isValid()) {
            return $this->json(['errors' => $this->normalizeErrors($form)], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateShopSlug($shop);
        $this->handleShopUploads(
            $shop,
            $request->files->get('logoFile'),
            $request->files->get('bannerFile')
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
            new OA\Parameter(name: 'perPage', in: 'query', schema: new Schema(type: 'integer')),
            new OA\Parameter(name: 'page', in: 'query', schema: new Schema(type: 'integer')),
            new OA\Parameter(name: 'status', in: 'query', schema: new Schema(type: 'string', enum: ['published', 'draft'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée'),
        ]
    )]
    public function listProducts(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = max(1, min(50, (int) $request->query->get('perPage', '10')));

        $filters = ['shop' => $shop];
        if ($status = $request->query->get('status')) {
            $filters['status'] = $status;
        }

        $pagination = $this->productRepository->filterByPaginated($filters, $page, $limit);
        $items = array_map(fn (Product $product) => $this->serializeProduct($product), $pagination['items']);

        return $this->json([
            'items' => $items,
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
                new OA\Property(property: 'isPublished', type: 'boolean'),
            ]
        )
    )]
    public function createProduct(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $product = new Product();
        $product->setShop($shop);

        $result = $this->hydrateProductFromRequest($product, $request, true);
        if (!$result['valid']) {
            return $this->json(['errors' => $result['errors']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateProductSlug($product);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $this->json($this->serializeProduct($product), JsonResponse::HTTP_CREATED);
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

        return $this->json($this->serializeProduct($product));
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
                new OA\Property(property: 'isPublished', type: 'boolean'),
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

        $result = $this->hydrateProductFromRequest($product, $request, false);
        if (!$result['valid']) {
            return $this->json(['errors' => $result['errors']], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->generateProductSlug($product);
        $this->entityManager->flush();

        return $this->json($this->serializeProduct($product));
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
        $shop = $this->getShop(); // vérifie que le vendeur dispose d’une boutique
        $vendor = $shop->getOwner() ?? $this->requireVendor();
        $profileKey = (string) ($request->request->get('profile') ?? '');
        if ('' === $profileKey) {
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

        $media = (new Media())
            ->setVendor($vendor)
            ->setProfile($profile->name)
            ->setPath($relativePath)
            ->setWidth($profile->width)
            ->setHeight($profile->height)
            ->setMimeType('image/webp');

        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $this->json([
            'id' => $media->getId(),
            'profile' => $media->getProfile(),
            'path' => $media->getPath(),
            'url' => '/'.ltrim((string) $media->getPath(), '/'),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'mimeType' => $media->getMimeType(),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/orders', name: 'orders_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste les commandes du vendeur',
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', schema: new Schema(type: 'string', enum: [OrderStatus::Pending->value, OrderStatus::Paid->value, OrderStatus::Shipped->value, OrderStatus::Cancelled->value])),
            new OA\Parameter(name: 'page', in: 'query', schema: new Schema(type: 'integer')),
            new OA\Parameter(name: 'perPage', in: 'query', schema: new Schema(type: 'integer')),
        ]
    )]
    public function listOrders(Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = max(1, min(50, (int) $request->query->get('perPage', '10')));
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
                    new OA\Property(property: 'status', type: 'string', enum: [OrderStatus::Pending->value, OrderStatus::Paid->value, OrderStatus::Shipped->value, OrderStatus::Cancelled->value]),
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
        try {
            $payload = json_decode($request->getContent() ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $payload = [];
        }
        $target = (string) ($payload['status'] ?? '');
        $allowedStatuses = array_map(
            static fn (OrderStatus $status): string => $status->value,
            OrderStatus::cases()
        );
        if (!in_array($target, $allowedStatuses, true)) {
            return $this->json(['error' => 'Statut cible invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $transition = match ($target) {
            OrderStatus::Paid->value => 'pay',
            OrderStatus::Shipped->value => 'ship',
            OrderStatus::Cancelled->value => 'cancel',
            default => null,
        };

        if (!$transition || !$this->orderWorkflow->can($order, $transition)) {
            return $this->json(['error' => 'Transition de statut non autorisée.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        if ('pay' === $transition) {
            $order->setPaidAt($order->getPaidAt() ?? new \DateTimeImmutable());
        }

        $this->orderWorkflow->apply($order, $transition, [
            'triggered_by' => sprintf('vendor_api:%s', $this->getUser()?->getUserIdentifier() ?? 'unknown'),
            'payload' => ['shop_id' => $shop->getId()],
        ]);

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
        ),
        responses: [
            new OA\Response(response: 201, description: 'Document généré'),
            new OA\Response(response: 400, description: 'Type invalide'),
        ]
    )]
    public function generateDocument(int $id, Request $request): JsonResponse
    {
        $shop = $this->getShop();
        $order = $this->findOrderForShop($shop, $id);
        $typeRaw = (string) $request->request->get('type', 'invoice');

        try {
            $type = DocumentType::from($typeRaw);
        } catch (\ValueError) {
            return $this->json(['error' => 'Type de document invalide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $document = $this->documentGenerator->generate($order, $type, $request->getSchemeAndHttpHost());
        $this->entityManager->persist($document);
        $this->entityManager->flush();

        return $this->json([
            'id' => $document->getId(),
            'type' => $document->getType()->value,
            'url' => $document->getUrl(),
            'hash' => $document->getHash(),
            'generatedAt' => $document->getUpdatedAt()?->format(\DateTimeImmutable::ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/orders/{id}/documents', name: 'orders_documents_list', methods: ['GET'])]
    #[OA\Get(
        summary: 'Liste les documents générés pour une commande',
        responses: [
            new OA\Response(response: 200, description: 'Liste des documents'),
        ]
    )]
    public function listDocuments(int $id): JsonResponse
    {
        $shop = $this->getShop();
        $order = $this->findOrderForShop($shop, $id);
        $documents = $this->documentRepository->findBy(['order' => $order], ['createdAt' => 'DESC']);

        return $this->json(array_map(static fn (OrderDocument $document) => [
            'id' => $document->getId(),
            'type' => $document->getType()->value,
            'url' => $document->getUrl(),
            'hash' => $document->getHash(),
            'generatedAt' => $document->getUpdatedAt()?->format(\DateTimeImmutable::ATOM),
        ], $documents));
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

    private function serializeProduct(Product $product): array
    {
        $mainImage = null;
        foreach ($product->getImages() as $image) {
            if ($image->isMain()) {
                $mainImage = $image;
                break;
            }
        }

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'shortDescription' => $product->getShortDescription(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'promoPrice' => $product->getPromoPrice(),
            'stock' => $product->getStock(),
            'status' => $product->isPublished() ? 'published' : 'draft',
            'type' => $product->getType(),
            'keywords' => $product->getKeywords(),
            'mainImage' => $mainImage ? $this->serializeImage($mainImage) : null,
            'gallery' => array_map([$this, 'serializeImage'], $product->getImages()->toArray()),
            'variants' => array_map([$this, 'serializeVariant'], $product->getVariants()->toArray()),
            'attributes' => array_map([$this, 'serializeAttribute'], $product->getAttributes()->toArray()),
        ];
    }

    private function serializeImage(ProductImage $image): array
    {
        return [
            'id' => $image->getId(),
            'url' => '/'.ltrim($image->getUrl(), '/'),
            'alt' => $image->getAlt(),
            'caption' => $image->getCaption(),
            'isMain' => $image->isMain(),
            'mimeType' => $image->getMimeType(),
            'position' => $image->getPosition(),
        ];
    }

    private function serializeVariant(ProductVariant $variant): array
    {
        return [
            'id' => $variant->getId(),
            'sku' => $variant->getSku(),
            'barcode' => $variant->getBarcode(),
            'price' => $variant->getPrice(),
            'promoPrice' => $variant->getPromoPrice(),
            'stock' => $variant->getStock(),
            'isAvailable' => $variant->isAvailable(),
            'image' => $variant->getImagePath() ? '/'.ltrim($variant->getImagePath(), '/') : null,
            'configuration' => $variant->getConfiguration(),
            'metadata' => $variant->getMetadata(),
        ];
    }

    private function serializeAttribute(ProductAttribute $attribute): array
    {
        return [
            'id' => $attribute->getId(),
            'name' => $attribute->getName(),
            'slug' => $attribute->getSlug(),
            'inputType' => $attribute->getInputType(),
            'values' => array_map(function (ProductAttributeValue $value) {
                return [
                    'id' => $value->getId(),
                    'value' => $value->getValue(),
                    'slug' => $value->getSlug(),
                    'colorHex' => $value->getColorHex(),
                ];
            }, $attribute->getValues()->toArray()),
        ];
    }

    private function hydrateProductFromRequest(Product $product, Request $request, bool $clearMissing): array
    {
        $form = $this->formFactory->create(ProductType::class, $product);
        $form->submit($request->toArray(), $clearMissing);
        if (!$form->isValid()) {
            return ['valid' => false, 'errors' => $this->normalizeErrors($form)];
        }

        $this->handleProductImagesUpload(
            $product,
            $this->normalizeUploadedFile($request->files->get('mainImageFile')),
            $this->normalizeGalleryUploads($request->files->get('galleryFiles'))
        );

        $this->applyProductVariants($product, $request->toArray()['variants'] ?? []);
        $this->applyProductAttributes($product, $request->toArray()['attributes'] ?? []);

        return ['valid' => true, 'errors' => []];
    }

    private function handleProductImagesUpload(Product $product, ?UploadedFile $mainImage, array $galleryFiles): void
    {
        if (!$mainImage && [] === $galleryFiles) {
            return;
        }

        foreach ($product->getImages() as $existing) {
            if ($mainImage && $existing->isMain()) {
                $existing->setIsMain(false);
            }
        }

        if ($mainImage instanceof UploadedFile) {
            $image = $this->createProductImageFromFile($product, $mainImage);
            $image->setIsMain(true);
            $image->setPosition(0);
            $product->addImage($image);
        }

        $position = 1;
        foreach ($galleryFiles as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }
            $image = $this->createProductImageFromFile($product, $file);
            $image->setPosition($position++);
            $product->addImage($image);
        }
    }

    /**
     * @param iterable<mixed> $files
     *
     * @return UploadedFile[]
     */
    private function normalizeGalleryUploads(?iterable $files): array
    {
        if (!is_iterable($files)) {
            return [];
        }

        $uploads = [];
        foreach ($files as $file) {
            $normalized = $this->normalizeUploadedFile($file);
            if ($normalized instanceof UploadedFile) {
                $uploads[] = $normalized;
            }
        }

        return $uploads;
    }

    private function applyProductVariants(Product $product, mixed $variants): void
    {
        foreach ($product->getVariants()->toArray() as $existing) {
            $product->removeVariant($existing);
        }

        if (!is_iterable($variants)) {
            return;
        }

        foreach ($variants as $variantData) {
            if (!is_array($variantData)) {
                continue;
            }

            $variant = new ProductVariant();
            if (isset($variantData['sku'])) {
                $variant->setSku((string) $variantData['sku']);
            }
            if (isset($variantData['barcode'])) {
                $variant->setBarcode((string) $variantData['barcode']);
            }
            if (isset($variantData['price'])) {
                $variant->setPrice((float) $variantData['price']);
            }
            if (isset($variantData['promoPrice'])) {
                $variant->setPromoPrice((float) $variantData['promoPrice']);
            }
            if (isset($variantData['stock'])) {
                $variant->setStock((int) $variantData['stock']);
            }
            if (isset($variantData['isAvailable'])) {
                $variant->setIsAvailable((bool) $variantData['isAvailable']);
            }
            $variant->setConfiguration($variantData['configuration'] ?? null);
            $variant->setMetadata($variantData['metadata'] ?? null);
            if (!empty($variantData['image'])) {
                $variant->setImagePath((string) $variantData['image']);
            }

            $product->addVariant($variant);
        }
    }

    private function applyProductAttributes(Product $product, mixed $attributes): void
    {
        foreach ($product->getAttributes()->toArray() as $existing) {
            $product->removeAttribute($existing);
        }

        if (!is_iterable($attributes)) {
            return;
        }

        foreach ($attributes as $attributeData) {
            if (!is_array($attributeData) || empty($attributeData['name'])) {
                continue;
            }

            $attribute = new ProductAttribute();
            $attribute->setName((string) $attributeData['name']);
            $attribute->setSlug((string) ($attributeData['slug'] ?? $this->slugger->slug($attribute->getName())->lower()));
            if (!empty($attributeData['inputType'])) {
                $attribute->setInputType((string) $attributeData['inputType']);
            }
            if (isset($attributeData['position'])) {
                $attribute->setPosition((int) $attributeData['position']);
            }

            $values = $attributeData['values'] ?? [];
            if (is_iterable($values)) {
                foreach ($values as $valueData) {
                    if (!is_array($valueData) || empty($valueData['value'])) {
                        continue;
                    }

                    $value = new ProductAttributeValue();
                    $value->setValue((string) $valueData['value']);
                    $value->setSlug((string) ($valueData['slug'] ?? $this->slugger->slug($value->getValue())->lower()));
                    $value->setColorHex($valueData['colorHex'] ?? null);
                    $attribute->addValue($value);
                }
            }

            $product->addAttribute($attribute);
        }
    }

    private function normalizeUploadedFile(mixed $file): ?UploadedFile
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return null;
        }

        return $file;
    }

    private function createProductImageFromFile(Product $product, UploadedFile $file): ProductImage
    {
        $relativePath = $this->imageUploader->upload($file, ImageProfileRegistry::get('product_image'));
        $image = new ProductImage();
        $image->setProduct($product);
        $image->setUrl($relativePath);
        $image->setMimeType('image/webp');
        $image->setAlt($product->getName() ?? '');
        $image->setTitle($product->getName() ?? '');

        return $image;
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
        if (!$user instanceof User) {
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

        $absolute = $this->getParameter('kernel.project_dir').'/public/'.ltrim($relativePath, '/');
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }
}
