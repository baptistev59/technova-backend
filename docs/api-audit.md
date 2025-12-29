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

Constats :
- Login, refresh token, inscription, “who am I”
- Conforme aux standards marketplace

Optionnel (non bloquant) :
- Confirmation email

Mise en place :
- Confirmation email avec lien `GET /verification/email/{token}`

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

Constats :
- Liste produits + fiche produit publique

Optionnel (non requis pédagogiquement) :
- Recherche avancée

## 5) Panier (client)
Présents :
- GET `/api/cart`
- POST `/api/cart`
- DELETE `/api/cart/{id}`
- PUT|PATCH `/api/cart/{id}`

Constats :
- Lecture panier, ajout produit, suppression produit

Optionnel :

## 6) Messagerie client ↔ vendeur (bonus)
Présents :
- GET `/api/account/conversations/{orderId}`
- POST `/api/account/conversations/{orderId}/messages`
- GET `/api/vendor/conversations/{orderId}`
- POST `/api/vendor/conversations/{orderId}/messages`

Constats :
- Séparation client / vendeur
- Flux bidirectionnel par commande
- Fonctionnalité avancée

## 7) Espace vendeur – boutique & profil
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

## 8) Produits vendeur (coeur marketplace)
Présents :
- GET `/api/vendor/products`
- POST `/api/vendor/products`
- GET `/api/vendor/products/{id}`
- PUT `/api/vendor/products/{id}`
- DELETE `/api/vendor/products/{id}`

Constats :
- CRUD complet
- Séparation vendeur / public

## 9) Médias vendeur
Présent :
- POST `/api/vendor/media`

Constats :
- Upload media
- Bonus apprécié

## 10) Commandes vendeur
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

## 11) Endpoints de test / audit (dev)
Présents :
- GET `/api/test`
- GET `/api/test-audit`

Constats :
- Acceptables en contexte pédagogique
- A restreindre en prod

## 12) Tableau synthèse “attendu vs exposé”

| Domaine | Attendu marketplace | Exposé |
|---|---|---|
| Auth / Token | ✅ | ✅ |
| Compte utilisateur | ✅ | ✅ |
| Catalogue produits | ✅ | ✅ |
| Panier | ✅ | ✅ |
| Profil vendeur | ✅ | ✅ |
| Produits vendeur | ✅ | ✅ |
| Commandes vendeur | ✅ | ✅ |
| Messagerie | ⭐ Bonus | ✅ |
| Médias | ⭐ Bonus | ✅ |
| Docs API | ⭐ Bonus | ✅ |

## 13) Conclusion

Les endpoints essentiels d’un marketplace client/vendeur sont bien exposés.
Le périmètre couvre l’authentification, le catalogue, le panier, les profils,
les produits et les commandes.
Des fonctionnalités avancées (messagerie, documents, audit, documentation API)
complètent l’ensemble et dépassent le socle minimal attendu.

## 14) Ajouts possibles (optionnels)
- PUT `/api/cart/{id}` (mise à jour quantité)
- GET `/api/orders` côté client (si checkout prévu)
- Pagination / filtres explicites
