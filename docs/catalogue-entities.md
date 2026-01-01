# Catalogue Entities Summary

## Catalogue & vendeurs

- **Category** : hiérarchie (parent facultatif), slug unique, champ `iconPath` vers les pictos SVG versionnés.
- **Brand** : slug unique, `logoPath` optionnel, relié aux produits.
- **Shop** : appartient à un `Vendor`, slug unique, expose les produits du vendeur, adresse logistique liée via `Address`.
- **Product** : appartient à une catégorie/une marque/un shop, contient les prix TTC, indicateurs `isFeatured` / `isPublished`, `shortDescription`, `description`.
- **ProductAttribute / ProductAttributeValue** : description des attributs configurables (couleur, édition, chipset, etc.).
- **ProductVariant** : combinaison des valeurs, prix/promo/stock/image propres, SKU spécifique.
- **ProductImage** : ordre/position, `isMain`, méta (alt/title/caption/mime).
- **ProductReview** : note (demi‑étoiles), commentaire, flag `approved` (modération).
- **ProductReviewReport** : signalement d’avis (raison, statut, reporter).
- **Repositories** : `ProductRepository` expose `findLatestPublished()` et `filterBy()` (catégorie, marque, texte, prix, tri).

## Utilisateurs & profils

- **User** : champs `avatarPath`, `newsletterOptIn`, `phone`, `isDeleted`. Les avatars par défaut sont stockés dans `public/images/avatars/` (admin, vendeurs, client).
- **Vendor** : relation 1‑1 avec `User`, coordonnées pro et `Shop`.
- **Address** : utilisée pour les adresses clients, shops et vendeurs (`isShipping`, `isBilling`, `isDefault`).
- **AuditLog** : trace les connexions/erreurs de sécurité (endpoint `/api/test-audit`).
- **SavedCart** : enregistre le panier JSON (`items`) et la date de mise à jour pour chaque client (utilisé lorsqu’un utilisateur quitte la session sans commander).

## Livraison & expédition

- **ShippingZone** : zone de livraison par boutique (libellé + codes pays/zip).
- **ShippingMethod** : méthode associée à une zone (nom, délai, actif, ordre).
- **ShippingRate** : grille tarifaire par poids (min/max, prix) liée à une méthode.

## Commandes & panier

- **CartService** (côté app) manipule la session, synchronise avec `SavedCart` et vérifie les stocks.
- **CustomerOrder** : référence `TN-YYYYMMDD-hhmmss`, `status`, `totalAmount`, `currency`, snapshots des adresses, horodatages `created_at`/`paid_at`, lien vers `User`.
- **CustomerOrderStatusHistory** : historique des transitions de statut (workflow + audit).
- **CustomerOrderItem** : produit, libellé, quantité, prix unitaire, total de ligne, miniature persistée (`productImage`).
- **OrderDocument** : facture/bon de livraison (type, url, hash, date de génération).
- **ReturnRequest** : demandes de retour (raison, statut, dates).
- **OrderMailer** : envoie un email HTML+texte après `CheckoutService::createOrder()`, avec miniatures embarquées (`cid:`).
- **SavedCart + CustomerOrder** permettent la reprise du panier et la création d’un historique (`/mon-compte/commandes`).

## Fixtures & données de démo

- Admin `admin@test.fr` / `123456`.
- 10 vendeurs `vendor0X@technova.test` avec mots de passe `Vendor#0X`.
- 3 clients démo `lena.client@technova.test` (`Client#01`), `maxime.client@technova.test` (`Client#02`), `nora.client@technova.test` (`Client#03`) – chacun possède un profil + adresse.
- 7 catégories, 8 marques, 10 shops, ~50 produits avec variantes + images SVG + deux avis par produit.
- **⚠️ Prod** : ne plus lancer `doctrine:fixtures:load`. Les données de démo Alwaysdata sont réimportées via `scripts/sync-demo-db.sh`.

## Interfaces consommant ces entités

- Twig : `/`, `/catalogue`, `/produit/{slug}`, `/panier`, `/commande`, `/mon-compte/*`.
- API : `/api/products`, `/api/products/{slug}`, `/api/cart`, `/api/login`, `/api/register`, `/api/me`, `/api/docs`.
- Tests Postman/Newman : `postman/technova-api.postman_collection.json` + script `./scripts/postman-tests.sh`.
