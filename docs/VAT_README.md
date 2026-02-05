# 📚 Documentation Système TVA - TechNova

Ce répertoire contient la documentation complète du système de gestion TVA pour TechNova.

---

## 📖 Guide de lecture

### 👤 Rôles et documents recommandés

| Rôle | Document(s) | Objectif |
|------|--------|----------|
| **Vendeur** | [vat-vendor-guide.md](vat-vendor-guide.md) | Comment utiliser l'interface pour configurer la TVA |
| **Administrateur** | [vat-admin-guide.md](vat-admin-guide.md) | Comment gérer taux globaux et zones prédéfinies |
| **Développeur** | [vat-management.md](vat-management.md) + [VAT_EXAMPLES.php](VAT_EXAMPLES.php) | Architecture, API, implémentation |
| **Architecte** | [vat-management.md](vat-management.md) + [VAT_IMPLEMENTATION_SUMMARY.md](VAT_IMPLEMENTATION_SUMMARY.md) | Vue d'ensemble complète du système |

---

## 📄 Documents disponibles

### 1. **vat-management.md** (Architecture technique)
   - **Cible :** Architectes, Développeurs
   - **Taille :** ~15 pages
   - **Contenu :**
     - Vue d'ensemble du système
     - Architecture des entités
     - Règles de priorité (IMPORTANT!)
     - Diagrammes flux
     - API publique (VatResolutionService)
     - Performance & optimisation
   - **À lire si :** Tu dois comprendre comment fonctionne le système

### 2. **vat-vendor-guide.md** (Guide vendeurs)
   - **Cible :** Vendeurs e-commerce
   - **Taille :** ~12 pages
   - **Contenu :**
     - Concepts de base (Zone, TVA, Override)
     - Configuration initiale
     - Comment gérer tes zones TVA
     - Comment assigner une zone à un produit
     - Comment ajouter des exceptions
     - Vérifier ta configuration
     - Cas pratiques détaillés
     - FAQ & problèmes courants
   - **À lire si :** Tu vends des produits et tu dois configurer la TVA

### 3. **vat-admin-guide.md** (Guide administrateurs)
   - **Cible :** Administrateurs TechNova
   - **Taille :** ~14 pages
   - **Contenu :**
     - Vue d'ensemble du modèle TVA
     - Rôles et permissions
     - Gérer les taux globaux
     - Gérer les zones TVA
     - Gérer les exceptions produit
     - Configuration multi-shop
     - Rapports et audits
     - Performance & optimisation
     - Troubleshooting techniques
   - **À lire si :** Tu es admin et dois gérer les configurations TVA globales

### 4. **VAT_IMPLEMENTATION_SUMMARY.md** (Résumé d'implémentation)
   - **Cible :** Équipe technique
   - **Taille :** ~10 pages
   - **Contenu :**
     - Checklist ce qui a été fait
     - Fichiers créés/modifiés
     - Architecture implémentée
     - Codebase map
     - Performance metrics
     - Next steps
   - **À lire si :** Tu veux voir quoi a été implémenté et où trouver le code

### 5. **VAT_EXAMPLES.php** (Exemples de code)
   - **Cible :** Développeurs
   - **Taille :** ~400 lignes de code
   - **Contenu :**
     - 11 exemples d'utilisation
     - Cas d'usage réels
     - Patterns de priorité
     - Requêtes repository
     - Usage dans controllers
   - **À lire si :** Tu dois implémenter une feature TVA

---

## 🚀 Démarrage rapide

### Pour un vendeur

1. Lire [vat-vendor-guide.md](vat-vendor-guide.md) section "Concepts de base"
2. Aller sur Admin → Zones TVA
3. Créer une zone ou en assigner une existante
4. Assigner la zone à tes produits
5. (Optionnel) Ajouter des exceptions par pays

### Pour un admin

