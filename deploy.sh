#!/usr/bin/env bash
#
# Pull and release, for Hostinger shared hosting.
#
#   ssh -p 65002 uXXXXXX@your-server
#   cd ~/domains/yourdomain.com/app && ./deploy.sh
#
# Assets are NOT built here - shared hosting has no Node. Run `npm run build`
# on your own machine and commit public/build before deploying.

set -euo pipefail

# The app needs PHP 8.4.1+ (Symfony 8). Hostinger's default `php` on the
# command line can be older than the version the website runs, so find a new
# enough binary rather than trusting PATH. Override with PHP=/path/to/php.
find_php() {
    for candidate in "${PHP:-}" /opt/alt/php84/usr/bin/php /opt/alt/php85/usr/bin/php php8.4 php8.5 php; do
        [ -n "$candidate" ] || continue
        command -v "$candidate" >/dev/null 2>&1 || continue
        if "$candidate" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' 2>/dev/null; then
            echo "$candidate"
            return 0
        fi
    done
    return 1
}

PHP_BIN=$(find_php) || {
    echo "No PHP 8.4.1+ found. In hPanel set PHP to 8.4, or run: PHP=/path/to/php8.4 ./deploy.sh" >&2
    exit 1
}
# Resolve composer's real path before the function below shadows the name.
COMPOSER_BIN=$(command -v composer) || { echo "composer not found on PATH" >&2; exit 1; }
php() { "$PHP_BIN" "$@"; }
composer() { "$PHP_BIN" "$COMPOSER_BIN" "$@"; }
echo "==> Using $("$PHP_BIN" -r 'echo PHP_BINARY, " (PHP ", PHP_VERSION, ")";')"

echo "==> Pulling latest"
git pull origin main

echo "==> Installing PHP dependencies (production)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Migrating"
# `migrate --force` adds new tables without touching existing data.
# Never `migrate:fresh` here - it drops every table.
php artisan migrate --force

echo "==> Linking storage"
php artisan storage:link || echo "    (already linked)"

echo "==> Caching config, routes and views"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Fixing permissions"
chmod -R 775 storage bootstrap/cache

echo ""
echo "Done. If you changed anything under resources/, make sure you ran"
echo "'npm run build' locally and committed public/build before this deploy."
