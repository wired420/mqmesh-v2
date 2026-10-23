#!/usr/bin/env bash
# Unpack a package from scripts/package.sh (read from stdin) into /var/www/v2
# and bring the site up to date. Never touches /var/www/html.
set -euo pipefail
cd /var/www/v2

php artisan down --retry=15 >/dev/null 2>&1 || true
trap 'php artisan up >/dev/null' EXIT

tar xzf - --no-overwrite-dir

sudo chgrp -R www-data storage bootstrap/cache public
sudo chmod -R g+rwX storage bootstrap/cache

php artisan migrate --force
php artisan optimize:clear >/dev/null
php artisan optimize >/dev/null
sudo -u www-data php artisan mqmesh:write-feeds

echo "Deployed $(date -u +%FT%TZ)"