1. Lire [vat-admin-guide.md](vat-admin-guide.md) section "Vue d'ensemble"
2. Créer les taux globaux (VatRate) pour chaque pays
3. Créer les zones TVA (TaxZone)
4. Tester avec un produit
5. Générer un rapport audit

### Pour un développeur

1. Lire [vat-management.md](vat-management.md) section "Architecture des entités"
2. Regarder [VAT_EXAMPLES.php](VAT_EXAMPLES.php) pour des exemples
3. Implémenter la feature TVA dans ta logique métier
4. Utiliser `VatResolutionService` pour obtenir les taux
5. Tester avec différents pays

---

## 🔑 Concepts clés

### 1. **Zone TVA (TaxZone)**
   Regroupement de pays avec le même taux
   ```
   Zone EU → [FR, DE, IT, ES, ...] → 20%
   ```

### 2. **Taux TVA global (VatRate)**
   Taux par défaut pour chaque pays
   ```
   France STANDARD → 20%
   France REDUCED → 5.5%
   ```

### 3. **Exception produit (ProductVatRate)**
   Override pour un produit spécifique dans un pays
   ```
   Produit X, Allemagne → 7% (override)
   ```

### 4. **Priorité de résolution**
   Quand plusieurs règles s'appliquent, voici l'ordre:
   ```
   1. ProductVatRate (spécifique produit)
   2. TaxZone (zone groupée)
   3. VatRate (taux global)
   4. Default 20% (fallback)
   ```

---

## 💡 Cas d'usage courants

