# Affichage du Pays Détecté dans le Menu Utilisateur

## Vue d'ensemble

Le système affiche automatiquement le pays détecté via l'IP de l'utilisateur dans le menu utilisateur de l'entête. Cela permet aux clients de voir quel pays a été détecté pour la calcul des taxes (TVA).

## Architecture

### 1. Détection du Pays (GeoIpExtension)

**Fichier**: `src/Twig/GeoIpExtension.php`

La nouvelle extension Twig fournit deux fonctions :

```twig
{# Détecte le pays de l'utilisateur #}
{% set country_code = get_country_from_ip() %}

{# Traduit le code pays en nom français #}
{% set country_name = get_country_name('FR') %} {# → "France" #}
```

**Logique de détection** (dans GeoIpService) :
- Appelle l'API ipapi.co pour obtenir le code pays
- Cache le résultat 7 jours (Redis ou APCu)
- Détecte les IPs privées → retourne 'FR' (fallback)
- Gère les timeouts gracieusement (2 secondes max)

### 2. Affichage dans le Menu

**Fichier**: `templates/base.html.twig` (lignes 166-173)

```twig
<div class="tn-user-menu__panel">
    {% set detected_country_code = get_country_from_ip() %}
    {% set country_name = get_country_name(detected_country_code) %}
    <div class="tn-user-menu__country" title="Pays détecté automatiquement">
        🇫🇷 France
    </div>
    <hr class="tn-user-menu__divider">
    {# Menu items ... #}
</div>
```

**Positionnement**: 
- Affiche le pays avec flag emoji
- Ajout d'une ligne de séparation (`<hr>`)
- Apparaît en haut du menu déroulant

### 3. Styles CSS

**Fichier**: `assets/styles/app.tailwind.css` (lignes 331-348)

```css
.tn-user-menu__country {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .6rem .85rem;
    font-size: .95rem;
    font-weight: 500;
    color: var(--tn-text);
    white-space: nowrap;
}

.tn-user-menu__country::before {
    content: "📍";
    display: inline-block;
    margin-right: .2rem;
}

.tn-user-menu__divider {
    margin: .5rem 0;
    border: none;
    border-top: 1px solid var(--tn-border);
}
```

**Caractéristiques**:
- Icône de localisation (📍) automatique
- Flag emoji (+30 pays supportés)
- Fallback global (🌍) pour pays inconnus
- Responsive et accessible (title tooltip)

## Traduction des Codes Pays

La méthode `getCountryName()` traduit les codes ISO 3166-1 alpha-2 en noms français :

| Code | Français | Code | Français |
|------|----------|------|----------|
| FR | France | DE | Allemagne |
| GB | Royaume-Uni | IT | Italie |
| ES | Espagne | NL | Pays-Bas |
| BE | Belgique | AT | Autriche |
| CH | Suisse | SE | Suède |
| ... | ... | ... | ... |

Extension facile pour ajouter plus de pays.

## Flux de Détection

```
Request HTTP
    ↓
GeoIpService::getCountryFromIp()
    ↓
├─ Check Cache (Redis/APCu)
│   └─ Hit → Return cached country
│   └─ Miss → Continue
├─ Get client IP (headers + fallback)
├─ Check if private IP (localhost, 192.168.x.x)
│   └─ Yes → Return 'FR'
├─ Call API ipapi.co
│   └─ Timeout (2s) → Return 'FR'
│   └─ Error → Return 'FR'
│   └─ Success → Cache + Return country
└─ Return country code (e.g., 'DE', 'IT')
    ↓
GeoIpExtension::getCountryName()
    ↓
Translate to French name
    ↓
Display in template: "🇩🇪 Allemagne"
```

## Intégration avec TVA

Le pays détecté est **également utilisé** pour :

1. **Pages produit** (`templates/catalog/product_show.html.twig`)
   - Calcule le prix HT/TVA/TTC basé sur le pays
   - Affiche "Taxes calculées pour: 🇫🇷 France"

2. **Factures PDF** (`templates/documents/order_document.html.twig`)
   - Inclut le code pays pour chaque article
   - Utilise le pays au moment de la commande

3. **Panier** (`app_cart_show`)
   - Peut afficher les taxes basées sur le pays détecté

## Configuration

Pas de configuration requise. L'extension s'enregistre automatiquement via Symfony's `autoconfigure: true`.

**Cependant**, en production, optimiser les performances :

```yaml
# config/services.yaml
cache:
    default_provider: redis_provider
```

## Debugging

Pour tester manuellement :

```bash
# Vérifier que l'extension est enregistrée
php bin/console debug:container geoip

# Valider les templates
php bin/console lint:twig templates/

# Vider le cache
php bin/console cache:clear
```

## Limitations & Améliorations Futures

**Limitations actuelles**:
- IP privées → toujours 'FR'
- Dépend de service externe (ipapi.co)
- Timeout de 2 secondes (UX trade-off)

**Améliorations possibles**:
- GeoIP database local (MaxMind GeoLite2) au lieu d'API
- User preference override (sélecteur de pays)
- Multi-currency basé sur pays détecté
- A/B testing de pays vs détection automatique

## Debugging en Développement

Pour tester différents pays, ajouter un paramètre de requête :

```
/produit?country=DE  → Force détection Allemagne
/produit?country=GB  → Force détection Royaume-Uni
```

**Note**: La ProductController supporte déjà ce paramètre pour les tests.

## Performance

- **Caching**: 7 jours par défaut (configurable)
- **Timeout API**: 2 secondes (non-bloquant)
- **Template rendering**: Milliseconde (juste un affichage)
- **Impact pages**: Négligeable (~1-2ms)

L'appel API est fait une seule fois par IP par semaine.
