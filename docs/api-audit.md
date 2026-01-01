# Audit des endpoints API (attendu vs exposé)

## 1) Endpoints techniques & documentation
Présents :
- GET `/api/docs`
- GET `/api/docs.{_format}`
- ANY `/api/docs.json`

Constats :
- Documentation API exposée
- Swagger / Nelmio en place
- Au-dessus du minimum attendu

## 2) Authentification & identité (commun)
Présents :
- POST `/api/login`
- POST `/api/logout`
- POST `/api/token/refresh`
- POST `/api/register`
- GET `/api/me`
- GET `/api/email/verify/{token}`
- POST `/api/email/verify/resend`

Constats :
- Login, refresh token, inscription, “who am I”
- Confirmation email API en place

Mise en place :
- Page web de confirmation : `GET /verification/email/{token}`

## 3) Profil utilisateur (client)
Présents :
- GET `/api/profile`
- POST `/api/profile` (PUT/PATCH également acceptés)
- DELETE `/api/profile`

Constats :
- Consultation, mise à jour, suppression
- POST pour update acceptable en contexte pédagogique

## 4) Catalogue produits (client)
Présents :
- GET `/api/products`
- GET `/api/products/{slug}`
- GET `/api/categories`
- GET `/api/brands`
- GET `/api/shops`
- GET `/api/shops/{slug}`

Constats :
- Liste produits + fiche produit publique
- Endpoints catalogue/shops exposés

## 5) Panier (client)
Présents :
- GET `/api/cart`
- POST `/api/cart`
- DELETE `/api/cart/{id}`
- PUT|PATCH `/api/cart/{id}`

Constats :
- Lecture panier, ajout produit, suppression produit

## 6) Commandes client
Présents :
- GET `/api/orders`
- GET `/api/orders/{id}`
- GET `/api/orders/{id}/invoice`
- POST `/api/returns`

Constats :
- Historique client, détails, lien de facture, retours

## 7) Avis produits
Présents :
- GET `/api/products/{id}/reviews`
- POST `/api/products/{id}/reviews`
- POST `/api/report`

Constats :
- Avis réservés aux acheteurs
- Modération et signalements disponibles

## 8) Checkout & livraison
Présents :
- GET `/api/checkout/shipping-options`
- POST `/api/checkout`

Constats :
- Calcul des options de livraison par boutique
- Lignes de livraison prises en compte dans la commande

## 9) Messagerie client ↔ vendeur (bonus)
Présents :
- GET `/api/account/conversations/{orderId}`
- POST `/api/account/conversations/{orderId}/messages`
- GET `/api/vendor/conversations/{orderId}`
- POST `/api/vendor/conversations/{orderId}/messages`

Constats :
- Séparation client / vendeur
- Flux bidirectionnel par commande
- Fonctionnalité avancée

## 10) Espace vendeur – boutique & profil
Présents :
- GET `/api/vendor/shop`
- POST `/api/vendor/shop`
- PUT `/api/vendor/shop`
- GET `/api/vendor/profile`
- PUT `/api/vendor/profile`

Constats :
- Création + mise à jour boutique
- Profil vendeur
- Conforme

## 11) Produits vendeur (coeur marketplace)
Présents :
- GET `/api/vendor/products`
- POST `/api/vendor/products`
- GET `/api/vendor/products/{id}`
- PUT `/api/vendor/products/{id}`
- DELETE `/api/vendor/products/{id}`

Constats :
- CRUD complet
- Séparation vendeur / public

## 12) Médias vendeur
Présent :
- POST `/api/vendor/media`

Constats :
- Upload media
- Bonus apprécié

## 13) Commandes vendeur
Présents :
- GET `/api/vendor/orders`
- GET `/api/vendor/orders/{id}`
- PATCH `/api/vendor/orders/{id}/status`
- POST `/api/vendor/orders/{id}/documents`
- GET `/api/vendor/orders/{id}/documents`

Constats :
- Liste + détail commande
- Changement statut
- Gestion documents
- Très bon niveau métier

## 14) Endpoints de test / audit (dev)
Présents :
- GET `/api/test`
- GET `/api/test-audit`

Constats :
- Acceptables en contexte pédagogique
- A restreindre en prod

## 15) Tableau synthèse “attendu vs exposé”

| Domaine | Attendu marketplace | Exposé |
|---|---|---|
| Auth / Token | ✅ | ✅ |
| Compte utilisateur | ✅ | ✅ |
| Catalogue produits | ✅ | ✅ |
| Panier | ✅ | ✅ |
| Avis produits | ⭐ Bonus | ✅ |
| Commandes client | ✅ | ✅ |
| Livraison / checkout | ✅ | ✅ |
| Profil vendeur | ✅ | ✅ |
| Produits vendeur | ✅ | ✅ |
| Commandes vendeur | ✅ | ✅ |
| Messagerie | ⭐ Bonus | ✅ |
| Médias | ⭐ Bonus | ✅ |
| Docs API | ⭐ Bonus | ✅ |

## 16) Conclusion

Les endpoints essentiels d’un marketplace client/vendeur sont bien exposés.
Le périmètre couvre l’authentification, le catalogue, le panier, les profils,
les produits, les commandes et la livraison.
Des fonctionnalités avancées (messagerie, documents, audit, documentation API,
avis clients) complètent l’ensemble et dépassent le socle minimal attendu.

## 17) Ajouts possibles (optionnels)
- Endpoint stock temps réel (ex. `/api/variants/{id}/stock`).
- Webhooks de livraison / expédition pour intégrations externes.
- Export CSV des commandes côté vendeur/admin.
