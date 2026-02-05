# 🎯 Guide Complet des ProductTaxZone (Zones TVA par Produit)

**Version:** 2.0 ✨ (TaxZone supprimée, refactorisation JSON)  
**Date:** 5 février 2026  
**Audience:** Vendeurs, Administrateurs, Développeurs

---

## 📌 Table des Matières

1. [Concept fondamental](#concept-fondamental)
2. [Architecture simplifiée](#architecture-simplifiée)
3. [Structure d'une ProductTaxZone](#structure-dune-producttaxzone)
4. [Priorité de résolution](#priorité-de-résolution)
5. [Cas d'usage pratiques](#cas-dusage-pratiques)
6. [Workflow vendeur](#workflow-vendeur)
7. [Gestion multiple pays](#gestion-multiple-pays)
8. [Bonnes pratiques](#bonnes-pratiques)
9. [Pièges courants](#pièges-courants)
10. [Implémentation technique](#implémentation-technique)

---

## Concept Fondamental

### Qu'est-ce qu'une ProductTaxZone ?

Une **ProductTaxZone** définit les **pays** où un **produit spécifique** applique une **classe TVA spéciale**.

C'est une **entité autonome** (sans dépendance à une TaxZone externe) qui permet une **configuration flexible par produit**.

### Analogie

```
Avant (TaxZone) → Gabarit partagé par tous les produits
Maintenant → Configuration directe par produit
             Spécifier simplement : "Ce produit s'applique à [FR, DE, IT]"

Exemple:
ProductTaxZone pour un Livre:
  - Pays: ['FR', 'DE', 'IT', 'ES']
  - Classe TVA: REDUCED
  - Taux appliqué: Résolu via VatRate('REDUCED', 'FR') = 5.5%
```

---

## Architecture Simplifiée

### ❌ Avant (Complexe - 3 niveaux)

```
Product → ProductTaxZone → TaxZone → VatRate → Taux final
                             ↑
                    (couche indirecte supprimée)
```

### ✅ Après (Simple - 2 niveaux)

```
Product → ProductTaxZone → VatRate → Taux final
          [countries[],
           taxClass]
```

**Avantages de la simplification:**
- ✅ Moins d'indirection
- ✅ Gestion directe par vendeur
- ✅ Pas de dépendance à des configurations globales
- ✅ Stockage JSON flexible

### Schéma Visuel

```
┌────────────────────────────────────────────────┐
│           PRODUCTTAXZONE                       │
│   (Configuration autonome par produit)         │
│                                                │
│  Livre:                                        │
│  ├─ Pays: ["FR", "DE", "IT", "ES", "BE"]     │
│  ├─ Classe: REDUCED                           │
│  └─ → Résolution TVA: VatRate(REDUCED, {pays})│
└────────────┬─────────────────────────────────┘
             │
    ┌────────┴────────┬──────────┬──────────┐
    ↓                 ↓          ↓          ↓
 VatRate('REDUCED', 'FR')   ...IT...    ...BE...
    │
    └─→ 5.5%  (taux TVA réduit France)
```
     │  └─ Pays DE → Taux 7% via VatRate    │
     └───────────────────────────────────────┘
```

---

## Structure d'une ProductTaxZone

### Champs de l'entité

## Structure d'une ProductTaxZone (Nouvelle)

### Entité ProductTaxZone refactorisée

```php
class ProductTaxZone {
    // === IDENTIFICATION ===
    int $id;                          // Identifiant unique
    
    // === RELATIONS ===
    Product $product;                 // Le produit concerné
    VatRate $vatRate;                 // Le taux TVA applicable
    
    // === CONFIGURATION ===
    array $countryCodes;              // Liste de pays ISO 3166-1 alpha-2
                                      // Exemple: ["FR", "DE", "IT", "ES", "BE"]
                                      // Stocké comme JSON en base
    
    // === AUDIT ===
    DateTimeImmutable $createdAt;     // Date de création
    DateTimeImmutable $updatedAt;     // Dernière modification
    
    // === MÉTHODES ===
    public function hasCountry(string $countryCode): bool {
        // Vérifie si le pays est couvert par cette zone
        return in_array(strtoupper($countryCode), $this->countryCodes, true);
    }
}
```

### Base de données

```sql
CREATE TABLE product_tax_zone (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    vat_rate_id BIGINT NOT NULL,
    country_codes JSON NOT NULL,        -- ["FR", "DE", "IT", ...]
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NULLABLE,
    
    UNIQUE KEY `uniq_product_vat_rate` (product_id, vat_rate_id),
    KEY `idx_product_tax_zone_product` (product_id),
    KEY `idx_product_tax_zone_vat_rate` (vat_rate_id),
    
    -- Index GIN pour recherches efficaces sur JSON
    FULLTEXT KEY `idx_country_codes` (country_codes),
    
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
    FOREIGN KEY (vat_rate_id) REFERENCES vat_rate(id) ON DELETE RESTRICT
);
```

### Changements apportés

| Aspect | Avant | Après | Raison |
|--------|-------|-------|--------|
| **Relation pays** | Via TaxZone | Directement dans array JSON | Autonomie, flexibilité |
| **Storage** | Foreign key `tax_zone_id` | JSON array `country_codes` | Denormalisation pour perf |
| **Relation TVA** | Via TaxZone indirect | Directe via `vat_rate_id` | Suppression TaxZone |
| **Query** | JOIN tax_zone + pays | PostgreSQL JSON @> operator | Optimisation des requêtes |

### Contraintes Clés

- ✅ **Unique** : Un produit ne peut avoir qu'UNE ProductTaxZone par VatRate
- ✅ **Cascade** : Supprimer produit → supprime les ProductTaxZone
- ✅ **Restrict** : Impossible de supprimer un VatRate avec des ProductTaxZone
- ✅ **JSON Valide** : Array de codes pays uppercase

---

## Priorité de Résolution

### Flux Complet (Simplifié après suppression de TaxZone)

```
QUESTION: Quel taux pour Produit "Laptop" en Allemagne (DE) ?

┌──────────────────────────────────────────┐
│ ÉTAPE 1️⃣: ProductTaxZone                 │
│ Chercher ProductTaxZone(Laptop)          │
│ Si DE dans countryCodes array?           │
│ OUI → Utiliser VatRate (DIRECT)          │
│ RÉSULTAT: 7% ✓                           │
└──────────────────────────────────────────┘
        │
        │ Si NON applicable ↓
        │
┌──────────────────────────────────────────┐
│ ÉTAPE 2️⃣: VatRate Global (Fallback)      │
│ Chercher: VatRate(DE, STANDARD)          │
│ Trouvé → Taux: 19%                       │
│ RÉSULTAT: 19% ✓                          │
└──────────────────────────────────────────┘
        │
        │ Si NON trouvé ↓
        │
┌──────────────────────────────────────────┐
│ ÉTAPE 3️⃣: Hard Default                   │
│ RÉSULTAT: 20% (hardcoded)                │
└──────────────────────────────────────────┘
```

### Pourquoi ProductTaxZone a la priorité la plus haute ?

**Raisons** :
1. **Granularité** : Configuration la plus spécifique (produit + pays)
2. **Flexibilité** : Permet d'overrider tout le reste
3. **Intent clair** : Le vendeur a explicitement configuré cette exception
4. **Business logic** : Cas spéciaux (livres, denrées, etc.)
5. **Dynamique** : Sélection intelligente basée sur VatRates du shop

---

## Cas d'Usage Pratiques

### Cas 1: Livre en UE avec Taux Réduit

**Besoin** : Les livres payent 5.5% en UE au lieu de 20%

**Configuration** :
```
Produit: "Python pour Nuls" (Livre)

ProductTaxZone:
  - countryCodes: ["FR", "DE", "IT", "ES", "BE", "NL", "AT"]
  - vat_rate_id: (5, REDUCED, 5.5%)

VatRate:
  - (REDUCED, FR) → 5.5%
  - (REDUCED, DE) → 7%
  - (REDUCED, IT) → 4%
```

**Résultat** :
```
Acheteur France:
  1️⃣ ProductTaxZone trouvée avec FR dans countryCodes
  2️⃣ VatRate appliquée: REDUCED = 5.5%
  ✓ Prix: 100€ + 5.5€ = 105.5€

Acheteur Allemagne:
  1️⃣ ProductTaxZone trouvée avec DE dans countryCodes
  2️⃣ VatRate appliquée: REDUCED = 7%
  ✓ Prix: 100€ + 7€ = 107€
  
Acheteur USA:
  1️⃣ ProductTaxZone NON trouvée (US pas dans countryCodes)
  2️⃣ Fallback VatRate global = 20%
  ✓ Prix: 100€ + 20€ = 120€
```

---

### Cas 2: Configuration Multi-pays avec Sélection Intelligente

**Besoin** : Vendeur français veut proposer 3 taux différents selon le pays

**Configuration UI** :
```
Formulaire ProductTaxZone pour Produit "Laptop":

1️⃣ Sélection du taux TVA:
   [Dropdown: "France (STANDARD, 20%)"]
   
2️⃣ Sélection des pays applicables:
   [Checkboxes]
   ☑️ 🇫🇷 France (20,0%)
   ☑️ 🇩🇪 Allemagne (19,0%)
   ☐ 🇮🇹 Italie (22,0%)    ← Grisé (vendeur n'a pas de taux STANDARD pour IT)
   
3️⃣ Sauvegarde:
   ProductTaxZone.vat_rate_id = 5  (France STANDARD)
   ProductTaxZone.country_codes = ["FR", "DE"]
```

**Intelligence** : 
- ✅ Affiche UNIQUEMENT les VatRates créés par ce vendeur
- ✅ Affiche le flag + nom pays + taux en ligne (depuis la table `country`)
- ✅ Empêche sélection de pays sans VatRate associé
- ✅ UX plus claire et moins d'erreurs

---

### Cas 3: Restriction par Shop
```
TaxZone: EU_STANDARD (tous pays UE, 20%)

Produit: "Smartphone XYZ"
  - TaxZone assignée: EU_STANDARD

ProductTaxZone 1:
  - Produit: Smartphone XYZ
  - Zone: EU_STANDARD
  - Classe: STANDARD (confirmé)

ProductTaxZone 2:
  - Produit: Smartphone XYZ
  - Zone: DE_SPECIAL (Allemagne uniquement)
  - Classe: REDUCED
```

**Résultat** :
```
Acheteur France:
  1️⃣ ProductTaxZone(Smartphone, zone_FR)?
     → EU_STANDARD trouvée
  2️⃣ Classe: STANDARD
  3️⃣ VatRate(FR, STANDARD) = 20%
  ✓ Prix: 500€ + 100€ = 600€

Acheteur Allemagne:
  1️⃣ ProductTaxZone(Smartphone, zone_DE)?
     → DE_SPECIAL trouvée (priorité!)
  2️⃣ Classe: REDUCED
  3️⃣ VatRate(DE, REDUCED) = 7%
  ✓ Prix: 500€ + 35€ = 535€
```

---

### Cas 3: Produit Multi-Zones Complexe

**Besoin** : Média USB avec taux différents selon région

**Configuration** :
```
Produit: "USB Flash Drive"

ProductTaxZone 1:
  - Zone: EU_STANDARD
  - Classe: STANDARD → 20%

ProductTaxZone 2:
  - Zone: UK_IRELAND
  - Classe: REDUCED → 17.5%

ProductTaxZone 3:
  - Zone: SWITZERLAND
  - Classe: STANDARD → 7.7%

ProductTaxZone 4:
  - Zone: NORWAY
  - Classe: ZERO → 0%
```

**Résultat** :
```
France (EU)          : 20%
Allemagne (EU)       : 20%
Royaume-Uni          : 17.5%
Irlande              : 17.5%
Suisse               : 7.7%
Norvège              : 0%
États-Unis (no zone) : 20% (default)
```

---

### Cas 4: Denrées Alimentaires (Classe ZERO)

**Besoin** : Produits alimentaires exemptés de TVA dans certains pays

**Configuration** :
```
Produit: "Farine Bio 1kg"

ProductTaxZone:
  - Zone: EU_FOOD_EXEMPT
  - Classe: ZERO

VatRate:
  - (FR, ZERO) → 0%
  - (DE, ZERO) → 0%
  - (IT, ZERO) → 0%
```

**Résultat** :
```
Tous pays UE: 0% TVA
Prix: 5€ HT = 5€ TTC (pas de TVA)
```

---

## Workflow Vendeur

### Ajouter une ProductTaxZone

**Interface** : Formulaire produit → Section "Zones TVA du produit"

**Étapes** :
```
1. Éditer produit: "Mon Livre"
2. Descendre à "Zones TVA du produit"
3. Cliquer: "+ Ajouter une zone TVA"
4. Formulaire apparaît:
   ┌────────────────────────────────┐
   │ Zone TVA*: [Dropdown]          │
   │ → EU_BOOKS_REDUCED             │
   │                                │
   │ Classe TVA*: [Dropdown]        │
   │ → REDUCED                      │
   │                                │
   │ [Ajouter]  [Annuler]           │
   └────────────────────────────────┘
5. Cliquer "Ajouter"
6. La ligne apparaît dans la liste
7. Enregistrer le produit
```

**Résultat en base** :
```sql
INSERT INTO product_tax_zone (product_id, tax_zone_id, tax_class)
VALUES (42, 15, 'REDUCED');
```

---

### Modifier une ProductTaxZone

```
1. Éditer produit
2. Dans "Zones TVA du produit", trouver la ligne
3. Cliquer sur l'icône "Éditer"
4. Modifier la classe TVA:
   REDUCED → STANDARD
5. Cliquer "Mettre à jour"
6. Enregistrer le produit
```

**Impact** :
```
Avant: Livre en France → 5.5%
Après: Livre en France → 20%
```

---

### Supprimer une ProductTaxZone

```
1. Éditer produit
2. Dans "Zones TVA du produit", trouver la ligne
3. Cliquer sur l'icône "Supprimer"
4. Confirmer
5. Enregistrer le produit
```

**Impact** :
```
Fallback à TaxZone ou VatRate global
```

---

## Gestion Multiple Zones

### Affichage dans le Formulaire

```
┌────────────────────────────────────────────┐
│ Zones TVA du produit                       │
├────────────────────────────────────────────┤
│                                            │
│ ┌────────────────────────────────────────┐ │
│ │ Zone           Classe      Actions     │ │
│ ├────────────────────────────────────────┤ │
│ │ EU_STANDARD    STANDARD    [✏️] [🗑️]   │ │
│ │ DE_SPECIAL     REDUCED     [✏️] [🗑️]   │ │
│ │ UK_IRELAND     REDUCED     [✏️] [🗑️]   │ │
│ └────────────────────────────────────────┘ │
│                                            │
│ [+ Ajouter une zone TVA]                   │
└────────────────────────────────────────────┘
```

### JavaScript (Alpine.js)

```javascript
// setupTaxZonesCollection() dans le template
function setupTaxZonesCollection() {
    const container = document.querySelector('[data-collection-holder]');
    const addButton = document.querySelector('[data-add-collection]');
    
    addButton.addEventListener('click', () => {
        // Créer nouveau prototype
        const prototype = container.dataset.prototype;
        const index = container.children.length;
        const newItem = prototype.replace(/__name__/g, index);
        
        // Ajouter au DOM
        container.insertAdjacentHTML('beforeend', newItem);
    });
}
```

---

## Bonnes Pratiques

### ✅ À FAIRE

**1. Utiliser ProductTaxZone pour les exceptions**
```
✓ 80% des produits → TaxZone simple
✓ 20% exceptions → ProductTaxZone
✓ Ne pas créer ProductTaxZone si TaxZone suffit
```

**2. Grouper les zones logiquement**
```
✓ EU_BOOKS_REDUCED pour tous les livres
✓ EU_FOOD_EXEMPT pour denrées
✓ Réutiliser les mêmes zones
```

**3. Documenter les raisons métier**
```
✓ Ajouter description: "Directive UE 2006/112/CE Article 98"
✓ Justifier pourquoi REDUCED au lieu de STANDARD
```

**4. Tester la résolution avant déploiement**
```
✓ Vérifier chaque pays manuellement
✓ Utiliser outil de preview TVA
✓ Confirmer avec équipe comptable
```

**5. Maintenir la cohérence**
```
✓ Si livre A → REDUCED, alors livre B → REDUCED aussi
✓ Éviter incohérences dans même catégorie
```

---

### ❌ À ÉVITER

**1. ProductTaxZone inutiles**
```
✗ Créer ProductTaxZone si TaxZone du produit suffit
→ Complexité inutile
```

**2. Zones chevauchantes confuses**
```
✗ ProductTaxZone 1: EU_STANDARD (FR, DE, IT)
✗ ProductTaxZone 2: EU_BOOKS (FR, DE, ES)
→ France = quelle zone? Ambiguïté!
```

**3. Classe incohérente avec la zone**
```
✗ Zone EU_REDUCED (classe: REDUCED)
✗ ProductTaxZone avec cette zone → Classe STANDARD
→ Confus! Pourquoi utiliser EU_REDUCED si override STANDARD?
```

**4. Oublier de valider après modification**
```
✗ Modifier classe sans tester
→ Risque de taux incorrect
```

**5. Trop de ProductTaxZone par produit**
```
✗ 10+ ProductTaxZone pour un produit
→ Signe de mauvaise architecture
→ Créer plutôt des TaxZone spécifiques
```

---

## Pièges Courants

### 🚨 Piège 1: Zone Inactive

```
ProductTaxZone:
  - Zone: EU_STANDARD (active = false)

Résultat:
ProductTaxZone ignorée (VatResolutionService vérifie isActive())
Fallback à TaxZone legacy ou VatRate
```

**Solution** :
```
Ne pas désactiver zone en utilisation
Ou migrer ProductTaxZone d'abord
```

---

### 🚨 Piège 2: VatRate Manquant

```
ProductTaxZone:
  - Classe: REDUCED

VatRate:
  - (FR, STANDARD) → 20%
  - (FR, REDUCED) → PAS CRÉÉ! ❌

Résultat:
Classe résolue = REDUCED
VatRate(FR, REDUCED) non trouvé
Fallback à taux de la zone ou 20% default
```

**Solution** :
```
Toujours créer VatRate pour toutes les classes utilisées:
- (FR, STANDARD) → 20%
- (FR, REDUCED) → 5.5%
- (FR, ZERO) → 0%
```

---

### 🚨 Piège 3: Doublon Product + Zone

```
Tentative:
ProductTaxZone 1: Laptop + EU_STANDARD → STANDARD
ProductTaxZone 2: Laptop + EU_STANDARD → REDUCED

Résultat:
Erreur SQL (contrainte UNIQUE product_id + tax_zone_id)
```

**Solution** :
```
Un produit = UNE ProductTaxZone par zone
Si besoin de changer classe:
→ Modifier ProductTaxZone existante
→ Ne pas en créer une nouvelle
```

---

### 🚨 Piège 4: Zone sans Pays pour Pays Client

```
ProductTaxZone:
  - Zone: DE_SPECIAL (Pays: [DE])

Client:
  - Pays: France (FR)

Résultat:
ProductTaxZone non applicable (FR pas dans DE_SPECIAL)
Fallback à TaxZone legacy ou VatRate
```

**Solution** :
```
Créer ProductTaxZone pour chaque zone couvrant le pays client
Ou laisser TaxZone legacy gérer les autres pays
```

---

### 🚨 Piège 5: Classe Oubliée dans Formulaire

```
Formulaire:
Zone TVA: EU_BOOKS_REDUCED ✓
Classe TVA: [Vide] ❌

Résultat:
Validation échoue (classe requise)
```

**Solution** :
```
Toujours remplir les deux champs:
- Zone TVA (required)
- Classe TVA (required, default: STANDARD)
```

---

## Implémentation Technique

### Entité PHP

```php
// src/Entity/ProductTaxZone.php
#[ORM\Entity(repositoryClass: ProductTaxZoneRepository::class)]
#[ORM\Table(name: 'product_tax_zone')]
#[ORM\UniqueConstraint(
    name: 'uniq_product_tax_zone_product_zone',
    columns: ['product_id', 'tax_zone_id']
)]
class ProductTaxZone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productTaxZones')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: TaxZone::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?TaxZone $taxZone = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['STANDARD', 'REDUCED', 'ZERO'])]
    private string $taxClass = 'STANDARD';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters/Setters...
}
```

### Repository

```php
// src/Repository/ProductTaxZoneRepository.php
public function findForProductAndCountry(
    Product $product, 
    string $countryCode
): ?ProductTaxZone {
    return $this->createQueryBuilder('ptz')
        ->innerJoin('ptz.taxZone', 'tz')
        ->where('ptz.product = :product')
        ->andWhere('tz.active = true')
        ->andWhere('JSON_CONTAINS(tz.countryCodes, :country) = 1')
        ->setParameter('product', $product)
        ->setParameter('country', json_encode($countryCode))
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
```

### Form Type

```php
// src/Form/ProductTaxZoneType.php
class ProductTaxZoneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('taxZone', EntityType::class, [
                'class' => TaxZone::class,
                'choice_label' => 'name',
                'query_builder' => fn(TaxZoneRepository $repo) => 
                    $repo->createQueryBuilder('tz')
                        ->where('tz.active = true')
                        ->orderBy('tz.sortOrder', 'ASC'),
                'label' => 'Zone TVA',
                'required' => true,
            ])
            ->add('taxClass', ChoiceType::class, [
                'choices' => [
                    'Standard' => 'STANDARD',
                    'Réduit' => 'REDUCED',
                    'Zéro' => 'ZERO',
                ],
                'label' => 'Classe TVA',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductTaxZone::class,
        ]);
    }
}
```

### Utilisation dans ProductType

```php
// src/Form/Vendor/ProductType.php
$builder
    ->add('productTaxZones', CollectionType::class, [
        'entry_type' => ProductTaxZoneType::class,
        'allow_add' => true,
        'allow_delete' => true,
        'by_reference' => false,
        'label' => 'Zones TVA du produit',
        'required' => false,
    ]);
```

### Template Twig

```twig
{# templates/vendor/product/form.html.twig #}
<div class="mb-6">
    <h3 class="text-lg font-semibold mb-3">Zones TVA du produit</h3>
    
    <div data-collection-holder 
         data-prototype="{{ form_widget(form.productTaxZones.vars.prototype)|e('html_attr') }}">
        {% for taxZoneForm in form.productTaxZones %}
            <div class="flex gap-4 mb-2">
                {{ form_row(taxZoneForm.taxZone) }}
                {{ form_row(taxZoneForm.taxClass) }}
                <button type="button" data-remove-collection class="btn-danger">
                    🗑️ Supprimer
                </button>
            </div>
        {% endfor %}
    </div>
    
    <button type="button" data-add-collection class="btn-secondary mt-3">
        + Ajouter une zone TVA
    </button>
</div>
```

### Service de Résolution

```php
// src/Service/VatResolutionService.php
public function resolveVatRateForProduct(
    Product $product,
    string $countryCode,
    ?Shop $shop = null
): array {
    // 1️⃣ ProductTaxZone (PRIORITÉ MAXIMALE)
    $productTaxZone = $this->productTaxZoneRepository
        ->findForProductAndCountry($product, $countryCode);
    
    if (null !== $productTaxZone && 
        null !== $productTaxZone->getTaxZone() &&
        $productTaxZone->getTaxZone()->isActive()) {
        
        $taxClass = $productTaxZone->getTaxClass();
        $vatRate = $this->vatRateRepository
            ->findEffectiveRate($countryCode, $shop, $taxClass);
        
        if (null !== $vatRate) {
            return [
                'rate' => $vatRate->getRate(),
                'source' => 'PRODUCT_TAX_ZONE',
                'priority' => 1,
                'entity' => $productTaxZone,
                'reason' => sprintf(
                    'ProductTaxZone "%s" classe %s: %.2f%%',
                    $productTaxZone->getTaxZone()->getName(),
                    $taxClass,
                    $vatRate->getRate()
                ),
            ];
        }
    }
    
    // 2️⃣ TaxZone legacy...
    // 3️⃣ VatRate global...
    // 4️⃣ Default 20%...
}
```

---

## Résumé Pratique

| Question | Réponse |
|----------|---------|
| **Qu'est-ce?** | Association produit + zone avec classe TVA spécifique |
| **Combien par produit?** | **Plusieurs** (une par zone) |
| **Priorité?** | **1ère** (plus haute) |
| **Qui gère?** | Vendeur (formulaire produit) |
| **Quand utiliser?** | Exceptions, règles granulaires, produits spéciaux |
| **Différence TaxZone?** | ProductTaxZone = spécifique produit, TaxZone = global |
| **Classe fixe?** | Non, peut différer de la zone |
| **Modification impact?** | Seul le produit concerné |

---

## Liens Connexes

- [Guide TaxZone Complet](tax-zones-guide.md)
- [Guide VAT Management](vat-management.md)
- [Guide Vendeur TVA](vat-vendor-guide.md)

---

**Last Updated:** 2026-02-02  
**Status:** ✅ Ready for Production
