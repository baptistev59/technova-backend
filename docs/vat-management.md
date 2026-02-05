# 📊 Gestion complète des TVA par produit, pays et zones

## 1️⃣ Vue d'ensemble

TechNova gère un système de TVA flexible pour une vente e-commerce internationale. Chaque produit peut avoir des taux différents selon les pays, avec un système de priorités clair.

### Principes fondamentaux

- **Un produit** peut être vendu dans plusieurs pays
- **Chaque pays** peut avoir un taux TVA différent  
- **Les zones** regroupent des pays avec des taux similaires
- **Les zones TVA par produit** définissent la classe applicable par zone

---

## 2️⃣ Architecture des entités

```
┌─────────────────────────────────────────────┐
│             PRODUCT                         │
├─────────────────────────────────────────────┤
│ • id                                        │
│ • name                                      │
│ • taxClass (STANDARD|REDUCED|ZERO)          │
│ • productTaxZones (Collection) ← NOUVEAUTÉ │
└─────────────────────────────────────────────┘
           ↓
    ┌──────────┴──────────┐
    ↓                     ↓
┌─────────────────┐  ┌────────────────────────┐
│   TAX ZONE      │  │ PRODUCT TAX ZONE       │
├─────────────────┤  │  (JOINTURE) ← NEW      │
│ • id            │  ├────────────────────────┤
│ • code          │  │ • id                   │
│ • name          │  │ • product (FK)         │
│ • countryCodes[]│  │ • taxZone (FK)         │
│ • taxClass      │  │ • taxClass             │
│ • rate          │  │ • createdAt            │
│ • isPreset      │  │ • updatedAt            │
│ • shop? (FK)    │  └────────────────────────┘
└─────────────────┘
       ↓
┌──────────────────┐
│   VAT RATE       │
├──────────────────┤
│ • id             │
│ • countryCode    │
│ • code           │
│ • rate           │
│ • isDefault      │
│ • shop? (FK)     │
└──────────────────┘
```

### Entités détaillées

#### **VatRate** (Taux TVA globaux)
```php
// Exemple: France - TVA Standard
$vat = new VatRate('FR', 20.0, 'STANDARD');
$vat->setLabel('France - TVA Standard');
$vat->setIsDefault(true);
$vat->setActive(true);
```

**Utilisés pour** :
- Définir les taux de base par pays
- Fallback quand aucune autre règle ne s'applique
- Configuration globale pour tous les vendeurs

---

#### **TaxZone** (Zones de TVA)
```php
// Exemple: Union Européenne
$zone = new TaxZone();
$zone->setCode('EU_STANDARD');
$zone->setName('Union Européenne — Standard');
$zone->setCountryCodes(['FR', 'DE', 'IT', 'ES', 'BE', 'NL', 'AT', 'LU']);
$zone->setTaxClass('STANDARD');
$zone->setRate(20.0);
$zone->setIsPreset(true); // Prédéfinie
```

**Utilisées pour** :
- Regrouper des pays avec taux similaires
- Simplifier la configuration pour les vendeurs
- Assigner rapidement à plusieurs produits

---

#### **Product** (Produit avec TVA)
```php
$product = new Product();
$product->setName('Smartphone');
$product->setTaxClass('STANDARD'); // Classe par défaut
// Les zones TVA par produit définissent la classe par zone
```

**Propriétés TVA** :
- `taxClass` : Classe TVA (STANDARD, REDUCED, ZERO)
- `productTaxZones` : Collection de zones TVA par produit

---

#### **ProductTaxZone** (NOUVEAU - Zones TVA par produit)
```php
$assignment = new ProductTaxZone();
$assignment->setProduct($smartphone);
$assignment->setTaxZone($euZone);
$assignment->setTaxClass('STANDARD');  // Classe appliquée dans cette zone
```

**Utilisé pour** :
- Associer un produit à plusieurs zones TVA
- Définir une classe TVA par zone pour ce produit
- Résoudre automatiquement la classe selon le pays de livraison

---

## 3️⃣ Règles de priorité (IMPORTANT ⚡)

### Résolution du taux applicable

Quand on cherche le taux TVA pour **Smartphone en Allemagne** :

```
1️⃣ Y a-t-il une ProductTaxZone correspondant au pays (DE) ?
   ├─ OUI  → Utiliser la classe TVA liée à la zone ✓
   └─ NON  → Continuer

2️⃣ Y a-t-il une zone TVA legacy assignée et DE dedans ?
   ├─ OUI  → Utiliser la classe/taux de la zone ✓
   └─ NON  → Continuer

3️⃣ Y a-t-il un VatRate(DE, STANDARD) global ?
   ├─ OUI  → Utiliser ce taux ✓
   └─ NON  → Continuer

4️⃣ Y a-t-il un VatRate par défaut ?
   ├─ OUI  → Utiliser le défaut ✓
   └─ NON  → Utiliser 20% (hard default)
```

