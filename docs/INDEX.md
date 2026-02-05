# 📚 Index Documentation TechNova Marketplace

## 🎯 Documentation par Sujet

### 🏪 Gestion Boutique & Vendeur

| Document | Audience | Contenu |
|----------|----------|---------|
| [vat-vendor-guide.md](vat-vendor-guide.md) | Vendeurs | Guide complet TVA en interface vendeur |
| [vat-admin-guide.md](vat-admin-guide.md) | Administrateurs | Configuration TVA globale |
| [DEPLOYMENT_ALWAYS_DATA.md](DEPLOYMENT_ALWAYS_DATA.md) | Devs/Ops | Déploiement sur Alwaysdata |

### 💰 Gestion TVA (Complète)

| Document | Audience | Sujet |
|----------|----------|-------|
| [REFACTOR_TAXZONE_REMOVAL.md](REFACTOR_TAXZONE_REMOVAL.md) | **🆕 Devs** | Refactorisation TaxZone (v2.0) |
| [product-tax-zones-guide.md](product-tax-zones-guide.md) | Devs/Archi | ProductTaxZone (architecture) |
| [VAT_IMPLEMENTATION_SUMMARY.md](VAT_IMPLEMENTATION_SUMMARY.md) | Archi | Vue d'ensemble système TVA |
| [vat-management.md](vat-management.md) | Devs | Architecture TVA complète |
| ~~[tax-zones-guide.md](tax-zones-guide.md)~~ | **❌ OBSOLÈTE** | TaxZone supprimée (fév 2026) |

### 📱 Gestion Produits

| Document | Contenu |
|----------|---------|
| [catalogue-entities.md](catalogue-entities.md) | Structure produit, variantes, attributs |
| [DEPLOYMENT_ALWAYSDATA_SETUP.md](DEPLOYMENT_ALWAYSDATA_SETUP.md) | Fictures et données initiales |

### 🔐 Authentification & Sécurité

| Document | Contenu |
|----------|---------|
| [email-verification.md](email-verification.md) | Confirmation email, OTP, tokens |
| [password-reset.md](password-reset.md) | Réinitialisation mot de passe |
| [security-backlog.md](security-backlog.md) | Checklist sécurité |
| [security-enhancements.md](security-enhancements.md) | Améliorations sécurité |

### 📊 API & Audit

| Document | Contenu |
|----------|---------|
| [api-audit.md](api-audit.md) | Audit endpoints attendus vs exposés |
| [api-endpoints-audit.md](api-endpoints-audit.md) | Détail status par endpoint |
| [vendor-api-endpoints.md](vendor-api-endpoints.md) | API spécifique aux vendeurs |

### 🎨 Design & Frontend

| Document | Contenu |
|----------|---------|
| [design-system.md](design-system.md) | Système de design (couleurs, typo, etc) |
| [user-menu-country-display.md](user-menu-country-display.md) | Affichage pays en menu utilisateur |
| [invoice-and-tax-display.md](invoice-and-tax-display.md) | Affichage taxes sur factures |

### 🛍️ Fonctionnalités Métier

| Document | Contenu |
|----------|---------|
| [wishlists-implementation.md](wishlists-implementation.md) | Listes de souhaits (favoris) |
| [marketing-roadmap.md](marketing-roadmap.md) | Roadmap marketing |
| [product-roadmap.md](product-roadmap.md) | Roadmap produit |

### 🗂️ Documentation Maintenance

| Document | Contenu |
|----------|---------|
| [DOCUMENTATION_UPDATE_NOTES.md](DOCUMENTATION_UPDATE_NOTES.md) | Suivi des mises à jour |
| [DOCUMENTATION_SUMMARY.md](DOCUMENTATION_SUMMARY.md) | Résumé mise à jour TVA |
| **CE FICHIER** | Index documentation |

---

## 🔄 Récemment Mis à Jour (Février 2026)

### Refactorisation TaxZone ✨

**Quoi?** Suppression entité TaxZone (couche indirecte redondante)

**Où?** 
- ✅ Code source (9 fichiers modifiés, 5 supprimés)
- ✅ Base de données (migration Version20260205034344)
- ✅ Documentation (4 fichiers documentaires)

