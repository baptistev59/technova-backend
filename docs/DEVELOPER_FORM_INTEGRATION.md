# Guide d'Implémentation - Collection ProductVatRate dans ProductType

## 📌 Fichiers Modifiés

### 1. `src/Form/Vendor/ProductType.php`

**Changements**:
- ✅ Import de `ProductVatRate` et `CollectionType`
- ✅ Ajout du champ `productVatRates` avec configuration CollectionType

```php
use App\Entity\ProductVatRate;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;

// Dans buildForm():
->add('productVatRates', CollectionType::class, [
    'label' => 'Taux TVA par pays',
    'entry_type' => ProductVatRateType::class,
    'entry_options' => [
        'label' => false,
    ],
    'allow_add' => true,
    'allow_delete' => true,
    'by_reference' => false,
    'required' => false,
    'help' => 'Ajoute des exceptions de TVA...',
    'attr' => [
        'class' => 'product-vat-rates-collection',
        'data-prototype-helper' => true,
    ],
])
```

### 2. `templates/vendor/product/form.html.twig`

**Changements**:
- ✅ Nouvelle section "Taux TVA par pays" après taxZone
- ✅ Tableau affichant les exceptions existantes
- ✅ Bouton "+ Ajouter une exception"
- ✅ Formulaire dynamique avec add/remove

**Structure HTML**:
```twig
{# Bouton d'ajout #}
<button type="button" id="add-vat-rate" ...>

{# Collection container #}
<div id="{{ form.productVatRates.vars.id }}" class="vat-rates-collection" ...>
    
    {# Tableau header #}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
        <div>Pays</div>
        <div>Classe</div>
        <div>Taux (%)</div>
        <div>Actif</div>
        <div>Actions</div>
    </div>
    
    {# Items (itération) #}
    {% for vatRateField in form.productVatRates %}
        <div class="vat-rate-item grid grid-cols-1 md:grid-cols-12 ...">
            {# Affichage des champs #}
        </div>
    {% endfor %}
</div>

{# État vide #}
{% if form.productVatRates|length == 0 %}
    <div class="empty-state">Aucune exception...</div>
{% endif %}
```

**CSS Classes**:
- `.vat-rates-collection` - Conteneur principal
- `.vat-rate-item` - Chaque ligne du tableau
- `.remove-vat-rate` - Bouton de suppression

### 3. JavaScript dans le template

**Fonctions principales**:

```javascript
function setupVatRatesCollection() {
    // Initialisation automatique au chargement
    
    // Récupérer les éléments clés
    const collectionContainer = document.querySelector('.vat-rates-collection');
    const prototype = collectionContainer.getAttribute('data-prototype');
    const addBtn = document.getElementById('add-vat-rate');
    
    // Gestionnaire d'ajout
    addBtn.addEventListener('click', (e) => {
        // Créer nouvel item avec prototype
        // Attacher listeners
        // Mettre à jour l'index
    });
    
    // Gestionnaires de suppression
    collectionContainer.querySelectorAll('.remove-vat-rate').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            // Supprimer l'élément
        });
    });
}

// Appels automatiques
document.addEventListener('DOMContentLoaded', setupVatRatesCollection);
document.addEventListener('turbo:load', setupVatRatesCollection);
```

## 🔄 Flux de Données

### 1. Création (Nouveau produit)

```
Vendeur remplit le formulaire
    ↓
ProductType valide les données
    ↓
ProductVatRateType valide chaque exception
    ↓
Doctrine crée Product + ProductVatRate(s)
    ↓
Sauvegarde en base de données
```

### 2. Modification (Produit existant)

```
Charger produit avec exceptions
    ↓
Afficher collection ProductVatRate dans formulaire
    ↓
Vendeur modifie/ajoute/supprime exceptions
    ↓
Soumission du formulaire
    ↓
Doctrine détecte changements (cascade)
    ↓
Mise à jour/insertion/suppression
```

### 3. Suppression de Produit

```
Supprimer produit
    ↓
Doctrine détecte orphanRemoval: true
    ↓
Supprime automatiquement ProductVatRate(s)
    ↓
Produit supprimé (cascade complète)
```

## 🧪 Test du Formulaire

### Test Unitaire (FormType)

```php
use Symfony\Component\Form\Test\TypeTestCase;

class ProductTypeTest extends TypeTestCase
{
    public function testFormSubmitsValidData(): void
    {
        $formData = [
            'name' => 'Test Product',
            'productVatRates' => [
                [
                    'countryCode' => 'FR',
                    'taxClass' => 'REDUCED',
                    'rate' => 5.5,
                    'active' => true,
                ]
            ]
        ];
        
        $form = $this->factory->create(ProductType::class);
        $form->submit($formData);
        
        $this->assertTrue($form->isSynchronized());
        $this->assertCount(1, $form->getData()->getProductVatRates());
    }
}
```

### Test Fonctionnel (UI)

1. **Naviguer**: `/vendor/products/new`
2. **Remplir**: Formulaire de base
3. **Cliquer**: "+ Ajouter une exception"
4. **Vérifier**: Formulaire s'affiche
5. **Remplir**: Pays, Classe, Taux
6. **Cliquer**: "+ Ajouter une autre"
7. **Soumettre**: Vérifier sauvegarde

### Validations à Tester

