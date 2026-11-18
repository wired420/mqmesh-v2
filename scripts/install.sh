#!/usr/bin/env bash
# Unpack a package from scripts/package.sh (read from stdin) into the site
# folder this script lives in, and bring the site up to date.
set -euo pipefail
cd "$(dirname "$0")/.."

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
