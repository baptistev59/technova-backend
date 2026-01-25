# Audit Findings - Detailed Checklist

**Project:** TechNova E-commerce Backend  
**Audit Date:** January 25, 2026  
**Status:** 🟠 Ready for Staging with Critical Fixes Required

---

## 🔴 CRITICAL FINDINGS (Must Fix Before Production)

### 1. Missing Authorization on Protected Endpoints
- [ ] **CartController** - Missing `#[IsGranted('ROLE_USER')]`
  - Affected: `add()`, `update()`, `remove()`, `clear()`
  - Risk: Non-authenticated users can manipulate anyone's cart
  - Fix: Add annotation on class or methods

- [ ] **CustomerOrderController** - Missing `#[IsGranted('ROLE_USER')]`
  - Affected: `list()`, `get()`, `cancel()`
  - Risk: Order data exposure (belongs to other users)
  - Fix: Add authorization voter to ensure user owns order

- [ ] **ProfileApiController** - Partially protected
  - Status: Some methods have `#[IsGranted('IS_AUTHENTICATED_FULLY')]` ✅
  - Review: All update/delete methods protected ❓

- [ ] **CheckoutController** - Missing `#[IsGranted('ROLE_USER')]`
  - Affected: `create()`, shipping selection
  - Risk: Non-authenticated checkout, order creation as anonymous
  - Fix: Require ROLE_USER

- [ ] **CheckoutShippingController** - Has `#[IsGranted('ROLE_USER')]` ✅
  - Status: GOOD

- [ ] **AddressController** - Verify authorization
  - Affected: All CRUD operations
  - Risk: Users can access other users' addresses
  - Fix: Add authorization + ownership check

- [ ] **ProductApiController** - Verify public access intentional
  - Public endpoints (product catalog, details) - OK if read-only
  - Action: Confirm intended public access

### 2. Rate Limiting Missing on Sensitive Endpoints
- [ ] **EmailVerificationController**
  - Route: `/verification/email/{token}`
  - Issue: Token brute force possible (6-digit codes)
  - Fix: Implement rate limiter (max 3 attempts per IP, 15 min window)
  - Impact: Account takeover vulnerability

- [ ] **PasswordResetController**
  - Routes: `/reset/{token}`, POST to confirm
  - Issue: Reset token brute force possible
  - Fix: Implement rate limiter (max 5 attempts per IP, 1 hour window)
  - Impact: Account takeover vulnerability

- [ ] **AuthController** - Login endpoint
  - Issue: No rate limiting on login attempts
  - Fix: Implement rate limiter (max 5 failed attempts, 15 min lockout)
  - Impact: Credential stuffing attacks

### 3. CORS Configuration Overly Permissive
- [ ] **File:** `config/packages/nelmio_cors.yaml`
  - Current:
    ```yaml
    defaults:
      allow_origin: ['*']  # ❌ INSECURE
    ```
  - Fix: Remove wildcard, add explicit list
    ```yaml
    defaults:
      allow_origin: []  # Deny by default
      allow_credentials: false
    
    paths:
      '^/api/':
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
    ```
  - Impact: CORS allows any origin for non-API routes

---

## 🟠 HIGH SEVERITY FINDINGS

### 4. EntityManager Direct Usage Bypasses Safety
- [ ] **WishlistController (API)**
  ```php
  // ❌ Bad:
  $this->entityManager->persist($wishlist);
  $this->entityManager->flush();
  ```
  - Issue: No transaction handling, error handling unclear
  - Fix: Use service layer (WishlistService)

- [ ] **CheckoutController**
  ```php
  // ❌ Bad:
  $address = $this->entityManager->getRepository(Address::class)->find($addressId);
  ```
  - Issue: Repository instantiation in controller
  - Fix: Inject AddressRepository instead

- [ ] **CheckoutShippingController** - Same pattern as CheckoutController

- [ ] **EmailVerificationController**
  ```php
  // Lines 66, 97: $this->entityManager->flush();
  // Issue: No error handling around flush
  ```

### 5. Generic Error Handling
- [ ] **ProfileApiController:150-152**
  ```php
  try {
      // ...
  } catch (\Throwable) {
      // ❌ Catches everything, logs nothing
  }
  ```
  - Issue: All exceptions silently ignored
  - Fix: Catch specific exceptions, log properly

