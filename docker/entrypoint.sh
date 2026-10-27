#!/bin/sh
set -e

# ROLE: web (php-fpm + nginx) | worker (queue:work) | scheduler (schedule:work)
cd /var/www/html

case "${ROLE:-web}" in
  web)
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
    exec php artisan schedule:work
    ;;
  *)
    exec "$@"
    ;;
esac
