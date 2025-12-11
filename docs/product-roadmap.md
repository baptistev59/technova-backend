# Roadmap Produits / Catalogue

Ce fichier liste les pistes d’évolution concernant la gestion des produits. Chaque item pourra être traité lors d’un futur sprint.

## 1. Produits groupés / bundles
- Autoriser les vendeurs à regrouper plusieurs SKU dans une offre (ex. “Laptop + souris”).
- Gérer le prix global (avec remise possible) et décompter le stock sur chaque élément.
- Laisser la possibilité de masquer/afficher les composants dans la page produit.

## 2. Bibliothèque d’attributs réutilisables
- Partager les `ProductAttribute`/`ProductAttributeValue` par vendeur pour éviter la duplication.
- Interface permettant de rattacher un attribut existant à plusieurs produits.
- Sélection des valeurs valides par produit + génération automatique des variantes.

## 3. SEO / Métadonnées
- Champs `metaTitle`, `metaDescription`, `metaImage` sur le produit.
- Génération d’un sitemap et personnalisation de l’URL canonique.

## 4. Historique des prix & promotions programmées
- Table `ProductPriceHistory`.
- Planning des promos (date début/fin) avec calcul automatique de la meilleure promo.

## 5. Recherche avancée
- Pagination/tri sur `/api/products`.
- Indexation Meilisearch/Elastic pour la recherche full-text et faceting.

## 6. Support des imports (CSV/Excel)
- Upload d’un fichier pour créer/mettre à jour des produits + variantes en masse.
- Gestion des erreurs et du rollback.

## 7. API stock en temps réel
- Endpoint léger `/api/products/{slug}/stock` ou `/api/variants/{id}/stock`.
- Permet de vérifier la disponibilité au moment du panier/checkout.

## 8. Images & optimisation
- Service d’optimisation (WebP/AVIF) + génération multi-résolution via `sharp` ou un worker.
- Possibilité d’associer une image spécifique à chaque variante.

## 9. Authentification front & session
- ✅ Formulaire Twig `/connexion` branché sur `App\Security\LoginFormAuthenticator` (firewall `main`) + remember-me.
- ✅ Session Symfony et `viewer_user()` alignés avec `Security` (JWT conservé pour compatibilité Twig).
- À poursuivre :
  - Persister le JWT côté navigateur (session/local storage) et automatiser `POST /api/token/refresh`.
  - Ajouter un logout API et la rotation/expiration serveur.
  - Prévoir un stockage sécurisé pour la future SPA React.
  - Indus : unifier les rôles/ACL entre Twig et SPA, exposer un endpoint `DELETE /api/logout` et documenter le besoin de rotation automatique.

## 10. Industrialisation CI/CD
- Automatiser les tests unitaires, lints et scénarios API via GitHub Actions (PHPUnit + `php bin/console lint:twig templates` + `./scripts/postman-tests.sh`).
- Interrompre le pipeline en cas d’échec et publier les rapports.
- Prévoir un job de déploiement contrôlé (SSH/rsync) après validation manuelle.

## 11. Sprint qualité (R1-R3)
- Ajouter une user story dédiée après les premières US du sprint 4A (création boutique / dashboard vendeur).
- Couvrir les services critiques : panier, checkout/Stripe, OrderMailer, anonymiseur, sauvegarde panier.
- Écrire des tests fonctionnels/API pour `/api/login`, `/api/register`, `/api/cart`, `/commande`, webhook Stripe (en simulant les events).
- Intégrer ces tests dans le pipeline GitHub Actions (voir section 10) afin de sécuriser les régressions.

## 12. Vitrine publique des boutiques
- Nouvelle route publique `/boutiques/{slug}` qui expose la bannière, le logo, la description et les politiques du vendeur.
- Grille des produits associés (cards existantes réutilisées) avec pagination / CTA “Voir la boutique” depuis les fiches produit.
- Bouton “Voir ma boutique” sur le dashboard vendeur pour prévisualiser la page publique.

## 13. Roadmap API vendeur
- Ajouter une US dédiée dans le sprint technique pour exposer les endpoints `vendor` / `shop` / `product`.
- Prévoir les routes suivantes :
  - `GET /api/vendor/shop`, `POST /api/vendor/shop`, `PUT/PATCH /api/vendor/shop`.
  - `GET /api/vendor/profile`, `PUT/PATCH /api/vendor/profile`.
  - `GET /api/vendor/products`, `POST /api/vendor/products`, `GET /api/vendor/products/{id}`, `PUT/PATCH /api/vendor/products/{id}`, `DELETE /api/vendor/products/{id}`.
  - `POST /api/vendor/media` pour l’upload des logos/bannières/visuels.
  - (futur) `GET/POST /api/vendor/tax-rules` pour configurer le rule engine TVA.
- Prévoir les tests Postman/Newman associés + intégration CI (GitHub Actions).

## 14. Actions catalogue (dupliquer / publier / supprimer)
- Ajouter les routes et méthodes nécessaires dans `VendorProductController` (ou équivalent) pour `dupliquer`, `togglePublish` (publier/dépublier) et `delete`.
- Brancher les icônes du listing `templates/vendor/product/index.html.twig` sur ces routes (POST/DELETE sécurisés avec CSRF, confirmations).
- Renvoyer les réponses en JSON pour préparer un rafraîchissement AJAX (Alpine) et mettre à jour la table sans rechargement complet.
