#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

log() {
    printf '[price-lists-provision] %s\n' "$*"
}

fail() {
    printf '[price-lists-provision] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -ge 2 ]] || fail 'Expected MODE and TARGET_DIR.'

mode="$1"
target_dir="$2"
unit_template="${3:-}"
api_key_file="${4:-}"
folder_id="${5:-}"
env_updater="${6:-}"

[[ "$mode" == 'prepare' || "$mode" == 'activate' || "$mode" == 'verify' ]] \
    || fail 'MODE must be prepare, activate or verify.'
[[ "$target_dir" == /* && "$target_dir" != '/' ]] || fail 'TARGET_DIR must be an absolute non-root path.'
[[ -d "$target_dir" && -f "$target_dir/artisan" && -f "$target_dir/.env" ]] \
    || fail 'TARGET_DIR is not a deployed Laravel application.'

target_dir="$(realpath -- "$target_dir")"
application_owner="$(stat -c '%U' "$target_dir")"
runtime_group='www-data'
php_binary="$(command -v php)"

[[ -n "$application_owner" && "$application_owner" != 'root' ]] || fail 'Application owner is unsafe.'
id "$application_owner" >/dev/null || fail 'Application owner does not exist.'
id "$runtime_group" >/dev/null || fail 'Runtime group does not exist.'
[[ -x "$php_binary" ]] || fail 'PHP CLI is unavailable.'

detect_clamav_socket() {
    local socket_path
    socket_path="$(awk '$1 == "LocalSocket" { print $2; exit }' /etc/clamav/clamd.conf 2>/dev/null || true)"
    [[ "$socket_path" == /* ]] || return 1
    printf '%s' "$socket_path"
}

wait_for_clamav() {
    local socket_path="$1"

    for _ in $(seq 1 30); do
        [[ -S "$socket_path" ]] && return 0
        sleep 2
    done

    return 1
}

has_clamav_database() {
    sudo find /var/lib/clamav -maxdepth 1 -type f \
        \( -name '*.cvd' -o -name '*.cld' \) -size +100k -print -quit | grep -q .
}

seed_clamav_database_from_official_image() {
    local image='clamav/clamav:1.5.3@sha256:afbacf91caa6e02cd3b86238a4b130255bc465c8928dfe505cae63ae22c7e966'
    local container_id=''
    local seed_dir
    local status=0

    [[ "$(dpkg --print-architecture)" == 'amd64' ]] || return 1
    command -v docker >/dev/null || return 1
    seed_dir="$(mktemp -d)"

    sudo docker pull "$image" || status=1
    if (( status == 0 )); then
        container_id="$(sudo docker create --entrypoint /bin/true "$image")" || status=1
    fi
    if (( status == 0 )); then
        sudo docker cp "${container_id}:/var/lib/clamav/." "$seed_dir/" || status=1
    fi
    if [[ -n "$container_id" ]]; then
        sudo docker rm --force "$container_id" >/dev/null 2>&1 || true
    fi

    if (( status == 0 )); then
        local database_count
        database_count="$(find "$seed_dir" -maxdepth 1 -type f \
            \( -name '*.cvd' -o -name '*.cld' \) -size +100k | wc -l)"
        (( database_count >= 2 )) || status=1
    fi
    if (( status == 0 )); then
        clamscan --database="$seed_dir" --no-summary /dev/null >/dev/null || status=1
    fi
    if (( status == 0 )); then
        while IFS= read -r -d '' database_file; do
            sudo install -m 0644 -o clamav -g clamav "$database_file" /var/lib/clamav/
        done < <(find "$seed_dir" -maxdepth 1 -type f \
            \( -name '*.cvd' -o -name '*.cld' \) -size +100k -print0)
    fi

    rm -rf -- "$seed_dir"
    (( status == 0 ))
}

prepare_host() {
    [[ -f "$unit_template" && ! -L "$unit_template" ]] || fail 'Worker unit template is missing or unsafe.'
    command -v apt-get >/dev/null || fail 'This audited provisioner requires apt-get.'

    log 'Installing ClamAV, image and TIFF runtime prerequisites.'
    sudo env DEBIAN_FRONTEND=noninteractive apt-get update -qq
    sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        clamav clamav-daemon ghostscript imagemagick libtiff-tools

    if ! php -r 'exit(extension_loaded("imagick") ? 0 : 1);'; then
        sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.4-imagick \
            || sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-imagick
    fi

    if ! php -r 'exit(extension_loaded("gd") ? 0 : 1);'; then
        sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php8.4-gd \
            || sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-gd
    fi

    php -r 'exit(extension_loaded("imagick") ? 0 : 1);' || fail 'PHP Imagick extension is unavailable.'
    php -r 'exit(extension_loaded("gd") ? 0 : 1);' || fail 'PHP GD extension is unavailable.'
    command -v tiff2pdf >/dev/null || fail 'tiff2pdf is unavailable.'
    getent group clamav >/dev/null || fail 'ClamAV group is unavailable.'

    if ! has_clamav_database; then
        sudo systemctl stop clamav-freshclam.service >/dev/null 2>&1 || true
        sudo freshclam --stdout || true
    fi
    if ! has_clamav_database; then
        log 'FreshClam database is unavailable; bootstrapping signed databases from the pinned official ClamAV image.'
        seed_clamav_database_from_official_image \
            || fail 'ClamAV databases could not be bootstrapped from the official image.'
    fi
    has_clamav_database || fail 'ClamAV signature database is unavailable.'

    sudo systemctl enable --now clamav-freshclam.service >/dev/null 2>&1 || true
    sudo systemctl enable --now clamav-daemon.service

    local clamav_socket
    clamav_socket="$(detect_clamav_socket)" || fail 'ClamAV local socket is not configured.'
    wait_for_clamav "$clamav_socket" || fail 'ClamAV local socket did not become ready.'

    local unit_staging
    unit_staging="$(mktemp)"
    sed \
        -e "s|__APPLICATION_USER__|${application_owner}|g" \
        -e "s|__RUNTIME_GROUP__|${runtime_group}|g" \
        -e "s|__TARGET_DIR__|${target_dir}|g" \
        -e "s|__PHP_BINARY__|${php_binary}|g" \
        "$unit_template" > "$unit_staging"

    grep -Fq "User=${application_owner}" "$unit_staging" || fail 'Worker user substitution failed.'
    grep -Fq "WorkingDirectory=${target_dir}" "$unit_staging" || fail 'Worker path substitution failed.'
    sudo install -m 0644 "$unit_staging" /etc/systemd/system/pischeprom-price-lists-worker.service
    rm -f -- "$unit_staging"
    sudo systemctl daemon-reload
    sudo systemctl enable pischeprom-price-lists-worker.service

    log 'Runtime prerequisites and the dedicated worker unit are ready.'
}

verify_application() {
    local clamav_socket
    clamav_socket="$(detect_clamav_socket)" || fail 'ClamAV local socket is not configured.'
    wait_for_clamav "$clamav_socket" || fail 'ClamAV local socket is unavailable.'

    cd "$target_dir"
    sudo -u "$application_owner" "$php_binary" artisan optimize:clear >/dev/null
    sudo -u "$application_owner" "$php_binary" artisan config:cache >/dev/null
    sudo systemctl enable --now pischeprom-price-lists-worker.service

    local schedule_output
    schedule_output="$(sudo -u "$application_owner" "$php_binary" artisan schedule:list --no-ansi)"
    grep -Fq 'price-lists:recover-stale' <<< "$schedule_output" \
        || fail 'Price-list recovery task is absent from the Laravel scheduler.'
    sudo -u "$application_owner" -g clamav "$php_binary" artisan price-lists:production-preflight --all
    sudo -u "$application_owner" "$php_binary" artisan max:webhook:ensure >/dev/null
    sudo systemctl is-active --quiet pischeprom-price-lists-worker.service \
        || fail 'Dedicated price-list worker is not active.'

    log 'Production application, integrations and dedicated worker passed preflight.'
}

activate_application() {
    [[ -f "$api_key_file" && ! -L "$api_key_file" ]] || fail 'API key file is missing or unsafe.'
    [[ -f "$env_updater" && ! -L "$env_updater" ]] || fail 'Environment updater is missing or unsafe.'
    [[ "$folder_id" =~ ^[a-z0-9]{20}$ ]] || fail 'Yandex folder ID has an unexpected format.'

    local clamav_socket env_path backup_dir backup_path env_owner env_group env_mode was_active activated
    clamav_socket="$(detect_clamav_socket)" || fail 'ClamAV local socket is not configured.'
    env_path="${target_dir}/.env"
    backup_dir="${target_dir}/storage/app/private/deploy-backups"
    backup_path="${backup_dir}/price-lists-env-$(date -u +%Y%m%dT%H%M%SZ)"
    env_owner="$(stat -c '%U' "$env_path")"
    env_group="$(stat -c '%G' "$env_path")"
    env_mode="$(stat -c '%a' "$env_path")"
    was_active=0
    activated=0

    if sudo systemctl is-active --quiet pischeprom-price-lists-worker.service; then
        was_active=1
    fi
    sudo install -d -m 0750 -o "$application_owner" -g "$runtime_group" "$backup_dir"
    sudo install -m 0600 -o "$env_owner" -g "$env_group" "$env_path" "$backup_path"

    rollback_activation() {
        local exit_code=$?
        trap - EXIT

        if (( exit_code != 0 && activated == 0 )); then
            sudo install -m "$env_mode" -o "$env_owner" -g "$env_group" "$backup_path" "$env_path" || true
            cd "$target_dir"
            sudo -u "$application_owner" "$php_binary" artisan optimize:clear >/dev/null 2>&1 || true
            sudo -u "$application_owner" "$php_binary" artisan config:cache >/dev/null 2>&1 || true

            if (( was_active == 1 )); then
                sudo systemctl start pischeprom-price-lists-worker.service >/dev/null 2>&1 || true
            else
                sudo systemctl stop pischeprom-price-lists-worker.service >/dev/null 2>&1 || true
            fi

            printf '[price-lists-provision] Activation failed; server-side .env was restored from backup.\n' >&2
        fi

        exit "$exit_code"
    }
    trap rollback_activation EXIT

    sudo "$php_binary" "$env_updater" "$env_path" "$api_key_file" "$folder_id" "$clamav_socket"
    sudo chown "$env_owner:$env_group" "$env_path"
    sudo chmod "$env_mode" "$env_path"
    verify_application
    activated=1
    trap - EXIT

    log "Activation completed; recoverable environment backup: ${backup_path}."
}

case "$mode" in
    prepare)
        prepare_host
        ;;
    activate)
        activate_application
        ;;
    verify)
        verify_application
        ;;
esac
