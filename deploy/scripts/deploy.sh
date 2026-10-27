#!/usr/bin/env bash
# Deploy tanpa Docker. Jalankan di server sebagai user aplikasi:  ./deploy/scripts/deploy.sh [branch]
set -euo pipefail
cd "$(dirname "$0")/../.."
BRANCH="${1:-main}"

git fetch --quiet origin
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

composer install --no-dev --optimize-autoloader --no-interaction
npm ci --no-audit --no-fund && npm run build

php artisan down --retry=10 || true
php artisan migrate --force
php artisan storage:link 2>/dev/null || true
php artisan optimize          # cache config, route, view
php artisan queue:restart     # worker keluar rapi; supervisor menghidupkan dengan kode baru
php artisan up

echo "Deploy $BRANCH selesai. Cek Admin → Kesiapan production."
