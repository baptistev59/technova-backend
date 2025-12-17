# TechNova – Alwaysdata Operations Log

Ce document synthétise toutes les manipulations réalisées pour préparer et déployer l’API sur Alwaysdata. Mets-le à jour à chaque action importante pour disposer d’un journal de bord lors de ta soutenance.

---

## 1. Nettoyage du dépôt (local)

- Suppression des clés JWT versionnées (`config/jwt/private.pem`, `config/jwt/public.pem`) et des scripts de debug `public/info.php`, `public/env.php`.
- Mise à jour de `.gitignore` pour exclure toutes les clés (`*.pem`, `*.key`, `*.crt`).
- Ajout d’un README bilingue dans `config/jwt/` rappelant d’exécuter `php bin/console lexik:jwt:generate-keypair`.

## 2. Configuration Alwaysdata – Variables d’environnement

- Valeurs courantes (via *Configuration → Variables d’environnement*) :
  - `APP_ENV=prod`, `APP_DEBUG=0`
  - `APP_SECRET` = généré via `openssl rand -hex 32`
  - `DATABASE_URL=postgresql://technova:******@postgresql-technova.alwaysdata.net:5432/technova_api?serverVersion=16&charset=utf8`
  - `JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem`
  - `JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem`
  - `JWT_PASSPHRASE` = identique à la passphrase utilisée lors du `lexik:jwt:generate-keypair`
  - `JWT_TOKEN_TTL=86400`
  - `CORS_ALLOW_ORIGIN=https://technova.alwaysdata.net`
  - `MAILER_DSN=smtp://technova@alwaysdata.net:<motdepasse>@smtp-technova.alwaysdata.net:587`
  - `MAILER_FROM="TechNova <technova@alwaysdata.net>"`
  - `STRIPE_SECRET_KEY=sk_live_xxx`
  - `STRIPE_PUBLISHABLE_KEY=pk_live_xxx`
  - `STRIPE_WEBHOOK_SECRET=whsec_xxx`
  - `MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0`
  - `DEFAULT_URI=https://technova.alwaysdata.net`

## 3. Installation sur l’hébergement via SSH

```bash
cd ~/www/technova-backend
composer install --no-dev --optimize-autoloader
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console app:create-admin --env=prod
```

## 4. Incidents rencontrés & résolutions

| Date | Commande | Symptôme | Résolution / Statut |
|------|----------|----------|---------------------|
| 29/11 | `composer install --no-dev --optimize-autoloader` | `Environment variable not found: "DEFAULT_URI"` + warning Doctrine `report_fields_where_declared` | Ajouter `DEFAULT_URI` dans les variables Alwaysdata (ex. `https://technova.alwaysdata.net`). Warning Doctrine simplement informatif. |
| 29/11 | `php bin/console lexik:jwt:generate-keypair` puis `--overwrite` | Message “Your keys already exist” lors de la première tentative | Résolu : exécution avec `--overwrite` a généré une nouvelle paire de clés sur Alwaysdata. |
| 29/11 | `php bin/console doctrine:migrations:migrate --env=prod` | `The version "latest" couldn't be reached, there are no registered migrations.` | Vérifier que les fichiers du dossier `migrations/` sont bien déployés (git pull) puis relancer la commande une fois `DEFAULT_URI` défini. |
| 29/11 | `composer install --no-dev --optimize-autoloader` | `ClassNotFoundError` sur `DebugBundle` lorsque `APP_ENV` resté à `dev` | Lancer toutes les commandes avec `APP_ENV=prod APP_DEBUG=0` dans la même ligne (`APP_ENV=prod APP_DEBUG=0 composer install ...`). |
| 29/11 | `php bin/console doctrine:migrations:migrate --env=prod` | Connexion PostgreSQL vers `127.0.0.1:5432` refusée | Les variables Alwaysdata ne sont pas injectées automatiquement en SSH. Exporter `DATABASE_URL` ou créer `.env.local` avant d’exécuter des commandes Doctrine. |
| 29/11 | Création de `.env.local` sur Alwaysdata | Besoin de forcer les variables pour toutes les commandes CLI | Fichier ajouté sur le serveur avec `APP_ENV=prod`, `APP_DEBUG=0`, `DATABASE_URL=...`, `DEFAULT_URI`, `MAILER_DSN`, `MAILER_FROM`, `MESSENGER_TRANSPORT_DSN`, etc. |
| 29/11 | `php bin/console doctrine:database:drop/create` | `permission denied to create database` | Alwaysdata interdit la création de base via CLI. Créer/supprimer la base depuis le manager puis relancer les commandes Symfony. |
| 29/11 | `php bin/console doctrine:migrations:migrate --env=prod` (après recréation via manager) | `relation "address" already exists` | Le schéma contenait encore les tables. Vider le schéma `public` depuis le manager (ou `DROP TABLE ... CASCADE`) avant de relancer la migration. Supprimer les migrations dupliquées côté serveur. |
| 05/12 | `php bin/console doctrine:migrations:migrate --no-interaction` | `Syntax error ... AUTO_INCREMENT` sur PostgreSQL lors de la création de `saved_cart` | Migration mise à jour pour utiliser `['autoincrement' => true]` (compatible Postgres). Après mise à jour du dépôt, relancer `composer dump-autoload` puis `doctrine:migrations:migrate`. |
| 05/12 | `php bin/console doctrine:fixtures:load --purge-with-truncate` | Violations de FK (`address` ↔ `user`) sur la prod démo | Décision : ne plus exécuter les fixtures en prod. Utiliser désormais `scripts/sync-demo-db.sh` pour écraser la base Alwaysdata à partir du dump local lorsque nécessaire. |
| 05/12 | `chmod -R g+rw var public/uploads` | `public/uploads` absent sur Alwaysdata | Créer le dossier (`mkdir -p public/uploads`) avant l’ajustement des droits. |
| 05/12 | `npm run dev` (asset-map) | `Could not find package "catalog-filters" / syntax error controllers.json` | Vérifier `assets/controllers.json` après ajout d’une entrée Stimulus ; la tâche échoue en cas d’erreur JSON ou d’entrée inexistante. |