- [ ] **AdminOrderActionController:146-149**
  ```php
  try {
      // ...
  } catch (\RuntimeException $exception) {
      $this->addFlash('danger', $exception->getMessage());
  }
  ```
  - Issue: Generic RuntimeException catching
  - Fix: Catch specific exceptions (PaymentException, ValidationException, etc.)

### 6. No Authorization Voters for Ownership Checks
- [ ] **VendorApiController**
  - Issue: Shop/product ownership verified via manual queries
  - Fix: Create VendorOwnershipVoter
  - Usage:
    ```php
    #[IsGranted('VENDOR_MANAGE', 'shop')]
    public function updateShop(Shop $shop): JsonResponse { ... }
    ```

- [ ] **CustomerOrderController**
  - Issue: Order ownership checked manually
  - Fix: Create OrderOwnershipVoter

- [ ] **AddressController**
  - Issue: Address ownership not checked
  - Fix: Create AddressOwnershipVoter

---

## 🟡 MEDIUM SEVERITY FINDINGS

### 7. Code Duplications - Slug Generation
- [ ] **VendorApiController.php:655-675**
  ```php
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
  ```

- [ ] **VendorApiController.php:675-695**
  ```php
  private function generateShopSlug(Shop $shop): void
  {
      // ❌ IDENTICAL LOGIC to generateProductSlug
  }
  ```

- [ ] **Fix:** Create shared `SlugGeneratorService`
  ```php
  class SlugGeneratorService {
      public function generateSlug(object $entity, string $field = 'slug'): void { ... }
  }
  ```

### 8. CSRF Token Validation Duplicated
- [ ] **CartController:49**
  ```php
  if (!$this->isCsrfTokenValid('add_to_cart_'.$product->getId(), (string) $request->request->get('_token'))) {
  ```

- [ ] **CartController:91, 135, 152** - Similar patterns

- [ ] **WishlistController:73** - Same pattern

- [ ] **VendorShippingController:126** - Same pattern

- [ ] **Fix:** Create CSRF validation middleware or trait
  ```php
  trait CsrfValidationTrait {
      protected function validateCsrfToken(string $tokenId, string $token): bool {
          return $this->isCsrfTokenValid($tokenId, $token);
      }
  }
  ```

### 9. Serialization Logic Duplicated
- [ ] **VendorApiController**
  - `serializeShop()` - ~15 LOC
  - `serializeProduct()` - ~30 LOC
  - `serializeVendor()` - ~20 LOC
  - `serializeOrder()` - ~50 LOC

- [ ] **Likely duplicated in:**
  - ProductApiController
  - CustomerOrderController
  - ProfileApiController

- [ ] **Fix:** Create serializer services
  ```php
  class ProductSerializer { public function serialize(Product $p): array { ... } }
  class OrderSerializer { public function serialize(CustomerOrder $o): array { ... } }
  ```

### 10. VendorApiController - Size Issue (1129 Lines!)
- [ ] **File:** `src/Controller/Api/VendorApiController.php`
  - Lines: 1129
  - Methods: 20+
  - Issues:
    - Too many responsibilities (products, shop, orders, media)
    - Hard to test
    - Hard to navigate
    - Complex interdependencies

- [ ] **Recommended split:**
  ```
  VendorProductController (250 LOC)
  ├─ listProducts()
  ├─ createProduct()
  ├─ getProduct()
  ├─ updateProduct()
  └─ deleteProduct()

  VendorShopController (200 LOC)
  ├─ fetchShop()
  ├─ createShop()
  ├─ updateShop()
  └─ getProfile()

  VendorOrderController (220 LOC)
  ├─ listOrders()
  ├─ getOrder()
  ├─ changeOrderStatus()
  └─ generateDocument()

  VendorMediaController (150 LOC)
  └─ uploadMedia()
  ```

### 11. Complex Product Hydration in Controller
- [ ] **VendorApiController.createProduct() and updateProduct()**
  - Methods call: `hydrateProductFromRequest()`, `generateProductSlug()`, `handleShopUploads()`, `sanitizeShopContent()`
  - Issue: Too much logic in controller

- [ ] **Fix:** Create `VendorProductService`
  ```php
  class VendorProductService {
      public function createFromRequest(Request $r, Shop $s): Product { ... }
      public function updateFromRequest(Product $p, Request $r): Product { ... }
  }
  ```

