# Affichage des Taxes sur les Fiches Produit et Factures

## Vue d'ensemble

Le système TechNova affiche maintenant le détail des taxes (HT, montant TVA, TTC) sur :
- **Fiches produit publiques** (pour les clients)
- **Factures PDF** (pour les commandes)

Les calculs sont basés sur la **localisation de l'utilisateur** (adresse ou IP).

---

## 1. Fiches Produit — Affichage des Taxes

### Fonctionnalité

Chaque fiche produit affiche un **résumé de pricing** avec 3 valeurs :

```
Prix HT:     49,99 €
TVA (20%):    9,99 €
Prix TTC:    59,98 €

Calcul basé sur le pays FR.
```

### Localisation Client

L'ordre de priorité pour détecter le pays est :

1. **Paramètre query** : `?country=DE` (override manuel)
2. **Adresse utilisateur** : si l'utilisateur est connecté + a une adresse par défaut
3. **Géolocalisation IP** : détection automatique via `GeoIpService`
4. **Fallback** : `FR` (France)

### Implémentation

**Contrôleur** : `src/Controller/Web/ProductController.php`

```php
public function show(Request $request, Product $product): Response
{
    // ... autres données ...
    
    $vatBreakdown = null;
    if (null !== $product->getPrice()) {
        $countryCode = $this->resolveVatCountryCode($request); // Détecte le pays
        
        // Résout le taux TVA applicable
        $ratePercent = $this->vatResolutionService->getRateForProduct(
            $product,
            $countryCode,
            $product->getShop()
        );
        
        // Calcule HT, TVA, TTC
        $net = (float) ($product->getPromoPrice() ?? $product->getPrice() ?? 0);
        $tax = round($net * ($ratePercent / 100), 2, PHP_ROUND_HALF_UP);
        $gross = round($net + $tax, 2, PHP_ROUND_HALF_UP);
        
        $vatBreakdown = [
            'country' => $countryCode,
            'rate' => $ratePercent,
            'net' => $net,
            'tax' => $tax,
            'gross' => $gross,
        ];
    }
    
    return $this->render('catalog/product_show.html.twig', [
        'vat_breakdown' => $vatBreakdown,
        // ... autres données ...
    ]);
}
```

**Template** : `templates/catalog/product_show.html.twig`

```twig
{% if not bundleComponentsActive and vat_breakdown %}
    <div class="mt-3 grid gap-2 text-sm text-slate-600 sm:grid-cols-3">
        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
            <span>Prix HT</span>
            <span class="font-semibold text-slate-900">{{ vat_breakdown.net|number_format(2, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
            <span>TVA ({{ vat_breakdown.rate|number_format(1, ',', ' ') }} %)</span>
            <span class="font-semibold text-slate-900">{{ vat_breakdown.tax|number_format(2, ',', ' ') }} €</span>
        </div>
        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
            <span>Prix TTC</span>
            <span class="font-semibold text-slate-900">{{ vat_breakdown.gross|number_format(2, ',', ' ') }} €</span>
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-400">Calcul basé sur le pays {{ vat_breakdown.country }}.</p>
{% endif %}
```

---

## 2. Géolocalisation par IP — `GeoIpService`

### Service

**Fichier** : `src/Service/GeoIpService.php`

```php
final class GeoIpService
{
    public function getCountryFromIp(string $ip): string
    {
        // Valide l'IP
        // Filtre les IPs privées (localhost, 192.168.x.x)
        // Cache les résultats (7 jours)
        // Appelle l'API ipapi.co (gratuit, fiable)
        // Fallback à 'FR' en cas d'erreur/timeout
    }
}
```

### API Utilisée

