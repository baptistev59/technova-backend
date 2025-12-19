# Roadmap Produits / Catalogue

Ce fichier liste les pistes d’évolution concernant la gestion des produits. Chaque item pourra être traité lors d’un futur sprint.

## 1. Produits groupés / bundles

- Autoriser les vendeurs à regrouper plusieurs SKU dans une offre (ex. “Laptop + souris”).
- Gérer le prix global (avec remise possible) et décompter le stock sur chaque élément.
- Laisser la possibilité de masquer/afficher les composants dans la page produit.
- ✅ Implémenté : configuration côté vendeur (recherche + sélection de composants, remise pack) et configurateur client (modale Alpine, multi-config par composant, affichage des stocks/configs, remise affichée dans le panier).
- ✅ Maintenant : `CheckoutService` vérifie le stock réel de chaque composant (simple, variable, composant de pack) et décrémente automatiquement lors d’une commande payée.
- À suivre : exposer une API pour gérer les bundles en masse et prévoir un historique des remises.

## 2. Bibliothèque d’attributs réutilisables

- Partager les `ProductAttribute`/`ProductAttributeValue` par vendeur pour éviter la duplication.
- Interface permettant de rattacher un attribut existant à plusieurs produits.
- Sélection des valeurs valides par produit + génération automatique des variantes.
- 🔄 En cours : chantier prioritaire du sprint actuel (conception du modèle partagé + UI de sélection).

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
- ✅ Mise en place d’une infrastructure `App\Image\ImageProfile` / `ImageProfileRegistry` / `ImageUploader` : tous les uploads (bannières, logos, produits, avatars) passent désormais par un service unique et produisent un `.webp` redimensionné selon les contraintes métier de chaque profil.

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

## 13. Produits liés (cross-sell / up-sell)

- Ajouter deux zones configurables dans la fiche produit vendeur : “Produits suggérés” et “Ventes croisées”.
- UI avec champ de recherche + suggestions (comportement identique à la recherche d’attributs) pour sélectionner rapidement les produits liés.
- Fiche produit cliente : afficher ces produits de manière différenciée (carrousel ou liste dédiée) pour booster le panier moyen.

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

## 15. Programmation des publications & promos

- Permettre de saisir une date/heure de publication différée pour la fiche produit (statut planifié).
- Étendre les champs promo pour renseigner une période (date début/fin) de prix remisé.
- Côté front, ne publier que les produits dont la date est atteinte et afficher les prix promos uniquement sur leur plage de validité.

## 16. Tableau de bord vendeur — commandes

- Ajouter une entrée “Commandes” dans la sidebar du dashboard vendeur (templates `vendor/layout.html.twig` et `vendor/shop/existing.html.twig`).
- Vue synthétique listant les dernières commandes du shop : numéro, date, client, montant, statut (avec badge couleur).
- Filtres rapides (période, statut) et pagination côté serveur pour charger les commandes par lot.
- Actions de base : accéder au détail d’une commande, changer le statut (préparation, expédiée), télécharger la facture/proforma.
- Préparer l’agrégation des stats (CA du jour/semaine, commandes en attente) pour affichage sur la page d’accueil du dashboard.
- Etape suivante : ajouter un mini CRUD côté vendeur pour modifier les statuts (`pending` → `paid`, `paid` → `shipped`) et annuler la commande si nécessaire (`cancelled`).
- À planifier : génération/chargement du bon de livraison et impression de la facture directement depuis la fiche commande vendeur.

## 17. Documents commerciaux PDF

- Créer un service `OrderDocumentGenerator` (Twig → PDF via Dompdf/Laminas) pour produire facture + bon de livraison à partir des commandes, en respectant le layout TechNova.
- Persister chaque document (`order_document` / `media`) avec type, référence, UUID, URL, date de génération et hash pour validation ultérieure.
- Exposer `GET /api/vendor/orders/{id}/documents` pour lister les documents existants et `POST` pour en générer un nouveau (retourne `id`, `type`, `url`, `base64` si nécessaire).
- Ajouter une action “Télécharger PDF” sur le dashboard vendeur et un bouton “Imprimer” côté client.
- Prévoir un système d’expiration/rotation (facultatif) et stocker les fichiers dans `public/uploads/documents` (ou bucket S3 plus tard).

## 18. Messagerie interne & tickets

- Créer les entités `Conversation` / `Message` liées à une commande (`order_id`) et partager un canal sécurisé client ↔ vendeur.
- Exposer les endpoints REST décrits dans `docs/vendor-api-endpoints.md` (GET/POST `/conversations/{orderId}` pour le vendeur et le client) avec JWT et vérification des participants.
- Ajouter un simple UI dans `templates/account/orders/show.html.twig` et `templates/vendor/order/index.html.twig` permettant de lire la conversation d’une commande et d’envoyer un message (Alpine + fetch + `<meta name="technova-jwt">`).
- Prévoir un formulaire “Demande d’info” pour les visiteurs : soumission via `/api/support/requests`, création d’un ticket conversationnel (`status : to_review`) et notifications internes, puis conversion manuelle en `Conversation` dès qu’un compte est créé.

## 19. Expérience publique & recherche

- Les carrousels `tn-carousel` (home, vitrine, dashboard vendeur) sont désormais stylés de façon uniforme : 10 slides produits avec trois visibles, navigation/fallback, et un attribut `data-swiper-visible` pour piloter la vue.
- La recherche catalogue / recherche globale utilise un dropdown custom (pas de datalist ou historique) ; le JS gère l’état, annule les fetchs en cours, ferme sur Enter/submit, et les suggestions proviennent exclusivement des `name` + `keywords` filtrés côté PHP.
