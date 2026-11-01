#!/bin/sh
set -e

# ROLE: web (php-fpm + nginx) | worker (queue:work via Supervisor) | scheduler (schedule:work)
#       | supervise (satu kontainer: semua worker + scheduler lewat `notifora:supervise`)
cd /var/www/html

# Volume baru dimiliki root: siapkan direktori yang ditulis aplikasi (www-data)
mkdir -p storage/app/public storage/app/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

case "${ROLE:-web}" in
  web)
    php artisan storage:link 2>/dev/null || true
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then php artisan migrate --force; fi
    exec supervisord -n -c /etc/supervisord.conf -j /tmp/supervisord.pid --configuration /etc/supervisor.d/web.conf 2>/dev/null \
      || exec supervisord -n -c /etc/supervisor.d/web.conf
    ;;
  worker)
    php artisan config:cache
    exec supervisord -n -c /etc/supervisor.d/worker.conf
    ;;
  scheduler)
    php artisan config:cache
    exec su -s /bin/sh www-data -c 'php artisan schedule:work'
    ;;
  supervise)
    php artisan config:cache
    exec su -s /bin/sh www-data -c 'php artisan notifora:supervise'
    ;;
  *)
    exec "$@"
    ;;
esac
