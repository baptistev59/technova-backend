# Action Plan - Remédiation des Findings d'Audit

## 🎯 Semaine 1: Authorization Enforcement (CRITICAL)

### Task 1.1: Audit Permissions - Identifier toutes les routes non-protégées
```bash
# Chercher les controllers sans #[IsGranted]
grep -r "class.*Controller extends" src/Controller/Api --include="*.php" | \
  while read -r file; do
    file="${file%%:*}"
    if ! grep -q "#\[IsGranted" "$file"; then
      echo "❌ NOT PROTECTED: $file"
    fi
  done
```

**Controllers à sécuriser:**
- [ ] CartController (add, update, remove, clear)
- [ ] CustomerOrderController (list, get, cancel)
- [ ] ProfileApiController (partiellement fait)
- [ ] AddressController (all CRUD)
- [ ] ProductApiController (product details are public, OK; but need to validate)
- [ ] CheckoutController (create, shipping, payment)
- [ ] CheckoutShippingController (partiellement fait)

### Task 1.2: Implémenter #[IsGranted] sur 15+ endpoints
**Template à appliquer:**

```php
// Avant:
public function add(Request $request): JsonResponse {
    // ...
}

// Après:
#[IsGranted('ROLE_USER')]
public function add(Request $request): JsonResponse {
    $user = $this->getUser();
    if (!$user instanceof User) {
        throw $this->createAccessDeniedException();
    }
    // ...
}
```

**Endpoints par type:**

**Authentifiés (ROLE_USER):**
```php
// CartController
#[IsGranted('ROLE_USER')]
public function add(Request $request): JsonResponse { ... }

// CustomerOrderController
#[IsGranted('ROLE_USER')]
public function list(Request $request): JsonResponse { ... }

#[IsGranted('ROLE_USER')]
public function get(int $id): JsonResponse { ... }

// ProfileApiController - VÉRIFIER car déjà partiellement fait
#[IsGranted('IS_AUTHENTICATED_FULLY')]
public function updateProfile(Request $request): JsonResponse { ... }

// AddressController
#[IsGranted('ROLE_USER')]
public function list(): JsonResponse { ... }

#[IsGranted('ROLE_USER')]
public function create(Request $request): JsonResponse { ... }

// CheckoutController
#[IsGranted('ROLE_USER')]
public function create(Request $request): JsonResponse { ... }

// CheckoutShippingController - VÉRIFIER (déjà #[IsGranted('ROLE_USER')])
```

**Vendor (ROLE_VENDOR):**
```php
// VendorApiController
#[IsGranted('ROLE_VENDOR')]
public function fetchShop(): JsonResponse { ... }

#[IsGranted('ROLE_VENDOR')]
public function listProducts(Request $request): JsonResponse { ... }

// Tous les autres endpoints vendor
```

**Admin (ROLE_ADMIN):**
```php
// Tous les controllers dans src/Controller/Admin/
// Ajouter globalement au-dessus de la classe:
#[IsGranted('ROLE_ADMIN')]
class AdminProductCrudController extends AbstractCrudController { ... }
```

### Task 1.3: Créer Authorization Voters pour Propriété
**Fichier nouveau:** `src/Security/VendorOwnershipVoter.php`

```php
<?php
namespace App\Security;

use App\Entity\Shop;
use App\Entity\Vendor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class VendorOwnershipVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'VENDOR_MANAGE' && $subject instanceof Shop;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $vendor = $user->getVendor();
        if (!$vendor) {
            return false;
        }

        return $vendor === $subject->getOwner();
    }
}
```

**Utilisation:**
```php
#[IsGranted('VENDOR_MANAGE', 'shop')]
public function updateShop(Shop $shop, Request $request): JsonResponse {
    // Seul le propriétaire peut accéder
}
```

---

## 🧪 Semaine 2-3: Test Coverage Sprint

### Task 2.1: Créer CheckoutControllerTest (Payment Critical!)
**Fichier:** `tests/Functional/CheckoutControllerTest.php`

