# Mise à Jour de la Fiche Produit - Gestion des Taux TVA par Pays

## 📋 Vue d'ensemble

La fiche produit a été mise à jour pour permettre aux vendeurs de gérer plusieurs taux TVA par pays (exceptions). Cette nouvelle fonctionnalité s'ajoute au système existant de classe TVA et zone TVA.

## ✨ Nouvelles Fonctionnalités

### 1. **Section "Taux TVA par Pays"**
- Position: Entre la section "Classe TVA / Zone TVA" et "Mots clés"
- Permet de définir des exceptions TVA pour des pays spécifiques
- Interface intuitive avec tableau affichant les exceptions existantes

### 2. **Ajouter une Exception TVA**
- Bouton "+ Ajouter une exception" pour créer un nouveau taux
- Form dynamique qui se complète automatiquement
- Champs:
  - **Pays**: Sélecteur de pays avec pays EU en priorité
  - **Classe TVA**: STANDARD, RÉDUIT ou TAUX ZÉRO
  - **Taux (%)**: Valeur numérique de 0 à 100
  - **Actif**: Checkbox pour activer/désactiver l'exception

### 3. **Gérer les Exceptions**
- Vue tableau de toutes les exceptions actuelles
- Modification en ligne des valeurs
- Suppression facile avec bouton "Supprimer"
- Basculer status avec checkbox "Actif"

### 4. **Validation et Feedback**
- Validation en temps réel du formulaire
- Messages d'erreur détaillés
- Indication visuelle quand aucune exception n'existe
- Info-bulles expliquant le système de priorité

## 🔄 Système de Priorité TVA

Les taux TVA sont résolus selon cette priorité (du plus spécifique au plus général):

1. **ProductVatRate** (Exception par produit/pays) ← **NOUVEAU**
2. **TaxZone** (Zone TVA sélectionnée)
3. **VatRate** (Taux global par pays)
4. **Défaut** (20% standard)

**Exemple:**
- Produit avec classe TVA = STANDARD (20%)
- Zone TVA = EU (taux différents par pays)
- Exception pour France = 5.5% (taux réduit)

→ Pour la France: **5.5%** (exception prioritaire)
→ Pour l'Allemagne: Taux défini dans la zone

## 🏗️ Architecture Technique

### Entités Impliquées

**Product.php**
```php
#[ORM\OneToMany(
    mappedBy: 'product',
    targetEntity: ProductVatRate::class,
    cascade: ['persist', 'remove'],
    orphanRemoval: true
)]
private Collection $productVatRates;

public function getProductVatRates(): Collection
public function addProductVatRate(ProductVatRate $rate): self
public function removeProductVatRate(ProductVatRate $rate): self
```

**ProductVatRate.php** (nouvelle entité)
```php
- product_id (FK) → Product
- country_code (2 chars, ISO 3166-1)
- tax_class (enum: STANDARD|REDUCED|ZERO)
- rate (0-100%)
- active (boolean)
- shop_id (optionnel, pour multi-shop)
- Constraint UNIQUE(product_id, country_code, shop_id)
```

### Formulaires

**ProductVatRateType.php** (nouveau)
```php
- countryCode: CountryType avec EU en priorité
- taxClass: ChoiceType (STANDARD, REDUCED, ZERO)
- rate: NumberType (0-100, scale: 2)
- active: CheckboxType
```

**ProductType.php** (modifié)
```php
// Ajout du CollectionType
->add('productVatRates', CollectionType::class, [
    'entry_type' => ProductVatRateType::class,
    'allow_add' => true,
    'allow_delete' => true,
    'by_reference' => false,
    'required' => false,
])
```

### Template

**templates/vendor/product/form.html.twig** (modifié)
```twig
{# VAT Rates by Country Section #}
<div class="md:col-span-4">
    <div class="space-y-4">
        {# Bouton d'ajout #}
        <button type="button" id="add-vat-rate" ...>
            Ajouter une exception
        </button>
        
        {# Collection container #}
        <div id="{{ form.productVatRates.vars.id }}" 
             class="vat-rates-collection"
             data-prototype="...">
            {# Tableau d'exceptions #}
            {# Items affichés dynamiquement #}
        </div>
    </div>
</div>
```

### JavaScript

**setupVatRatesCollection()** - Fonctions principales:
- `createItemElement(html, index)`: Crée un nouvel élément de formulaire
- `attachRemoveListener(element)`: Associe le bouton de suppression
- Gestionnaire du bouton "+ Ajouter"
- Gestion automatique des états (affichage/masquage du message vide)
- Réinitialisation après navigation Turbo

## 📝 Workflow Vendeur

### Créer un Produit avec Exceptions TVA

1. **Aller à**: Tableau de bord → Produits → Créer un produit
2. **Remplir les champs standards**: Nom, description, prix, etc.
3. **Configurer la TVA (défaut)**:
   - Classe TVA: "Standard (20%)"
   - Zone TVA: Sélectionner une zone (optionnel)
4. **Ajouter des exceptions** (nouveau):
   - Cliquer "+ Ajouter une exception"
   - Sélectionner pays (ex: France)
   - Choisir classe (ex: RÉDUIT)
   - Entrer taux (ex: 7.00%)
   - Cocher "Actif"
   - Répéter pour d'autres pays
5. **Enregistrer le produit**: Cliquer le bouton "Enregistrer"

### Résultat

```
Produit: "NovaBook Q4"
Classe TVA: STANDARD (20%) - défaut
Zone TVA: UE
Exceptions:
  - France: 5.5% (RÉDUIT)
  - Allemagne: 7% (STANDARD)
  - Italie: 4% (RÉDUIT)
```

