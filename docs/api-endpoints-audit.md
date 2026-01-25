# Audit complet des endpoints API

## Endpoints listés dans README vs implémentés

### ✅ PRÉSENTS ET ACTIFS

#### Authentification & Identité
- ✅ `GET /api/test` → `TestApiController::index()`
- ✅ `GET /api/test-audit` → `TestApiController::audit()`
- ✅ `POST /api/login` → `AuthController::login()` (firewall json_login)
- ✅ `POST /api/logout` → `AuthController::logout()`
- ✅ `POST /api/register` → `RegistrationController::register()`
- ✅ `POST /api/token/refresh` → `TokenController::refresh()`
- ✅ `GET /api/me` → `MeController::me()`

#### Email & Mot de passe
- ✅ `GET /api/email/verify/{token}` → `EmailVerificationController::verify()`
- ✅ `POST /api/email/verify/resend` → `EmailVerificationController::resend()`
- ✅ `POST /api/password-reset/request` → `PasswordResetController::request()` (NOUVEAU)
- ✅ `GET /api/password-reset/check/{token}` → `PasswordResetController::check()` (NOUVEAU)
- ✅ `POST /api/password-reset/reset` → `PasswordResetController::reset()` (NOUVEAU)

#### Catalogue & Produits
- ✅ `GET /api/categories` → `CategoryController::list()`
- ✅ `GET /api/brands` → `BrandController::list()`
- ✅ `GET /api/shops` → `ShopController::list()`
- ✅ `GET /api/shops/{slug}` → `ShopController::show()`
- ✅ `GET /api/products` → `ProductApiController::list()` (avec filtres/tri/pagination)
- ✅ `GET /api/products/{slug}` → `ProductApiController::show()`
- ✅ `GET /api/products/{id}/reviews` → `ProductReviewController::list()`
- ✅ `POST /api/products/{id}/reviews` → `ProductReviewController::create()`

#### Panier & Commandes
- ✅ `GET /api/cart` → `CartController::list()`
- ✅ `POST /api/cart` → `CartController::add()`
- ✅ `PUT /api/cart/{id}` → `CartController::update()`
- ✅ `DELETE /api/cart/{id}` → `CartController::delete()`

#### Adresses & Profil
- ✅ `GET /api/addresses` → `AddressController::list()`
- ✅ `POST /api/addresses` → `AddressController::create()`
- ✅ `PUT /api/addresses/{id}` → `AddressController::update()`
- ✅ `DELETE /api/addresses/{id}` → `AddressController::delete()`
- ✅ `GET /api/profile` → `ProfileApiController::get()`
- ✅ `POST /api/profile` (ou PUT) → `ProfileApiController::update()`
- ✅ `DELETE /api/profile` → `ProfileApiController::delete()`

#### Commandes Client
- ✅ `GET /api/orders` → `CustomerOrderController::list()`
- ✅ `GET /api/orders/{id}` → `CustomerOrderController::show()`
- ✅ `GET /api/orders/{id}/invoice` → `CustomerOrderController::invoice()`

#### Modération & Signalements
- ✅ `POST /api/report` → `ReviewReportController::report()`
- ✅ `POST /api/returns` → `ReturnRequestController::create()`

#### Checkout & Livraison
- ✅ `GET /api/checkout/shipping-options` → `CheckoutShippingController::shippingOptions()`
- ✅ `POST /api/checkout` → `CheckoutController::checkout()` (crée session Stripe + commande)

#### Documentation
- ✅ `GET /api/docs` → Swagger UI (NelmioApiDocBundle)
- ✅ `GET /api/docs.json` → JSON Schema
- ✅ `GET /api/docs.yaml` → YAML Schema

### 📋 API VENDEUR (Sprint 4A)

Présents (voir `docs/vendor-api-endpoints.md`):
- ✅ `GET /api/vendor/profile` → infos vendeur
- ✅ `PUT /api/vendor/profile` → mise à jour profil
- ✅ `GET /api/vendor/shop` → détail boutique
- ✅ `POST /api/vendor/shop` → création/édition boutique
- ✅ `POST /api/vendor/media` → upload d'images (profils shop_banner, shop_logo, product_image, avatar)
- ✅ `GET /api/vendor/products` → liste des produits du vendeur
- ✅ `GET /api/vendor/products/{id}` → détail produit (édition)
- ✅ `POST /api/vendor/products` → création de produit
- ✅ `PUT /api/vendor/products/{id}` → modification de produit
- ✅ `DELETE /api/vendor/products/{id}` → suppression de produit
- ✅ `GET /api/vendor/orders` → commandes reçues
- ✅ `GET /api/vendor/orders/{id}` → détail d'une commande
- ✅ `POST /api/vendor/orders/{id}/documents` → upload facture/BL
- ✅ `GET /api/vendor/orders/{id}/documents` → liste des documents
- ✅ `POST /api/vendor/conversations/{orderId}/messages` → messagerie client
- ✅ `GET /api/vendor/conversations/{orderId}` → consultation messagerie

### ⚠️ À AJOUTER / AMÉLIORER

#### Security & Rate Limiting
- ⏳ Rate limiting sur `/api/password-reset/request` (prévu dans `docs/security-backlog.md`)
- ⏳ Rate limiting sur `/api/register` (protection contre abus)
- ⏳ Rate limiting sur `/api/email/verify/resend` (éviter spam)

#### Wishlists (mention dans README)
- ❌ `GET /api/wishlists` – NON IMPLÉMENTÉ
- ❌ `POST /api/wishlists` – NON IMPLÉMENTÉ
- ❌ `DELETE /api/wishlists/{id}` – NON IMPLÉMENTÉ

#### Conversions & Métriques
- ⏳ Endpoints pour statistiques vendeur (en attente Sprint 4B)
- ⏳ Endpoints pour analytics client (en attente)

## Résumé

| Catégorie | Total | Implémentés | Manquants |
|-----------|-------|-------------|-----------|
| Authentification | 7 | 7 | 0 |
| Email & Password | 5 | 5 | 0 |
| Catalogue | 8 | 8 | 0 |
| Panier | 4 | 4 | 0 |
| Adresses & Profil | 7 | 7 | 0 |
| Commandes | 3 | 3 | 0 |
| Modération | 2 | 2 | 0 |
| Checkout | 2 | 2 | 0 |
| Documentation | 3 | 3 | 0 |
| **API Vendeur** | **16** | **16** | **0** |
| Wishlists | 3 | 0 | 3 |
| **TOTAL** | **60** | **57** | **3** |

## Taux de couverture
- **Endpoints core (hors wishlists)** : 100% ✅
- **Endpoints inclus README** : 95% (wishlists à ajouter si requis)
- **Production-ready** : Oui ✅
- **Swagger documentation** : Oui ✅

## Prochaines étapes

1. **Wishlists** (si requis par product manager)
   - `POST /api/wishlists` – Ajouter à favori
   - `GET /api/wishlists` – Lister favori
   - `DELETE /api/wishlists/{id}` – Retirer de favori

2. **Rate limiting** (sécurité)
   - Password reset
   - Registration
   - Email verification resend

3. **Analytics & Metrics** (Sprint 4B)
   - Endpoint stats vendeur
   - Conversion tracking
   - Popular products
