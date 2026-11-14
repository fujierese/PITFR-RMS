#!/usr/bin/env bash
# Server-side deploy for PITFR-RMS (GoDaddy cPanel).
#
# Called by .github/workflows/deploy.yml after it has pulled the new code and
# uploaded a release archive containing vendor/ and public/build/ (built on
# GitHub, because the shared host has no Node.js and Composer is unreliable).
#
# Manual use on the server:
#   ./deploy.sh                         # code already pulled; runs composer if available
#   ./deploy.sh ~/pitfr-release.tgz     # also installs a prebuilt vendor/ + public/build/
#
# Set PHP_BIN if the default `php` on the server is not PHP 8.2+, e.g.
#   PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php ./deploy.sh
set -euo pipefail

cd "$(dirname "$0")"
PHP="${PHP_BIN:-php}"
ARCHIVE="${1:-}"

echo "==> Using $($PHP -r 'echo PHP_BINARY, " (PHP ", PHP_VERSION, ")";')"

"$PHP" artisan down --retry=15 || true
trap '"$PHP" artisan up || true' EXIT

if [ -n "$ARCHIVE" ]; then
    echo "==> Installing prebuilt vendor/ and public/build/ from $ARCHIVE"
    rm -rf .deploy-tmp
    mkdir .deploy-tmp
    tar -xzf "$ARCHIVE" -C .deploy-tmp
    for dir in vendor public/build; do
        if [ -d ".deploy-tmp/$dir" ]; then
            rm -rf "$dir.old"
            if [ -d "$dir" ]; then mv "$dir" "$dir.old"; fi
            mv ".deploy-tmp/$dir" "$dir"
            rm -rf "$dir.old"
        fi
    done
    rm -rf .deploy-tmp "$ARCHIVE"
elif command -v composer >/dev/null 2>&1; then
    echo "==> Running composer install"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
else
    echo "==> No archive and no composer; keeping the existing vendor/"
fi

echo "==> Refreshing package manifest"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
"$PHP" artisan package:discover

echo "==> Running migrations"
"$PHP" artisan migrate --force

echo "==> Rebuilding caches"
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan queue:restart || true

echo "==> Deployed $(git rev-parse --short HEAD)"
