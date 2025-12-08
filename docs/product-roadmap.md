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
- Déléguer les formulaires Twig (`/connexion`, `/inscription`) à `POST /api/login` / `/api/register` afin d’unifier le workflow.
- Persister le JWT côté navigateur (session/local storage) et automatiser `POST /api/token/refresh`.
- Créer un authenticator Symfony pour mettre à disposition les rôles (`Security`) au lieu d’un simple `viewer_user()`.
- Ajouter une vraie gestion de session (logout côté API, expiration du jeton, rotation).
- Prévoir un stockage sécurisé du token côté React (future SPA) pour réutiliser la même base.

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
