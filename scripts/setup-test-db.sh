#!/usr/bin/env bash

set -euo pipefail

SYMFONY_BIN="php bin/console"
ENV_EXPORT="APP_ENV=test DATABASE_URL=pgsql://app:secret@localhost:5432/technova"

echo "Crée la base de test et applique les migrations via Doctrine..."
eval "$ENV_EXPORT $SYMFONY_BIN doctrine:database:create --if-not-exists --env=test"
eval "$ENV_EXPORT $SYMFONY_BIN doctrine:migrations:migrate --no-interaction --env=test"

echo "Chargement des fixtures de test (optionnel, supprime --append si besoin)"
eval "$ENV_EXPORT $SYMFONY_BIN doctrine:fixtures:load --no-interaction --env=test --purge-with-truncate"

echo "Base de test prête."