**Résultat?**
- Architecture TVA simplifiée (2 niveaux au lieu de 3)
- Sélection intelligente des pays dans formulaire
- Performance JSON queries améliorée

**À lire:**
1. [REFACTOR_TAXZONE_REMOVAL.md](REFACTOR_TAXZONE_REMOVAL.md) – Détails techniques
2. [product-tax-zones-guide.md](product-tax-zones-guide.md) – Architecture actualislée
3. [DOCUMENTATION_SUMMARY.md](DOCUMENTATION_SUMMARY.md) – Résumé complet

---

## 🚀 Démarrage Rapide

### Je suis...

#### 💼 Vendeur
→ **Lire:** [vat-vendor-guide.md](vat-vendor-guide.md) + [product-tax-zones-guide.md](product-tax-zones-guide.md#cas-dusage-pratiques)

#### 👨‍💻 Développeur
→ **Lire:** [REFACTOR_TAXZONE_REMOVAL.md](REFACTOR_TAXZONE_REMOVAL.md) + [product-tax-zones-guide.md](product-tax-zones-guide.md#implémentation-technique)

#### 🏗️ Architecte
→ **Lire:** [VAT_IMPLEMENTATION_SUMMARY.md](VAT_IMPLEMENTATION_SUMMARY.md) + [vat-management.md](vat-management.md)

#### ⚙️ Administrateur
→ **Lire:** [vat-admin-guide.md](vat-admin-guide.md) + [security-backlog.md](security-backlog.md)

#### 🚀 DevOps/Déploiement
→ **Lire:** [DEPLOYMENT_ALWAYSDATA_SETUP.md](DEPLOYMENT_ALWAYSDATA_SETUP.md) + [DEPLOYMENT_ALWAYS_DATA.md](DEPLOYMENT_ALWAYS_DATA.md)

---

## 📊 Statistiques Documentation

| Métrique | Valeur |
|----------|--------|
| **Total documents** | 27 |
| **Dernière update** | 5 février 2026 |
| **Pages TVA** | 5 (1 nouveau) |
| **Historiques** | Voir [CHANGELOG_SESSION_2026-01-31.md](../CHANGELOG_SESSION_2026-01-31.md) |

---

## ✨ Points Forts Documentation

✅ **Architecture claire** – Tous les niveaux expliqués (vendeur → dev → archi)  
✅ **Cas d'usage réels** – Exemples concrets avec données  
✅ **Schémas visuels** – Diagrammes Mermaid pour comprendre flux  
✅ **Code snippets** – Exemples PHP/SQL/API prêts à copier  
✅ **Migration guide** – Procédure déploiement step-by-step  
✅ **Historique** – Versions documentées et archivées  

---

## 🔗 Navigation Rapide

**Formulaires & UI?** → [vat-vendor-guide.md](vat-vendor-guide.md)  
**Architecture système?** → [VAT_IMPLEMENTATION_SUMMARY.md](VAT_IMPLEMENTATION_SUMMARY.md)  
**Refactorisation TaxZone?** → [REFACTOR_TAXZONE_REMOVAL.md](REFACTOR_TAXZONE_REMOVAL.md)  
**Endpoints API?** → [api-endpoints-audit.md](api-endpoints-audit.md)  
**Sécurité?** → [security-enhancements.md](security-enhancements.md)  
**Deploy en prod?** → [DEPLOYMENT_ALWAYS_DATA.md](DEPLOYMENT_ALWAYS_DATA.md)  

---

## 📝 Notes

- Certains documents restent en cours d'actualisation (voir [DOCUMENTATION_UPDATE_NOTES.md](DOCUMENTATION_UPDATE_NOTES.md))
- Le document [tax-zones-guide.md](tax-zones-guide.md) est **OBSOLÈTE** depuis février 2026 (TaxZone supprimée)
- Pour les questions non couvertes, créer une issue ou une PR

---

**Dernière mise à jour:** 5 février 2026  
**Responsable:** GitHub Copilot  
**Status:** ✅ Actuel et complet
