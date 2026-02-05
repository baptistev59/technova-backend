# 📍 Guide Complet des Zones de TVA (TaxZone)

**Version:** 1.0  
**Date:** 2 février 2026  
**Audience:** Administrateurs, Vendeurs, Développeurs

---

## 📌 Table des Matières

1. [Concept fondamental](#concept-fondamental)
2. [Structure d'une TaxZone](#structure-dune-taxzone)
3. [Différences TaxZone vs ProductTaxZone](#différences-taxzone-vs-producttaxzone)
4. [Flux de résolution TVA](#flux-de-résolution-tva)
5. [Cas d'usage pratiques](#cas-dusage-pratiques)
6. [Gestion des zones](#gestion-des-zones)
7. [Hiérarchie complète](#hiérarchie-complète-de-résolution)
8. [Bonnes pratiques](#bonnes-pratiques)
9. [Pièges courants](#pièges-courants)
10. [Implémentation technique](#implémentation-technique)

---

## Concept Fondamental

### Qu'est-ce qu'une TaxZone ?

Une **TaxZone** est un **regroupement de pays** associé à :
- Une **classe TVA unique** (STANDARD, REDUCED, ZERO)
- Un **taux unique**
- Un **code identificateur**

**C'est un gabarit réutilisable** qu'on assigne à des produits pour simplifier la configuration TVA.

### Analogie

```
TaxZone = Modèle de configuration TVA

Exemple: "Tous mes livres en UE payent 5.5%"
→ Au lieu de configurer chaque produit individuellement
→ Je crée une TaxZone "EU_BOOKS_REDUCED"
→ Je l'assigne à tous mes livres
```

---

## Structure d'une TaxZone

### Champs de l'entité

```php
class TaxZone {
    // === IDENTIFICATION ===
    int $id;                          // Identifiant unique
    string $code;                     // Code technique unique
                                      // Exemples: "EU_STANDARD", "UK_REDUCED"
    string $name;                     // Libellé lisible
                                      // Exemple: "Union Européenne — Standard"
    string $description;              // Description optionnelle
    
    // === GÉOGRAPHIE ===
    array $countryCodes;              // Liste de codes pays ISO 3166-1 alpha-2
                                      // Exemple: ["FR", "DE", "IT", "ES", "BE"]
    
    // === TAUX TVA ===
    string $taxClass;                 // Classe TVA: STANDARD|REDUCED|ZERO
    float $rate;                       // Taux en pourcentage
                                      // Exemples: 20.0, 5.5, 0.0
    
    // === CONFIGURATION ===
    bool $isPreset;                    // true = Prédéfinie (non modifiable)
                                      // false = Créée par vendeur (modifiable)
    bool $active;                      // true = Disponible, false = Archivée
    int $sortOrder;                    // Ordre d'affichage dans les listes
    
    // === OWNERSHIP ===
    Shop $shop;                        // null = Global (tous les shops)
                                      // Shop instance = Spécifique à un shop
    
    // === AUDIT ===
    DateTimeImmutable $createdAt;      // Date de création
    DateTime $updatedAt;               // Dernière modification
}
```

### Types de données en base

```sql
CREATE TABLE tax_zone (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    shop_id BIGINT NULLABLE,
    code VARCHAR(64) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULLABLE,
    country_codes JSON,                  -- ["FR", "DE", "IT", ...]
    tax_class VARCHAR(32) DEFAULT 'STANDARD',
    rate DECIMAL(5, 2),                  -- 20.00, 5.5, 0.00
    is_preset BOOLEAN DEFAULT false,
    sort_order INT DEFAULT 999,
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NULLABLE,
    
    UNIQUE KEY `uniq_tax_zone_code_shop` (code, shop_id),
    KEY `idx_tax_zone_shop` (shop_id),
    KEY `idx_tax_zone_is_preset` (is_preset),
    
    FOREIGN KEY (shop_id) REFERENCES shop(id) ON DELETE SET NULL
);
```

---

## Exemples Concrets de TaxZone

### Zone 1: Union Européenne Standard

```
Code:          EU_STANDARD
Nom:           Union Européenne — Standard
Pays:          ["FR", "DE", "IT", "ES", "BE", "NL", "AT", "LU", "GR", "PT", "SI", "SK", "CY", "MT"]
Classe TVA:    STANDARD
Taux:          20.0%
Prédéfinie:    true (Créée par admin)
Active:        true
Shop:          NULL (Global)
```

**Utilisation** :
> Tous les produits standard dans l'UE → 20%

---

### Zone 2: Livres Réduits UE

```
Code:          EU_BOOKS_REDUCED
Nom:           Livres UE — Taux réduit
Description:   Directive UE pour les publications
Pays:          ["FR", "DE", "IT", "ES", "BE", "NL", "AT", "LU"]
Classe TVA:    REDUCED
Taux:          5.5%
Prédéfinie:    true
Active:        true
Shop:          NULL (Global)
```

**Utilisation** :
> Tous les livres en UE → 5.5% au lieu de 20%

---

### Zone 3: Royaume-Uni Post-Brexit

```
Code:          GB_REDUCED
Nom:           Royaume-Uni & Irlande — Réduit
Pays:          ["GB", "IE"]
Classe TVA:    REDUCED
Taux:          17.5%
Prédéfinie:    true
Active:        true
Shop:          NULL (Global)
```

---

### Zone 4: Configuration Shop-Spécifique

```
Code:          MYSHOP_SPECIAL
Nom:           Ma Boutique — Édition spéciale
Pays:          ["FR", "BE", "CH"]
Classe TVA:    STANDARD
Taux:          19.0%
Prédéfinie:    false (Créée par vendeur)
Active:        true
Shop:          "My Custom Shop" (instance Shop)
```

**Utilisation** :
> Uniquement pour les produits de "My Custom Shop"

---

## Différences: TaxZone vs ProductTaxZone

### Tableau Comparatif

| Aspect | TaxZone | ProductTaxZone |
|--------|---------|-----------------|
| **Objet métier** | Regroupement de pays | Association produit ↔ zone |
| **Assignation** | 1 par produit (legacy) | Plusieurs par produit |
| **Entité** | `TaxZone` | `ProductTaxZone` (jointure) |
| **Champ produit** | `product.taxZone` | `product.productTaxZones[]` |
| **Classe TVA** | Fixe (dans la zone) | Peut différer par zone |
| **Priorité résolution** | 2 | 1 (plus haute) |
| **Cas d'usage** | Configuration simple, fallback | Règles granulaires, exceptions |
| **Flexibilité** | Basse (une classe pour tous) | Haute (classe par zone) |
| **Modifiabilité** | Peut être prédéfinie (gelée) | Toujours modifiable |

### Exemple de Différence

#### **Scénario: Laptop en Allemagne**

**Avec TaxZone seul** :
```
Produit: Laptop
TaxZone assignée: EU_STANDARD
  → Pays: ["FR", "DE", "IT", ...]
  → Classe: STANDARD
  → Taux: 20%

Acheteur en Allemagne:
  → Laptop utilise EU_STANDARD
  → Classe appliquée: STANDARD
  → TVA: 20%
```

**Avec ProductTaxZone** :
```
Produit: Laptop
ProductTaxZone 1: Laptop + EU_STANDARD → Classe STANDARD (20%)
ProductTaxZone 2: Laptop + DE_REDUCED → Classe REDUCED (7%)
  → ProductTaxZone 2 a priorité pour l'Allemagne

Acheteur en Allemagne:
  → ProductTaxZone 2 trouvée (DE_REDUCED)
  → Classe appliquée: REDUCED
  → TVA: 7% (au lieu de 20%)
```

---

## Flux de Résolution TVA

### Étape 1: Acheteur commande

```
Acheteur achète: Laptop
Pays de livraison: Allemagne (DE)
↓
L'application doit trouver la classe TVA applicable
```

### Étape 2: Résolution par priorité

```
PRIORITÉ 1️⃣: ProductTaxZone
├─ Chercher: ProductTaxZone(Laptop, zone_contenant_DE)
├─ Si trouvée → Utiliser sa classe TVA
└─ Si non trouvée → Continuer

PRIORITÉ 2️⃣: TaxZone (Legacy)
├─ Chercher: TaxZone assignée au produit
├─ Vérifie: DE est-il dans country_codes?
├─ Si oui → Utiliser sa classe et taux
└─ Si non → Continuer

PRIORITÉ 3️⃣: VatRate (Taux global)
├─ Chercher: VatRate(DE, classe_résolue)
├─ Si trouvé → Utiliser ce taux
└─ Si non → Continuer

PRIORITÉ 4️⃣: Hard Default
└─ Utiliser 20% (taux par défaut immuable)
```

### Exemple Concret

```
Données en base:
- Produit: "Laptop"
- ProductTaxZone: Laptop + EU_STANDARD → STANDARD
- ProductTaxZone: Laptop + DE_SPECIAL → REDUCED
- TaxZone: EU_STANDARD (FR, DE, IT) → STANDARD 20%
- VatRate: (DE, REDUCED) → 7%

Acheteur en Allemagne achète Laptop:

1️⃣ ProductTaxZone(Laptop + DE_SPECIAL) ?
   → TROUVÉ ✓
   → Classe: REDUCED

2️⃣ Chercher VatRate(DE, REDUCED)
   → TROUVÉ ✓
   → Taux: 7%

RÉSULTAT: 7% appliqué (au lieu de 20%)
```

---

## Cas d'Usage Pratiques

### Cas 1: Configuration Simple Multi-Produits

**Besoin** : Tous les livres en UE à taux réduit

**Solution** :
```
1. Créer TaxZone:
   Code: EU_BOOKS
   Pays: [FR, DE, IT, ES, BE, NL, AT, LU]
   Classe: REDUCED
   Taux: 5.5%

2. Assigner à chaque livre:
   book1.setTaxZone($euBooks)
   book2.setTaxZone($euBooks)
   book3.setTaxZone($euBooks)

3. Résultat:
   Tous les livres → 5.5% en UE
   Tous autres produits → 20% (fallback)
```

---

### Cas 2: Exceptions par Pays

**Besoin** : Livres à 5.5% partout en UE, SAUF Allemagne à 7%

**Solution** :
```
1. Créer TaxZone EU_BOOKS_REDUCED (5.5%)
   Assigner au produit "Book"

2. Créer ProductTaxZone:
   Book + DE_SPECIAL → Classe REDUCED

3. Créer VatRate:
   (DE, REDUCED) → 7%

Résultat:
- France: 5.5% (TaxZone EU)
- Allemagne: 7% (ProductTaxZone de-spécifique)
```

---

### Cas 3: Zones Multiples par Produit

**Besoin** : Produit avec taux différents selon la région

**Solution** :
```
Produit: "Média USB"

ProductTaxZone 1:
  Média USB + EU_STANDARD → STANDARD (20%)

ProductTaxZone 2:
  Média USB + GB_REDUCED → REDUCED (17.5%)

ProductTaxZone 3:
  Média USB + SWITZERLAND → STANDARD (7.7%)

Résultat:
- Acheteur France: 20%
- Acheteur UK: 17.5%
- Acheteur Suisse: 7.7%
```

---

### Cas 4: Configuration Shop-Spécifique

**Besoin** : Ma boutique a des zones spécifiques, autres boutiques utilisent globales

**Solution** :
```
TaxZone GLOBAL (shop = null):
  EU_STANDARD (20%)

TaxZone SHOP-SPECIFIC (shop = "My Bookshop"):
  MYSHOP_EU_BOOKS (5.5%, uniquement pour cette shop)

Résultat:
- My Bookshop: Livres à 5.5%
- Other Bookshop: Livres à 20%
```

---

## Gestion des Zones

### Qui Crée les TaxZone ?

| Type | Créateur | Portée | Modifiable |
|------|----------|--------|-----------|
| **Prédéfinie** | Admin système | Globale | ❌ Non (isPreset=true) |
| **Custom** | Vendeur | Shop-spécifique | ✅ Oui |
| **Shop-specific** | Vendeur | Shop seul | ✅ Oui |

### Workflow Admin: Créer Zone Prédéfinie

```
1. Aller à: Admin Panel → Configuration TVA → Zones Prédéfinies
2. Cliquer: "+ Nouvelle zone"
3. Remplir:
   - Code: EU_STANDARD
   - Nom: Union Européenne — Standard
   - Pays: [Sélectionner FR, DE, IT, ES, ...]
   - Classe TVA: STANDARD
   - Taux: 20.0%
   - Marquer: "Prédéfinie" ✓
4. Sauvegarder

Résultat: Zone disponible globalement, non modifiable
```

### Workflow Vendeur: Assigner Zone à Produit

```
1. Aller à: Mon Espace Vendeur → Mes Produits
2. Éditer produit: "Laptop"
3. Section "Configuration TVA":
   - Sélectionner Zone TVA: "EU_STANDARD"
   - (Optionnel) Ajouter ProductTaxZone pour exceptions
4. Sauvegarder

Résultat: Laptop utilise EU_STANDARD (20%) comme fallback
```

---

## Hiérarchie Complète de Résolution

### Diagramme Visuel

```
┌─────────────────────────────────────────────────┐
│ RÉSOLUTION TVA POUR: Produit + Pays             │
│ Exemple: Laptop en Allemagne (DE)               │
└────────────────────┬────────────────────────────┘
                     ↓
        ┌────────────────────────┐
        │ ProductTaxZone         │ ← PRIORITÉ 1 (PLUS HAUTE)
        │ (Laptop + zone_DE)     │
        │ Si trouvée:            │
        │ Classe TVA → VatRate   │
        └────────────┬───────────┘
                     │
              NON trouvée ↓
        
        ┌────────────────────────┐
        │ TaxZone (Legacy)       │ ← PRIORITÉ 2
        │ product.taxZone        │
        │ Si DE in countries:    │
        │ Taux direct            │
        └────────────┬───────────┘
                     │
          Pas applicable ↓
        
        ┌────────────────────────┐
        │ VatRate (Global)       │ ← PRIORITÉ 3
        │ (DE, STANDARD)         │
        │ Si trouvé:             │
        │ Taux appliqué          │
        └────────────┬───────────┘
                     │
            Non trouvé ↓
        
        ┌────────────────────────┐
        │ Hard Default           │ ← PRIORITÉ 4 (PLUS BASSE)
        │ 20%                    │
        └────────────────────────┘
```

### Table de Priorité

| Priorité | Source | Condition | Résultat |
|----------|--------|-----------|----------|
| 1 | ProductTaxZone | Produit + zone trouvée | Classe TVA → VatRate |
| 2 | TaxZone | Produit.taxZone assignée ET pays dans zone | Taux direct |
| 3 | VatRate | VatRate(pays, classe) trouvé | Taux appliqué |
| 4 | Hard Default | Aucune règle applicable | 20% |

---

## Bonnes Pratiques

### ✅ À FAIRE

**1. Créer des zones prédéfinies pour les cas communs**
```
✓ EU_STANDARD (tous les pays UE)
✓ EU_BOOKS_REDUCED
✓ UK_STANDARD (post-Brexit)
```

**2. Réutiliser les zones sur plusieurs produits**
```
✓ Au lieu de configurer chaque produit individuellement
✓ Une seule TaxZone = maintenance centralisée
```

**3. Utiliser ProductTaxZone pour les exceptions**
```
✓ TaxZone: Base pour 80% des produits
✓ ProductTaxZone: Exceptions pour 20% des cas
```

**4. Garder les zones prédéfinies simples**
```
✓ EU_STANDARD → Tous les pays UE, classe STANDARD
✓ EU_BOOKS → Tous les pays UE, classe REDUCED (livres)
✓ Pas de chevauchements inutiles
```

**5. Documenter les zones shop-spécifiques**
```
✓ Ajouter description si logic métier spéciale
✓ Ajouter commentaires pour explications
```

---

### ❌ À ÉVITER

**1. Zones trop spécifiques**
```
✗ Zone "France-Laptop-20%"
→ Utiliser ProductTaxZone au lieu
```

**2. Zones chevauchantes confuses**
```
✗ EU_STANDARD (FR, DE, IT)
✗ EU_REDUCED (FR, DE, ES)
→ Clarifier la priorité ou renommer
```

**3. Modifier les zones prédéfinies**
```
✗ "EU_STANDARD" est gelée, ne pas modifier
→ Créer une nouvelle zone custom si besoin
```

**4. Ignorer l'ordre de résolution**
```
✗ Assigner à la fois TaxZone ET ProductTaxZone
   sans comprendre la priorité
→ ProductTaxZone gagne toujours
```

**5. Laisser des zones inactives sans raison**
```
✗ Zone en base mais inactive, jamais utilisée
→ Archiver correctement ou documenter
```

---

## Pièges Courants

### 🚨 Piège 1: Zones Chevauchantes

```
Zone EU_STANDARD: [FR, DE, IT]
Zone EU_REDUCED: [FR, DE, ES, BE]

France (FR) est dans les deux zones!
Quelle classe s'applique?

RÉSOLUTION:
ProductTaxZone gagne (priorité 1)
Si pas de ProductTaxZone → TaxZone du produit
Si deux TaxZone → Erreur/ambiguïté (à éviter)
```

**Solution** :
```
Séparer les zones clairement:
- Zone EU_STANDARD: Tous les produits (20%)
- Zone EU_BOOKS: Spécifique aux livres (5.5%)
→ Assigner chaque produit à UNE SEULE zone
```

---

### 🚨 Piège 2: Produit sans Zone Assignée

```
Produit: Headphones
TaxZone assignée: NULL (aucune!)
Pays: Allemagne (DE)

Résultat:
1️⃣ ProductTaxZone? Non
2️⃣ TaxZone? Non (NULL)
3️⃣ VatRate(DE, STANDARD)? Espérons que oui!
4️⃣ Sinon → 20% hard default

⚠️ Risque: Incertitude sur le taux appliqué
```

**Solution** :
```
Toujours assigner UNE TaxZone par défaut:
product.setTaxZone($defaultZone)
Ou créer ProductTaxZone explicites
```

---

### 🚨 Piège 3: Zone Inactive

```
Zone: EU_STANDARD (active = false)
Produit assigné à cette zone

Résultat:
La zone est ignorée (VatResolutionService vérifie isActive())
Fallback aux VatRate globales
```

**Solution** :
```
Ne pas désactiver une zone en utilisation
Ou migrer les produits d'abord:
1. Créer nouvelle zone
2. Assigner produits
3. Ensuite archiver l'ancienne
```

---

### 🚨 Piège 4: Taux Incohérent en Zone

```
Zone: EU_STANDARD
Pays: [FR, DE, IT, ES]
Taux: 20%

Mais en réalité:
- France TVA standard: 20% ✓
- Allemagne TVA standard: 19% ✗ (INCOHÉRENT!)
- Italie TVA standard: 22% ✗ (INCOHÉRENT!)

Résultat:
Zone applique 20% partout → ERREUR pour DE et IT!
```

**Solution** :
```
Créer zones homogènes:
- EU_COMMON_20: [FR, BE, NL, ...]
- EU_COMMON_19: [DE, AT, ...]
- EU_COMMON_22: [IT, ...]

Ou utiliser VatRate + ProductTaxZone
pour exceptions par pays
```

---

### 🚨 Piège 5: Shop-Specific Oublié

```
Créer zone shop-specific:
shop_id = "My Bookshop"

Mais assigner à produit:
product.shop_id = "Other Shop"

Résultat:
Zone non trouvée pour ce produit!
(Recherche filtre par shop_id du produit)
```

**Solution** :
```
Vérifier cohérence:
- Zone shop-specific → assigner à produits même shop
- Zone global (shop=null) → assigner à produits tous shops
```

---

## Implémentation Technique

### Entité PHP

```php
// src/Entity/TaxZone.php
#[ORM\Entity(repositoryClass: TaxZoneRepository::class)]
#[ORM\Table(name: 'tax_zone')]
class TaxZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Shop $shop = null;

    #[ORM\Column(length: 64)]
    private string $code = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(type: 'json')]
    private array $countryCodes = [];

    #[ORM\Column(length: 32)]
    private string $taxClass = 'STANDARD';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $rate = '0.00';

    #[ORM\Column]
    private bool $isPreset = false;

    #[ORM\Column]
    private bool $active = true;

    // ... getters/setters
}
```

### Assignation à un Produit

```php
// src/Entity/Product.php
#[ORM\ManyToOne(targetEntity: TaxZone::class)]
#[ORM\JoinColumn(nullable: true)]
private ?TaxZone $taxZone = null;

// Utilisation
$product = new Product();
$product->setName('Laptop');
$product->setTaxZone($euStandardZone);

$em->persist($product);
$em->flush();
```

### Résolution dans le Service

```php
// src/Service/VatResolutionService.php
public function resolveVatRateForProduct(
    Product $product, 
    string $countryCode, 
    ?Shop $shop = null
): array {
    $countryCode = strtoupper($countryCode);

    // 1️⃣ ProductTaxZone
    $productTaxZone = $this->productTaxZoneRepository
        ->findForProductAndCountry($product, $countryCode);
    
    if (null !== $productTaxZone && 
        null !== $productTaxZone->getTaxZone() &&
        $productTaxZone->getTaxZone()->isActive()) {
        // Retourner taux via ProductTaxZone
        return ['rate' => ..., 'source' => 'PRODUCT_TAX_ZONE'];
    }

    // 2️⃣ TaxZone (Legacy)
    $legacyZone = $product->getTaxZone();
    if (null !== $legacyZone && 
        $legacyZone->isActive() && 
        in_array($countryCode, $legacyZone->getCountryCodes())) {
        // Retourner taux direct de la zone
        return ['rate' => $legacyZone->getRate(), 'source' => 'TAX_ZONE'];
    }

    // 3️⃣ VatRate global
    // 4️⃣ Hard default
    // ...
}
```

### Requête SQL: Produits par Zone

```sql
-- Tous les produits utilisant la zone EU_STANDARD
SELECT p.* 
FROM product p
WHERE p.tax_zone_id = (
    SELECT id FROM tax_zone 
    WHERE code = 'EU_STANDARD'
);

-- Zones assignées à des produits
SELECT DISTINCT pz.*
FROM tax_zone pz
INNER JOIN product p ON p.tax_zone_id = pz.id
WHERE pz.active = true
ORDER BY pz.sort_order;

-- Produits sans zone assignée (potentiellement problématique)
SELECT p.*
FROM product p
WHERE p.tax_zone_id IS NULL
LIMIT 10;
```

---

## Résumé Pratique

| Question | Réponse |
|----------|---------|
| **Qu'est-ce qu'une TaxZone?** | Regroupement de pays avec même classe TVA et taux |
| **Combien par produit?** | 1 seule (legacy), complétée par ProductTaxZone |
| **Qui la crée?** | Admin (prédéfinie) ou Vendeur (custom) |
| **Quand l'utiliser?** | Quand 80%+ des produits partagent même config |
| **Priorité?** | 2ème (après ProductTaxZone, avant VatRate) |
| **Peut être modifiée?** | Non si prédéfinie (isPreset=true) |
| **Shop-specific?** | Oui, via shop_id (null = global) |
| **Cas limite?** | Zones chevauchantes, produits sans zone |

---

## Liens Connexes

- [Guide VAT Complet](vat-management.md)
- [Guide Produit TVA par Zone](vat-management.md#5️⃣-zones-tvà-par-produit)
- [Résolution TVA Détaillée](vat-management.md#3️⃣-règles-de-priorité-important-)
- [VatResolutionService API](vat-management.md#1️⃣1️⃣-api--services-publiques)

---

**Last Updated:** 2026-02-02  
**Status:** ✅ Ready for Production
