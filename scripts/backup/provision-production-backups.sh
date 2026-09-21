#!/usr/bin/env bash
# Invoked through the production-backups Actions workflow; no customer data stdout.
set -Eeuo pipefail
umask 077
fail() { printf '{"ok":false,"error":"%s"}\n' "$1" >&2; exit 1; }
[[ $# -eq 7 ]] || fail usage_invalid
backup_action="$1"
app_dir="$2"
iam_token_file="$3"
folder_id="$4"
bucket="$5"
manager_id="$6"
expected_sha="$7"
[[ "$EUID" -eq 0 ]] || fail root_required_for_installation
[[ "$backup_action" =~ ^(plan|apply|run|verify|status)$ ]] || fail action_invalid
[[ "$app_dir" =~ ^/[A-Za-z0-9._/-]+$ && "$app_dir" != / && ! "$app_dir" =~ (^|/)\.\.?(/|$) ]] || fail application_path_invalid
[[ "$expected_sha" =~ ^[0-9a-f]{40}$ ]] || fail expected_sha_invalid
[[ -d "$app_dir/.git" && -f "$app_dir/vendor/autoload.php" ]] || fail deployed_application_missing
app_owner="$(stat -c '%U' "$app_dir")"
[[ "$app_owner" =~ ^[A-Za-z_][A-Za-z0-9_-]*$ && "$app_owner" != root ]] || fail application_owner_invalid
[[ "$(runuser -u "$app_owner" -- git -C "$app_dir" rev-parse HEAD)" == "$expected_sha" ]] || fail deployed_commit_mismatch
source_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
config_dir=/etc/pischeprom-backup
work_dir=/var/lib/pischeprom-backup
installed_dir=/opt/pischeprom-backup
for directory in "$config_dir" "$work_dir" "$installed_dir"; do
    [[ ! -L "$directory" ]] || fail backup_directory_symlink
done
php_binary="$(command -v php)"
python_binary="$(command -v python3)"
[[ -n "$php_binary" && -n "$python_binary" ]] || fail runtime_missing
export PATH="$(dirname "$php_binary"):/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"

run_backup() {
    runuser -u "$app_owner" -- "$python_binary" "$installed_dir/run-backup.py" "$1" --app-dir "$app_dir" --config-dir "$config_dir" --work-dir "$work_dir"
}
if [[ "$backup_action" == run || "$backup_action" == verify || "$backup_action" == status ]]; then
    [[ -f "$installed_dir/run-backup.py" ]] || fail backups_not_installed
    run_backup "$backup_action"
    if [[ "$backup_action" == status ]]; then
        systemctl is-enabled --quiet pischeprom-backup.timer pischeprom-backup-verify.timer || fail backup_timers_disabled
        systemctl is-active --quiet pischeprom-backup.timer pischeprom-backup-verify.timer || fail backup_timers_inactive
    fi
    exit 0
fi

[[ -f "$iam_token_file" && ! -L "$iam_token_file" ]] || fail iam_token_missing
preflight_dir="$(mktemp -d /tmp/pischeprom-backup-preflight.XXXXXXXX)"
trap 'rm -rf -- "$preflight_dir"' EXIT
chmod 0700 "$preflight_dir"
chown "$app_owner" "$preflight_dir"
runuser -u "$app_owner" -- "$php_binary" "$app_dir/scripts/backup/export-application.php" inspect "$app_dir" > "$preflight_dir/inspect.json"
# No paths, DB names, credentials or message content are printed by inspect.
"$python_binary" - "$preflight_dir/inspect.json" "$work_dir" "$backup_action" <<'PY'
import json, os, shutil, sys
inspection = json.load(open(sys.argv[1]))
disk_path = sys.argv[2] if os.path.isdir(sys.argv[2]) else '/var/lib'
free = shutil.disk_usage(disk_path).free
estimate = int(inspection.get('database_estimated_bytes', 0)) + int(inspection.get('media_estimated_bytes', 0))
required = 3 * estimate + 1024 ** 3
inspection.update(free_bytes=free, estimated_required_free_bytes=required,
                  restic_available=bool(shutil.which('restic')),
                  isolated_database_server_available=bool(shutil.which('mysqld') or shutil.which('mariadbd')),
                  mysql_apparmor_profile_present=os.path.isfile('/etc/apparmor.d/usr.sbin.mysqld'))
print(json.dumps({'preflight': inspection}))
if sys.argv[3] == 'apply':
    driver = inspection['database_driver']
    if (driver not in ('mysql', 'mariadb', 'sqlite') or inspection['non_transactional_tables']
        or not inspection['pdo_sqlite_available'] or free < required
        or any(inspection.get('database_objects', {}).values())
        or (driver != 'sqlite' and (not inspection['dump_tool_available'] or not inspection['isolated_database_server_available']))):
        sys.exit('Backup preflight failed; no cloud changes were made.')
PY

"$python_binary" "$source_dir/provision-yandex-backup.py" --action "$backup_action" \
    --iam-token-file "$iam_token_file" --folder-id "$folder_id" --bucket "$bucket" \
    --management-service-account-id "$manager_id" --config-dir "$config_dir"
[[ "$backup_action" == apply ]] || exit 0

if ! command -v restic >/dev/null; then
    command -v apt-get >/dev/null || fail restic_installation_requires_apt
    # Signed distribution packages; package output stays on the VPS.
    DEBIAN_FRONTEND=noninteractive apt-get update -qq > "$preflight_dir/packages.log" 2>&1 || fail package_index_failed
    DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends restic >> "$preflight_dir/packages.log" 2>&1 || fail restic_installation_failed
fi
restic backup --help > "$preflight_dir/restic-backup-help"
restic restore --help > "$preflight_dir/restic-restore-help"
grep -q -- '--json' "$preflight_dir/restic-backup-help" || fail restic_json_not_supported
grep -q -- '--verify' "$preflight_dir/restic-restore-help" || fail restic_restore_verify_not_supported

install -d -m 0700 -o "$app_owner" -g "$(id -gn "$app_owner")" "$config_dir" "$work_dir"
find "$config_dir" -maxdepth 1 -type f -exec chmod 0600 {} + -exec chown "$app_owner" {} +
install -d -m 0755 -o root -g root "$installed_dir"
install -m 0755 -o root -g root "$source_dir/run-backup.py" "$installed_dir/run-backup.py"

for operation in backup backup-verify; do
    runtime_action=run
    [[ "$operation" == backup ]] || runtime_action=verify
    cat > "$preflight_dir/pischeprom-${operation}.service" <<EOF
[Unit]
Description=Pischeprom encrypted ${operation}
Wants=network-online.target
After=network-online.target

[Service]
Type=oneshot
User=$app_owner
Group=$(id -gn "$app_owner")
UMask=0077
Environment=PATH=$PATH
ExecStart=$python_binary $installed_dir/run-backup.py $runtime_action --app-dir $app_dir --config-dir $config_dir --work-dir $work_dir
TimeoutStartSec=12h
Nice=10
IOSchedulingClass=idle
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=read-only
ReadWritePaths=$config_dir $work_dir
ProtectKernelTunables=true
ProtectKernelModules=true
ProtectControlGroups=true
RestrictSUIDSGID=true
EOF
done
cat > "$preflight_dir/pischeprom-backup.timer" <<'EOF'
[Unit]
Description=Daily encrypted production backup
[Timer]
OnCalendar=*-*-* 03:10:00 Europe/Moscow
RandomizedDelaySec=5m
Persistent=true
Unit=pischeprom-backup.service
[Install]
WantedBy=timers.target
EOF
cat > "$preflight_dir/pischeprom-backup-verify.timer" <<'EOF'
[Unit]
Description=Weekly isolated recovery drill
[Timer]
OnCalendar=Sun *-*-* 05:30:00 Europe/Moscow
RandomizedDelaySec=5m
Persistent=true
Unit=pischeprom-backup-verify.service
[Install]
WantedBy=timers.target
EOF
systemd-analyze verify "$preflight_dir"/*.service "$preflight_dir"/*.timer > "$preflight_dir/systemd.log" 2>&1 || fail systemd_configuration_invalid
for unit in "$preflight_dir"/*.service "$preflight_dir"/*.timer; do
    install -m 0644 -o root -g root "$unit" /etc/systemd/system/
done
systemctl daemon-reload
run_backup init
# The first copy and recovery drill must succeed before activating the timers.
systemctl start pischeprom-backup.service || fail first_backup_failed
systemctl start pischeprom-backup-verify.service || fail first_restore_check_failed
systemctl enable --now pischeprom-backup.timer pischeprom-backup-verify.timer >/dev/null 2>&1
run_backup status
printf '{"timers_enabled":true,"daily_backup_moscow":"03:10","weekly_restore_moscow":"Sunday 05:30"}\n'