## 5. Prochaines actions

1. **Variables** : déclarer `DEFAULT_URI=https://technova.alwaysdata.net` dans Alwaysdata, vérifier les valeurs d’`APP_SECRET` et `JWT_PASSPHRASE`.
2. **Installation** : relancer `composer install --no-dev --optimize-autoloader` (devrait passer une fois la variable ajoutée).
3. **Migrations** : le dossier `~/www/technova-backend/migrations/` est vide sur Alwaysdata (voir commande `ls migrations/`). Vérifier que les fichiers sont bien suivis en Git localement puis pousser/puller depuis le serveur (`git pull origin master`). Sur base neuve, nettoyer le schéma via le manager si des tables subsistent avant d’exécuter `php bin/console doctrine:migrations:migrate --no-interaction --env=prod`. Supprimer toute migration dupliquée directement sur le serveur.
4. **Provisioning** : lancer `php bin/console app:create-admin --env=prod`. ✅ (admin `admin@test.fr` créé depuis Alwaysdata, commande validée le 29/11).
5. **Tests** : valider `/api/test`, `/api/docs` et lancer le smoke-test Postman (`./scripts/postman-tests.sh --env prod.postman_environment.json`).  
6. **Ne pas lancer `doctrine:fixtures:load` en prod** : privilégier le script de synchronisation pour recharger les données de démo (section 7).
7. **Stripe** : exécuter `stripe listen --forward-to https://technova.alwaysdata.net/stripe/webhook` lors des tests ou configurer un webhook HTTPS dans le Dashboard (événement `checkout.session.completed`).

## 6. GitHub Actions – `deploy-alwaysdata.yml`

- Déclenchement : `push` sur `master`.  
- Étapes principales :
  1. `actions/checkout` récupère le code.
  2. `rsync` copie les sources vers Alwaysdata en excluant `.git`, `.github`, `var/`, `vendor/`.
  3. Via `appleboy/ssh-action`, le serveur exécute :

     ```bash
     composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
     php bin/console lexik:jwt:generate-keypair --no-interaction # si les clés n'existent pas
     php bin/console doctrine:migrations:migrate --no-interaction --env=prod
     php bin/console cache:clear --env=prod --no-warmup
     ```

- Secrets GitHub à renseigner :
  - `SSH_REMOTE_HOST`, `SSH_REMOTE_PORT`
  - `SSH_REMOTE_USER`
  - `SSH_PRIVATE_KEY` (clé privée autorisée sur Alwaysdata)
  - `DEPLOY_PATH` (ex. `/home/technova/www/technova-backend`)
- Les variables d’environnement restent gérées par Alwaysdata (plus de `.env.local.php` généré par le workflow).

> Pense à enrichir ce fichier dès que tu réalises une nouvelle opération (tests, corrections, incidents). Ce sera ta trace pour la soutenance.

## 7. Synchroniser la base Alwaysdata avec la base locale

- Script utilitaire : `scripts/sync-demo-db.sh`. Il :
  1. Dump la base locale (pg_dump).
  2. Transfère le fichier via `scp` sur le serveur.
  3. Restaure le dump dans `technova_api` (purge avec respect des FK).
  4. Supprime le dump temporaire (local + distant).
- Variables à définir (`export` ou modification du fichier) :
  - `LOCAL_DB_NAME`, `LOCAL_DB_USER`, `LOCAL_DB_HOST`
  - `REMOTE_SSH` (ex. `technova@ssh-technova.alwaysdata.net`), `REMOTE_DB_NAME`, `REMOTE_DB_USER`, `REMOTE_DB_HOST`
  - `REMOTE_DB_PASSWORD` (injecté dans `PGPASSWORD` lors de la restauration)
- Exemple :

  ```bash
  export REMOTE_DB_PASSWORD="***"
  bash scripts/sync-demo-db.sh
  ```

- Cette méthode remplace `doctrine:fixtures:load` en prod : la base est réalimentée ponctuellement à partir du dump local puis laissée “vivre” pour la démo.

## 8. Logs & monitoring

- Depuis décembre 2025, le handler Monolog prod (`config/packages/monolog.yaml`) écrit dans un `rotating_file` (`var/log/prod.log`) conservant les 30 derniers fichiers (`max_files: 30`).
- Les logs applicatifs restent accessibles via Alwaysdata (`~/logs/php-*.log`) mais cette rotation locale permet de télécharger rapidement un pack complet si besoin.
- Les endpoints `/api/test` et `/api/test-audit` sont utiles pour vérifier que l’écriture des logs fonctionne après un déploiement.
