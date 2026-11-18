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
# Server-specific settings can live in an untracked .deploy.env next to this
# script (it is sourced if present):
#   PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php   # if `php` is not 8.2+
#   PUBLIC_PATH=/home/USER/public_html              # if the web root is NOT this app's public/
#   RUN_MIGRATIONS=0                                # skip `migrate --force` (default: 1)
#
# With PUBLIC_PATH set, every deploy copies public/ into that folder
# (build/ is replaced; other folders are merged so extra files survive).
# index.php and .htaccess there are never touched, because they are
# usually customized to point at this app.
set -euo pipefail

cd "$(dirname "$0")"
if [ -f .deploy.env ]; then
    # shellcheck disable=SC1091
    . ./.deploy.env
fi
PHP="${PHP_BIN:-php}"
PUBLIC_PATH="${PUBLIC_PATH:-}"
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

if [ -n "$PUBLIC_PATH" ] && [ "$(cd "$PUBLIC_PATH" && pwd -P)" != "$(cd public && pwd -P)" ]; then
    echo "==> Publishing public/ to $PUBLIC_PATH (index.php and .htaccess left untouched)"
    for item in public/* public/.[!.]*; do
        [ -e "$item" ] || continue
        name="$(basename "$item")"
        case "$name" in
            index.php|.htaccess|storage) continue ;;
        esac
        target="$PUBLIC_PATH/$name"
        if [ "$name" = build ]; then
            # Vite output has hashed names: replace the folder so stale files go away.
            rm -rf "$target.new" "$target.old"
            cp -R "$item" "$target.new"
            if [ -e "$target" ]; then mv "$target" "$target.old"; fi
            mv "$target.new" "$target"
            rm -rf "$target.old"
        elif [ -d "$item" ]; then
            mkdir -p "$target"
            cp -R "$item/." "$target/"
        else
            cp "$item" "$target"
        fi
    done
fi

echo "==> Refreshing package manifest"
rm -f bootstrap/cache/packages.php bootstrap/cache/services.php
"$PHP" artisan package:discover

if [ "${RUN_MIGRATIONS:-1}" = "0" ]; then
    echo "==> Skipping migrations (RUN_MIGRATIONS=0 in .deploy.env). Pending:"
    "$PHP" artisan migrate:status | grep -i pending || echo "    (none)"
else
    echo "==> Running migrations"
    "$PHP" artisan migrate --force
fi

echo "==> Rebuilding caches"
"$PHP" artisan optimize:clear
"$PHP" artisan optimize
"$PHP" artisan queue:restart || true

# NTF-02: the scheduler sends reminders, processes review deadlines and runs the
# queue worker that delivers notification emails. Add its cron entry once, if no
# schedule:run entry exists yet. Set INSTALL_CRON=0 in .deploy.env to skip.
if [ "${INSTALL_CRON:-1}" != "0" ] && command -v crontab >/dev/null 2>&1; then
    if crontab -l 2>/dev/null | grep -q "schedule:run"; then
        echo "==> Scheduler cron entry already present"
    else
        PHP_ABS="$("$PHP" -r 'echo PHP_BINARY;' 2>/dev/null || echo "$PHP")"
        CRON_LINE="* * * * * cd $(pwd -P) && $PHP_ABS artisan schedule:run >> /dev/null 2>&1"
        if { crontab -l 2>/dev/null; echo "$CRON_LINE"; } | crontab -; then
            echo "==> Added scheduler cron entry: $CRON_LINE"
        else
            echo "==> Could not add the cron entry; add it in cPanel > Cron Jobs: $CRON_LINE"
        fi
    fi
fi

echo "==> Deployed $(git rev-parse --short HEAD)"
