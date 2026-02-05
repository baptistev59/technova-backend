# 🎯 Guide Complet des ProductTaxZone (Zones TVA par Produit)

**Version:** 1.0  
**Date:** 2 février 2026  
**Audience:** Vendeurs, Administrateurs, Développeurs

---

## 📌 Table des Matières

1. [Concept fondamental](#concept-fondamental)
2. [Différence avec TaxZone](#différence-avec-taxzone)
3. [Structure d'une ProductTaxZone](#structure-dune-producttaxzone)
4. [Priorité de résolution](#priorité-de-résolution)
5. [Cas d'usage pratiques](#cas-dusage-pratiques)
6. [Workflow vendeur](#workflow-vendeur)
7. [Gestion multiple zones](#gestion-multiple-zones)
8. [Bonnes pratiques](#bonnes-pratiques)
9. [Pièges courants](#pièges-courants)
10. [Implémentation technique](#implémentation-technique)

---

## Concept Fondamental

### Qu'est-ce qu'une ProductTaxZone ?

Une **ProductTaxZone** est une **association** entre :
- Un **produit** spécifique
- Une **TaxZone** (regroupement de pays)
- Une **classe TVA** pour ce duo (peut différer de la classe par défaut de la zone)

C'est une **table de jointure enrichie** qui permet de définir des règles TVA **granulaires par produit**.

### Analogie

```
TaxZone = Gabarit pour tous les produits
ProductTaxZone = Exception spécifique pour UN produit

Exemple:
TaxZone EU_STANDARD : 
  - Tous produits en UE → 20%

ProductTaxZone :
  - Livre + EU_BOOKS → 5.5% (REDUCED au lieu de STANDARD)
  - Smartphone + DE_SPECIAL → 7% (REDUCED pour Allemagne seulement)
```

---

## Différence avec TaxZone

### Tableau Comparatif Détaillé

| Aspect | TaxZone | ProductTaxZone |
|--------|---------|-----------------|
| **Nature** | Regroupement de pays | Association produit ↔ zone |
| **Entité** | `TaxZone` (standalone) | `ProductTaxZone` (jointure) |
| **Relation** | Indépendante | Dépend de Product + TaxZone |
| **Assignation** | 1 par produit (legacy) | **Plusieurs par produit** ✓ |
| **Classe TVA** | Fixe dans la zone | **Peut être différente** ✓ |
| **Taux** | Stocké dans la zone | Résolu via VatRate + classe |
| **Flexibilité** | Basse (même pour tous) | **Haute (par produit)** ✓ |
| **Priorité** | 2 (après ProductTaxZone) | **1 (PLUS HAUTE)** ✓ |
| **Cas d'usage** | Config simple, fallback | Règles complexes, exceptions |
| **Gestion** | Admin ou vendeur | **Vendeur uniquement** |
| **Modification** | Impact tous les produits | Impact 1 seul produit |

### Schéma Visuel

```
┌─────────────────────────────────────────────┐
│              TAXZONE                        │
│  (Gabarit réutilisable)                     │
│                                             │
│  EU_STANDARD                                │
│  ├─ Pays: [FR, DE, IT, ES, ...]            │
│  ├─ Classe: STANDARD                        │
│  └─ Taux: 20%                               │
└────────────────┬────────────────────────────┘
                 │
        Utilisée par plusieurs produits
                 │
     ┌───────────┴──────────┬─────────────┐
     ↓                      ↓             ↓
┌─────────┐          ┌─────────┐    ┌─────────┐
│ Laptop  │          │ Mouse   │    │ Book    │
└─────────┘          └─────────┘    └─────────┘
     │                                   │
     └───────────────────────────────────┘
                     │
                     ↓
     ┌───────────────────────────────────────┐
     │      PRODUCTTAXZONE                   │
     │  (Exception spécifique)               │
     │                                       │
     │  Book + EU_BOOKS_REDUCED              │
     │  ├─ Zone: EU_BOOKS_REDUCED            │
     │  ├─ Classe: REDUCED (au lieu de STD) │
     │  └─ Pays DE → Taux 7% via VatRate    │
     └───────────────────────────────────────┘
```

---

## Structure d'une ProductTaxZone

### Champs de l'entité

```php
class ProductTaxZone {
    // === IDENTIFICATION ===
    int $id;                          // Identifiant unique
    
    // === RELATIONS ===
    Product $product;                 // Le produit concerné
    TaxZone $taxZone;                 // La zone utilisée
    
    // === CONFIGURATION ===
    string $taxClass;                 // Classe TVA pour ce produit dans cette zone
                                      // Peut différer de $taxZone->taxClass
                                      // Valeurs: STANDARD|REDUCED|ZERO
    
    // === AUDIT ===
    DateTimeImmutable $createdAt;     // Date de création
    DateTimeImmutable $updatedAt;     // Dernière modification
}
```

### Base de données

```sql
CREATE TABLE product_tax_zone (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    product_id BIGINT NOT NULL,
    tax_zone_id BIGINT NOT NULL,
    tax_class VARCHAR(32) DEFAULT 'STANDARD',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NULLABLE,
    
    UNIQUE KEY `uniq_product_tax_zone_product_zone` (product_id, tax_zone_id),
    KEY `idx_product_tax_zone_product` (product_id),
    KEY `idx_product_tax_zone_zone` (tax_zone_id),
    
    FOREIGN KEY (product_id) REFERENCES product(id) ON DELETE CASCADE,
    FOREIGN KEY (tax_zone_id) REFERENCES tax_zone(id) ON DELETE CASCADE
);
```

### Contraintes Clés

- ✅ **Unique** : Un produit ne peut avoir qu'UNE ProductTaxZone par TaxZone
- ✅ **Cascade** : Supprimer produit → supprime les ProductTaxZone
- ✅ **Validation** : taxClass doit être STANDARD, REDUCED ou ZERO

---

## Priorité de Résolution

### Flux Complet

```
QUESTION: Quel taux pour Produit "Laptop" en Allemagne (DE) ?

┌──────────────────────────────────────────┐
│ ÉTAPE 1️⃣: ProductTaxZone                 │
│ Chercher: ProductTaxZone(Laptop, zone_DE)│
│ Trouvée? → Utiliser sa classe            │
│ Classe: REDUCED                          │
│ → Chercher VatRate(DE, REDUCED) = 7%     │
│ RÉSULTAT: 7% ✓                           │
└──────────────────────────────────────────┘
        │
        │ Si NON trouvée ↓
        │
┌──────────────────────────────────────────┐
│ ÉTAPE 2️⃣: TaxZone (Legacy)               │
│ Produit.taxZone = EU_STANDARD?           │
│ DE dans EU_STANDARD.countryCodes?        │
│ OUI → Taux: 20%                          │
│ RÉSULTAT: 20% ✓                          │
└──────────────────────────────────────────┘
        │
        │ Si NON applicable ↓
        │
┌──────────────────────────────────────────┐
│ ÉTAPE 3️⃣: VatRate Global                 │
│ Chercher: VatRate(DE, STANDARD)          │
│ Trouvé → Taux: 19%                       │
│ RÉSULTAT: 19% ✓                          │
└──────────────────────────────────────────┘
        │
        │ Si NON trouvé ↓
        │
┌──────────────────────────────────────────┐
│ ÉTAPE 4️⃣: Hard Default                   │
│ RÉSULTAT: 20% (hardcoded)                │
└──────────────────────────────────────────┘
```

### Pourquoi ProductTaxZone a la priorité la plus haute ?

**Raisons** :
1. **Granularité** : Configuration la plus spécifique (produit + zone)
2. **Flexibilité** : Permet d'overrider tout le reste
3. **Intent clair** : Le vendeur a explicitement configuré cette exception
4. **Business logic** : Cas spéciaux (livres, denrées, etc.)

---

## Cas d'Usage Pratiques

### Cas 1: Livre en UE avec Taux Réduit

**Besoin** : Les livres payent 5.5% en UE au lieu de 20%

**Configuration** :
```
TaxZone: EU_STANDARD
  - Pays: [FR, DE, IT, ES, BE, NL, AT, LU]
  - Classe: STANDARD
  - Taux: 20%

Produit: "Python pour Nuls" (Livre)
  - TaxZone assignée: EU_STANDARD (fallback)

ProductTaxZone:
  - Produit: "Python pour Nuls"
  - Zone: EU_BOOKS_REDUCED (même pays que EU_STANDARD)
  - Classe: REDUCED

VatRate:
  - (FR, REDUCED) → 5.5%
  - (DE, REDUCED) → 7%
  - (IT, REDUCED) → 4%
```

**Résultat** :
```
Acheteur France:
  1️⃣ ProductTaxZone trouvée (EU_BOOKS_REDUCED)
  2️⃣ Classe: REDUCED
  3️⃣ VatRate(FR, REDUCED) = 5.5%
  ✓ Prix: 100€ + 5.5€ = 105.5€

Acheteur Allemagne:
  1️⃣ ProductTaxZone trouvée (EU_BOOKS_REDUCED)
  2️⃣ Classe: REDUCED
  3️⃣ VatRate(DE, REDUCED) = 7%
  ✓ Prix: 100€ + 7€ = 107€
```

---

### Cas 2: Smartphone avec Exception Allemagne

**Besoin** : Smartphone à 7% en Allemagne, 20% ailleurs en UE

**Configuration** :
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
