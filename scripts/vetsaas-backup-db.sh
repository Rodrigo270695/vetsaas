#!/usr/bin/env bash
# Backup diario VetSaaS (alternativa/cron del SO).
# Uso en VPS (crontab -e):
#   0 2 * * * /var/www/vetsaas/scripts/vetsaas-backup-db.sh >> /var/log/vetsaas-backup.log 2>&1
#
# Requiere que el schedule de Laravel O este script corran a diario.
# Preferido: `* * * * * php artisan schedule:run` (incluye vetsaas:backup-database a las 02:00).

set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$APP_DIR"

php artisan vetsaas:backup-database
