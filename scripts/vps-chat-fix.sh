#!/usr/bin/env bash
# Reparación rápida del chat interno + deps en VPS (copy-paste).
# Uso: bash scripts/vps-chat-fix.sh
#
# BROADCAST_CONNECTION=log until reverb works, then reverb

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

echo "==> composer install --no-dev -o"
composer install --no-dev -o

echo "==> php artisan vetsaas:chat-ensure-schema"
php artisan vetsaas:chat-ensure-schema

echo "==> php artisan vetsaas:tenant-migrate-all"
php artisan vetsaas:tenant-migrate-all

echo "==> config clear/cache"
php artisan config:clear
php artisan config:cache || true

echo ""
echo "Listo."
echo "Mantén BROADCAST_CONNECTION=log hasta que Reverb + nginx /app + supervisor funcionen;"
echo "luego cambia a BROADCAST_CONNECTION=reverb y vuelve a ejecutar: php artisan config:cache"
echo "Opcional: pnpm install && pnpm run build"
