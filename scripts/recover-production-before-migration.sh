#!/usr/bin/env bash

# Incident-specific recovery: the Telegram pre-migration failure on 2026-10-10.
# No migration, environment update or provider provisioning is performed here.
set -Eeuo pipefail
umask 027

log() { printf '[recovery] %s\n' "$*"; }
fail() { printf '[recovery] ERROR: %s\n' "$*" >&2; exit 1; }

[[ $# -eq 1 ]] || fail 'Expected the base64-encoded application directory.'
target_dir="$(printf '%s' "$1" | base64 --decode)" || fail 'Invalid application directory encoding.'
[[ "$target_dir" == /* && "$target_dir" != / && -d "$target_dir" ]] || fail 'Invalid application directory.'
target_dir="$(realpath -- "$target_dir")"
[[ "$target_dir" != / ]] || fail 'Unsafe application directory.'
cd "$target_dir"

failed_sha='7f1395ef700b61043f08854a7751ef61da7b55d8'
healthy_sha='bd7dae11c94b4cb8ee66a1be0b3e4ac329bd68f8'
manifest_sha='9fe0124cdc46dba2299ba9cb889a68bf0a681ddaaf1daa430a694d997768cac3'
ssr_sha='62a48ed0592992ec34a22a8a9613f4363722d125beb45bef511f19ca654d19ff'

exec 9> "$target_dir/.git/pischeprom-deploy.lock"
flock -n 9 || fail 'Another deployment or recovery is running.'
[[ "$(git rev-parse HEAD)" == "$failed_sha" ]] || fail 'Server HEAD does not match the failed deployment.'
git cat-file -e "${healthy_sha}^{commit}" || fail 'The healthy commit is unavailable.'
git merge-base --is-ancestor "$healthy_sha" "$failed_sha" || fail 'The recovery commit is not an ancestor.'
git diff --quiet && git diff --cached --quiet || fail 'Tracked server files have changed.'
[[ -z "$(git diff "$healthy_sha" "$failed_sha" -- composer.json composer.lock)" ]] || fail 'Recovery would change dependency definitions.'
[[ -f .env && ! -L .env && -f storage/framework/down ]] || fail 'Expected environment and maintenance files are absent.'
environment_sha="$(sha256sum .env | awk '{print $1}')"

verify_assets() {
    [[ -f public/build/manifest.json && ! -L public/build/manifest.json \
        && -f bootstrap/ssr/ssr.js && ! -L bootstrap/ssr/ssr.js ]] \
        || fail 'The previous frontend or SSR artifacts are unavailable.'
    printf '%s  public/build/manifest.json\n%s  bootstrap/ssr/ssr.js\n' "$manifest_sha" "$ssr_sha" \
        | sha256sum --check --status || fail 'Frontend artifacts differ from the verified previous release.'
    [[ -f public/build/assets/app-BP_Kg4zV.js && -f public/build/assets/Show-DDc-BRzA.js ]] \
        || fail 'The previous public entry assets are missing.'
}
verify_assets

# Bootstrap only to inspect maintenance state and schema; suppress application
# exceptions because they may include database connection information.
if ! php -- "$target_dir" >/dev/null 2>&1 <<'PHP'
<?php
set_exception_handler(static function (Throwable $exception): void { exit(1); });
require $argv[1].'/vendor/autoload.php';
$app = require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (! $app->isDownForMaintenance()
    || Illuminate\Support\Facades\Schema::hasTable('catalog_landings')
    || Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_10_10_120000_create_catalog_landings_table')->exists()) {
    exit(1);
}
PHP
then
    fail 'Recovery requires maintenance mode and an unapplied catalog landing migration.'
fi

application_owner="$(stat -c '%U' "$target_dir")"
[[ -n "$application_owner" && "$application_owner" != root && "$application_owner" != UNKNOWN ]] \
    || fail 'Unexpected application owner.'
id "$application_owner" >/dev/null
id www-data >/dev/null
systemctl is-active --quiet php8.4-fpm || fail 'PHP-FPM is unavailable.'

units=(pischeprom-ssr pischeprom-reverb pischeprom-realtime-worker pischeprom-mail-sync-worker)
for unit in "${units[@]}"; do
    systemctl cat "${unit}.service" >/dev/null 2>&1 || fail 'A required application service is missing.'
done
for unit in pischeprom-mail-notifications-worker pischeprom-banking-worker pischeprom-routing-worker pischeprom-price-lists-worker; do
    if systemctl cat "${unit}.service" >/dev/null 2>&1; then
        units+=("$unit")
    fi
done
for unit in "${units[@]}"; do
    if systemctl is-active --quiet "$unit"; then
        fail 'An application worker is unexpectedly active; inspect before recovery.'
    fi
done

recovery_started=0
recovery_complete=0
started_units=()
cleanup() {
    result=$?
    trap - EXIT
    if (( result != 0 && recovery_started == 1 && recovery_complete == 0 )); then
        php artisan down --retry=60 --refresh=15 >/dev/null 2>&1 || true
        for unit in "${started_units[@]}"; do
            sudo -n systemctl stop "$unit" >/dev/null 2>&1 || true
        done
        log 'Recovery failed; maintenance remains enabled and restarted application services were stopped.'
    fi
    exit "$result"
}
trap cleanup EXIT

log 'Preflight passed; restoring the fixed previous release without schema changes.'
recovery_started=1
git checkout --detach "$healthy_sha" >/dev/null 2>&1 || fail 'Code recovery failed.'
composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress >/dev/null 2>&1 \
    || fail 'Dependency verification failed.'
php artisan optimize:clear >/dev/null 2>&1 || fail 'Cache clearing failed.'
php artisan config:cache >/dev/null 2>&1 || fail 'Configuration cache failed.'
php artisan route:cache >/dev/null 2>&1 || fail 'Route cache failed.'
php artisan view:cache >/dev/null 2>&1 || fail 'View cache failed.'
[[ "$(sha256sum .env | awk '{print $1}')" == "$environment_sha" ]] || fail 'Environment changed during recovery.'
verify_assets

sudo -n chown -R "${application_owner}:www-data" "$target_dir"
sudo -n find storage bootstrap/cache -type d -exec chmod 2770 {} +
sudo -n find storage bootstrap/cache -type f -exec chmod 0660 {} +
timeout 30s sudo -n systemctl reload php8.4-fpm || fail 'PHP-FPM reload failed.'
systemctl is-active --quiet php8.4-fpm || fail 'PHP-FPM failed after reload.'
php artisan queue:restart >/dev/null 2>&1 || fail 'Queue restart signal failed.'
sudo -n systemctl daemon-reload

for unit in "${units[@]}"; do
    started_units+=("$unit")
    sudo -n systemctl start "$unit" || fail 'An application service could not start.'
    systemctl is-active --quiet "$unit" || fail 'An application service is not active.'
done
ssr_ready=0
for attempt in 1 2 3 4 5; do
    if php artisan inertia:check-ssr >/dev/null 2>&1; then
        ssr_ready=1
        break
    fi
    sleep 3
done
(( ssr_ready == 1 )) || fail 'Previous release SSR is unavailable.'
php "$target_dir/scripts/check-production-realtime.php" "$target_dir" >/dev/null 2>&1 \
    || fail 'Previous release realtime services are unavailable.'

php artisan up >/dev/null 2>&1 || fail 'Could not leave maintenance mode.'
php artisan app:deploy-smoke --path=/ >/dev/null 2>&1 || fail 'Previous release home page smoke failed.'
php artisan app:check-class-pages >/dev/null 2>&1 || fail 'Previous release class guide SSR smoke failed.'
[[ "$(git rev-parse HEAD)" == "$healthy_sha" ]] || fail 'Recovered code changed unexpectedly.'
recovery_complete=1
log "Previous release restored and verified: ${healthy_sha}. No migrations or environment changes were performed."
