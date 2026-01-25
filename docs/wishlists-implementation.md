# Wishlists — Implémentation Frontend + API

## ✅ Déploiement complet (January 25, 2026)

### 1️⃣ **Backend API** ✅

3 endpoints REST JSON pour gérer les favoris :

**a) GET `/api/wishlists`** — Lister mes favoris

```bash
curl -H "Authorization: Bearer JWT" http://localhost:8000/api/wishlists

``` Réponse :

```json
{
  "count": 2,
  "items": [
    {
      "id": 1,
      "createdAt": "2026-01-25T14:30:00+00:00",
      "product": { "id": 42, "name": "Quantum Laptop", "slug": "quantum-laptop", "price": 1999.99 }
    }
  ]
}
```

**b) POST `/api/wishlists`** — Ajouter aux favoris

```bash
curl -X POST -H "Authorization: Bearer JWT" \
  -H "Content-Type: application/json" \
  -d '{"productId": 42}' \
  http://localhost:8000/api/wishlists
```

Réponse (201 Created) :

```json
{ "status": "added", "wishlistId": 1 }
```

**c) DELETE `/api/wishlists/{id}`** — Retirer des favoris

```bash
curl -X DELETE -H "Authorization: Bearer JWT" http://localhost:8000/api/wishlists/1
```

Réponse (204 No Content)

---

### 2️⃣ **Frontend Twig Pages** ✅

#### **Page `/mon-compte/favoris`**

- **Contrôleur** : `App\Controller\Web\WishlistController`
- **Template** : `templates/account/favorites.html.twig`
- **Fonctionnalités** :
  - ✅ Lister tous les favoris avec grille responsive (3 colonnes sur large écran)
  - ✅ Afficher image, nom, prix, boutique
  - ✅ Bouton "Ajouter au panier" (POST form)
  - ✅ Bouton "Retirer des favoris" avec confirmation CSRF
  - ✅ Date d'ajout en favori
  - ✅ Empty state avec CTA vers catalogue

#### **Menu utilisateur (Header)**

- ✅ Ajout lien "❤️ Mes favoris" dans `base.html.twig`
- Visible uniquement si connecté
- Placé avant "Mes commandes"

#### **Fiche produit** (À implémenter)

- Button "❤️ Ajouter aux favoris" (icône remplie/vide selon état)
- Appel AJAX optionnel pour feedback instant

---

### 3️⃣ **Routes Twig implémentées** ✅

| Route | Méthode | Description |
|-------|---------|-------------|
| `/mon-compte/favoris` | GET | Lister les favoris |
| `/mon-compte/favoris/{id}` | POST | Supprimer un favori |

Intégrées dans `routes.yaml` via `#[Route]` PHP attributes.

---

### 4️⃣ **Base de données** ✅

**Table `wishlist`**

```sql
CREATE TABLE wishlist (
  id SERIAL PRIMARY KEY,
  user_id INT NOT NULL REFERENCES "user"(id) ON DELETE CASCADE,
  product_id INT NOT NULL REFERENCES product(id) ON DELETE CASCADE,
  created_at TIMESTAMP NOT NULL,
  UNIQUE(user_id, product_id)
);
```

**Migrations** :

- `Version20260125131333.php` — Création table wishlist

---

### 5️⃣ **Sécurité** ✅

- **Authentification** : Routes nécessitent `#[IsGranted('ROLE_USER')]` (JWT)
- **Propriété** : Vérification que l'utilisateur possède le favori avant suppression
- **CSRF** : Token obligatoire pour les DELETE (Symfony form)
- **Unique constraint** : `(user_id, product_id)` — Empêche les doublons

---

## 🎯 Plan suivant (optionnel)

### Phase 2 — UX Améliorations

- [ ] Bouton "Ajouter aux favoris" sur `/produit/{slug}` avec toggle AJAX
- [ ] Compteur de favoris dans le header (ex. "❤️ 3")
- [ ] Toast notification "Ajouté aux favoris" après POST
- [ ] Animation du cœur au survol
- [ ] Endpoint optionnel `GET /api/wishlists/check/{productId}` (vérifier si en favori)

### Phase 3 — Fonctionnalités avancées

- [ ] Partage de favoris (lien public/privé)
- [ ] Notification si un favori est en promo
- [ ] Export favoris (PDF/CSV)
- [ ] Historique des prix (suivi dans le temps)

---

## 📊 Résumé implémentation

| Composant | Statut | Fichiers |
|-----------|--------|----------|
| **Entity Wishlist** | ✅ | `src/Entity/Wishlist.php` |
| **Repository** | ✅ | `src/Repository/WishlistRepository.php` |
| **API Controller** | ✅ | `src/Controller/Api/WishlistController.php` |
| **API Routes (3x)** | ✅ | GET/POST/DELETE `/api/wishlists` |
| **Web Controller** | ✅ | `src/Controller/Web/WishlistController.php` |
| **Twig Page** | ✅ | `templates/account/favorites.html.twig` |
| **Header Link** | ✅ | `templates/base.html.twig` (modifié) |
| **Migration** | ✅ | `migrations/Version20260125131333.php` |
| **README** | ✅ | Routes ajoutées au tableau endpoints |
| **Tests** | ⏳ | À faire (see below) |

---

## 🧪 Tests rapides

**1. Vérifier les routes chargées** :

```bash
php bin/console debug:router | grep -i wishlist
# Résultat attendu :
# app_wishlist_list      GET  /mon-compte/favoris
# app_wishlist_delete    POST /mon-compte/favoris/{id}
# api_wishlists_list     GET  /api/wishlists
# api_wishlists_add      POST /api/wishlists
# api_wishlists_delete   DELETE /api/wishlists/{id}
```

**2. Test API favoris (Postman/Curl)** :

```bash
# Login d'abord
JWT=$(curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"lena.client@technova.test","password":"Client#01"}' \
  | jq -r '.token')

# Ajouter un favori
curl -X POST http://localhost:8000/api/wishlists \
  -H "Authorization: Bearer $JWT" \
  -H "Content-Type: application/json" \
  -d '{"productId":1}'

# Lister les favoris
curl http://localhost:8000/api/wishlists \
  -H "Authorization: Bearer $JWT"

# Retirer un favori
curl -X DELETE http://localhost:8000/api/wishlists/1 \
  -H "Authorization: Bearer $JWT"
```

**3. Test page Twig** :

```bash
# Accédez à http://localhost:8000/mon-compte/favoris
# (connecté comme lena.client@technova.test / Client#01)
# Vous devriez voir la grille de favoris
```

---

## 📝 Notes développeur

- **Authentification** : La page Twig et l'API utilisent le même JWT (LexikJWTAuthentication)
- **Responsive** : Grille 1/2/3 colonnes adaptée à mobile/tablet/desktop (Tailwind)
- **Empty state** : UX optimisée (emoji 💔 + CTA vers catalogue)
- **Forms** : Boutons POST/DELETE utilisent formulaires HTML5 + CSRF token
- **Performance** : Chargement des relations `product` et `shop` via `findBy()`

---

## 🚀 Prochaines étapes

1. **Ajouter bouton sur fiche produit** → `/produit/{slug}`
2. **Intégrer Alpine.js pour toggle instant** (optionnel)
3. **Ajouter tests PHPUnit** (APITestCase + WishlistControllerTest)
4. **Tester rate limiting** : vérifier que `/api/wishlists/add` respecte les limites
5. **Déployer sur Alwaysdata** → Tester migration + API en prod
