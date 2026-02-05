# 🔑 Guide complet - Gestion TVA pour administrateurs

Ce guide explique comment gérer les taux de TVA globaux et les zones pré-définies pour TechNova.

---

## Table des matières

1. [Vue d'ensemble](#overview)
2. [Architecture TVA](#architecture)
3. [Gérer les taux globaux (VatRate)](#vatr-rates)
4. [Gérer les zones TVA](#tax-zones)
5. [Gérer les zones TVA par produit](#product-overrides)
6. [Configuration multi-shop](#multishop)
7. [Rapports et audits](#reports)
8. [Performance et optimisation](#performance)
9. [Troubleshooting](#troubleshooting)

---

## 1. Vue d'ensemble {#overview}

### 🏛️ Modèle TVA TechNova

TechNova utilise un système TVA à 3 niveaux :

```
┌──────────────────────────────────────────────────────┐
│ LEVEL 1: PRODUCT TAX ZONES                          │
│ ProductTaxZone(product, taxZone, taxClass)          │
│ ↓ Highest Priority                                  │
├──────────────────────────────────────────────────────┤
│ LEVEL 2: TAX ZONES                                  │
│ TaxZone(countries[], rate)                          │
│ ↓ Medium Priority                                   │
├──────────────────────────────────────────────────────┤
│ LEVEL 3: GLOBAL VAT RATES                           │
│ VatRate(country, code, rate)                        │
│ ↓ Low Priority                                      │
├──────────────────────────────────────────────────────┤
│ FALLBACK: Hard Default (20%)                        │
└──────────────────────────────────────────────────────┘
```

### 👥 Rôles et permissions

| Rôle | VatRate | TaxZone | ProductTaxZone | Voir Rapports |
|------|---------|---------|---|---|
| Admin Global | ✅ CRUD | ✅ CRUD | ✅ Voir | ✅ |
| Admin Shop | ❌ Voir | ✅ CRUD | ✅ CRUD | ✅ |
| Vendeur | ❌ | ❌ | ✅ CRUD | ❌ |

---

## 2. Architecture TVA {#architecture}

### Entités et relations

```
┌─────────────┐
│  VatRate    │  Taux globaux par pays/code
├─────────────┤
│ country_code│  (2 chars: FR, DE, US, etc.)
│ code        │  (STANDARD, REDUCED, ZERO)
│ rate        │  (20.00, 5.50, 0.00)
│ isDefault   │  Marqueur pour fallback
│ shop_id?    │  Optionnel: shop-specific
└─────────────┘

┌─────────────┐
│  TaxZone    │  Regroupement zones
├─────────────┤
│ code        │  (EU_STANDARD, UK_IE, etc.)
│ name        │  (Union Européenne, UK & Irlande)
│ countries[] │  JSON array [FR, DE, IT, ...]
│ rate        │  (20.00 for all countries)
│ isPreset    │  true = non-modifiable par vendors
│ shop_id?    │  Optionnel: shop-specific
└─────────────┘

┌──────────────────┐
│ ProductTaxZone   │  Zones TVA par produit
├──────────────────┤
│ product_id       │  FK Product
│ tax_zone_id      │  FK TaxZone
│ taxClass         │  (STANDARD, REDUCED, ZERO)
│ created_at       │  Timestamp
│ updated_at       │  Timestamp
└──────────────────┘
```

---

## 3. Gérer les taux globaux (VatRate) {#vat-rates}

### 📍 Accès

**Admin Panel** → **Taux TVA globaux** (ou EasyAdmin)

### 📋 Liste des taux

```
VatRate list
├─ Voir tous les taux par pays/code
├─ Filter par pays, code, shop
└─ Pagination (20 taux par page)
```

### ➕ Créer un taux global

**Exemple : France TVA Standard (20%)**

```
Pays (countryCode)*      : FR
Classe TVA (code)*       : STANDARD
Label (optionnel)        : France - TVA Standard
Type (optionnel)         : 
Taux (rate)*             : 20.00
Par défaut (isDefault)   : ✅ (oui)
Actif (active)           : ✅ (oui)
Shop (optionnel)         : (laisser vide = global)
```

Clique **"Créer"**

### 📋 Tous les taux standards à créer

| Pays | Code | Taux | Classe |
|------|------|------|--------|
| FR | STANDARD | 20.00 | Standard |
| FR | REDUCED | 5.50 | Réduit |
| FR | ZERO | 0.00 | Zéro |
| DE | STANDARD | 19.00 | Standard |
| DE | REDUCED | 7.00 | Réduit |
| DE | ZERO | 0.00 | Zéro |
| IT | STANDARD | 22.00 | Standard |
| IT | REDUCED | 5.00 | Réduit |
| ES | STANDARD | 21.00 | Standard |
| ES | REDUCED | 10.00 | Réduit |
| ES | SUPER_REDUCED | 4.00 | Super-réduit |
| GB | STANDARD | 20.00 | Standard (post-Brexit) |
| US | STANDARD | 0.00 | Pas de TVA fédérale |

**💡 Tip :** Exporte ces données depuis les registres fiscaux officiels

### ✏️ Modifier un taux

1. Clique sur le taux dans la liste
2. Modifie les champs
3. Clique **"Mettre à jour"**

**Attention :** Les changements s'appliquent à tous les produits utilisant ce taux!

### 🗑️ Supprimer un taux

1. Clique sur le menu du taux
2. Clique **"Supprimer"**

**Conséquences :**
- Produits sans zone → Utilisent le fallback (20%)
- Vendeurs voient une alerte

### 🔒 Taux protégés

Certains taux ne peuvent pas être supprimés :
- FR STANDARD (20%)
- Tous les taux "isDefault=true"

**Raison :** Éviter les configuration invalides

---

## 4. Gérer les zones TVA {#tax-zones}

### 📍 Accès

**Admin Panel** → **Zones TVA**

### 📋 Zones prédéfinies (preset)

Lors de l'installation, ces zones sont créées :

| Code | Nom | Pays | Taux | Preset |
|------|-----|------|------|--------|
| EU_STANDARD | Union Européenne | FR,DE,IT,ES,BE,NL,AT,LU,GR,PT,PL,CZ,HU,RO,BG,HR,IE,DK,FI,SE,LV,LT,EE,SL,SK | 20.0 | ✅ |
| UK_IRELAND | UK & Irlande | GB,IE | 20.0/23.0 | ✅ |
| SWISS_LIECHTENSTEIN | Suisse & Liechtenstein | CH,LI | 7.7 | ✅ |

**⚠️ Zones preset :** Non modifiables, affichées à tous les vendeurs

### ➕ Créer une zone personnalisée

```
Code*                : MY_ZONE_2026
Nom*                 : Amérique du Nord
Description          : Zone USA + Canada (aucune TVA)
Pays applicables*    : [US, CA]
Classe TVA*          : STANDARD
Taux (%)*            : 0.00
Est preset           : ❌ (non, c'est custom)
Ordre de tri         : 100
Actif                : ✅
Shop (optionnel)     : (vide = global)
```

Clique **"Créer"**

### ✏️ Modifier une zone

1. Clique sur la zone
2. Modifie les champs
3. Clique **"Mettre à jour"**

**Attention :** Si tu changes les pays, les vendeurs vont voir de nouvelles options!

### 🗑️ Supprimer une zone

1. Clique le menu
2. Clique **"Supprimer"**

**Impact :**
- ✅ Les produits conservent les autres zones
- ✅ Les produits sans zone utilisent le fallback VatRate
- ⚠️ Les associations à cette zone sont supprimées

### 📊 Rapport zones

Vérifier que tes zones sont complètes:

```
SELECT z.name, COUNT(z.id) as product_count
FROM tax_zone z
LEFT JOIN product p ON p.tax_zone_id = z.id
GROUP BY z.id
ORDER BY product_count DESC
```

**Résultat attendu :**
- UE Standard : 1500+ produits
- UK & Irlande : 200+ produits
- Custom zones : variable

---

## 5. Gérer les zones TVA par produit {#product-overrides}

### 📍 Accès

**Admin Panel** → **Produits** (via édition produit)

### 🔍 Voir les zones par produit

```
ProductTaxZone list
├─ Voir toutes les zones associées à un produit
├─ Filtrer par produit et zone
└─ Colonne "Classe" = classe TVA appliquée
```

### ➕ Associer une zone (normalement vendeur)

```
Produit*             : Smartphone Pro
Zone TVA*            : DE_REDUCED
Classe TVA*          : REDUCED
```

Clique **"Créer"**

### 🔍 Auditer les zones produit

**Query pour auditer :**

```sql
-- Trouver les zones produit avec taux élevés
SELECT 
    ptz.id, 
    p.name as product_name, 
    tz.code,
    ptz.tax_class,
    tz.rate as zone_rate
FROM product_tax_zone ptz
JOIN product p ON p.id = ptz.product_id
JOIN tax_zone tz ON tz.id = ptz.tax_zone_id
WHERE tz.rate > 30  -- Taux suspect (> 30%)
ORDER BY tz.rate DESC
```

**Interprétation :**
- ✅ Taux entre 0 et 25% = Normal
- 🟡 Taux > 25% = À vérifier
- 🔴 Taux > 50% = Erreur probable

---

## 6. Configuration multi-shop {#multishop}

### 🏪 Architecture multi-tenant

TechNova supporte les taux shop-spécifiques :

```
Global rates (shop_id=NULL)
  ├─ Utilisé par défaut
  ├─ Tous les produits
  └─ FR Standard = 20%

Shop-specific rates (shop_id=123)
  ├─ Utilisé seulement pour Shop #123
  ├─ Override les taux globaux
  └─ FR Standard = 20.5% (custom)
```

### ⚙️ Configuration

**Pour chaque shop, tu peux :**

1. ✅ Définir des taux spécifiques
2. ✅ Créer des zones personnalisées
3. ✅ Laisser les zones produit (ProductTaxZone)

**Priorité :**
1. ProductTaxZone shop-specific
2. ProductTaxZone global
3. TaxZone shop-specific
4. TaxZone global
5. VatRate shop-specific
6. VatRate global
7. Default (20%)

### 🔧 Setup exemple pour Shop Luxe

```
Shop Luxe (id=2)
├─ VatRate FR STANDARD → 20.6% (custom)
├─ TaxZone EU_LUXURY → 20% pour articles premium
└─ ProductTaxZone pour articles spécifiques
```

**Command pour checker :**

```bash
bin/console debug:container --parameter=shops
```

---

## 7. Rapports et audits {#reports}

### 📊 Dashboard TVA

Créer un dashboard avec métriques :

```
┌────────────────────────────────┐
│ TVA Dashboard - 2026-02-01     │
├────────────────────────────────┤
│ ✓ Taux globaux actifs    : 45  │
│ ✓ Zones prédéfinies      : 8   │
│ ✓ Zones customs          : 12  │
│ ✓ Zones produit          : 234 │
│                                │
│ Couverture par pays :          │
│ ├─ Complète (10+ taux)  : 150 pays │
│ ├─ Partielle (3+ taux)  : 30 pays  │
│ └─ Minimale (1 taux)    : 15 pays  │
│                                │
│ Erreurs possibles :            │
│ ├─ Zones vides          : 0    │
│ ├─ Taux > 50%           : 2    │
│ └─ Produits sans zone   : 45   │
└────────────────────────────────┘
```

### 🔍 Audit complet

```sql
-- Audit TVA complet
SELECT 
    'VatRate' as entity_type,
    COUNT(*) as count,
    COUNT(DISTINCT country_code) as countries,
    MIN(rate) as min_rate,
    MAX(rate) as max_rate,
    SUM(CASE WHEN active=0 THEN 1 ELSE 0 END) as inactive
FROM vat_rate
WHERE shop_id IS NULL

UNION ALL

SELECT 
    'TaxZone',
    COUNT(*),
    COUNT(*),
    NULL, NULL, NULL
FROM tax_zone
WHERE shop_id IS NULL

UNION ALL

SELECT 
    'ProductTaxZone',
    COUNT(*),
    COUNT(DISTINCT tax_zone_id),
    NULL,
    NULL,
    NULL
FROM product_tax_zone
```

### 📈 Rapport de conformité

Vérifier que tous les produits sont configurés correctement :

```sql
-- Trouver les produits sans configuration TVA
SELECT 
    p.id, p.name,
    CASE 
           WHEN p.tax_zone_id IS NULL AND 
               NOT EXISTS (SELECT 1 FROM product_tax_zone WHERE product_id=p.id)
           THEN 'WARN: No TAX_ZONE and No ProductTaxZone'
        ELSE 'OK'
    END as status
FROM product p
WHERE p.shop_id IS NOT NULL
ORDER BY status DESC
```

---

## 8. Performance et optimisation {#performance}

### 💾 Indexes

```sql
-- Vérifier les indexes
EXPLAIN ANALYZE 
SELECT * FROM vat_rate WHERE country_code='FR' AND code='STANDARD';

EXPLAIN ANALYZE 
SELECT * FROM product_tax_zone WHERE product_id=123;
```

**Indexes actuels :**
- ✅ vat_rate.country_code
- ✅ product_tax_zone.product_id
- ✅ product_tax_zone.tax_zone_id
- ✅ tax_zone.shop_id

### 🚀 Caching

Recommandations pour caching :

```php
// Cache des taux par clé
Cache key: "vat_rate:{$shop_id}:{$country_code}:{$code}"
TTL: 86400 (1 day)
Invalidate on: VatRate update

Cache key: "tax_zone:{$shop_id}:{$zone_id}"
TTL: 86400
Invalidate on: TaxZone update

Cache key: "product_vat:{$product_id}:{$country_code}"
TTL: 3600 (1 hour)
Invalidate on: ProductTaxZone update
```

### 📊 N+1 Query Prevention

```php
// BON ✓
$products = $repo->findBy([], null, 100)
    ->with('taxZone')
    ->with('productTaxZones')
    ->getQuery()
    ->getResult();

// MAUVAIS ✗
foreach ($products as $p) {
    $zone = $p->getTaxZone();  // N+1!
    $zones = $p->getProductTaxZones();  // N+1!
}
```

---

## 9. Troubleshooting {#troubleshooting}

### ❌ Erreur: "Taux invalide (< 0 ou > 100%)"

**Cause :** Validation échouée

**Solution :**
1. Vérifier la valeur entrée
2. Assurer format décimal (20.00, non 20,00)
3. Checker la contrainte en DB

```sql
ALTER TABLE vat_rate ADD CONSTRAINT check_rate_range CHECK (rate >= 0 AND rate <= 100);
```

---

### ❌ Erreur: "Zone vide (pas de pays)"

**Cause :** Validation : countryCodes[] doit être non-vide

**Solution :**
1. Ajouter au moins un pays
2. Validator JSON:

```php
$zone->setCountryCodes(['FR', 'DE']);  // OK
$zone->setCountryCodes([]);  // Erreur!
```

---

### ❌ "Taux trop bas/élevé dans un pays"

**Diagnostic :**

```sql
-- Chercher les anomalies
SELECT country_code, MIN(rate) as min_rate, MAX(rate) as max_rate
FROM vat_rate
GROUP BY country_code
HAVING MAX(rate) - MIN(rate) > 15  -- Écart > 15% suspect
ORDER BY max_rate DESC
```

**Action :** Vérifier les sources OCDE/UE officielles

---

### ❌ "ProductTaxZone - Doublons détectés"

**Cause :** Constraint violation (product + zone dupliquée)

**Solution :**
1. Chercher les doublons:

```sql
SELECT product_id, tax_zone_id, COUNT(*) as cnt
FROM product_tax_zone
GROUP BY product_id, tax_zone_id
HAVING cnt > 1
```

2. Supprimer les doublons:

```sql
DELETE FROM product_tax_zone 
WHERE id NOT IN (
    SELECT MIN(id) FROM product_tax_zone 
    GROUP BY product_id, tax_zone_id
)
```

---

## 🎯 Checklist de vérification

- [ ] Tous les taux standards créés (FR, DE, IT, ES, ...)
- [ ] Zones prédéfinies active
- [ ] Aucun taux > 50% sauf exception justifiée
- [ ] Aucun zone vide
- [ ] Rapport audit TVA généré et validé
- [ ] Cache invalidation testé
- [ ] Performances indexées
- [ ] Documentation à jour

---

## 📞 Support & Escalade

| Problème | Action |
|----------|--------|
| Taux incorrect pour pays | Vérifier VatRate global |
| Zone manquante | Créer zone personnalisée |
| Zone produit incohérente | Auditer ProductTaxZone |
| Performance dégradée | Checker N+1 queries |
| Doublon détecté | Chercher contraintes DB |

---

**Dernière mise à jour :** 2026-02-01  
**Version :** 1.0  
**Auteur :** TechNova Admin Team