```php
<?php
namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\Product;
use App\Entity\CartItem;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CheckoutControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        self::bootKernel();
        
        // Créer user + produits + cart
        $this->token = $this->loginAndGetJwt('test@example.com');
    }

    public function testCheckoutRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/checkout', [], [], [], json_encode([
            'billingAddress' => [...],
            'shippingAddress' => [...],
        ]));
        
        $this->assertResponseStatusCodeSame(401);
    }

    public function testCheckoutWithValidAddressesCreatesOrder(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/checkout',
            [],
            [],
            ['HTTP_AUTHORIZATION' => "Bearer {$this->token}"],
            json_encode([
                'billingAddress' => [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                    'email' => 'john@example.com',
                    'street' => '123 Main St',
                    'city' => 'Paris',
                    'postalCode' => '75001',
                    'country' => 'FR',
                ],
                'shippingAddress' => [...],
                'shippingLine' => 1,
            ])
        );
        
        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('orderId', $data);
    }

    public function testCheckoutWithoutAddressRejected(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/checkout',
            [],
            [],
            ['HTTP_AUTHORIZATION' => "Bearer {$this->token}"],
            json_encode([])
        );
        
        $this->assertResponseStatusCodeSame(422);
    }

    public function testCheckoutWithInvalidShippingLineFails(): void
    {
        // ...
    }

    private function loginAndGetJwt(string $email): string
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], [], json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));
        
        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'] ?? throw new \Exception('Auth failed');
    }
}
```

### Task 2.2: Créer CustomerOrderControllerTest
**Fichier:** `tests/Functional/CustomerOrderControllerTest.php`

```php
<?php
public function testListOrdersOnlyShowsUserOrders(): void
{
    // User A crée commande
    // User B essaie de voir commandes de User A
    // Doit recevoir 403 ou liste vide
}

public function testGetOrderVerifiesOwnership(): void
{
    // User A crée commande #123
    // User B tente GET /api/orders/123
    // Doit recevoir 404
}

public function testCancelOrderOnlyByOwner(): void
{
    // ...
}
```

### Task 2.3: Créer AdminControllerTests (All 9)
```
AdminProductCrudControllerTest.php
AdminUserCrudControllerTest.php
AdminOrderActionControllerTest.php
AdminVendorActionControllerTest.php
AdminLogControllerTest.php
// + 4 more
```

**Chaque test doit vérifier:**
1. ✅ Admin peut accéder et effectuer actions
2. ✅ User normal reçoit 403
3. ✅ Actions modifient correctement les données

### Task 2.4: Stratégie de Test (Priorisation)
```
Priority 1 (Done in week 2):
├─ CheckoutControllerTest (Payment)
├─ CustomerOrderControllerTest (Orders)
└─ CartControllerTest (Core commerce)

Priority 2 (Week 3):
├─ All 9 Admin controllers
├─ AuthControllerTest (2FA, login)
└─ ProfileApiControllerTest

Priority 3 (Week 4+):
├─ ProductApiControllerTest
├─ CategoryControllerTest
├─ AddressControllerTest
└─ Other API endpoints
```

---

## 🏗️ Semaine 3-4: Refactoring VendorApiController

### Task 3.1: Split into 4 Controllers

**File structure:**
```
src/Controller/Api/
├─ VendorProductController.php (250 LOC)
├─ VendorShopController.php (200 LOC)
├─ VendorOrderController.php (220 LOC)
└─ VendorMediaController.php (150 LOC)

(Original VendorApiController: DELETE)
```

