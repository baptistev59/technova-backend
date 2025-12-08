#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

echo "[TechNova] Nettoyage de public/assets"
rm -rf public/assets

echo "[TechNova] npm run build"
npm run build

echo "[TechNova] php bin/console asset-map:compile"
php bin/console asset-map:compile

echo "[TechNova] Compilation des assets terminée."
