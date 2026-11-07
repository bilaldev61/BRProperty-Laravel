#!/usr/bin/env bash
#
# One-shot server setup for Hostinger shared hosting.
#
# Run it from hPanel > Advanced > Browser Terminal (works even when SSH is
# blocked), or over SSH when you have a connection:
#
#   cd ~/domains/brealstate.example/public_html/realestate-portfolio
#   git pull && bash server-setup.sh
#
# Safe to run more than once - every step checks before it acts.

set -euo pipefail

DOMAIN="${1:-brealstate.example}"
BASE="$HOME/domains/$DOMAIN"
APP="$BASE/app"
WEB="$BASE/public_html"

say()  { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }
ok()   { printf '    \033[32m✓\033[0m %s\n' "$1"; }
warn() { printf '    \033[33m!\033[0m %s\n' "$1"; }
die()  { printf '\n\033[31mSTOPPED: %s\033[0m\n' "$1" >&2; exit 1; }

[ -d "$BASE" ] || die "No such domain directory: $BASE"

# ---------------------------------------------------------------- 1. layout
say "Putting the app outside the web root"

if [ -d "$APP/public" ]; then
    ok "already at $APP"
elif [ -d "$WEB/realestate-portfolio" ]; then
    mv "$WEB/realestate-portfolio" "$APP"
    ok "moved out of public_html -> $APP"
else
    die "Cannot find the app. Expected $WEB/realestate-portfolio or $APP"
fi

# Anything else still sitting in public_html gets kept, not deleted.
if [ -e "$WEB" ] && [ ! -L "$WEB" ]; then
    leftovers=$(find "$WEB" -mindepth 1 -maxdepth 1 2>/dev/null | wc -l)
    if [ "$leftovers" -gt 0 ]; then
        STASH="$BASE/public_html_old_$(date +%s)"
        mv "$WEB" "$STASH"
        warn "public_html was not empty - kept a copy at $STASH"
    else
        rm -rf "$WEB"
    fi
fi

if [ -L "$WEB" ]; then
    ok "public_html already points at $(readlink "$WEB")"
else
    ln -s app/public "$WEB"
    ok "public_html -> app/public"
fi

cd "$APP"

# ------------------------------------------------------------ 2. dependencies
say "Installing PHP dependencies"
if [ -d vendor ] && [ -f vendor/autoload.php ]; then
    ok "vendor/ present"
else
    composer install --no-dev --optimize-autoloader --no-interaction
    ok "composer install done"
fi

# -------------------------------------------------------------------- 3. env
say "Environment file"
if [ -f .env ]; then
    ok ".env exists - leaving it alone"
else
    cp .env.hostinger.example .env
    ok "created .env from the template"
    echo
    echo "    Now enter your MySQL details (hPanel > Databases > MySQL)."
    echo "    Press Enter on the first one to skip and edit .env by hand later."
    echo
    read -rp "    DB name:     " DBN
    if [ -n "$DBN" ]; then
        read -rp "    DB user:     " DBU
        read -rsp "    DB password: " DBP; echo
        sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DBN|" .env
        sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DBU|" .env
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DBP|" .env
        ok "database credentials written"
    else
        warn "skipped - run 'nano .env' and fill DB_DATABASE / DB_USERNAME / DB_PASSWORD"
    fi
    sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" .env
    ok "APP_URL set to https://$DOMAIN"
fi

grep -q '^APP_KEY=base64:' .env || { php artisan key:generate --force; ok "APP_KEY generated"; }

# ------------------------------------------------------------ 4. permissions
say "Permissions"
chmod -R 775 storage bootstrap/cache
ok "storage and bootstrap/cache writable"

# -------------------------------------------------------------- 5. database
say "Database"
if php artisan migrate --force 2>&1 | tail -5; then
    ok "migrations applied"
    if [ "$(php artisan tinker --execute='echo App\Models\Property::count();' 2>/dev/null | tail -1)" = "0" ]; then
        php artisan db:seed --force >/dev/null 2>&1 && ok "demo content seeded"
    else
        ok "already has data - not seeding"
    fi
else
    warn "migrations failed - check the DB credentials in .env"
fi

# --------------------------------------------------------------- 6. storage
say "Storage link"
php artisan storage:link 2>/dev/null && ok "linked" || ok "already linked"

# ---------------------------------------------------------------- 7. caches
say "Caching config, routes and views"
php artisan optimize:clear >/dev/null
php artisan config:cache >/dev/null
php artisan route:cache  >/dev/null
php artisan view:cache   >/dev/null
ok "cached"

# ----------------------------------------------------------------- 8. verify
say "Checking"
[ -L "$WEB" ]                  && ok "public_html is a symlink"     || warn "public_html is NOT a symlink"
[ -f "$APP/public/index.php" ] && ok "index.php reachable"          || warn "index.php missing"
[ -f "$APP/public/build/manifest.json" ] && ok "compiled assets present" \
                                         || warn "public/build missing - run 'npm run build' locally and commit it"
[ -d "$APP/vendor" ]           && ok "vendor/ present"              || warn "vendor/ missing"

printf '\n\033[1;32mDone.\033[0m Open https://%s\n\n' "$DOMAIN"
echo "If you see a 500, read the error:"
echo "  tail -30 $APP/storage/logs/laravel.log"