### Task 3.2: Create VendorProductController
```php
<?php
#[Route('/api/vendor/products', name: 'api_vendor_products_')]
class VendorProductController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly VendorProductService $productService, // NEW SERVICE
        private readonly ImageUploader $imageUploader,
        private readonly SlugGenerator $slugGenerator, // NEW SERVICE
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[IsGranted('ROLE_VENDOR')]
    public function list(Request $request): JsonResponse
    {
        $vendor = $this->requireVendor();
        $shop = $vendor->getShop();
        
        $page = max(1, (int) $request->query->get('page', '1'));
        $limit = max(1, min(50, (int) $request->query->get('perPage', '10')));

        $pagination = $this->productRepository->filterByPaginated(
            ['shop' => $shop],
            $page,
            $limit
        );

        $items = array_map(
            fn (Product $product) => $this->productService->serialize($product),
            $pagination['items']
        );

        return $this->json([
            'items' => $items,
            'total' => $pagination['total'],
            'page' => $pagination['page'],
            'perPage' => $pagination['per_page'],
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_VENDOR')]
    public function create(Request $request): JsonResponse
    {
        $shop = $this->getVendorShop();
        
        $product = $this->productService->createFromRequest($request, $shop);
        
        return $this->json(
            $this->productService->serialize($product),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_VENDOR')]
    public function update(int $id, Request $request): JsonResponse
    {
        $shop = $this->getVendorShop();
        $product = $this->productRepository->findOneBy(['id' => $id, 'shop' => $shop]);

        if (!$product) {
            throw $this->createNotFoundException();
        }

        $product = $this->productService->updateFromRequest($product, $request);

        return $this->json($this->productService->serialize($product));
    }

    // ... autres méthodes
}
```

### Task 3.3: Create VendorProductService
```php
<?php
namespace App\Service\Vendor;

use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class VendorProductService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SlugGenerator $slugGenerator,
        private readonly ImageUploader $imageUploader,
        private readonly HtmlSanitizerInterface $sanitizer,
    ) {}

    public function createFromRequest(Request $request, Shop $shop): Product
    {
        $product = new Product();
        $product->setShop($shop);

        return $this->updateFromRequest($product, $request, isNew: true);
    }

    public function updateFromRequest(Product $product, Request $request, bool $isNew = false): Product
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        // Hydrate from payload
        if (isset($payload['name'])) {
            $product->setName($payload['name']);
        }
        // ... autres champs

        // Generate/regenerate slug
        $this->slugGenerator->generateSlug($product);

        // Sanitize HTML content
        if ($product->getDescription()) {
            $product->setDescription($this->sanitizer->sanitize($product->getDescription()));
        }

        // Handle image uploads if present
        if ($request->files->has('images')) {
            $this->handleImageUploads($product, $request);
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    public function serialize(Product $product): array
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
            'price' => (float) $product->getPrice(),
            'image' => $mainImage ? ['url' => $mainImage->getPath()] : null,
            'isPublished' => $product->isPublished(),
            'createdAt' => $product->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function handleImageUploads(Product $product, Request $request): void
    {
        foreach ($request->files->get('images', []) as $file) {
            // Upload logic
        }
    }
}
```

### Task 3.4: Create SlugGenerator Service
```php
<?php
namespace App\Service;

class SlugGenerator
{
    public function __construct(
        private readonly SluggerInterface $slugger,
        private readonly ProductRepository $productRepository,
        private readonly ShopRepository $shopRepository,
    ) {}

    public function generateSlug(object $entity, string $field = 'slug'): void
    {
        $baseValue = match (get_class($entity)) {
            Product::class => $entity->getSlug() ?: $entity->getName(),
            Shop::class => $entity->getSlug() ?: $entity->getName(),
            default => 'item',
        };

        $base = trim((string) $baseValue);
        $slugBase = (string) $this->slugger->slug($base)->lower();
        $candidate = $slugBase;
        $suffix = 0;

        $repository = $this->getRepositoryForEntity($entity);

        while (true) {
            $existing = $repository->findOneBy(['slug' => $candidate]);
            if (!$existing || $existing === $entity) {
                $entity->setSlug($candidate);
                return;
            }
            ++$suffix;
            $candidate = sprintf('%s-%d', $slugBase, $suffix);
        }
    }

    private function getRepositoryForEntity(object $entity): mixed
    {
        return match (get_class($entity)) {
            Product::class => $this->productRepository,
            Shop::class => $this->shopRepository,
        };
    }
}
```