### Diagramme visuel

```
┌─────────────────────────────────────┐
│  Chercher TVA pour Produit + Pays   │
└────────────┬────────────────────────┘
             │
             ↓
    ┌────────────────────┐
   │ ProductTaxZone     │ ← PLUS PRIORITAIRE
   │ (product + zone)   │   (classe par zone)
    └─────────┬──────────┘
              │
        Non trouvé ↓
    ┌────────────────────┐
    │ TaxZone.rate       │ ← MOYEN
    │ (zone + pays)      │   (par zone)
    └─────────┬──────────┘
              │
        Non trouvé ↓
    ┌────────────────────┐
    │ VatRate global     │ ← MOINS PRIORITAIRE
    │ (code VatRate)     │   (taux global)
    └─────────┬──────────┘
              │
        Non trouvé ↓
    ┌────────────────────┐
    │ Hard default 20%   │ ← MINIMUM
    └────────────────────┘
```

---

## 4️⃣ Exemples de cas d'usage

### Cas 1 : Zone EU simple

**Configuration** :
- Zone EU (all countries) → 20% STANDARD
- Product: Smartphone assigné à zone EU

**Résultat** :
- France → 20% (zone EU)
- Allemagne → 20% (zone EU)
- Italie → 20% (zone EU)

```php
$rate = $vatService->getRateForProduct($smartphone, 'FR');
// Returns: 20.0%
```

---

### Cas 2 : Zone spécifique par pays

**Configuration** :
- Zone EU (all countries) → 20% STANDARD
- Zone DE_REDUCED (DE uniquement) → 7% REDUCED
- Product: Smartphone assigné aux deux zones

**Résultat** :
- France → 20% (zone EU)
- Allemagne → **7%** (zone DE_REDUCED) ⭐
- Italie → 20% (zone EU)

```php
$rate = $vatService->getRateForProduct($smartphone, 'DE');
// Returns: 7.0% (zone DE_REDUCED)

$rate = $vatService->getRateForProduct($smartphone, 'FR');
// Returns: 20.0% (zone default)
```

---

### Cas 3 : Multi-zones par produit

**Configuration** :
- Zone EU (all countries) → 20% STANDARD
- Product: Software assigné à zone EU
- Zones produit :
   - Zone DE_REDUCED (DE) → 7% REDUCED
   - Zone FR_ZERO (FR) → 0% ZERO
   - Zone IE_STANDARD (IE) → 23% STANDARD

**Résultat** :
- France (Books) → 0% (zone FR_ZERO)
- France (Software) → 20% (zone EU)
- Allemagne → 7% (zone DE_REDUCED)
- Irlande → 23% (zone IE_STANDARD)
- Espagne → 20% (zone EU)

---

### Cas 4 : Pas de zone (fallback global)

**Configuration** :
- Product: Spécialité locale (NO zone)

**Résultat** :
- France → 5.5% (VatRate global)
- Belgique → 6% (VatRate global)
- Italie → 20% (hard default, pas de config)

---

## 5️⃣ Implémentation technique

### Service de résolution (VatResolutionService)

```php
namespace App\Service;

use App\Entity\Product;
use App\Repository\ProductTaxZoneRepository;
use App\Repository\VatRateRepository;

class VatResolutionService
{
    public function getRateForProduct(
        Product $product,
        string $countryCode,
        ?Shop $shop = null
    ): float {
        $countryCode = strtoupper($countryCode);
        
      // 1️⃣ Check ProductTaxZone (zone matched by country)
      $productTaxZone = $this->productTaxZoneRepository
         ->findForProductAndCountry($product, $countryCode);
      if ($productTaxZone && $productTaxZone->getTaxZone()) {
         $taxClass = $productTaxZone->getTaxClass();
         $vatRate = $this->vatRateRepository->findEffectiveRate(
            $countryCode,
            $shop,
            $taxClass
         );
         if ($vatRate) {
            return $vatRate->getRate();
         }
      }

      // 2️⃣ Legacy TaxZone fallback
      $zone = $product->getTaxZone();
      if ($zone && in_array($countryCode, $zone->getCountryCodes(), true)) {
         return $zone->getRate();
      }
        
        // 3️⃣ Check VatRate global
        $vatRate = $this->vatRateRepository->findEffectiveRate(
            $countryCode,
            $shop,
            $product->getTaxClass()
        );
        if ($vatRate) {
            return $vatRate->getRate();
        }
        
        // 4️⃣ Default
        return 20.0;
    }
}
```

---

## 6️⃣ Workflows vendeurs

