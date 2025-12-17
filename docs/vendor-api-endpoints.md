# API Vendeur / Shop / Produits

Ce fichier liste les endpoints REST qu’il reste à implémenter pour couvrir l’expérience vendeur complète (profil, boutique, produits, commandes, médias). Chaque endpoint doit :

* Valider un JWT doté du rôle `ROLE_VENDOR`.
* Retourner `403` si le vendeur ne possède pas d’espace `Shop` ou si le JWT ne concerne pas le propriétaire.
* Utiliser `ImageUploader` + `ImageProfileRegistry` pour tous les uploads (logo/bannière/visuels).

## Auth common

* En-tête `Authorization: Bearer <jwt>`.
* Vérifier `ViewerAccessChecker` / `App\Security\ViewerAccessChecker::requireViewer(...)`.
* Réponse : `401` si JWT manquant, `403` si le scope n’est pas `ROLE_VENDOR`.

### Préparer la base de tests

Avant d’exécuter `php bin/phpunit`, lance `bash scripts/setup-test-db.sh`. Le script ouvre `APP_ENV=test` avec `DATABASE_URL=…/technova`, Doctrine crée `technova_test` (suffixe `_test` appliqué automatiquement), migre le schéma puis charge les fixtures nécessaires aux tests fonctionnels (uploads, shops, commandes). Cela évite les erreurs `password authentication failed` ou `database does not exist`.

## 1. Boutique (Shop)

### GET `/api/vendor/shop`

* Retourne la boutique associée au vendeur connecté.
* Response 200 : `{ id, name, slug, description, policies, contactEmail, logoPath, bannerPath }`.
* 404 si aucune boutique.

### POST `/api/vendor/shop`

* Création d’une nouvelle boutique (`ROLE_VENDOR` non nécessaire si on autorise la création).
* Payload (multipart/form-data) :
  * `name`, `description`, `contactEmail`, `policies` (texte), `bannerFile` (fichier), `logoFile`.
* Traitement : `ImageUploader::upload(..., ImageProfileRegistry::get('shop_banner'))` etc.
* Response 201 + location `/api/vendor/shop`.

### PUT/PATCH `/api/vendor/shop`

* Mise à jour complète/partielle de la boutique.
* Validation identique, support du multipart pour les fichiers.
* Supprimer les fichiers précédents via `deleteUploadFile`.

## 2. Profil vendeur

### GET `/api/vendor/profile`

* Retourne `Vendor` (firstname, lastname, phone, company, pays).

### PUT/PATCH `/api/vendor/profile`

* Permet de modifier les champs `Vendor` (nom, téléphone, site, langue).
* La boutique doit aussi refléter `contactEmail / policies`.

## 3. Produits

### GET `/api/vendor/products`

* Filtres : `perPage`, `page`, `status` (`published`, `draft`), `search`.
* Response : `items[]` (id, name, slug, price, status, mainImage), `total`, `page`.

### POST `/api/vendor/products`

* Création d’un produit complet.
* Payload : JSON partiaire + `mainImage`, `gallery[]` via multipart + `attributes`.
* Les images utilisent les profils `product_image`.
* Générer variantes via `ProductVariant`/`attributes`.

### GET `/api/vendor/products/{id}`

* Détail du produit (images, variantes, attributs, stocks).
* Return `404` si produit absent du shop.

### PUT/PATCH `/api/vendor/products/{id}`

* Mise à jour, même payload que POST.
* Autoriser la mise à jour des images (remplacement + ajout).

### DELETE `/api/vendor/products/{id}`

* Suppression (cascade images, variantes).
* Retourne `204` et supprime les fichiers `uploads/products/...`.

## 4. Media & uploads

### POST `/api/vendor/media`

* Upload générique (logo/bannière/visuel). Corps multipart `file`, `profile` (nom de profil `shop_banner`, `product_image`, `avatar`).
* Utiliser `ImageUploader` et retourner `{ path, width, height }`.

## 5. Commandes vendeur

### GET `/api/vendor/orders`

* Filtres : `status`, `from`, `to`, `page`, `perPage`.
* Response : `{ items: [{ reference, status, total, createdAt }], total, page }`.

### GET `/api/vendor/orders/{id}`

* Détail des lignes, statuts, liens vers les produits.

### PATCH `/api/vendor/orders/{id}/status`

* Payload : `{ status: "pending|paid|shipped|cancelled" }`.
* Vérifie la transition (ex. `paid`→`shipped`).

### POST `/api/vendor/orders/{id}/documents`

* Génère bon de livraison ou facture (optionnel). Accept types `invoice|delivery`.

## 6. Documentation & tests

* Mettre à jour Swagger (`/api/docs`) avec ces endpoints, ajouter `@OA\Response` et `@Security`.
* Ajouter au README + `docs/product-roadmap.md`.
* Mettre à jour la collection Postman (ajouter `vendor-shop.postman_collection.json`) avec les flux auth + CRUD.
