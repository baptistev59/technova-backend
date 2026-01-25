# Security Backlog & Enhancements — Implementation Summary

## ✅ Points implémentés (January 25, 2026)

### 1️⃣ **Rate Limiting sur `password-reset/request`** ✅

**Objectif** : Prévenir les attaques par force brute et le spam d'emails

**Configuration** :
```yaml
# config/services.yaml
password_reset.rate_limiter:
  class: Symfony\Component\RateLimiter\RateLimiterFactory
  arguments:
    - key: 'password_reset_%s'
      policy: 'sliding_window'
      limit: 3
      interval: '15 minutes'
```

**Code** :
- Ajouté dans `PasswordResetController::request()`
- Max 3 requêtes par IP par 15 minutes
- Réponse HTTP 429 si dépassement

**Test** :
```bash
POST /api/password-reset/request HTTP/1.1
Content-Type: application/json

{ "email": "user@test.fr", "recaptchaToken": "..." }

# Réponse OK (1-3ème requête):
200 { "status": "requested", "message": "..." }

# Réponse KO (4ème requête):
429 { "error": "Trop de tentatives. Veuillez réessayer dans 15 minutes." }
```

---

### 2️⃣ **Rate Limiting sur `register`** ✅

**Objectif** : Prévenir la création massive de comptes bots

**Configuration** :
```yaml
# config/services.yaml
registration.rate_limiter:
  class: Symfony\Component\RateLimiter\RateLimiterFactory
  arguments:
    - key: 'registration_%s'
      policy: 'sliding_window'
      limit: 5
      interval: '24 hours'
```

**Code** :
- Ajouté dans `RegistrationController::register()`
- Max 5 inscriptions par IP par jour
- Réponse HTTP 429 si dépassement

**Test** :
```bash
POST /api/register HTTP/1.1
Content-Type: application/json

{
  "email": "new@test.fr",
  "password": "P@ss123",
  "firstname": "John",
  "lastname": "Doe",
  "recaptchaToken": "..."
}

# Réponse KO après 5 comptes:
429 { "error": "Trop de tentatives d'inscription. Veuillez réessayer demain." }
```

---

### 3️⃣ **reCAPTCHA v3 Optionnel** ✅

**Objectif** : Bloquer les bots automatiques sur les formulaires sensibles

**Service créé** : `App\Service\RecaptchaValidator`

**Fonctionnalités** :
- Valide tokens reCAPTCHA v3 avec Google
- Score > 0.5 = utilisateur humain probable
- Intégré sur `password-reset/request` et `register`

**Configuration requise** (.env) :
```
RECAPTCHA_SECRET_KEY=<votre_secret_key_google>
```

**Code** :
```php
// Dans PasswordResetController et RegistrationController
$recaptchaToken = $data['recaptchaToken'] ?? null;

if ($recaptchaToken && !$this->recaptchaValidator->isValid($recaptchaToken)) {
    return $this->json(
        ['error' => 'Validation reCAPTCHA échouée. Vous êtes peut-être un bot.'],
        Response::HTTP_FORBIDDEN
    );
}
```

**Test** :
```bash
POST /api/password-reset/request HTTP/1.1

{
  "email": "user@test.fr",
  "recaptchaToken": "03AFcWeA5aBcD12345..."
}

# Réponse si bot détecté:
403 { "error": "Validation reCAPTCHA échouée. Vous êtes peut-être un bot." }

# Réponse si humain détecté:
200 { "status": "requested", "message": "..." }
```

**Frontend** (React/Vue) :
```javascript
import { useGoogleReCaptcha } from '@react-google-recaptcha-v3';

export function PasswordResetForm() {
    const { executeRecaptcha } = useGoogleReCaptcha();

    const handleSubmit = async (email) => {
        const token = await executeRecaptcha('password_reset');
        
        const response = await fetch('/api/password-reset/request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, recaptchaToken: token })
        });

        return response.json();
    };

    return (
        <form onSubmit={(e) => {
            e.preventDefault();
            handleSubmit(email);
        }}>
            <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
            <button type="submit">Réinitialiser</button>
        </form>
    );
}
```

---

### 4️⃣ **Wishlists API** ✅

**Objectif** : Permettre aux utilisateurs de sauvegarder leurs produits favoris

**Entity créée** : `App\Entity\Wishlist`

**Champs** :
```php
- id: int (PK)
- user: User (FK, cascade delete)
- product: Product (FK, cascade delete)
- createdAt: DateTimeImmutable
- Unique constraint: (user_id, product_id)
```

**3 Endpoints créés** :

#### **a) GET `/api/wishlists` — Lister mes favoris**
```bash
GET http://localhost:8000/api/wishlists
Authorization: Bearer <jwt>
```

**Réponse** :
```json
{
  "count": 3,
  "items": [
    {
      "id": 1,
      "createdAt": "2026-01-25T13:15:00+00:00",
      "product": {
        "id": 42,
        "name": "Quantum Laptop",
        "slug": "quantum-laptop",
        "price": 1999.99
      }
    },
    ...
  ]
}
```

#### **b) POST `/api/wishlists` — Ajouter aux favoris**
```bash
POST http://localhost:8000/api/wishlists
Authorization: Bearer <jwt>
Content-Type: application/json

{ "productId": 42 }
```

**Réponse** (201 Created) :
```json
{
  "status": "added",
  "wishlistId": 1
}
```

**Réponse** (409 Conflict - déjà dans les favoris) :
```json
{ "error": "Produit déjà dans les favoris." }
```

#### **c) DELETE `/api/wishlists/{id}` — Retirer des favoris**
```bash
DELETE http://localhost:8000/api/wishlists/1
Authorization: Bearer <jwt>
```

**Réponse** (204 No Content) : Vide

**Migration** : `Version20260125131333.php` créée automatiquement

---

## 📊 Résumé des changements

| Item | Fichiers modifiés/créés | Statut |
|------|-------------------------|--------|
| Rate Limiting | `PasswordResetController`, `config/services.yaml` | ✅ |
| Rate Limiting Register | `RegistrationController`, `config/services.yaml` | ✅ |
| reCAPTCHA v3 | `RecaptchaValidator`, 2 contrôleurs mis à jour | ✅ |
| Wishlists API | `WishlistController`, `Wishlist`, `WishlistRepository`, Migration | ✅ |

---

## 🔐 Sécurité implémentée

✅ **Rate Limiting** : Protection contre force brute et spam
✅ **reCAPTCHA** : Protection contre bots (invisible, v3)
✅ **Unique Constraint** : Un seul favoris par produit/utilisateur
✅ **Ownership Check** : Les utilisateurs ne voient que leurs propres favoris
✅ **Cascade Delete** : Suppression auto des favoris si produit supprimé

---

## 🚀 Prochaines étapes (Optional)

- [ ] Test d'intégration des rate limiters
- [ ] Frontend reCAPTCHA implementation
- [ ] Wishlist counter sur le profil utilisateur
- [ ] Export favoris en PDF/CSV
- [ ] Notifications si produit en promo (favoris)
- [ ] Partage de favoris (lien public/privé)

---

## 📝 Notes

**Environment Variables requis** :
```
# .env.local (optionnel pour dev)
RECAPTCHA_SECRET_KEY=<clé_google>
```

**Rate Limiter stockage** : Redis recommandé en prod (par défaut: cache local)

**Doctrine Migration** :
```bash
php bin/console doctrine:migrations:migrate
```

**Routes testables via Postman** : Collection à jour avec les 3 nouveaux endpoints wishlists