---

## 🔐 Semaine 1: Security Hardening (Parallel)

### Task 4.1: Fix CORS Configuration
**File:** `config/packages/nelmio_cors.yaml`

```yaml
# BEFORE:
nelmio_cors:
  defaults:
    allow_origin: ['*']
    allow_credentials: true

# AFTER:
nelmio_cors:
  defaults:
    allow_origin: []  # EXPLICITLY DENY by default
    allow_credentials: false

  paths:
    '^/api/':
      allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
      allow_credentials: true
      allow_headers: ['Content-Type', 'Authorization']
      allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
```

**Update .env:**
```
# For dev (multiple origins):
CORS_ALLOW_ORIGIN=http://localhost:3000,http://localhost:4200

# For prod (single origin):
CORS_ALLOW_ORIGIN=https://app.technova.com
```

### Task 4.2: Implement Rate Limiting
**Install:** `composer require symfony/rate-limiter`

**Create:** `config/packages/rate_limiter.yaml`
```yaml
framework:
  rate_limiter:
    email_verification:
      policy: 'sliding_window'
      limit: 3
      interval: '15 minutes'
      
    password_reset:
      policy: 'sliding_window'
      limit: 5
      interval: '1 hour'

    login:
      policy: 'sliding_window'
      limit: 5
      interval: '15 minutes'
```

**Apply in Controllers:**
```php
#[Route('/verification/email/{token}', name: 'app_verify_email')]
#[RateLimiter('email_verification')]
public function verify(string $token): Response
{
    // ...
}
```

### Task 4.3: Error Handling Improvement
**Create:** `src/EventListener/ApiExceptionListener.php`

```php
<?php
class ApiExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $statusCode = 500;
        $message = 'Internal server error';

        if ($exception instanceof BadRequestHttpException) {
            $statusCode = 400;
            $message = $exception->getMessage();
        } elseif ($exception instanceof AccessDeniedException) {
            $statusCode = 403;
            $message = 'Access denied';
        } elseif ($exception instanceof NotFoundHttpException) {
            $statusCode = 404;
            $message = 'Not found';
        }

        $this->logger->error($exception->getMessage(), [
            'exception' => $exception,
            'statusCode' => $statusCode,
        ]);

        $event->setResponse(new JsonResponse(
            ['error' => $message],
            $statusCode
        ));
    }
}
```

---

## 📈 Success Metrics

### After Week 1
- ✅ All endpoints have #[IsGranted] or authorization check
- ✅ 0 critical auth vulnerabilities
- ✅ Rate limiting on sensitive endpoints

### After Week 3
- ✅ 50+ new tests written
- ✅ Test coverage: 30%+
- ✅ VendorApiController refactored into 4 focused controllers
- ✅ SlugGenerator service eliminates duplication

### After Week 4
- ✅ All critical endpoints covered by tests (70%+ coverage)
- ✅ Code quality improved (no >100 LOC methods)
- ✅ Production-ready security posture

---

## 📊 Effort Estimation

| Task | Effort | Resources |
|------|--------|-----------|
| 1. Authorization enforcement | 5 days | 1 senior dev |
| 2. Rate limiting + CORS | 2 days | 1 dev |
| 3. Test coverage (50+ tests) | 10 days | 2 devs |
| 4. VendorApiController refactor | 8 days | 1 senior dev |
| 5. SlugGenerator service | 2 days | 1 dev |
| **TOTAL** | **27 days** | **~1 month sprint** |

---

## 🚀 Implementation Order

1. **Day 1-2:** Authorization enforcement (#[IsGranted])
2. **Day 3-4:** CORS + Rate limiting + Error handling
3. **Day 5-14:** Comprehensive test suite
4. **Day 15-21:** VendorApiController refactor
5. **Day 22-28:** Buffer + final testing

All tasks should be tracked in your project management tool (GitHub Issues, Jira, etc.) with test verification.