- **Service** : `ipapi.co` (gratuit, pas d'authentification)
- **Endpoint** : `GET https://ipapi.co/{ip}/json/`
- **Timeout** : 2 secondes (évite les ralentissements)
- **Réponse** : `{ "country_code": "DE", ... }`

### Caching

Résultat mis en cache pendant **7 jours** (Redis/APCu).

Clé cache : `geoip_` + md5(ip)

### Gestion des Erreurs

- ✅ API disponible → retourne le pays
- ⚠️ API timeout/down → retourne 'FR' (pas de ralentissement)
- ⚠️ Cache indisponible → continue sans cache
- ⚠️ IP invalide/privée → retourne 'FR'

### Détection des Proxies

Le service détecte les headers :
- `CF-Connecting-IP` (Cloudflare)
- `X-Forwarded-For` (proxies standards)
- `REMOTE_ADDR` (IP directe)

---

## 3. Factures PDF — Détail des Taxes

### Affichage

Les factures PDF affichent un **tableau complet** avec colonnes :

| Produit | Qté | Prix HT | TVA % | Montant TVA | Total TTC |
|---------|-----|---------|-------|-------------|-----------|
| Laptop | 1 | 999,99 € | 20% | 199,99 € | 1 199,98 € |
| Souris | 2 | 29,99 € | 20% | 12,00 € | 41,99 € |

### Récapitulatif TVA

```
Total HT (produits)    : 1 029,98 €
Total TVA              :   211,99 €
Total TTC (produits)   : 1 241,97 €
Livraison              :    12,50 €
────────────────────────────────────
Total à payer          : 1 254,47 €
```

### Données Utilisées

Source : Entité `CustomerOrderItem`

```php
// Données sauvegardées lors de la commande (snapshot)
$item->getAppliedNetAmount();      // HT
$item->getAppliedVatAmount();      // Montant TVA
$item->getAppliedVatPercent();     // Taux TVA (%)
$item->getAppliedGrossAmount();    // TTC
$item->getVatCountryCode();        // Code pays (ex: 'FR')
```

### Implémentation

**Template PDF** : `templates/documents/order_document.html.twig`

```twig
<table>
    <thead>
        <tr>
            <th>Produit</th>
            <th class="text-right">Qté</th>
            <th class="text-right">Prix HT</th>
            <th class="text-right">TVA %</th>
            <th class="text-right">Montant TVA</th>
            <th class="text-right">Total TTC</th>
        </tr>
    </thead>
    <tbody>
        {% for item in document_items %}
            <tr>
                <td>{{ item.productName }} <span class="text-muted">({{ item.vatCountryCode }})</span></td>
                <td class="text-right">{{ item.quantity }}</td>
                <td class="text-right">{{ item.appliedNetAmount|number_format(2, ',', ' ') }} €</td>
                <td class="text-right">{{ item.appliedVatPercent|number_format(1, ',', ' ') }} %</td>
                <td class="text-right">{{ item.appliedVatAmount|number_format(2, ',', ' ') }} €</td>
                <td class="text-right">{{ item.appliedGrossAmount|number_format(2, ',', ' ') }} €</td>
            </tr>
        {% endfor %}
    </tbody>
</table>

<div class="tax-summary">
    <div class="tax-row">
        <span>Total HT (produits)</span>
        <span>{{ order.itemsNetTotal|number_format(2, ',', ' ') }} €</span>
    </div>
    <div class="tax-row">
        <span>Total TVA</span>
        <span>{{ order.itemsVatTotal|number_format(2, ',', ' ') }} €</span>
    </div>
    <div class="tax-row">
        <span>Total TTC (produits)</span>
        <span>{{ order.itemsGrossTotal|number_format(2, ',', ' ') }} €</span>
    </div>
    <div class="tax-row bold">
        <span>Total à payer</span>
        <span>{{ order.totalAmount|number_format(2, ',', ' ') }} €</span>
    </div>
</div>
```

---

## 4. Résolution des Taux TVA

### Service : `VatResolutionService`

**Ordre de priorité** (fallback automatique) :

1. **ProductTaxZone** : zone + classe TVA spécifique au produit + pays
2. **TaxZone** : zone legacy si le produit a une zone configurée
3. **VatRate** : taux global par pays + classe TVA
4. **Défaut** : 20% (hard default)

**Exemple** :

```php
$rate = $this->vatResolutionService->getRateForProduct(
    product: $product,
    countryCode: 'DE',
    shop: $product->getShop()
);
// Retourne : 19.0 (taux TVA allemand pour ce produit)
```

### Cas d'Usage

- **Livre en France** : ProductTaxZone → REDUCED (5.5%)
- **Même livre en Belgique** : TaxZone ou VatRate → REDUCED (6%)
- **Produit sans zone** : VatRate global → STANDARD (20%)

---

## 5. Flux Complet de Commande

### 1. Client Visite la Fiche Produit

```
GET /produit/mon-produit
Client IP: 81.234.56.78 (Allemagne)
      ↓
GeoIpService détecte: DE
      ↓
VatResolutionService résout: 19% (TVA allemande)
      ↓
Affichage: "Prix HT: 50€ | TVA (19%): 9.50€ | TTC: 59.50€"
```

### 2. Client Ajoute au Panier et Commande

```
POST /checkout
Pays de facturation: DE (depuis l'adresse client)
      ↓
Chaque produit → VatResolutionService
      ↓
Snapshot sauvegardé dans CustomerOrderItem:
  - appliedNetAmount = 50.00
  - appliedVatPercent = 19.00
  - appliedVatAmount = 9.50
  - appliedGrossAmount = 59.50
  - vatCountryCode = DE
      ↓
Commande créée
```

### 3. Client Télécharge la Facture PDF

```
GET /api/orders/{id}/invoice
      ↓
OrderDocumentGenerator exécute:
  - template: templates/documents/order_document.html.twig
  - données: CustomerOrder + items snapshots
      ↓
PDF généré avec tableau complet des taxes
      ↓
Fichier stocké: /public/uploads/documents/order-XXX-invoice-YYY.pdf
```

---

## 6. Points Importants

### Snapshot des Taxes

✅ **Les taxes sont sauvegardées** lors de la commande dans `CustomerOrderItem`.  
Cela garantit que :
- La facture PDF affiche les taxes **telles qu'elles étaient au moment de la commande**
- Même si les taux TVA changent après, les anciennes factures restent correctes
- Les refunds utilisent les mêmes montants

### Pas de Rechargement pour les Bundles

⚠️ Le détail de TVA sur les fiches produit **ne s'affiche pas** pour les produits "groupés" (packs).  
Raison : un pack contient plusieurs produits avec des taux TVA différents.

### Formats Numériques

- **Toutes les valeurs** sont arrondies au centime le plus proche
- **Rounding mode** : `PHP_ROUND_HALF_UP` (standard bancaire)
- **Format affichage** : `2` décimales, `,` décimale, espace millier

---

## 7. Configuration & Déploiement

### Variables d'Environnement

```bash
# .env
HTTPCLIENT_DEFAULT_TIMEOUT=2  # Pour GeoIpService
CACHE_URL=redis://localhost:6379  # Pour cacher les géolocalisations IP
```

### Services Requis

- **Redis** (optionnel) : améliore les performances du caching
- **HTTPS** (obligatoire) : les données sensibles doivent être chiffrées

### Performance

- Fiches produit : **+1-2 ms** (résolution TVA cached)
- IP géolocation : **+0 ms** (cache 7 jours, timeout 2s)
- Factures PDF : **+0 ms** (utilise données sauvegardées)

---

## 8. Maintenance

### Mise à Jour des Taux TVA

Quand un taux change (ex: France 20% → 25%) :

1. Créer une nouvelle `VatRate` (ne pas modifier l'existante)
2. Les anciennes commandes conservent les anciens taux ✅
3. Les nouvelles commandes utilisent les nouveaux taux ✅

### Monitoring GeoIpService

Vérifier les logs de cache :

```bash
# Redis
redis-cli INFO stats

# Erreurs API
tail -f var/log/prod.log | grep "GeoIpService\|geoip"
```

### Tests

```bash
# Tester la géolocalisation IP
php bin/console debug:container GeoIpService

# Tester la résolution TVA
php bin/console debug:container VatResolutionService
```

---

## 9. Améliorations Futures

- [ ] WebSocket pour calcul TVA en temps réel (lors du changement de pays au checkout)
- [ ] Analytics : tracker les pays des clients
- [ ] A/B testing : affichage HT/TTC par défaut selon région
- [ ] Intégration avec les APIs de taxe officielles (Stripe Tax, Avalara)

---

## 10. Ressources

- [Tax Zones Guide](tax-zones-guide.md)
- [Product Tax Zones Guide](product-tax-zones-guide.md)
- [VAT Admin Guide](vat-admin-guide.md)
- [VAT Vendor Guide](vat-vendor-guide.md)