### 👨‍💼 Workflow 1 : Créer une zone et assigner

1. **Créer zone** → `/mon-espace-vendeur/zones-tva`
   - Nom: "Mon UE à 20%"
   - Pays: FR, DE, IT, ES
   - Classe: STANDARD
   - Taux: 20.0%

2. **Créer produit** → `/mon-espace-vendeur/mes-produits`
   - Assigner zone: "Mon UE à 20%"
   - Tous les pays de la zone → 20%

---

### 👨‍💼 Workflow 2 : Ajouter une zone par pays

1. **Éditer produit** → `/mon-espace-vendeur/mes-produits/{id}`
   - Zones actuelles: UE à 20%
   - **Ajouter une zone TVA** (section "Zones TVA du produit")
   - Choisir zone DE_REDUCED (DE uniquement)
   - Définir: Classe REDUCED
   - Résultat: DE → 7%, autres pays → 20%

---

### 👨‍💼 Workflow 3 : Plusieurs zones

1. **Éditer produit** → Ajouter zones
   - Zone: EU 20%
   - Zone DE_REDUCED → 7%
   - Zone IE_STANDARD → 23%
   - Zone GB_ZERO → 0% (post-Brexit)
   - Autres → 20% (zone EU)

---

## 7️⃣ Workflows administrateurs

### 🔑 Admin Workflow 1 : Créer taux globaux

1. **Admin panel** → Taux TVA globaux
2. Ajouter pour chaque pays :
   - Code pays: FR, DE, ES, ...
   - Classe: STANDARD, REDUCED, ZERO
   - Taux: 20%, 19%, 21%, ...
3. Marquer comme défaut si premier taux

**Résultat** : Fallback globaux pour tous les vendeurs

---

### 🔑 Admin Workflow 2 : Créer zones prédéfinies

1. **Admin panel** → Zones TVA prédéfinies
2. Créer:
   - EU Standard (20%)
   - UK & Ireland (20%/23%)
   - Swiss/Liechtenstein (7.7%)
3. Marquer `isPreset=true`

**Résultat** : Tous les vendeurs voient ces zones

---

## 8️⃣ Configuration par shop (multi-tenant)

### Cas : Zones spécifiques par shop

```php
// Cas 1: Global configuration
$zone = new TaxZone();
$zone->setCode('EU_STANDARD');
$zone->setShop(null); // Global

// Cas 2: Shop-specific override
$shopZone = new TaxZone();
$shopZone->setCode('EU_CUSTOM');
$shopZone->setShop($myShop);
// Different rate for this shop only
```

**Priorité shop** :
1. Shop-specific ProductTaxZone
2. Global ProductTaxZone
3. Shop-specific TaxZone
4. Global TaxZone
5. Shop-specific VatRate
6. Global VatRate
7. Default 20%

---

## 9️⃣ Validation et contraintes

### Règles de validation

| Entité | Contrainte | Raison |
|--------|-----------|--------|
| **ProductTaxZone** | productId + taxZone unique | Pas de doublons |
| **ProductTaxZone** | taxClass ∈ {STANDARD, REDUCED, ZERO} | Classe valide |
| **TaxZone** | countryCodes non-vide | Au moins un pays |
| **TaxZone** | code unique (per shop) | Identification |
| **VatRate** | countryCode + code unique (per shop) | Pas de doublons |

---

## 🔟 Cas limites et pièges

### ⚠️ Piège 1 : Zones qui se chevauchent

```
Zone EU → France 20%
Zone FR_REDUCED → France 5.5%

Résultat: France 5.5% ✓ (Zone spécifique gagne)
```

---

### ⚠️ Piège 2 : Pays pas couvert par une zone

```
Zone EU (countries: [FR, DE])
Pas de zone pour Italie

Résultat: Italie 20% ✓ (VatRate global)
```

---

### ⚠️ Piège 3 : Zone inactive

```
Zone EU inactive
Zone DE_REDUCED active

Résultat: Allemagne 7% ✓ (Zone active)
```

---

### ⚠️ Piège 4 : Produit sans zone

```
Product NO zone
Pas de ProductTaxZone pour France

Résultat: France → VatRate global (20%) ✓
```

---

## 1️⃣1️⃣ API / Services publiques

### VatResolutionService

```php
// Obtenir le taux pour un produit/pays
$rate = $vatService->getRateForProduct($product, 'FR', $shop);
// Returns: 20.0

// Obtenir le taux avec détails
$resolution = $vatService->resolveVatRateForProduct($product, 'FR');
// Returns: {
//   rate: 20.0,
//   source: 'TAX_ZONE',      // PRODUCT_TAX_ZONE|TAX_ZONE|VAT_RATE|DEFAULT
//   priority: 2,
//   zone?: TaxZone
// }

// Vérifier la couverture VAT pour un produit
$coverage = $vatService->getProductVatCoverage($product);
// Returns: {
//   covered_countries: ['FR', 'DE', 'ES', ...],
//   missing_countries: ['US', 'CN', ...],
//   zones_used: [TaxZone, ...],
//   overrides: []
// }
```

