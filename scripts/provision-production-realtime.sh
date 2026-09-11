#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 077

log() { printf '[realtime-provision] %s\n' "$*"; }
fail() { printf '[realtime-provision] ERROR: %s\n' "$*" >&2; exit 1; }

[[ $# -eq 1 ]] || fail 'Expected TARGET_DIR; run under the production deployment lock.'
target_dir="$(realpath -- "$1")"
[[ "$target_dir" =~ ^/[A-Za-z0-9_./-]+$ && "$target_dir" != '/' \
    && -f "$target_dir/artisan" && -f "$target_dir/.env" && ! -L "$target_dir/.env" ]] \
    || fail 'TARGET_DIR must be a deployed Laravel application with a safe path.'

for command_name in install mktemp nginx php sed stat sudo systemctl systemd-analyze; do
    command -v "$command_name" >/dev/null || fail "Required command is unavailable: ${command_name}."
done

application_owner="$(stat -c '%U' "$target_dir")"
runtime_group='www-data'
php_binary="$(command -v php)"
[[ "$application_owner" =~ ^[a-z_][a-z0-9_-]*$ && "$application_owner" != 'root' \
    && "$php_binary" =~ ^/[A-Za-z0-9_./-]+$ ]] || fail 'Application owner or PHP binary is unsafe.'
id "$application_owner" >/dev/null || fail 'Application owner does not exist.'
getent group "$runtime_group" >/dev/null || fail 'Application runtime group does not exist.'

for source_file in scripts/update-production-realtime-env.php scripts/plan-production-realtime-nginx.php \
    scripts/lib/RealtimeNginx.php deploy/nginx/pischeprom-realtime.conf \
    deploy/systemd/pischeprom-reverb.service.template deploy/systemd/pischeprom-realtime-worker.service.template; do
    [[ -f "$target_dir/$source_file" && ! -L "$target_dir/$source_file" ]] \
        || fail 'A realtime provisioning source is missing or unsafe.'
done

staging_dir="$(mktemp -d /tmp/pischeprom-realtime.XXXXXXXXXX)"
site_path=''
nginx_changed=0
nginx_complete=0
snippet_existed=0
snippet_path='/etc/nginx/snippets/pischeprom-realtime.conf'

cleanup() {
    local exit_code=$?
    local rollback_failed=0
    trap - EXIT
    if (( nginx_changed == 1 && nginx_complete == 0 )); then
        sudo -n cp -p -- "$staging_dir/site.previous.conf" "$site_path" || rollback_failed=1
        if (( snippet_existed == 1 )); then
            sudo -n cp -p -- "$staging_dir/snippet.previous.conf" "$snippet_path" || rollback_failed=1
        else
            sudo -n rm -f -- "$snippet_path" || rollback_failed=1
        fi
        if sudo -n nginx -t >/dev/null 2>&1; then
            sudo -n systemctl reload nginx >/dev/null 2>&1 || rollback_failed=1
        else
            rollback_failed=1
        fi
        if (( rollback_failed == 1 )); then
            printf '[realtime-provision] Nginx rollback failed. Protected backups retained at %s; manual recovery is required.\n' "$staging_dir" >&2
            exit 1
        fi
        printf '[realtime-provision] Nginx activation failed; previous site configuration was restored.\n' >&2
    fi
    sudo -n rm -rf -- "$staging_dir" || true
    exit "$exit_code"
}
trap cleanup EXIT

# Neither the nginx dump nor server .env is printed to GitHub Actions logs.
sudo -n nginx -T > "$staging_dir/nginx.dump" 2> "$staging_dir/nginx-errors.log" \
    || fail 'The current Nginx configuration is invalid; inspect nginx -t on the VPS.'

application_host="$("$php_binary" -r '
    require $argv[1]."/vendor/autoload.php";
    try {
        $values = Dotenv\Dotenv::parse((string) file_get_contents($argv[1]."/.env"));
        $url = parse_url($values["APP_URL"] ?? "");
        if (!is_array($url) || ($url["scheme"] ?? "") !== "https"
            || ($url["port"] ?? 443) !== 443
            || preg_match("/^[a-zA-Z0-9][a-zA-Z0-9.-]*[a-zA-Z0-9]$/D", $url["host"] ?? "") !== 1) exit(1);
        echo strtolower($url["host"]);
    } catch (Throwable) { exit(1); }
' "$target_dir")" || fail 'Realtime requires a valid HTTPS APP_URL on port 443.'

sudo -n "$php_binary" "$target_dir/scripts/plan-production-realtime-nginx.php" \
    "$target_dir" "$application_host" "$staging_dir/nginx.dump" "$staging_dir" \
    || fail 'The application Nginx vhost could not be safely identified.'
site_path="$(sudo -n cat "$staging_dir/site.path")"
[[ "$site_path" == /etc/nginx/* && -f "$site_path" && ! -L "$site_path" ]] \
    || fail 'The planned application vhost path is unsafe.'
[[ ! -L /etc/nginx/snippets && ! -L "$snippet_path" ]] \
    || fail 'The managed Nginx snippet location is unsafe.'

log 'Installing the loopback Reverb service and the dedicated event worker.'
for service_name in pischeprom-reverb pischeprom-realtime-worker; do
    sed \
        -e "s|__APPLICATION_USER__|${application_owner}|g" \
        -e "s|__RUNTIME_GROUP__|${runtime_group}|g" \
        -e "s|__TARGET_DIR__|${target_dir}|g" \
        -e "s|__PHP_BINARY__|${php_binary}|g" \
        "$target_dir/deploy/systemd/${service_name}.service.template" \
        > "$staging_dir/${service_name}.service"
done
sudo -n systemd-analyze verify \
    "$staging_dir/pischeprom-reverb.service" "$staging_dir/pischeprom-realtime-worker.service" \
    > "$staging_dir/systemd-verify.log" 2>&1 \
    || fail 'Realtime systemd units failed verification; inspect the templates on the VPS.'
for service_name in pischeprom-reverb pischeprom-realtime-worker; do
    sudo -n install -m 0644 -o root -g root "$staging_dir/${service_name}.service" \
        "/etc/systemd/system/${service_name}.service"
done
sudo -n systemctl daemon-reload
sudo -n systemctl enable pischeprom-reverb.service pischeprom-realtime-worker.service

log 'Generating or preserving Reverb credentials in the server environment.'
"$php_binary" "$target_dir/scripts/update-production-realtime-env.php" "$target_dir/.env"

sudo -n cp -p -- "$site_path" "$staging_dir/site.previous.conf"
if [[ -e "$snippet_path" ]]; then
    [[ -f "$snippet_path" ]] || fail 'Managed Nginx snippet is not a regular file.'
    sudo -n cp -p -- "$snippet_path" "$staging_dir/snippet.previous.conf"
    snippet_existed=1
fi
sudo -n install -d -m 0755 -o root -g root /etc/nginx/snippets
nginx_changed=1
sudo -n install -m 0644 -o root -g root "$target_dir/deploy/nginx/pischeprom-realtime.conf" "$snippet_path"
sudo -n install -m 0644 -o root -g root "$staging_dir/site.next.conf" "$site_path"
sudo -n nginx -t > "$staging_dir/nginx-check.log" 2>&1 \
    || fail 'Realtime Nginx configuration failed validation; restoring the previous configuration.'
sudo -n systemctl reload nginx
nginx_complete=1

log 'Realtime infrastructure is configured. Deployment starts the services after migrations and config cache.'
