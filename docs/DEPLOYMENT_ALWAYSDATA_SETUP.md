# Déploiement TechNova sur AlwaysData

## Architecture

- **Runtime** : PHP-FPM (variables accessibles)
- **CLI/SSH** : Shell (variables NON accessibles)
- **Configuration** : Variables définies dans l'interface AlwaysData

## Variables d'environnement requises

Toutes ces variables doivent être définies dans **Configuration → Variables d'environnement** sur AlwaysData :

```
APP_ENV=prod
APP_DEBUG=
APP_SECRET=<clé aléatoire de 64 caractères>
DATABASE_URL=postgresql://technova:PASSWORD@postgresql-technova.alwaysdata.net:5432/technova_api?serverVersion=16&charset=utf8
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<passphrase utilisée lors de la génération des clés>
JWT_TOKEN_TTL=86400
CORS_ALLOW_ORIGIN=https://technova.alwaysdata.net
MAILER_DSN=smtp://technova@alwaysdata.net:PASSWORD@smtp-technova.alwaysdata.net:587
MAILER_FROM="TechNova <technova@alwaysdata.net>"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
DEFAULT_URI=https://technova.alwaysdata.net
STRIPE_SECRET_KEY=sk_live_xxx
STRIPE_PUBLISHABLE_KEY=pk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

## Déploiement manuel via SSH

```bash
ssh technova@ssh1.alwaysdata.net
cd ~/www/technova-backend

# 1. Mettre à jour le code
git pull origin master

# 2. Installer les dépendances
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# 3. Générer les clés JWT (si absentes)
if [ ! -f config/jwt/private.pem ] || [ ! -f config/jwt/public.pem ]; then
  export JWT_SECRET_KEY='%kernel.project_dir%/config/jwt/private.pem'
  export JWT_PUBLIC_KEY='%kernel.project_dir%/config/jwt/public.pem'
  export JWT_PASSPHRASE='<votre_passphrase>'
  php bin/console lexik:jwt:generate-keypair --no-interaction
fi

# 4. Exécuter les migrations
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# 5. Vider et régénérer le cache
rm -rf var/cache/*
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod --no-debug

# 6. Compiler les assets
php bin/console asset-map:compile --env=prod
```

## Déploiement automatisé (GitHub Actions)

Le workflow `.github/workflows/deploy-alwaysdata.yml` exécute automatiquement :
1. rsync des sources (excluant vendor/, var/, .git/, etc.)
2. composer install
3. Génération des clés JWT si nécessaire
4. Migrations de base de données
5. Cache warming
6. Compilation des assets

### Secrets GitHub requis

- `SSH_REMOTE_HOST` : ssh1.alwaysdata.net
- `SSH_REMOTE_PORT` : Port SSH (22 généralement)
- `SSH_REMOTE_USER` : technova
- `SSH_PRIVATE_KEY` : Clé SSH privée autorisée sur AlwaysData
- `DEPLOY_PATH` : /home/technova/www/technova-backend

## Particularités AlwaysData

### ✅ Ce qui fonctionne
- Variables définies dans l'interface → Accessibles en runtime PHP
- Symfony lit automatiquement `$_SERVER` et `$_ENV`

### ❌ Ce qui NE fonctionne PAS
- `composer dump-env prod` en CLI SSH (les variables ne sont pas exportées)
- `.env.local` en production (pas sécurisé et non persistant)

### ✅ Solution
- Pas de `.env.local.php`
- Pas de `composer dump-env prod`
- Les variables sont lues directement par Symfony via `autoload_runtime.php`

## Diagnostic

Pour vérifier que tout est bien configuré :

```bash
# En SSH
php bin/console about --env=prod
# Doit afficher : Debug false ✅

# En web (ajouter temporairement dans un contrôleur)
dd($_ENV['JWT_SECRET_KEY'] ?? null);
# Doit afficher le chemin de la clé privée
```

## Troubleshooting

### Erreur : "Environment variable not found: JWT_SECRET_KEY"

**Cause** : Les variables JWT ne sont pas définies dans AlwaysData

**Solution** : Ajouter les variables manquantes dans l'interface AlwaysData

### Erreur 500 avec assets manquants

**Cause** : `asset-map:compile` n'a pas été exécuté

**Solution** : 
```bash
php bin/console asset-map:compile --env=prod
```

### Cache obsolète après déploiement

**Solution** :
```bash
rm -rf var/cache/*
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod --no-debug
```