### VatCalculator

```php
// Existant - calculer la TVA
$tax = $calculator->calculateTaxFromNet(100, 'FR');
// Returns: 20.0

$gross = $calculator->calculateGrossFromNet(100, 'FR');
// Returns: 120.0

$net = $calculator->calculateNetFromGross(120, 'FR');
// Returns: 100.0
```

---

## 1️⃣2️⃣ Diagrammes flux

### Flux d'achat (Customer checkout)

```
1. Customer ajoute produit au panier
   ↓
2. Backend calcule TVA pour product + country du customer
   ├─ getRateForProduct(product, customer_country)
   └─ VatResolutionService applique priorités
   ↓
3. Backend applique TVA
   ├─ calculateTaxFromNet()
   └─ Affiche NET + TAX = GROSS
   ↓
4. Customer voit le prix TTC
```

---

### Flux configuration vendeur

```
1. Vendeur crée/édite produit
   ↓
2. Vendeur sélectionne Zone TVA
   ├─ Global ou custom
   ├─ Pays regroupés
   └─ Taux standard
   ↓
3. Vendeur associe plusieurs zones (optionnel)
   ├─ Par zone/pays
   ├─ Classe différente
   └─ Taux issu du VatRate
   ↓
4. Vendeur valide et enregistre
   ├─ ProductTaxZone créés
   └─ Système prêt pour checkout
```

---

## 1️⃣3️⃣ Migration depuis version précédente

### Avant (simple)
```
Product → TaxZone → taux fixe pour tous les pays
```

### Après (flexible)
```
Product → ProductTaxZone (multi-zones) + TaxZone (fallback)
```

### Migration data
```sql
-- Aucune perte de données
-- TaxZone existantes conservées
-- product_tax_zone table créée vide
-- Vendors peuvent associer des zones graduellement
```

---

## 1️⃣4️⃣ Performance et optimisation

### Caching recommendations

```php
// Cache les taux calculés pour 1h
$vatService->getRateForProduct($product, 'FR');
// → Cache key: "product_vat_rate:1:FR"
// → TTL: 3600s

// Invalidate on update
$productTaxZone->setTaxClass('REDUCED');
// → clear "product_vat_rate:*"
```

### Query optimization

```php
// N+1 query problem avoided
$product->getProductTaxZones()->toArray(); // ✓ Load once

// Eager load recommended
->with('taxZone')
->with('productTaxZones')
```

---

## 1️⃣5️⃣ Checklist de test

- [ ] ProductTaxZone (classe REDUCED) > TaxZone fallback
- [ ] TaxZone désactivée ignore ProductTaxZone inactive
- [ ] Pas de ProductTaxZone, pas de TaxZone → VatRate global
- [ ] Pas de ProductTaxZone, pas de TaxZone, pas de VatRate → 20%
- [ ] Multiple ProductTaxZone par produit différents pays
- [ ] Update ProductTaxZone recalcule checkout
- [ ] Shop-specific vs global priorités
- [ ] Validation: rate entre 0 et 100%
- [ ] Validation: au moins un pays dans TaxZone
- [ ] Cache invalidation après modification

---

## 📚 Ressources supplémentaires

### Documentation

- **[Guide Complet des TaxZone](tax-zones-guide.md)** - Comprendre les zones de TVA (regroupement de pays)
- **[Guide ProductTaxZone](product-tax-zones-guide.md)** - Zones TVA par produit (associations granulaires)
- **[Guide Vendeur - Gestion TVA](vat-vendor-guide.md)** - Mode d'emploi pour vendeurs

### Code Source

- **Entités** : [src/Entity/VatRate.php](../src/Entity/VatRate.php), [src/Entity/TaxZone.php](../src/Entity/TaxZone.php), [src/Entity/ProductTaxZone.php](../src/Entity/ProductTaxZone.php)
- **Services** : [src/Service/VatResolutionService.php](../src/Service/VatResolutionService.php), [src/Service/VatCalculator.php](../src/Service/VatCalculator.php)
- **Repositories** : [src/Repository/ProductTaxZoneRepository.php](../src/Repository/ProductTaxZoneRepository.php)
- **Forms** : [src/Form/ProductTaxZoneType.php](../src/Form/ProductTaxZoneType.php)

---

**Document généré:** 2026-02-01  
**Dernière mise à jour:** Migration vers ProductTaxZone (multi-zones)