✅ Ajouter plusieurs exceptions
✅ Modifier exception existante
✅ Supprimer exception
✅ Désactiver/Réactiver exception
✅ Taux invalide (< 0 ou > 100)
✅ Taux manquant
✅ Pays manquant
✅ Classe invalide

## 📊 Structure de Données

### À l'Affichage (Édition)

```php
Product {
    id: 1,
    name: "NovaBook Q4",
    taxClass: "STANDARD",
    taxZone: TaxZone { id: 1, name: "EU" },
    productVatRates: Collection [
        ProductVatRate {
            id: 101,
            countryCode: "FR",
            taxClass: "REDUCED",
            rate: 5.5,
            active: true,
            product: Product {id: 1}
        },
        ProductVatRate {
            id: 102,
            countryCode: "DE",
            taxClass: "STANDARD",
            rate: 19.0,
            active: true,
            product: Product {id: 1}
        }
    ]
}
```

### À la Soumission (Form)

```php
[
    'name' => 'NovaBook Q4',
    'taxClass' => 'STANDARD',
    'taxZone' => 1,
    'productVatRates' => [
        [
            'countryCode' => 'FR',
            'taxClass' => 'REDUCED',
            'rate' => '5.5',
            'active' => true,
        ],
        [
            'countryCode' => 'DE',
            'taxClass' => 'STANDARD',
            'rate' => '19.0',
            'active' => true,
        ]
    ]
]
```

### En Base de Données

```sql
-- product table
INSERT INTO product (id, name, tax_class, tax_zone_id, ...)
VALUES (1, 'NovaBook Q4', 'STANDARD', 1, ...);

-- product_vat_rate table
INSERT INTO product_vat_rate (id, product_id, country_code, tax_class, rate, active, ...)
VALUES 
    (101, 1, 'FR', 'REDUCED', 5.5, true, ...),
    (102, 1, 'DE', 'STANDARD', 19.0, true, ...);
```

## 🛠️ Troubleshooting

### Problème: Les exceptions ne s'affichent pas

**Cause**: La collection n'est pas hydratée
**Solution**: 
```php
// Dans le contrôleur de chargement
$product = $productRepository->find($id);
// S'assurer que productVatRates est chargé
$product->getProductVatRates(); // Force l'hydratation
return $this->render('...', ['form' => $form]);
```

### Problème: Le bouton "Ajouter" ne fonctionne pas

**Cause**: JavaScript `setupVatRatesCollection()` n'a pas été exécuté
**Solution**:
```javascript
// Vérifier dans la console
console.log(document.querySelector('.vat-rates-collection'));
// Appeler manuellement
setupVatRatesCollection();
```

### Problème: Erreur "Une entité avec ce pays existe déjà"

**Cause**: Constraint UNIQUE(product_id, country_code) violée
**Solution**: 
- Vérifier qu'il n'y a pas deux exceptions pour le même pays
- Modifier l'exception existante au lieu d'en ajouter une nouvelle

### Problème: Exceptions supprimées malgré orphanRemoval: true

**Cause**: `by_reference: false` manquant dans CollectionType
**Solution**: Vérifié ✅ (déjà inclus)

## 📈 Performance

### Optimisations Appliquées

✅ **Indexes**: `product_id`, `country_code`, `shop_id`
✅ **Lazy Loading**: CollectionType décharge seulement si nécessaire
✅ **Cascade**: Délégué à Doctrine pour performances
✅ **Validation**: Côté client + côté serveur

### Requêtes Générées

```sql
-- Charger un produit avec exceptions
SELECT p.* FROM product p
LEFT JOIN product_vat_rate pvr ON pvr.product_id = p.id
WHERE p.id = 1;

-- Ajouter exception (1 requête)
INSERT INTO product_vat_rate (...) VALUES (...);

-- Supprimer exception (1 requête)
DELETE FROM product_vat_rate WHERE id = 101;
```

## 🚀 Déploiement

### Checklist

- ✅ Migration Doctrine exécutée (`php bin/console doctrine:migrations:migrate`)
- ✅ Cache Symfony vidé (`php bin/console cache:clear`)
- ✅ Assets compilés (s'il y a du CSS/JS)
- ✅ Base de données sauvegardée (backup avant migration)
- ✅ Tests passent (`php bin/phpunit`)

### Rollback (Si nécessaire)

```bash
# Annuler la migration
php bin/console doctrine:migrations:execute --down Version20260201154314

# Code revient automatiquement avec git
git checkout HEAD~1 src/Form/Vendor/ProductType.php
git checkout HEAD~1 templates/vendor/product/form.html.twig
```

## 📚 Ressources Connexes

- **Entity**: [src/Entity/ProductVatRate.php](../src/Entity/ProductVatRate.php)
- **Form Type**: [src/Form/Vendor/ProductVatRateType.php](../src/Form/Vendor/ProductVatRateType.php)
- **Repository**: [src/Repository/ProductVatRateRepository.php](../src/Repository/ProductVatRateRepository.php)
- **Service**: [src/Service/VatResolutionService.php](../src/Service/VatResolutionService.php)
- **Admin CRUD**: [src/Controller/Admin/ProductVatRateCrudController.php](../src/Controller/Admin/ProductVatRateCrudController.php)
- **Documentation**: [docs/vat-management.md](./vat-management.md)
