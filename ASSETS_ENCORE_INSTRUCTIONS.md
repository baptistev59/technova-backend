Objectif

Préparer l'intégration de Webpack Encore pour builder les assets JS/CSS.

Ce que j'ai déjà ajouté

- `webpack.config.js` à la racine
- `assets/js/app.js` (entrée principale qui importe `product-form` et Tailwind)
- `assets/js/product-form.js` (module ES existant)
- `.babelrc` pour la compilation
- `package.json` mis à jour avec les dépendances et scripts (ajout d'`encore`)

Étapes manuelles à exécuter localement

1) Installer les dépendances npm/yarn

```bash
# avec npm
npm install

# ou avec yarn
yarn install
```

2) Installer le bundle Symfony (si pas déjà présent)

```bash
composer require symfony/webpack-encore-bundle --dev
```

3) Builder en mode développement (watch)

```bash
# watch + rebuild (dev)
npm run dev
# ou
yarn dev
```

4) Builder pour la production

```bash
npm run build
# ou
yarn build
```

5) Intégrer les tags dans Twig

Lorsque le bundle est installé et que tu as exécuté `npm run build` (ou `dev`), remplace dans `templates/base.html.twig` les inclusions manuelles par les helpers Encore :

```twig
{# dans le <head> #}
{{ encore_entry_link_tags('app') }}

{# avant la fin de body, bloc javascripts #}
{{ encore_entry_script_tags('app') }}
```

Vérifications post-build

- Confirmer que `public/build/` contient les fichiers générés.
- Charger la page d'édition produit et vérifier la console pour s'assurer que les erreurs précédentes ont disparu (slugEditor, promoPricing, etc.).
- Tester le workflow Turbo (navigation, remplacements de body) pour valider que les factories Alpine restent accessibles.

Notes et recommandations

- J'ai conservé une version ES module non-bundlée pour tests rapides. La migration complète doit utiliser Encore en prod pour minification, versioning et sourcemaps.
- Préférer une seule entrée `app` qui regroupe les modules partagés (product-form, swiper, etc.) pour réduire les requêtes réseau.
- Pour HMR (hot reload), utiliser `encore dev --watch --hot` et configurer correctement le proxy si nécessaire.

Pièges courants

- Appeler `encore_entry_*` sans installer `symfony/webpack-encore-bundle` provoquera une erreur Twig : installe d'abord le bundle.
- Ne pas oublier d'ajouter `public/build/` à `.gitignore` et de s'assurer que les fichiers buildés sont publiés par le pipeline de déploiement.
- Si tu continues à utiliser `importmap` pour certains modules, évite la duplication (ne charge pas la même librairie via importmap et encore en même temps).

Prochaine étape recommandée

1. Tu lances les commandes d'installation localement (`npm install` + `composer require ...`).
2. Tu exécutes `npm run dev` puis tu testes l'application.
3. Si tout est valide, remplace définitivement les inclusions Twig par `encore_entry_*` (déjà prêt dans `templates/base.html.twig`).

Si tu veux, je peux aussi ajouter un petit script de validation (script PHP ou JS) qui vérifie la présence des factories côté client après build.
