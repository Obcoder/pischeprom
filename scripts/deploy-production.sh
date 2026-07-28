#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

log() {
    printf '[deploy] %s\n' "$*"
}

fail() {
    printf '[deploy] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 3 ]] || fail 'Expected TARGET_DIR_BASE64, COMMIT_SHA and FRONTEND_ARCHIVE.'

target_dir_encoded="$1"
commit_sha="$2"
frontend_archive="$3"

[[ "$commit_sha" =~ ^[0-9a-f]{40}$ ]] || fail 'Commit SHA is invalid.'

for required_command in awk base64 composer find flock git grep id php realpath sha256sum stat sudo systemctl tar; do
    command -v "$required_command" >/dev/null \
        || fail "Required server command is unavailable: ${required_command}."
done

target_dir="$(printf '%s' "$target_dir_encoded" | base64 --decode)" \
    || fail 'TARGET_DIR could not be decoded.'

[[ "$target_dir" == /* && "$target_dir" != "/" ]] \
    || fail 'TARGET_DIR must be an absolute non-root path.'
[[ -d "$target_dir" ]] || fail 'TARGET_DIR does not exist.'

target_dir="$(realpath -- "$target_dir")"
[[ "$target_dir" != "/" ]] || fail 'Resolved TARGET_DIR must not be the filesystem root.'

application_owner="$(stat -c '%U' "$target_dir")"
runtime_group='www-data'
[[ -n "$application_owner" && "$application_owner" != "UNKNOWN" && "$application_owner" != "root" ]] \
    || fail 'TARGET_DIR must be owned by a non-root application user.'
id "$application_owner" >/dev/null \
    || fail 'TARGET_DIR application owner does not exist.'
id "$runtime_group" >/dev/null \
    || fail 'Runtime group does not exist.'

[[ -f "$frontend_archive" ]] || fail 'Frontend archive is missing.'
[[ -f "${frontend_archive}.sha256" ]] || fail 'Frontend checksum is missing.'

expected_checksum="$(
    awk 'NR == 1 { print $1 }' "${frontend_archive}.sha256"
)"
[[ "$expected_checksum" =~ ^[0-9a-f]{64}$ ]] || fail 'Frontend checksum is invalid.'

actual_checksum="$(
    sha256sum "$frontend_archive" | awk '{ print $1 }'
)"
[[ "$actual_checksum" == "$expected_checksum" ]] || fail 'Frontend checksum mismatch.'

archive_entries=0
while IFS= read -r archive_entry; do
    archive_entry="${archive_entry#./}"
    [[ -n "$archive_entry" ]] || continue
    ((archive_entries += 1))

    case "$archive_entry" in
        public/build | public/build/* | bootstrap/ssr | bootstrap/ssr/*)
            ;;
        *)
            fail 'Frontend archive contains a path outside the allowlist.'
            ;;
    esac

    [[ "$archive_entry" != /* && "/${archive_entry}/" != *"/../"* ]] \
        || fail 'Frontend archive contains an unsafe path.'
done < <(tar -tzf "$frontend_archive")

(( archive_entries > 0 )) || fail 'Frontend archive is empty.'

[[ -f "$target_dir/artisan" ]] || fail 'Laravel artisan file is missing.'
[[ -d "$target_dir/.git" ]] || fail 'TARGET_DIR is not a Git working tree.'
[[ -f "$target_dir/.env" ]] || fail 'Server-side .env is missing.'
[[ -d "$target_dir/storage/framework" ]] || fail 'Laravel storage directory is missing.'

exec 9> "$target_dir/.git/pischeprom-deploy.lock"
flock -n 9 || fail 'Another production deployment is already running.'

cd "$target_dir"

git restore --source=HEAD --staged --worktree -- public/sitemap.xml 2>/dev/null || true

server_changes="$(
    LC_ALL=C git -c core.quotePath=true status --porcelain=v1 --untracked-files=normal \
        | grep -vE '^.. public/sitemap\.xml$' \
        || true
)"

if [[ -n "$server_changes" ]]; then
    while IFS= read -r server_change; do
        [[ -n "$server_change" ]] || continue
        printf '[deploy] Server-side change: %s\n' "$server_change" >&2
    done <<< "$server_changes"

    stash_message="automated pre-deploy preservation for ${commit_sha}"
    git stash push --include-untracked --message "$stash_message" >/dev/null \
        || fail 'Server-side changes could not be preserved in Git stash.'

    preserved_stash_sha="$(git rev-parse refs/stash)"
    [[ "$preserved_stash_sha" =~ ^[0-9a-f]{40}$ ]] \
        || fail 'The preservation stash could not be verified.'

    remaining_server_changes="$(
        LC_ALL=C git status --porcelain=v1 --untracked-files=normal \
            | grep -vE '^.. public/sitemap\.xml$' \
            || true
    )"
    [[ -z "$remaining_server_changes" ]] \
        || fail 'The server working tree remains dirty after preservation.'

    log "Server-side changes were preserved in Git stash ${preserved_stash_sha}."
fi

log "Fetching commit ${commit_sha}."
git fetch --no-tags --prune origin main
git cat-file -e "${commit_sha}^{commit}" \
    || fail 'Requested commit is unavailable on the VPS.'
git merge-base --is-ancestor "$commit_sha" origin/main \
    || fail 'Requested commit is not part of origin/main.'

previous_sha="$(git rev-parse HEAD)"
maintenance_started=0
code_switch_started=0
mail_worker_stopped=0
mail_notification_worker_stopped=0
banking_worker_stopped=0
routing_worker_stopped=0
ssr_stopped=0
mail_notification_worker_installed=0
banking_worker_installed=0
routing_worker_installed=0

if systemctl cat pischeprom-mail-notifications-worker.service >/dev/null 2>&1; then
    mail_notification_worker_installed=1
fi

if systemctl cat pischeprom-banking-worker.service >/dev/null 2>&1; then
    banking_worker_installed=1
fi

if systemctl cat pischeprom-routing-worker.service >/dev/null 2>&1; then
    routing_worker_installed=1
fi

restore_application() {
    exit_code=$?
    trap - EXIT

    if (( exit_code != 0 && maintenance_started == 1 && code_switch_started == 1 )); then
        printf '[deploy] FAILED after the code switch. The application remains in maintenance mode; stopped services were not restarted.\n' >&2
        printf '[deploy] Manual recovery is required. Previous SHA was %s.\n' \
            "$previous_sha" >&2
        exit "$exit_code"
    fi

    if (( ssr_stopped == 1 )); then
        sudo systemctl start pischeprom-ssr >/dev/null 2>&1 || true
    fi

    if (( banking_worker_stopped == 1 )); then
        sudo systemctl start pischeprom-banking-worker >/dev/null 2>&1 || true
    fi

    if (( routing_worker_stopped == 1 )); then
        sudo systemctl start pischeprom-routing-worker >/dev/null 2>&1 || true
    fi

    if (( mail_notification_worker_stopped == 1 )); then
        sudo systemctl start pischeprom-mail-notifications-worker >/dev/null 2>&1 || true
    fi

    if (( mail_worker_stopped == 1 )); then
        sudo systemctl start pischeprom-mail-sync-worker >/dev/null 2>&1 || true
    fi

    if (( maintenance_started == 1 )); then
        php "$target_dir/artisan" up >/dev/null 2>&1 || true
    fi

    if (( exit_code != 0 )); then
        printf '[deploy] FAILED while deploying %s; previous SHA was %s.\n' \
            "$commit_sha" "$previous_sha" >&2
    fi

    exit "$exit_code"
}
trap restore_application EXIT

log 'Stopping queue workers before changing application code.'
sudo systemctl stop pischeprom-mail-sync-worker
mail_worker_stopped=1

if (( mail_notification_worker_installed == 1 )); then
    sudo systemctl stop pischeprom-mail-notifications-worker
    mail_notification_worker_stopped=1
fi

if (( banking_worker_installed == 1 )); then
    sudo systemctl stop pischeprom-banking-worker
    banking_worker_stopped=1
fi

if (( routing_worker_installed == 1 )); then
    sudo systemctl stop pischeprom-routing-worker
    routing_worker_stopped=1
fi

php artisan schedule:interrupt
php artisan down --retry=60 --refresh=15
maintenance_started=1

sudo systemctl stop pischeprom-ssr
ssr_stopped=1

code_switch_started=1
git checkout --detach --force "$commit_sha"

composer install \
    --no-dev \
    --classmap-authoritative \
    --no-interaction \
    --prefer-dist \
    --no-progress

rm -rf -- "$target_dir/public/build" "$target_dir/bootstrap/ssr"
mkdir -p "$target_dir/public" "$target_dir/bootstrap"
tar -xzf "$frontend_archive" \
    --directory "$target_dir" \
    --no-same-owner \
    --no-same-permissions

php artisan optimize:clear
php artisan migrate --force --isolated
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=LogisticsExpenseCategorySeeder --force

php artisan route:list --path=stock-alerts >/dev/null
php artisan route:list --path=Ameise/commercial-offers >/dev/null
php artisan route:list --path=Ameise/bank >/dev/null
php artisan route:list --path=Ameise/orders >/dev/null
php artisan route:list --path=api/logistics >/dev/null
php artisan route:list --path=Ameise/logistics >/dev/null
php artisan route:list --path=unisender-go >/dev/null
php artisan route:list --path=unsubscribe >/dev/null
php artisan list mailings >/dev/null

if ! php artisan max:webhook:ensure >/dev/null 2>&1; then
    fail 'MAX webhook verification failed; rerun the command locally on the VPS.'
fi

if ! php artisan max:mail-notifications:health >/dev/null 2>&1; then
    fail 'MAX mail notification health check failed; rerun the command locally on the VPS.'
fi

if ! php artisan beeline:sync-calls --period=today --limit=500 >/dev/null 2>&1; then
    log 'WARNING: Beeline call sync failed; deployment continues.'
fi

if ! php artisan beeline:rebuild-calls --limit=2000 >/dev/null 2>&1; then
    log 'WARNING: Beeline call rebuild failed; deployment continues.'
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan bank:sber:health --if-enabled

sudo chown -R "${application_owner}:${runtime_group}" "$target_dir"
sudo find storage bootstrap/cache -type d -exec chmod 2770 {} +
sudo find storage bootstrap/cache -type f -exec chmod 0660 {} +

php artisan queue:restart

sudo systemctl daemon-reload
sudo systemctl start pischeprom-mail-sync-worker
mail_worker_stopped=0

if (( mail_notification_worker_installed == 1 )); then
    sudo systemctl start pischeprom-mail-notifications-worker
    mail_notification_worker_stopped=0
else
    log 'WARNING: pischeprom-mail-notifications-worker.service is not installed.'
fi

if (( banking_worker_installed == 1 )); then
    sudo systemctl start pischeprom-banking-worker
    banking_worker_stopped=0
else
    log 'WARNING: pischeprom-banking-worker.service is not installed.'
fi

if (( routing_worker_installed == 1 )); then
    sudo systemctl start pischeprom-routing-worker
    routing_worker_stopped=0
else
    log 'WARNING: pischeprom-routing-worker.service is not installed; routing jobs will remain queued.'
fi

sudo systemctl start pischeprom-ssr
ssr_stopped=0

ssr_ready=0
for attempt in 1 2 3 4 5; do
    if php artisan inertia:check-ssr >/dev/null 2>&1; then
        ssr_ready=1
        break
    fi

    sleep 3
done

if (( ssr_ready == 0 )); then
    log 'WARNING: Inertia SSR is unavailable; deployment continues with client-side rendering.'
fi

php artisan up
maintenance_started=0
code_switch_started=0

if ! php artisan app:deploy-smoke --path=/ >/dev/null 2>&1; then
    fail 'Smoke check for / failed; rerun the command locally on the VPS.'
fi

if ! php artisan app:deploy-smoke --path=/g >/dev/null 2>&1; then
    fail 'Smoke check for /g failed; rerun the command locally on the VPS.'
fi

if ! php artisan app:deploy-smoke --path=/Ameise/ >/dev/null 2>&1; then
    fail 'Smoke check for /Ameise/ failed; rerun the command locally on the VPS.'
fi

log "Deployment completed: ${previous_sha} -> ${commit_sha}."
