# TechNova – Design System (extrait)

## Identité visuelle

- **Logo** : `docs/maquettes.pdf` page 1
- **Accroche** : « Explorez le monde de l’innovation technologique »

## Palette principale

| Couleur | Usage |
|---------|-------|
| `#1E88E5` | Boutons primaires, éléments interactifs |
| `#64B5F6` | Survols / variantes secondaires |
| `#F5F6FA` | Fond des sections |
| `#212121` | Texte principal |
| `#999999` | Sous-titres / métadonnées |

## Typographies (Inter)

- **H1** : 48px bold
- **H2** : 24px semi-bold/bold
- **Texte normal** : 16px regular
- **Texte small** : 14px regular

## Composants clés

- **Navigation** :
  - Logo + liens Produits / Vendeurs / Aide à gauche.
  - Champ de recherche pilulé (`rounded-full`, largeur contrôlée via Tailwind) avec icône loupe centrée verticalement.
  - Menu profil à droite : avatar circulaire (48 px), nom + chevron, dropdown extérieur à l’en-tête contenant « Mon profil », « Mon panier », « Déconnexion ».
  - Bouton « Panier » supprimé de la barre principale (accès via le menu profil).
- **Hero** : bloc texte + visuel, CTA « Parcourir les produits ».
- **Filtres catalogue** : champ recherche + listes déroulantes catégorie/marque triées, déclenchement automatique sur changement ou touche Entrée.
- **Cartes catégories** : icône + titre, fond `#F5F6FA`, bordure bleu clair.
- **Cartes produits** : visuel (SVG), titre, vendeur, prix, bouton « Ajouter au panier ».
- **Cartes panier / checkout** :
  - Miniature 88×88 px (ou 48×48 dans l’e-mail), bloc texte à droite, puces « Quantité », « Prix unitaire », totaux.
  - Cartes adresse + récap total arrondies (rayon 32px) avec gradient léger.
- **Formulaires profil** : inputs pilulés, champs séparés « Informations personnelles » / « Adresse », zone avatar cliquable avec prévisualisation instantanée.
- **Footer** : 4 colonnes (« À propos », « Aide », « Légal », réseaux sociaux) sur fond sombre.

## Pages

- **Accueil** : hero, catégories populaires, produits populaires, bandeau promotion, footer.
- **Catalogue** : filtres (recherche, catégorie, marque, tri) avec validation automatique, grille responsive.
- **Fiche produit** : galerie, description, caractéristiques, avis, produits similaires, bannière de confirmation quand un produit est ajouté au panier (CTA « Continuer mes achats » / « Voir le panier »).
- **Panier** : résumé interactif (quantité + recalcul en temps réel, miniatures), CTA « Continuer mes achats ».
- **Checkout `/commande`** : récapitulatif stylé (blocs arrondis) + adresse + totals + boutons primaires/secondaires.
- **Historique de commandes** : cartes verticales affichant référence, statut, date, miniatures.
- **Profil** : formulaire en deux colonnes + bloc avatar clickable, switch newsletter, bouton « Supprimer mon compte » ouvrant un modal de confirmation.

> Toutes les captures sont disponibles dans `docs/maquettes.pdf`.