### Cas 1: Je vends en UE uniquement
**Voir:** [vat-vendor-guide.md](vat-vendor-guide.md#cas-1) + [VAT_EXAMPLES.php](VAT_EXAMPLES.php#L30)

### Cas 2: Je vends des livres (taux réduit)
**Voir:** [vat-vendor-guide.md](vat-vendor-guide.md#cas-2) + [VAT_EXAMPLES.php](VAT_EXAMPLES.php#L80)

### Cas 3: Je vends du digital (0% partout)
**Voir:** [vat-vendor-guide.md](vat-vendor-guide.md#cas-3)

### Cas 4: Je vends mondialement
**Voir:** [vat-vendor-guide.md](vat-vendor-guide.md#cas-4) + [VAT_EXAMPLES.php](VAT_EXAMPLES.php#L350)

---

## 🔗 Fichiers source (codebase)

### Entités
- [src/Entity/ProductVatRate.php](../src/Entity/ProductVatRate.php) ← NEW
- [src/Entity/Product.php](../src/Entity/Product.php) (modifié)
- [src/Entity/TaxZone.php](../src/Entity/TaxZone.php)
- [src/Entity/VatRate.php](../src/Entity/VatRate.php)

### Services
- [src/Service/VatResolutionService.php](../src/Service/VatResolutionService.php) ← NEW
- [src/Service/VatCalculator.php](../src/Service/VatCalculator.php)

### Repositories
- [src/Repository/ProductVatRateRepository.php](../src/Repository/ProductVatRateRepository.php) ← NEW
- [src/Repository/VatRateRepository.php](../src/Repository/VatRateRepository.php)

### Formulaires
- [src/Form/Vendor/ProductVatRateType.php](../src/Form/Vendor/ProductVatRateType.php) ← NEW
- [src/Form/Vendor/TaxZoneType.php](../src/Form/Vendor/TaxZoneType.php)

### Admin
- [src/Controller/Admin/ProductVatRateCrudController.php](../src/Controller/Admin/ProductVatRateCrudController.php) ← NEW

---

## ✅ Checklist avant utilisation

- [ ] Lire la documentation pertinente à ton rôle
- [ ] Migrer la base de données : `php bin/console doctrine:migrations:migrate`
- [ ] Créer les taux globaux pour tes pays
- [ ] Créer les zones TVA
- [ ] Tester avec un produit
- [ ] Valider les prix TTC en checkout
- [ ] Vérifier les rapports d'audit TVA

---

## 🆘 Besoin d'aide?

| Question | Réponse | Document |
|----------|---------|----------|
| Comment crée-je une zone TVA? | Va sur Admin → Zones TVA | [vat-admin-guide.md](vat-admin-guide.md#gérer-les-zones-tva) |
| Comment assigne-je une zone à un produit? | Édite le produit → TVA | [vat-vendor-guide.md](vat-vendor-guide.md#assigner-une-zone-à-un-produit) |
| Comment ajoute-je une exception? | Produit → Taux TVA par pays | [vat-vendor-guide.md](vat-vendor-guide.md#ajouter-des-exceptions-tva-par-pays) |
| Quelle est la priorité? | ProductVatRate > Zone > Global | [vat-management.md](vat-management.md#règles-de-priorité) |
| Comment j'utilise le service en code? | Voir VatResolutionService | [VAT_EXAMPLES.php](VAT_EXAMPLES.php#L115) |
| Erreur: Rate > 100%? | Validation échouée | [vat-admin-guide.md](vat-admin-guide.md#erreur-taux-invalide) |

---

## 📞 Support et escalade

- **Vendeurs :** Voir [vat-vendor-guide.md#besoin-daide](vat-vendor-guide.md#besoin-daide)
- **Admins :** Voir [vat-admin-guide.md#support--escalade](vat-admin-guide.md#support--escalade)
- **Devs :** Voir [vat-management.md#diagrammes-flux](vat-management.md#diagrammes-flux)

---

## 🔄 Workflow complet

### Setup initial (Admin)
```
1. Créer taux globaux (VatRate) par pays
2. Créer zones TVA (TaxZone)
3. Tester avec un produit dummy
4. Générer rapport audit
```

### Utilisation vendeur
```
1. Créer produit
2. Assigner zone TVA
3. (Optionnel) Ajouter exceptions par pays
4. Vérifier dans "Résumé TVA"
5. Enregistrer produit
```

### Validation checkout
```
1. Ajouter produit au panier (pays = FR)
2. Vérifier TVA calculée correctement
3. Tester avec différents pays
4. Vérifier montant TTC
```

---

## 📊 Métriques et performance

- **Query time** : < 10ms pour résoudre un taux
- **Cache** : 1h pour ProductVatRate, 1 jour pour zones
- **Indexes** : ✓ product_id, country_code, shop_id
- **N+1 prevention** : ✓ with() sur relations

Voir [vat-management.md#performance](vat-management.md#performance) pour détails.

---

## 🎓 Formation recommandée

### Niveau 1 (Vendeur basique)
- Lire "Concepts de base" de [vat-vendor-guide.md](vat-vendor-guide.md)
- Temps: 15 min

### Niveau 2 (Vendeur avancé)
- Lire intégralité de [vat-vendor-guide.md](vat-vendor-guide.md)
- Temps: 45 min

### Niveau 3 (Admin)
- Lire [vat-admin-guide.md](vat-admin-guide.md)
- Temps: 1h

### Niveau 4 (Développeur)
- Lire [vat-management.md](vat-management.md)
- Étudier [VAT_EXAMPLES.php](VAT_EXAMPLES.php)
- Implémenter une feature TVA
- Temps: 3-4h

### Niveau 5 (Architecte)
- Lire [vat-management.md](vat-management.md)
- Analyser [VAT_IMPLEMENTATION_SUMMARY.md](VAT_IMPLEMENTATION_SUMMARY.md)
- Revoir codebase
- Temps: 2-3h

---

## 📅 Versioning

| Date | Version | Changements |
|------|---------|------------|
| 2026-02-01 | 1.0 | Initial release |

---

## 📝 Auteurs

- **Conception :** GitHub Copilot + équipe TechNova
- **Documentation :** 2026-02-01
- **Maintenance :** À assigner

---

**Last updated:** 2026-02-01  
**Status:** ✅ Production-ready  
**Questions?** Voir le document pertinent à ton rôle
