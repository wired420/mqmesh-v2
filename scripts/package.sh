#!/usr/bin/env bash
# Build a production package of the site at /tmp/mqmesh-v2.tgz.
# Deploy it with:  cat /tmp/mqmesh-v2.tgz | ssh cjohnson@mqmesh.com 'bash /var/www/v2/scripts/install.sh'
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-dev --optimize-autoloader --no-interaction -q
npm run build >/dev/null

tar czf /tmp/mqmesh-v2.tgz \
    --exclude=./.git --exclude=./node_modules --exclude=./.env --exclude=./tests \
    --exclude=./database/database.sqlite --exclude=./public/hot \
    --exclude=./public/zones.json --exclude=./public/updater.json --exclude=./public/meshdb.json \
    --exclude='./storage/app/*' --exclude='./storage/logs/*' \
    --exclude='./storage/framework/sessions/*' --exclude='./storage/framework/views/*' \
    --exclude='./storage/framework/cache/data/*' --exclude='./bootstrap/cache/*.php' \
    .

composer install --no-interaction -q
ls -lh /tmp/mqmesh-v2.tgz