### 12. HTML Sanitization Config
- [ ] **VendorApiController**
  - Uses `#[Autowire(service: 'html_sanitizer.sanitizer.rich_text')]`
  - Check: Is sanitizer properly configured?
  - Action: Verify in `config/packages/`

### 13. Image Upload Logic Mixed in Controller
- [ ] **VendorApiController.uploadMedia()**
  - Methods: `handleShopUploads()`, `generateShopSlug()`
  - Issue: Image handling should be in ImageService

- [ ] **Fix:** Extract to `ImageUploadService`
  ```php
  class ImageUploadService {
      public function uploadShopLogo(UploadedFile $file): string { ... }
      public function uploadProductImage(UploadedFile $file): string { ... }
  }
  ```

---

## 🟡 MEDIUM SEVERITY - TEST COVERAGE GAPS

### 14. No Tests for Critical Payment Endpoints
- [ ] **CheckoutController** - 0 tests ❌
  - Methods: `create()`, `selectShipping()`, `confirmPayment()`
  - Impact: Payment failures not caught until production

- [ ] **StripePaymentService** - 0 integration tests ❌
  - Webhook handling not tested
  - Charge creation not verified

- [ ] **Fix:** Create CheckoutControllerTest with scenarios
  ```php
  public function testCheckoutWithValidAddresses() { ... }
  public function testCheckoutRequiresAuthentication() { ... }
  public function testCheckoutWithInvalidAddressFails() { ... }
  ```

### 15. No Tests for Admin Operations (9 Controllers!)
- [ ] **AdminProductCrudController** - 0 tests ❌
- [ ] **AdminUserCrudController** - 0 tests ❌
- [ ] **AdminOrderActionController** - 0 tests ❌
- [ ] **AdminVendorActionController** - 0 tests ❌
- [ ] **AdminLogController** - 0 tests ❌
- [ ] **AdminUserTwoFactorController** - 0 tests ❌
- [ ] **ReturnRequestCrudController** - 0 tests ❌
- [ ] **ConversationCrudController** - 0 tests ❌
- [ ] **AttributeDefinitionCrudController** - 0 tests ❌

- [ ] **Impact:** No verification that ROLE_ADMIN enforcement works

### 16. No Tests for Core Customer Operations
- [ ] **CartController** - 0 tests ❌
- [ ] **CustomerOrderController** - 0 tests ❌
- [ ] **ProfileApiController** - 0 tests ❌
- [ ] **AddressController** - 0 tests ❌

---

## ✅ LOW SEVERITY / INFORMATIONAL

### 17. EventSubscriber Underutilization
- [ ] **Opportunity:** Use Doctrine lifecycle events
  - `prePersist`: Auto-generate `created_at`
  - `preUpdate`: Auto-update `updated_at`
  - `prePersist`: Auto-generate slugs

- [ ] **Example:**
  ```php
  #[ORM\PrePersist]
  public function prePersist(): void
  {
      $this->createdAt = new \DateTimeImmutable();
      $this->updatedAt = new \DateTimeImmutable();
  }
  ```

### 18. Migration Documentation Status
- [ ] **Status:** ✅ All 47 migrations documented
- [ ] **Action:** Continue this practice

### 19. JWT Configuration
- [ ] **Status:** ✅ Properly configured
- [ ] **Verify:** Check `JWT_TOKEN_TTL` env var value
  - Should be: 3600-7200 seconds max
  - Not: Days or weeks

### 20. Database Indexes
- [ ] **Verify missing indexes:**
  - `product (shop_id, is_published)` - for catalog filtering
  - `customer_order (owner_id, status)` - for customer order listing
  - `product_review (product_id, rating)` - for aggregation
  - `conversation_message (conversation_id, created_at)` - for message listing

---

## 🎯 SUMMARY BY CONTROLLER

### VendorApiController (1129 LOC)
- [ ] ADD: `#[IsGranted('ROLE_VENDOR')]` to all methods
- [ ] SPLIT: Into 4 focused controllers
- [ ] EXTRACT: Slug generation → SlugGeneratorService
- [ ] EXTRACT: Image handling → ImageUploadService
- [ ] EXTRACT: Product hydration → VendorProductService
- [ ] EXTRACT: Shop management → VendorShopService

### CartController
- [ ] ADD: `#[IsGranted('ROLE_USER')]`
- [ ] VERIFY: CSRF token validation on all modifying operations
- [ ] CREATE: CartControllerTest

