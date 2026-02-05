# 👨‍💼 Guide complet - Gestion TVA pour vendeurs

Ce guide explique comment gérer les taux de TVA pour tes produits sur TechNova.

---

## Table des matières

1. [Concepts de base](#concepts)
2. [Configuration initiale](#config)
3. [Gérer tes zones TVA](#zones)
4. [Assigner une zone à un produit](#assigner)
5. [Ajouter des zones TVA par produit](#overrides)
6. [Vérifier ta configuration](#verifier)
7. [Cas pratiques](#cas)
8. [Problèmes courants](#problemes)

---

## 1. Concepts de base {#concepts}

### 🌍 Zone TVA
Un groupement de pays avec le même taux de TVA.

**Exemple :**
- Zone "Union Européenne" = France, Allemagne, Italie, Espagne, ... → 20%

### 🏷️ Classe TVA
Le type de produit pour le calcul TVA.

**Types** :
- **Standard** : Taux normal (ex: 20%)
- **Réduit** : Taux réduit (ex: 5.5%)
- **Zéro** : Pas de TVA (ex: livres)

### 🔁 Taux TVA
Le pourcentage appliqué au prix.

**Exemple :** 20% de TVA sur un produit à 100€ NET = 20€ de TVA = 120€ TTC

### 🎯 Zone TVA par produit
Une zone associée à un produit, qui détermine la classe TVA selon le pays.

**Exemple :**
- Zone UE → 20%
- **Zone DE_REDUCED** → 7% (zone spécifique)

---

## 2. Configuration initiale {#config}

### Première visite

1. Va sur **Espace Vendeur** → **Zones TVA**
2. Tu verras les zones pré-créées :
   - ✅ Union Européenne (20%)
   - ✅ UK & Irlande (20%/23%)
   - ✅ Suisse & Liechtenstein (7.7%)

3. Tu peux aussi **créer tes propres zones** si nécessaire

---

## 3. Gérer tes zones TVA {#zones}

### 📍 Voir tes zones

1. Va sur **Espace Vendeur** → **Zones TVA**
2. Tu vois une liste :

```
┌─────────────────────────────────────────┐
│ Zone                    Pays  Taux Actif│
├─────────────────────────────────────────┤
│ UE Standard             27    20%   ✅  │
│ UK & Irlande             2    20%   ✅  │
│ Suisse & Liechtenstein   2    7.7%  ✅  │
└─────────────────────────────────────────┘
```

### ➕ Créer une zone personnalisée

1. Clique sur **"+ Nouvelle zone"**
2. Remplis le formulaire :

```
Nom de la zone*       : "Amérique du Nord"
Description           : "États-Unis et Canada"
Pays applicables*     : [États-Unis, Canada]
Classe TVA*           : Standard
Taux TVA (%)*         : 0 (pas de TVA en douane)
Active                : ✅ (coché)
```

3. Clique **"Enregistrer"**

### ✏️ Modifier une zone

1. Clique sur le nom de la zone
2. Modifie les champs
3. Clique **"Mettre à jour"**

**⚠️ Attention :** Les changements s'appliquent à **tous les produits** utilisant cette zone!

### 🗑️ Supprimer une zone

1. Clique sur le menu de la zone
2. Clique **"Supprimer"**
3. **Important :** Les produits perdront leur zone, utiliseront les taux globaux

---

## 4. Assigner une zone à un produit {#assigner}

> **💡 Pour comprendre :** Une TaxZone = regroupement de pays avec même taux.  
> **Lire aussi :** [Guide Complet des TaxZone](tax-zones-guide.md) pour approfondir

### 🆕 En créant un produit

1. Va sur **Espace Vendeur** → **Mes produits**
2. Clique **"+ Créer un produit"**
3. Remplis les infos de base
4. Descends jusqu'à la section **"Pricing & TVA"**
5. Sélectionne :

```
Zone TVA : "Union Européenne"
Classe TVA : "Standard"
```

6. Clique **"Enregistrer"**

### ✏️ En éditant un produit existant

1. Va sur **Espace Vendeur** → **Mes produits**
2. Clique sur le produit
3. Descends jusqu'à **"Pricing & TVA"**
4. Modifie la zone TVA

```
Zone TVA : "Amérique du Nord"  (avant: "UE")
```

5. Clique **"Mettre à jour"**

### Résultat

- ✅ Tous les pays de la zone utiliseront le taux défini
- ✅ France, Allemagne, Italie → 20%
- ✅ USA, Canada → 0%

### 🎯 Pourquoi assigner une zone ?

**Avantages :**
- **Simplicité** : Un seul choix, plusieurs pays couverts
- **Maintenance** : Modifier la zone = tous les produits mis à jour
- **Fallback** : Si pas de ProductTaxZone, cette zone s'applique

**Quand utiliser :**
- ✅ Configuration simple (tous les produits même taux)
- ✅ Fallback sécurisé (au cas où)
- ✅ Pays groupés (ex: toute l'UE)

**Quand ne pas utiliser :**
- ❌ Besoin de taux différents par pays → utilise ProductTaxZone
- ❌ Produit très spécifique → ProductTaxZone plus flexible

---

## 5. Ajouter des zones TVA par produit {#overrides}

> **💡 Pour comprendre :** ProductTaxZone = association produit + zone avec classe spécifique.  
> **Lire aussi :** [Guide ProductTaxZone](product-tax-zones-guide.md) pour les détails complets

**Situation :** Tu veux une classe TVA différente selon le pays de livraison.

### Exemple : Allemagne avec taux réduit

**Contexte :**
- Zone UE → 20%
- Zone DE_REDUCED (DE uniquement) → **7% RÉDUIT**

### ✅ Comment faire

1. Va sur **Espace Vendeur** → **Mes produits**
2. Édite le produit
3. Descends à la section **"Zones TVA du produit"**
4. Clique **"+ Ajouter une zone TVA"**
5. Remplis :

```
Zone TVA* : DE_REDUCED
Classe TVA : Réduit
```

6. Clique **"Ajouter"**
7. Clique **"Enregistrer le produit"**

### Résultat

```
Zones du produit :
  ├─ UE → 20% ✓
  ├─ DE_REDUCED → 7% ✓
  └─ (les autres pays utilisent la zone UE)
```

### ➕ Ajouter plusieurs zones

```
Zones du produit :
├─ UE → 20% (STANDARD)
├─ DE_REDUCED → 7% (REDUCED)
└─ FR_ZERO → 0% (ZERO)
```

Chaque zone a sa propre ligne.

### 🔄 Modifier une zone

1. Clique sur la zone dans la liste
2. Modifie la classe TVA
3. Clique **"Mettre à jour"**

### 🗑️ Supprimer une zone

1. Clique sur le menu de la zone
2. Clique **"Supprimer"**
3. La zone TVA fallback reste applicable

---

## 6. Vérifier ta configuration {#verifier}

### 📊 Voir tous les taux pour un produit

1. Va sur **Espace Vendeur** → **Mes produits**
2. Clique sur un produit
3. Regarde la section **"Résumé TVA"** :

```
┌──────────────────────────────────────┐
│ Configuration TVA - Smartphone       │
├──────────────────────────────────────┤
│ Zones assignées : UE + DE_REDUCED    │
│                                      │
│ Taux par pays :                      │
│ ├─ France (FR) → 20%     [UE Zone]  │
│ ├─ Allemagne (DE) → 7%   [Zone DE]  │
│ ├─ Italie (IT) → 20%     [UE Zone]  │
│ ├─ Irlande (IE) → 23%    [Zone IE]  │
│ ├─ USA (US) → 20%        [Défaut]   │
│ └─ Chine (CN) → 20%      [Défaut]   │
└──────────────────────────────────────┘
```

### 🔍 Vérifier le prix TTC

**Exemple avec Smartphone à 100€ NET :**

```
France (20%)    : 100 + 20  = 120€ TTC ✓
Allemagne (7%)  : 100 + 7   = 107€ TTC ✓
```

### ⚠️ Signales d'alerte

| Signal | Signification | Action |
|--------|--|--|
| 🔴 Aucun taux trouvé | Pas de config TVA | Assigne une zone |
| 🟡 Taux 20% défaut | Utilise le défaut | Risqué! Crée une zone |
| ✅ Vert | Config OK | Rien à faire |

---

## 7. Cas pratiques {#cas}

### Cas 1️⃣ : Je vends en UE uniquement

**Configuration idéale :**

```
Produits
  └─ Zone : "UE Standard" (20%)
     └─ Résultat : France, DE, IT... → 20%
```

**Pas besoin de zones spécifiques!**

---

### Cas 2️⃣ : Je vends des livres (taux réduit en France)

**Configuration :**

```
Produits
  └─ Livre de Français
      ├─ Zone : "UE Standard"
      ├─ Zone : "FR_REDUCED"
      └─ Zone : "DE_REDUCED"
```

**Résultat :**
- France → 5.5% (zone FR_REDUCED)
- Allemagne → 7% (zone DE_REDUCED)
- Italie → 20% (zone)

---

### Cas 3️⃣ : Je vends du digital (0% partout, sauf zones locales)

**Configuration :**

```
Produits
  └─ Logiciel Pro
      ├─ Zone : "FR_STANDARD"
      ├─ Zone : "DE_STANDARD"
      └─ Zone : "ES_STANDARD"
```

**Résultat :**
- France, DE, ES → Taux zone
- USA → Défaut 20%

---

### Cas 4️⃣ : Vente mondiale avec zones

**Configuration :**

```
Produits
  └─ Gadget Global
      ├─ Zone : "UE Standard" (20%)
      ├─ Zone : "US_ZERO" (0%)
      ├─ Zone : "IE_STANDARD" (23%)
      └─ Zone : "CH_REDUCED" (7.7%)
```

**Résultat :**
- France, Allemagne, etc. → 20% (UE)
- USA → 0% (zone)
- Irlande → 23% (zone)
- Suisse → 7.7% (zone)
- Autres pays → 20% (défaut)

---

## 8. Problèmes courants {#problemes}

### ❌ "Je n'ai pas d'options TVA au choix"

**Cause :** Les zones pré-créées ne sont pas compatibles

**Solution :**
1. Va sur **Zones TVA**
2. Crée une **"Nouvelle zone"** personnalisée
3. Sélectionne-la dans le produit

---

### ❌ "J'ai modifié la zone, mais le prix ne change pas"

**Cause :** Les prix TTC sont calculés au checkout, pas instantanément

**Solution :**
1. Attends quelques secondes
2. Recharge la page
3. Va vérifier le "Résumé TVA" du produit

---

### ❌ "J'ai supprimé une zone, mes produits n'ont plus de TVA!"

**Cause :** Les produits de cette zone ont perdu leur référence

**Solution :**
1. Va sur **Mes produits**
2. Édite chaque produit
3. Réassigne une zone TVA
4. Clique "Enregistrer"

---

### ❌ "Je ne vois pas mes zones TVA sauvegardées"

**Cause :** Elles n'ont peut-être pas été enregistrées

**Solution :**
1. Va sur le produit
2. Scroll jusqu'à **"Zones TVA du produit"**
3. Si vide, clique **"+ Ajouter une zone TVA"**
4. Clique **"Enregistrer le produit"** en bas

---

### ❌ "Un client me dit qu'il paye pas la bonne TVA"

**Diagnostic :**

1. Vérifie le résumé TVA du produit
2. Vérifie le pays du client
3. Cherche une zone correspondante

```
Client en Allemagne
Produit UE (20%) + Zone DE_REDUCED (7%)
  → Doit payer 7% ✓
```

Si c'est incorrect, contacte le support!

---

## 🎯 Résumé rapide

| Action | Où | Comment |
|--------|--|----|
| Créer zone | Zones TVA | + Nouvelle zone |
| Assigner zone | Produit | Pricing & TVA |
| Ajouter zone | Produit | Zones TVA du produit |
| Voir tous les taux | Produit | Résumé TVA |
| Modifier zone | Produit | Clique zone |

---

## 📞 Besoin d'aide?

- **TVA confuse :** Vois la section [Concepts de base](#concepts)
- **Produit sans taux :** Va sur **Zones TVA** et crée une zone
- **Bug ou erreur :** Contacte le support TechNova

---

**Dernière mise à jour :** 2026-02-01
