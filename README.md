🛒 TechNova Marketplace — Backend API
====================================

Symfony 7.3 • PostgreSQL • JWT Auth • Swagger UI • Modular Architecture

Bienvenue dans l’API officielle du projet TechNova Marketplace, une plateforme e-commerce multi‑vendeurs construite avec Symfony 7.3. Ce backend gère l’authentification, les utilisateurs, les vendeurs, les produits, les commandes et la gouvernance de la marketplace.

Sommaire
--------

- [Stack & modules clés](#stack--modules-clés)
- [Audit des endpoints API](#audit-des-endpoints-api)
- [Qualité de code (PSR-12 & Best Practices)](#qualité-de-code-psr-12--best-practices)
- [Confirmation email](#confirmation-email)
- [Endpoints disponibles](#endpoints-disponibles)
- [Installation locale (dev)](#installation-locale-dev)
- [Authentification JWT & Postman](#authentification-jwt--postman)
- [Documentation API (Swagger)](#documentation-api-swagger)
- [Déploiement Alwaysdata (prod)](#déploiement-alwaysdata-prod)
- [Scripts utiles](#scripts-utiles)
- [Comptes de démo](#comptes-de-démo)
- [Design / UI](#design--ui)

Stack & modules clés
--------------------

- **Symfony 7.3 (full attributes)** – Architecture modulaire, domaines `User`, `Vendor`, `Product`, `Order`, …  
- **Base PostgreSQL** – Doctrine ORM 3, migrations versionnées.  
- **Authentification** – LexikJWTAuthenticationBundle (login JSON → JWT).  
- **Double auth** – OTP email pour clients, TOTP obligatoire pour vendeurs/admin, audit des validations.  
- **Mot de passe oublié** – SymfonyCasts ResetPasswordBundle (email + lien 5 min).  
- **Audit & logs** – `AuditLoggerService`, audit des actions admin + page de consultation, export et purge des logs applicatifs.  
- **Documentation** – NelmioApiDocBundle + Swagger UI exposé sur `/api/docs`.  
- **Sécurité** – Firewalls séparés (`/api/login`, `/api/docs`, zone `/api/**` protégée).  
- **Front tooling** – AssetMapper + Stimulus pour interfacer la doc ou l’admin.  
- **Catalogue avancé** – Attributs/valeurs/variantes (prix/promo/stock/image par combinaison) + sélection d’options côté front.  
- **Livraison** – Zones, méthodes et tarifs par boutique + calcul au checkout (poids + adresse).  
- **Avis produits** – Notes demi-étoiles, modération et signalements (admin).  
- **Alertes stock** – Seuils par produit/variante + email vendeur + audit.  
- **Monitoring** – Monolog JSON sur `php://stderr` en prod (Alwaysdata récupère les logs PHP).
- **Paiement** – Stripe Checkout + webhook `/stripe/webhook` pour confirmer les commandes.

Audit des endpoints API
-----------------------

Un audit pédagogique complet des endpoints (attendu vs exposé) est disponible ici :
`docs/api-audit.md`

Audit complet avec status de chaque endpoint :
`docs/api-endpoints-audit.md`

Confirmation email
------------------

Guide d’usage (token, expiration, relance) :
`docs/email-verification.md`
Réinitialisation de mot de passe
---------------------------------

Guide complet (flux, configuration SMTP, tests, troubleshooting) :
`docs/password-reset.md`
### Webpack Encore vs Asset Mapper (justification)

Ce projet n’utilise pas Webpack Encore : il s’appuie sur **Asset Mapper + importmap + Tailwind CLI**.  
Pour l’activité demandée, l’équivalence fonctionnelle est la suivante :

- **Bundling JS/CSS**
  - Encore : build bundlé (Webpack) via `encore production`.
  - Asset Mapper : chargement ES modules via `importmap.php` + `assets/` (pas de bundling).
- **Minification**
  - Encore : minification intégrée Webpack.
  - Ici : `npm run build` (Tailwind `--minify`) pour `assets/styles/app.css`.
- **Cache assets**
  - Encore : fichiers versionnés (hash) → cache long.
  - Asset Mapper : versionning géré par Symfony (asset mapper), possibilité d’ajouter des headers de cache côté serveur.
- **Chargement JS**
  - Encore : `{{ encore_entry_script_tags(...) }}`.
  - Asset Mapper : `importmap()`/`asset()` + modules ES (voir `importmap.php`).

Ce choix est aligné avec Symfony 7 (Asset Mapper recommandé) et reste acceptable pour la consigne,
tant que la minification CSS est assurée et que la politique de cache des assets est documentée.

Notes complémentaires
- **Build CSS en prod** : `npm run build` (Tailwind CLI avec `--minify`, voir `package.json`).
- **Cache assets** : config serveur à prévoir (ex. `Cache-Control: public, max-age=31536000, immutable` sur `public/assets/`).

Qualité de code (PSR-12 & Best Practices)
----------------------------------------

Outils ajoutés :
- PHP-CS-Fixer (PSR-12 + Symfony)
- PHPStan (niveau 5)
- Lint Symfony (Twig/YAML/Container)

Installation des outils

```bash
composer update friendsofphp/php-cs-fixer phpstan/phpstan phpstan/phpstan-symfony
```

Note : si tu modifies `composer.json`, il faut mettre à jour `composer.lock` via `composer update <packages>` pour que les outils soient réellement installés.

⚠️ Avertissement PHP-CS-Fixer  
Si tu exécutes PHP-CS-Fixer avec PHP 8.3 alors que le projet cible PHP 8.2 (`composer.json`), l’outil affiche un warning.  
Recommandation : lancer `composer lint` avec PHP 8.2 pour éviter d’introduire une syntaxe non supportée.

Commandes pédagogiques (preuve outillée)

```bash
# Vérifier sans modifier
composer lint

# Corriger automatiquement le style
composer lint-fix
```

Endpoints disponibles
---------------------

| Méthode | Route                           | Description                                                      | Auth |
|---------|---------------------------------|------------------------------------------------------------------|------|
| GET     | `/api/test`                     | Vérifie l’uptime de l’API (log dans monolog).                    | Publique |
| GET     | `/api/test-audit`               | Génère une entrée dans `audit_log`.                              | JWT |
| POST    | `/api/login`                    | Authentifie via email/password, renvoie JWT.                     | Publique |
| POST    | `/api/logout`                   | Déconnexion stateless (côté client).                             | JWT |
| POST    | `/api/register`                 | Inscription client + JWT de bienvenue.                           | Publique |
| POST    | `/api/token/refresh`            | Régénère un JWT à partir du token courant.                       | JWT |
| GET     | `/api/me`                       | Infos du user connecté (id/email).                               | JWT || POST    | `/api/password-reset/request`   | Demande de réinitialisation par email (token valable 5 min).     | Publique |
| GET     | `/api/password-reset/check/{token}`| Valide un token sans effectuer la réinitialisation.           | Publique |
| POST    | `/api/password-reset/reset`     | Réinitialise le mot de passe avec token + nouveau mot de passe.  | Publique || GET     | `/api/email/verify/{token}`     | Confirme l’email (token).                                        | Publique |
| POST    | `/api/email/verify/resend`      | Renvoie un lien de confirmation.                                 | JWT |
| GET     | `/api/categories`               | Liste des catégories.                                            | Publique |
| GET     | `/api/brands`                   | Liste des marques.                                               | Publique |
| GET     | `/api/shops`                    | Liste des boutiques + note moyenne.                              | Publique |
| GET     | `/api/shops/{slug}`             | Détail d’une boutique.                                           | Publique |
| GET     | `/api/products`                 | Liste produits (filtres + tri + pagination).                     | Publique |
| GET     | `/api/products/{slug}`          | Fiche produit détaillée (prix, variantes, images, avis).         | Publique |
| GET     | `/api/products/{id}/reviews`    | Avis approuvés d’un produit.                                     | Publique |
| POST    | `/api/products/{id}/reviews`    | Crée/maj un avis (achat requis).                                 | JWT |
| GET     | `/api/cart`                     | Contenu du panier (session).                                     | JWT |
| POST    | `/api/cart`                     | Ajoute un produit (JSON `{ productId, quantity }`).              | JWT |
| PUT     | `/api/cart/{id}`                | Met à jour la quantité (`quantity`, `variantId`).                | JWT |
| DELETE  | `/api/cart/{id}`                | Supprime un produit du panier.                                   | JWT |
| GET     | `/api/wishlists`                | Liste les favoris de l'utilisateur.                              | JWT |
| POST    | `/api/wishlists`                | Ajoute un produit aux favoris (JSON `{ productId }`).            | JWT |
| DELETE  | `/api/wishlists/{id}`           | Retire un produit des favoris.                                   | JWT |
| GET     | `/api/addresses`                | Liste des adresses client.                                       | JWT |
| POST    | `/api/addresses`                | Crée une adresse.                                                | JWT |
| PUT     | `/api/addresses/{id}`           | Met à jour une adresse.                                          | JWT |
| DELETE  | `/api/addresses/{id}`           | Supprime une adresse.                                            | JWT |
| GET     | `/api/orders`                   | Liste paginée des commandes client.                              | JWT |
| GET     | `/api/orders/{id}`              | Détail d’une commande client.                                    | JWT |
| GET     | `/api/orders/{id}/invoice`      | Lien de facture (commande payée).                                | JWT |
| POST    | `/api/returns`                  | Demande de retour.                                               | JWT |
| POST    | `/api/report`                   | Signalement d’avis produit.                                      | JWT |
| GET     | `/api/checkout/shipping-options`| Options de livraison par boutique (adresse).                     | JWT |
| POST    | `/api/checkout`                 | Crée une session Stripe + commande.                              | JWT |
| GET     | `/api/docs`                     | Swagger UI (documentation interactive).                          | Publique (à protéger en prod) |

## API Vendeur

- Nouvelle documentation détaillée : [`docs/vendor-api-endpoints.md`](docs/vendor-api-endpoints.md) (shop/profile, produits, commandes, upload de média).  
- Ces routes requièrent `Authorization: Bearer <jwt>` avec `ROLE_VENDOR` et s’appuient sur les profils `ImageProfile` (logo/shop_banner/product_image/avatars).
- Ajoute ces appels à la collection Postman (`postman/technova-api.postman_collection.json`) quand tu travailleras sur le front vendeur.
- `POST /api/vendor/media` centralise les uploads (profils `shop_banner|shop_logo|product_image|avatar`), stocke chaque fichier dans la table `media` et renvoie maintenant `{ id, profile, path, url, width, height, mimeType }` pour que le front puisse lier l’ID à la fiche boutique ou produit.
- `POST /api/vendor/orders/{id}/documents` et `GET /api/vendor/orders/{id}/documents` gèrent les factures/bon de livraison (PDF, hash, URL, id) via `OrderDocumentGenerator` + table `order_document`. Les boutons “Télécharger PDF” dans le dashboard s’appuient sur ces endpoints.
- Côté client, la facture se récupère via `GET /api/orders/{id}/invoice` (lien PDF), tandis que le dashboard vendeur utilise les endpoints `/api/vendor/orders/{id}/documents`.
- `POST /api/vendor/conversations/{orderId}/messages` / `GET /api/vendor/conversations/{orderId}` + les variantes `/api/account/...` permettent de créer/consulter une messagerie interne client ↔ vendeur pour chaque commande (`Conversation`, `Message`). Ces endpoints seront repris par le dashboard vendeur et la fiche commande du client (fetch + Alpine + `<meta name="technova-jwt">` pour les JS calls).

**Query params utiles (`/api/products`)**

| Paramètre | Exemple | Description |
|-----------|---------|-------------|
| `category` | `future-laptops` | Filtre par slug de catégorie |
| `brand` | `aurora-dynamics` | Filtre par marque |
| `minPrice` / `maxPrice` | `minPrice=500&maxPrice=2500` | Fourchette de prix (euros) |
| `search` | `quantum` | Recherche plein texte dans le nom / résumé |
| `sort` | `price_desc` | `newest`, `oldest`, `price_asc`, `price_desc` |

Pages Twig (catalogue)
----------------------

- `/` : accueil + sections “Nouveautés” et “Produits à la une”.
- Les carrousels “tn-carousel” (home, vitrine boutique, dashboard vendeur) utilisent Swiper + attribut `data-swiper-visible` pour afficher trois slides simultanés ; ils affichent jusqu’à 10 produits, en complétant les slides manquants et en conservant une UX identique sur toutes les pages.
- `/boutiques` : annuaire public des boutiques (recherche + pagination).
- `/boutique/{slug}` : vitrine publique d’une boutique (bannière, produits phares, notes).
- `/boutique/{slug}/catalogue` : catalogue complet d’une boutique avec pagination.
- `/catalogue` : listing avec filtres catégorie/marque/prix/texte + tri (soumission automatique au changement ou via Entrée).
- `/panier` + `/commande` : panier interactif puis checkout récapitulatif avant création de la commande + page de succès.
- `/mon-compte/commandes` : historique de commandes + détail par référence.
- `/mon-compte/profil` : mise à jour des informations + suppression/anonymisation RGPD du compte.
- `/mon-compte/favoris` : liste des produits en favoris avec option CRUD (supprimer / ajouter au panier).
- `/mon-compte/2fa` : configuration TOTP (vendeurs/admin).
- Confirmation d’une commande déclenche un e-mail (HTML + texte) envoyé via le SMTP configuré (`MAILER_DSN`).
- `/produit/{slug}` : fiche produit (images, caractéristiques, options, variantes).
- `/panier` : récapitulatif du panier stocké côté session (ajout/suppression/vidage) — accès réservé aux clients connectés.
- La recherche catalogue et globale utilise un dropdown custom sans historique : suggestions provenant uniquement des noms et mots clés produits, filtrées côté serveur, fermeture sur Enter ou blur, annulation des fetchs en cours et pagination dynamique calculée `rows × columns` grâce au JS commun (`base.html.twig`).

Espace compte (Twig + API)
--------------------------

- `/inscription` : formulaire Tailwind qui appelle directement `POST /api/register`.  
  Après validation l’utilisateur est automatiquement connecté (ID + JWT stockés en session) puis redirigé vers `/mon-compte/profil`.
- `/connexion` : formulaire Symfony (`LoginType`) branché sur `App\Security\LoginFormAuthenticator` (firewall `main`). L’utilisateur est authentifié via `Security`, la session Symfony est ouverte (remember-me disponible) et un JWT Lexik est toujours regénéré pour alimenter les pages Twig (`viewer_user()` s’appuie désormais sur `Security` quand c’est possible).  
- **Réinitialisation de mot de passe** (voir [`docs/password-reset.md`](docs/password-reset.md)) :
  - **Routes Twig** (pages HTML) :
    - `/connexion/mot-de-passe-oublie` (GET/POST) : demande de réinitialisation par email (lien valable 5 minutes).
    - `/connexion/mot-de-passe-oublie/check-email` (GET) : page de confirmation après la demande.
    - `/connexion/mot-de-passe-oublie/reset/{token}` (GET/POST) : formulaire de réinitialisation avec validation du token.
  - **Endpoints API JSON** (pour SPA/mobile) :
    - `POST /api/password-reset/request` : demande de réinitialisation (JSON `{ email }`).
    - `GET /api/password-reset/check/{token}` : valide un token (optionnel).
    - `POST /api/password-reset/reset` : réinitialise le password (JSON `{ token, password }`).
- `/mon-compte/changer-mot-de-passe` (GET/POST) : formulaire pour les utilisateurs connectés qui souhaitent changer leur mot de passe.  
- `/mon-compte/profil` : page composée de deux formulaires (`ProfileType`, `AddressType`) pour compléter les informations personnelles, préférences marketing et adresse principale.  
- `/mon-compte/2fa` : activation TOTP et QR code pour les vendeurs/admin.  
- `/api/profile` (GET/POST/DELETE) : endpoints jumeaux utilisés par le front Twig, protégés par le firewall JWT (`DELETE` anonymise le compte).
- **Uploads** : les fichiers (logos, bannières, visuels produits) ne sont plus traités dans chaque controller. Ils passent tous par `ImageProfile` / `ImageUploader` (WebP, redimensionnement, backgrounds uniformes) et utilisent les profils `shop_banner`, `shop_logo`, `product_image` ou `avatar`.

Espace vendeur (Sprint 4A)
--------------------------

- Accessible aux comptes vendeurs (`ROLE_VENDOR`) pour la consultation/modification de la boutique.  
- Menu profil → **Mon espace vendeur** → `/mon-espace-vendeur/boutique`.
- `ShopType` (Twig) permet de créer la boutique : nom, description, email de contact, politiques SAV + upload optionnel d’un logo/bannière (stockés dans `public/uploads/shops`).
- Le traitement des uploads y est orchestré par le service `App\Image\ImageUploader` avec les profils `shop_banner` / `shop_logo`, le reste de la logique (déplacement, création de répertoires) étant totalement centralisé.
- Le slug est généré automatiquement (unique) et la boutique est liée au profil `Vendor` du user.
- Gestion livraison : `/mon-espace-vendeur/livraison` permet de configurer zones, méthodes et grilles tarifaires (poids/zone).
- Gestion stock : un seuil “stock faible” est disponible sur produit ou variante et déclenche un email vendeur.
- Si une boutique existe déjà, la page affiche les informations en attendant les US d’édition / gestion.
- Un client peut créer sa boutique : lors de la soumission du formulaire, un profil `Vendor` est créé/associé et le rôle `ROLE_VENDOR` est ajouté automatiquement. Les vendeurs existants ne voient que la page de gestion.

Bundles & packs groupés
-----------------------

- L’onglet **Pack de produits** de la fiche vendeur (`templates/vendor/product/form.html.twig`) permet de composer un bundle à partir de produits simples/variables existants, de rendre certains composants obligatoires et de définir une remise globale (champ `bundleDiscountPercent`).  
- Côté client (`templates/catalog/product_show.html.twig`), la section “Configure ton pack” repose sur `bundleComposer` (Alpine). Chaque composant peut être configuré plusieurs fois avec des variantes différentes, une modale affiche la fiche produit et le prix/stock de la configuration sélectionnée.  
- Le bloc résumé au-dessus du bouton “Ajouter au panier” indique :
  - le détail des configurations retenues (prix/stock/unités) ;
  - le prix total du pack et, le cas échéant, la remise appliquée (pourcentage + montant) ;
  - une plage indicative “prix min / max des composants” tant que le pack n’est pas entièrement configuré.  
- Le panier (`templates/cart/show.html.twig`) liste chaque composant comme une ligne indépendante. Les réductions pack sont répercutées sur les prix unitaires et un libellé “Remise appliquée” rappelle le montant économisé par ligne.  
- La validation impose au moins deux configurations distinctes avant l’ajout au panier. Lors de l’envoi, `CartController::add()` transforme les sélections client en ajouts de produits réels, applique la remise pack et refuse les configurations incomplètes.
- Checkout : `CheckoutService` vérifie désormais le stock disponible (produits simples, variantes et composants de packs) avant de créer la commande puis décrémente automatiquement les stocks lors de la confirmation du paiement. Toute rupture détectée bloque l’opération.

Installation locale (dev)
-------------------------

Prérequis : PHP 8.2+, Composer 2, PostgreSQL 16, Node (facultatif pour assets).

```bash
git clone https://github.com/baptistev59/technova-backend.git
cd technova-backend
cp .env.dev .env.local         # exemple fourni pour WSL2
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
symfony serve -d               # ou php -S localhost:8000 -t public
```

> Le fichier `.env.local` peut contenir une URL Postgres locale (WSL2) et une passphrase JWT de développement.

Authentification JWT & Postman
------------------------------

### Login

1. `POST /api/login` avec JSON :

   ```json
   { "email": "user@example.com", "password": "password" }
   ```

2. Réponse :

   ```json
   { "token": "xxx.yyy.zzz" }
   ```

3. Dans Postman, ajoutez dans l’onglet **Tests** :

   ```js
   const data = pm.response.json();
   pm.collectionVariables.set("jwt_token", data.token);
   ```

4. Dans vos requêtes protégées, utilisez l’en‑tête `Authorization: Bearer {{jwt_token}}`.

### Inscription client

`POST /api/register` accepte :

```json
{
  "email": "client@test.fr",
  "password": "P@ssword123",
  "firstname": "Alex",
  "lastname": "Martin"
}
```

La réponse retourne directement un token et les informations du compte créé, ce qui permet de connecter l’utilisateur immédiatement après son inscription.

### Garder la session ouverte

- Les tokens expirent après `JWT_TOKEN_TTL` secondes (par défaut 86400 s = 24 h, configurable via l’ENV).
- Appelez `POST /api/token/refresh` avec le JWT actuel pour en obtenir un nouveau (`{ "token": "...", "expiresIn": 3600 }`).  
- Le front peut automatiser cette requête pour prolonger la session tant que l’utilisateur est actif.

### Paiement Stripe (Checkout)

- Le bouton “Payer en ligne (Stripe)” sur `/commande` crée une session Stripe Checkout avec les lignes du panier.  
- URLs : `success_url` → `/commande/confirmee/{reference}?session_id=...` et `cancel_url` → `/commande/annulee/{reference}`.  
- Lors du retour Stripe, l’utilisateur voit l’état de la commande (paiement confirmé ou en attente).  
- Webhook `/stripe/webhook` : Stripe envoie `checkout.session.completed`, on vérifie la signature (`STRIPE_WEBHOOK_SECRET`) puis on bascule la commande en `paid` et on envoie l’e-mail.

Variables à déclarer (`.env.local` + Alwaysdata) :

```
STRIPE_SECRET_KEY=sk_test_xxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxx      # optionnel (exposé côté front)
STRIPE_WEBHOOK_SECRET=whsec_xxx         # récupéré via Stripe CLI ou Dashboard
```

Stripe Checkout (front + CSP)
--------------------------------

- `STRIPE_PUBLISHABLE_KEY` expose la clé `pk_…` au navigateur. Dans `config/services.yaml`, lie-la à un paramètre (ex. `stripe.public_key: '%env(STRIPE_PUBLISHABLE_KEY)%'`) puis transmets-la au template `templates/checkout/index.html.twig` via le contrôleur `App\Controller\Web\CheckoutController` (paramètre `stripe_public_key`). Le template doit ensuite charger `<script src="https://js.stripe.com/v3/"></script>` et initialiser Stripe (`const stripe = Stripe('{{ stripe_public_key }}');`) avant d’appeler `stripe.redirectToCheckout({ sessionId: 'cs_test_...' })`.
- `ContentSecurityPolicySubscriber` étend le CSP pour inclure un `connect-src` autorisant `https://checkout.stripe.com` et `https://api.stripe.com`, ce qui empêche les bloqueurs (`default-src 'self'`) de refuser les requêtes Stripe. Cette configuration est déjà appliquée dans `src/EventSubscriber/ContentSecurityPolicySubscriber.php`.

Webhook en local (Stripe CLI) :

```bash
stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook
```

### Tests automatisés (Postman / Newman)

- La collection `postman/technova-api.postman_collection.json` couvre : `/api/test`, `/api/login`, `/api/me`, `/api/products`, `/api/cart`, `/api/token/refresh`, `/api/profile`.
- L’environnement `postman/local.postman_environment.json` contient les variables utilisées (email/mot de passe, infos de profil, etc.).
- Le script `./scripts/postman-tests.sh` lance `newman` avec la collection + l’environnement.  
  Assure-toi que le serveur Symfony tourne (`symfony serve -d` ou équivalent) avant d’exécuter :

  ```bash
  ./scripts/postman-tests.sh                                    # utilise newman global si dispo
  ./scripts/postman-tests.sh <collection> <env> --reporters cli  # options avancées
  ```

Documentation API (Swagger)
---------------------------

**NelmioApiDocBundle** expose la documentation interactive via Swagger UI :

- **UI locale** : <http://localhost:8000/api/docs>  
- **JSON Schema** : <http://localhost:8000/api/docs.json>  
- **YAML Schema** : <http://localhost:8000/api/docs.yaml>  

**Configuration** :
- Bundle : `NelmioApiDocBundle` configuré dans `config/packages/nelmio_api_doc.yaml`
- Attributs PHP : Les endpoints sont documentés via `#[OA\...]` (OpenAPI) sur les contrôleurs (`src/Controller/Api/`).
- **Firewall** : Swagger est public par défaut (firewall `docs`). En production, **protégez cet accès** :
  - HTTP Basic Auth sur Alwaysdata
  - IP allowlist (recommandé)
  - Ou IP whitelisting Nginx (`docker/nginx.conf`)

**Endpoints clés** :
- POST `/api/login` : Authentification JWT (voir "Authentification JWT" ci-dessus)
- GET `/api/products` : Catalogue produits avec filtres
- POST|PUT `/api/vendor/shop`, `/api/vendor/profile` : Gestion boutique vendeur (voir `docs/vendor-api-endpoints.md`)
- CRUD `/api/orders`, `/api/wishlists`, `/api/addresses`, `/api/reviews` : Ressources utilisateur

Déploiement Alwaysdata (prod)
-----------------------------

1. **Manager Alwaysdata**
   - Créez un site web pointant sur `/home/technova/www/technova-backend/public`.
   - Forcez PHP 8.2 (web + SSH) et Composer 2.
2. **Variables d’environnement** (Configuration → Variables d’environnement) :

   ```
   APP_ENV=prod
   APP_DEBUG=0
   APP_SECRET=<openssl rand -hex 32>
   DATABASE_URL=postgresql://technova:<motdepasse>@postgresql-technova.alwaysdata.net:5432/technova_api?serverVersion=16&charset=utf8
   JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
   JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
   JWT_PASSPHRASE=<même valeur que celle utilisée pour lexik:jwt:generate-keypair>
   JWT_TOKEN_TTL=86400
   CORS_ALLOW_ORIGIN=https://technova.alwaysdata.net

  MAILER_DSN=smtp://technova@alwaysdata.net:xxxxx@smtp-technova.alwaysdata.net:587
  MAILER_FROM="TechNova <technova@alwaysdata.net>"
  MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
  DEFAULT_URI=<https://technova.alwaysdata.net>
  STRIPE_SECRET_KEY=sk_live_xxx
  STRIPE_PUBLISHABLE_KEY=pk_live_xxx
  STRIPE_WEBHOOK_SECRET=whsec_xxx

  ```
3. **Première installation via SSH** :
   ```bash
   cd ~/www
   git clone https://github.com/baptistev59/technova-backend.git
   cd technova-backend
   composer install --no-dev --optimize-autoloader
   php bin/console lexik:jwt:generate-keypair   # respectez la passphrase ci-dessus
   php bin/console doctrine:migrations:migrate --no-interaction --env=prod
   php bin/console app:create-admin --env=prod   # crée admin@test.fr ou équivalent
   ```

1. **Compilation des envs pour les workflows** :  
   Toujours sur Alwaysdata, générez le cache des variables :

   ```bash
   composer dump-env prod
   php bin/console cache:clear --env=prod --no-warmup
   ```

   Cela crée `.env.local.php` (non versionné) contenant les variables ; toutes les commandes (cron, GitHub Actions) utiliseront automatiquement les bons secrets.
2. **Automatisation GitHub Actions** (`.github/workflows/deploy-alwaysdata.yml`) :
   - Secrets requis : `SSH_REMOTE_HOST`, `SSH_REMOTE_PORT`, `SSH_REMOTE_USER`, `SSH_PRIVATE_KEY`, `DEPLOY_PATH`.
   - Le workflow rsync le code, puis exécute sur Alwaysdata :

     ```bash
     composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
     php bin/console lexik:jwt:generate-keypair --no-interaction    # génère si absent
     php bin/console doctrine:migrations:migrate --no-interaction --env=prod
     php bin/console cache:clear --env=prod --no-warmup
     ```

   - Grâce à `composer dump-env prod`, les commandes voient `DATABASE_URL` et `JWT_*` sans avoir à exporter les variables dans le workflow.

Scripts utiles
--------------

- `php bin/console app:create-admin` – Create/update admin interactif.
- `php bin/console doctrine:fixtures:load` – (quand des fixtures seront ajoutées).
- `php bin/console make:migration` – Génère les migrations lors des évolutions du schéma.
- `php bin/console cache:clear --env=prod --no-warmup` – À utiliser après toute modification de config en prod.
- `npm run optimize-images` – Convertit les images `public/images/**/*.{png,jpg}` en WebP via `sharp` (utile avant un push pour réduire le poids des médias).
- `bash scripts/api-smoke.sh https://technova.alwaysdata.net` – Smoke-test API (curl + jq requis, URL optionnelle).
- `bash scripts/setup-test-db.sh` – Crée `technova_test`, applique les migrations et recharge les fixtures avant de lancer les tests PHPUnit (`APP_ENV=test`). Doctrine ajoute automatiquement `_test` au nom.

Tests automatisés
-----------------

- **Stack** : PHPUnit 11 + WebTestCase.  
- **Couverture actuelle** :
  - `tests/Unit/UserRegistrationServiceTest` vérifie la création de compte et la validation côté `UserRegistrationService`.
  - `tests/Functional/TestApiControllerTest` boot le kernel et s’assure que `/api/test` répond correctement (JSON + statut 200).
- **Exécution** :

  ```bash
  ./vendor/bin/phpunit        # Linux/WSL/macOS
  vendor\bin\phpunit.bat      # Windows
  ```

  La configuration est centralisée dans `phpunit.dist.xml` et la bootstrap `tests/bootstrap.php` charge l’autoloader + `.env`.

Tests API (Newman/Postman)
--------------------------

- Les scénarios sont décrits dans `postman/technova-api.postman_collection.json` + l’environnement `postman/local.postman_environment.json`.  
- On peut les éditer via Postman si besoin, mais l’exécution se fait désormais exclusivement via Newman (CLI).  
- Avant lancement : renseignez `baseUrl`, `loginEmail` et `loginPassword`. La requête catalogue se charge de remplir `sampleProductSlug` et `cartProductId` avec un produit publié.
- Commande standard :

  ```bash
  ./scripts/postman-tests.sh                                    # utilise newman global si dispo
  ./scripts/postman-tests.sh <collection> <env> --reporters cli  # options avancées
  ```

  Le script choisit automatiquement `newman` (global) ou `npx --yes newman` en fallback.

Bonnes pratiques / sécurité
---------------------------

- Ne versionnez jamais `config/jwt/*.pem` ni `.env.local.php`.  
- Après chaque changement de passphrase, régénérez les clés :  
  `rm config/jwt/*.pem && php bin/console lexik:jwt:generate-keypair`.  
- Swagger étant public, pensez à activer une protection HTTP Basic sur Alwaysdata.  
- Monitorer `~/logs/php-*.log` sur Alwaysdata pour diagnostiquer les 500.  
- Les endpoints `/api/test*` peuvent être désactivés en prod (feature flag) via un firewall si nécessaire.
- **2FA** : clients → OTP email (trusted device optionnel), vendeurs/admin → TOTP obligatoire (QR code dans `/mon-compte/2fa`).
- **Droit à l’oubli** : via `/mon-compte/profil`, un utilisateur peut supprimer son compte. Les données sont anonymisées (`email deleted-xxxx@technova.local`, avatars effacés, adresses et paniers supprimés) et le champ `is_deleted` bloque toute reconnexion.

Design / UI
-----------

- Maquettes (Figma/PDF) : `docs/maquettes.pdf`
- Synthèse palette/typo/composants : `docs/design-system.md`
- Pages Twig alignées sur ces maquettes : `/`, `/catalogue`, `/produit/{slug}`
- **Assets locaux** : toutes les illustrations/placeholder sont versionnées dans `public/assets/images/` pour éviter les liens externes (logo, hero, pictos catégories, visuels produits).
- **Commentaires Twig** : chaque template (`templates/catalog/*.html.twig` + `templates/base.html.twig`) contient des commentaires en français qui servent de pense-bête pour se rappeler le rôle des sections (utile pour la soutenance).

Comptes de démo
---------------

- **Admin** : `admin@test.fr` / `123456`
- **Vendeurs** : `vendor01@technova.test` → `vendor10@technova.test` / `Vendor#0X`
- **Endpoints vendor** : `GET|POST|PUT /api/vendor/shop` gèrent la boutique (logo/bannière via `ImageUploader`, policies, contact) et `GET|PUT /api/vendor/profile` expose les coordonnées du vendeur. Voir `docs/vendor-api-endpoints.md` pour les payloads et Swagger.
- **Clients** : `lena.client@technova.test` / `Client#01`, `maxime.client@technova.test` / `Client#02`, `nora.client@technova.test` / `Client#03`

🚀 Bon déploiement
--------------------

Pour toute question ou pour la soutenance, suivez également le journal `docs/DEPLOYMENT_ALWAYS_DATA.md` qui retrace toutes les actions réalisées (nettoyage des clés, génération des envs, résolution d’incidents, etc.).