### Calcul du Prix TTC

Pour le client en France achetant ce produit:
- Prix HT: 100€
- Taux appliqué: 5.5% (exception)
- TVA: 5.50€
- **Prix TTC: 105.50€**

Pour le client en Espagne (pas d'exception, zone EU):
- Prix HT: 100€
- Taux appliqué: 20% (classe STANDARD)
- TVA: 20€
- **Prix TTC: 120€**

## 🔧 Modification/Suppression d'Exceptions

### Modifier une Exception Existante

1. Ouvrir la fiche produit
2. Scrollrer jusqu'à "Taux TVA par pays"
3. Modifier les champs directement dans le tableau
4. Cliquer "Enregistrer le produit"

### Désactiver une Exception Temporaire

- Décocher la checkbox "Actif" pour l'exception
- L'exception reste en base de données mais n'est pas utilisée
- Cliquer "Enregistrer"
- La TVA par défaut s'appliquera

### Supprimer une Exception

1. Cliquer le bouton "Supprimer" rouge sur la ligne
2. Cliquer "Enregistrer le produit"
3. L'exception est supprimée de la base de données

## 🧪 Tests de Validation

Le formulaire valide:

✅ **Taux valides**: 0 à 100% (décimales acceptées)
- ✓ 0
- ✓ 5.5
- ✓ 20.00
- ✓ 100

❌ **Taux invalides**:
- ✗ -1 (négatif)
- ✗ 101 (> 100)
- ✗ Vide (requis)

✅ **Pays**: Toute valeur ISO 3166-1 valide
- ✓ FR, DE, IT, ES, etc.
- Options EU prioritaires

❌ **Pays invalides**:
- ✗ Vide (requis)
- ✗ Code invalide

✅ **Classe TVA**: STANDARD, REDUCED, ZERO uniquement

## 📊 Couverture TVA

En haut de la section, vous verrez:
- **Nombre d'exceptions actives**: X pays configurés
- **Statut de couverture**: 
  - 🟢 Complète si défaut applicables à tous
  - 🟡 Partielle si certains pays manquent
  - ⚠️ À risque si VAT zone manque

## 🚀 Intégration API

### Récupérer Taux TVA pour un Produit

```php
$product = $productRepository->find($id);
$rate = $vatResolutionService->getRateForProduct(
    $product,
    'FR', // country code
    $shopId
);
// Retourne: 5.5 (pour notre exemple)
```

### Résolution Complète

```php
$resolution = $vatResolutionService->resolveVatRateForProduct(
    $product,
    'FR',
    $shopId
);
/*
Retourne:
[
    'rate' => 5.5,
    'source' => 'ProductVatRate',
    'priority' => 1,
    'reason' => 'Exception TVA pour France'
]
*/
```

### Couverture Produit

```php
$coverage = $vatResolutionService->getProductVatCoverage($product);
/*
Retourne:
[
    'covered_countries' => ['FR', 'DE', 'IT'],
    'with_exceptions' => ['FR', 'DE'],
    'using_default' => ['IT'],
    'coverage_percentage' => 85,
    'gaps' => ['PL', 'CZ']
]
*/
```

## ⚠️ Points Importants

1. **Cascade Operations**: Les exceptions sont supprimées automatiquement si le produit est supprimé
2. **Orphan Removal**: Actif - les exceptions orphelines sont automatiquement supprimées
3. **Multi-Shop**: Chaque exception peut être spécifique à un shop (optionnel)
4. **Performance**: Index sur product_id et country_code pour requêtes rapides
5. **Validation**: Taux 0-100%, pays ISO valide, classe TVA dans enum

## 🔍 Administration

### Interface EasyAdmin

Une interface admin est disponible pour gérer les ProductVatRate:

**URL**: `/admin/product-vat-rate`

Fonctionnalités:
- Lister toutes les exceptions avec filtrage
- Créer/modifier/supprimer exceptions
- Filtrer par produit, pays, classe
- Bulk actions (activation/désactivation)

### Requêtes SQL Utiles

```sql
-- Exceptions pour un produit
SELECT * FROM product_vat_rate 
WHERE product_id = ? 
ORDER BY country_code;

-- Taux spécifiques par pays
SELECT DISTINCT country_code, rate 
FROM product_vat_rate 
WHERE active = true 
GROUP BY country_code;

-- Couverture complète
SELECT 
    p.id, 
    p.name, 
    COUNT(pvr.id) as exception_count,
    ARRAY_AGG(pvr.country_code) as countries
FROM product p
LEFT JOIN product_vat_rate pvr ON pvr.product_id = p.id
GROUP BY p.id
HAVING COUNT(pvr.id) > 0;
```

## 🎯 Prochaines Étapes (Optionnel)

1. **Import/Export**: Importer des exceptions depuis fichier CSV
2. **Templates**: Utiliser un produit comme template pour copier exceptions
3. **Règles Conditionnelles**: Appliquer automatiquement exceptions basées sur des règles
4. **Notifications**: Alerter si couverture TVA incomplète
5. **Historial**: Tracer les modifications des taux

## 📞 Support

Pour toute question:
- Consulter la documentation VAT complète: [docs/vat-management.md](vat-management.md)
- Guides spécialisés: [docs/vat-vendor-guide.md](vat-vendor-guide.md) ou [docs/vat-admin-guide.md](vat-admin-guide.md)
- Exemples: [docs/VAT_EXAMPLES.php](VAT_EXAMPLES.php)
