#!/usr/bin/env bash
# Salin backup database ke penyimpanan luar server dengan rclone (S3/Backblaze/GDrive).
# Prasyarat: `rclone config` sudah membuat remote, mis. "offsite". Cron: 30 3 * * * /var/www/notifora/deploy/scripts/backup-offsite.sh
set -euo pipefail
SRC="$(dirname "$0")/../../storage/app/backups"
rclone copy "$SRC" "${RCLONE_REMOTE:-offsite}:notifora-backups" --include "notifora-*.gz" --min-age 1m