### CheckoutController
- [ ] ADD: `#[IsGranted('ROLE_USER')]`
- [ ] REFACTOR: Use CheckoutService (already exists)
- [ ] CREATE: CheckoutControllerTest with payment scenarios
- [ ] ADD: Rate limiting on sensitive payment operations

### CustomerOrderController
- [ ] ADD: `#[IsGranted('ROLE_USER')]`
- [ ] ADD: OrderOwnershipVoter for authorization
- [ ] CREATE: CustomerOrderControllerTest
- [ ] VERIFY: Order ownership checks on all methods

### ProfileApiController
- [ ] VERIFY: All update/delete methods have `#[IsGranted(...)]`
- [ ] FIX: Replace `catch (\Throwable)` with specific exceptions
- [ ] CREATE: ProfileApiControllerTest

### AddressController
- [ ] ADD: `#[IsGranted('ROLE_USER')]`
- [ ] ADD: AddressOwnershipVoter
- [ ] CREATE: AddressControllerTest
- [ ] VERIFY: Users can only access their addresses

### EmailVerificationController (Web)
- [ ] ADD: Rate limiting (max 3 attempts, 15 min)
- [ ] VERIFY: Token validation logic (no timing attacks)

### PasswordResetController
- [ ] ADD: Rate limiting (max 5 attempts, 1 hour)
- [ ] ADD: `#[IsGranted('PUBLIC_ACCESS')]` explicitly
- [ ] VERIFY: Token validation security

### AuthController
- [ ] ADD: Login rate limiting (5 attempts, 15 min)
- [ ] VERIFY: 2FA is enforced when configured

### All Admin Controllers (9 total)
- [ ] ADD: Class-level `#[IsGranted('ROLE_ADMIN')]`
- [ ] CREATE: Comprehensive tests for all 9 controllers
- [ ] VERIFY: All sensitive actions (delete, suspend) require admin role

---

## 📋 VERIFICATION CHECKLIST

### Pre-Production Requirements
- [ ] All endpoints have explicit authorization (`#[IsGranted]` or `denyAccessUnlessGranted()`)
- [ ] Ownership checks in place for user-specific resources
- [ ] Rate limiting on: login, password reset, email verification
- [ ] CSRF protection verified on all form endpoints
- [ ] Error handling logs exceptions properly
- [ ] No generic `catch (\Throwable)` blocks
- [ ] CORS configuration restricted to known origins
- [ ] JWT token TTL is reasonable (≤7200 seconds)
- [ ] Database indexes on frequently queried columns exist
- [ ] At least 50+ functional tests covering critical paths
- [ ] Payment flow tested end-to-end
- [ ] Admin operations verified with authorization tests

### Code Quality Requirements
- [ ] No controllers exceed 500 LOC
- [ ] No private methods exceed 50 LOC
- [ ] Duplicate logic extracted to services
- [ ] All business logic in Service layer, not Controller
- [ ] EntityManager only used in services, not controllers
- [ ] Form handling extracted from complex hydration logic
- [ ] Serialization logic centralized

### Security Audit Checklist
- [ ] SQL injection: ✅ Not possible (ORM)
- [ ] CSRF: ✅ Tokens validated
- [ ] Authorization: ⚠️ Need improvements
- [ ] Authentication: ✅ JWT configured
- [ ] Password security: ✅ Hashed properly
- [ ] Rate limiting: ❌ Missing (critical)
- [ ] CORS: ⚠️ Need hardening
- [ ] Error messages: ❌ Need improvement (generic catches)
- [ ] Logging: ⚠️ Check coverage

---

## 📞 Questions for Team

1. **JWT Token TTL:** What's the current value of `JWT_TOKEN_TTL` in .env?
2. **CORS Origins:** Should CORS allow `http://localhost:3000` for development?
3. **Payment Webhook:** How is Stripe webhook verification tested?
4. **2FA Enforcement:** Is 2FA required for admin users?
5. **Admin Roles:** How many distinct admin roles? (ROLE_ADMIN, ROLE_MODERATOR, etc.)
6. **Data Sensitivity:** Are there fields that should be masked in logs? (SSN, passwords, etc.)

---

## 📌 Change Log

| Date | Finding | Status |
|------|---------|--------|
| 2026-01-25 | Initial audit | Complete |
| TBD | Authorization enforcement | In Progress |
| TBD | Test coverage expansion | Pending |
| TBD | VendorApiController refactor | Pending |
| TBD | Production readiness | Pending |
