#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

log() {
    printf '[routing-provision] %s\n' "$*"
}

fail() {
    printf '[routing-provision] ERROR: %s\n' "$*" >&2
    exit 1
}

[[ $# -eq 6 ]] \
    || fail 'Expected TARGET_DIR_BASE64, OSM_SNAPSHOT, GRAPH_ARCHIVE, GRAPH_CHECKSUM, SOURCE_ARCHIVE and SOURCE_CHECKSUM.'

target_dir_encoded="$1"
snapshot="$2"
graph_archive="$3"
graph_checksum="$4"
source_archive="$5"
source_checksum="$6"

[[ "$snapshot" =~ ^[0-9]{6}$ ]] || fail 'OSM snapshot must use YYMMDD format.'
[[ "$graph_checksum" =~ ^[0-9a-f]{64}$ ]] || fail 'Graph checksum is invalid.'
[[ "$source_checksum" =~ ^[0-9a-f]{64}$ ]] || fail 'Source checksum is invalid.'

for required_command in apt-cache base64 curl find git id php realpath sed sha256sum stat sudo systemctl tar; do
    command -v "$required_command" >/dev/null \
        || fail "Required server command is unavailable: ${required_command}."
done

target_dir="$(printf '%s' "$target_dir_encoded" | base64 --decode)" \
    || fail 'TARGET_DIR could not be decoded.'
[[ "$target_dir" == /* && "$target_dir" != "/" ]] \
    || fail 'TARGET_DIR must be an absolute non-root path.'
[[ -d "$target_dir" && -f "$target_dir/artisan" && -d "$target_dir/.git" ]] \
    || fail 'TARGET_DIR is not a deployed Laravel application.'
[[ -f "$target_dir/.env" ]] || fail 'Production .env is missing.'
[[ -f "$graph_archive" ]] || fail 'Verified graph archive is missing.'
[[ -f "$source_archive" ]] || fail 'Verified source archive is missing.'

target_dir="$(realpath -- "$target_dir")"
graph_archive="$(realpath -- "$graph_archive")"
source_archive="$(realpath -- "$source_archive")"
application_owner="$(stat -c '%U' "$target_dir")"
application_group="$(id -gn "$application_owner")"
php_binary="$(command -v php)"

[[ -n "$application_owner" && "$application_owner" != "root" ]] \
    || fail 'Application owner must be a non-root user.'

actual_checksum="$(sha256sum "$graph_archive" | awk '{print $1}')"
[[ "$actual_checksum" == "$graph_checksum" ]] || fail 'Graph archive checksum mismatch.'
actual_source_checksum="$(sha256sum "$source_archive" | awk '{print $1}')"
[[ "$actual_source_checksum" == "$source_checksum" ]] || fail 'Source archive checksum mismatch.'

archive_entries=0
while IFS= read -r archive_entry; do
    archive_entry="${archive_entry#./}"
    [[ -n "$archive_entry" ]] || continue
    ((archive_entries += 1))

    [[ "$archive_entry" == "$snapshot" || "$archive_entry" == "$snapshot/"* ]] \
        || fail 'Graph archive contains a path outside the selected snapshot.'
    [[ "$archive_entry" != /* && "/${archive_entry}/" != *"/../"* ]] \
        || fail 'Graph archive contains an unsafe path.'
done < <(tar -tzf "$graph_archive")
(( archive_entries > 0 )) || fail 'Graph archive is empty.'

source_entries=0
while IFS= read -r source_entry; do
    source_entry="${source_entry#./}"
    [[ -n "$source_entry" ]] || continue
    ((source_entries += 1))

    [[ "$source_entry" == "$snapshot" || "$source_entry" == "$snapshot/"* ]] \
        || fail 'Source archive contains a path outside the selected snapshot.'
    [[ "$source_entry" != /* && "/${source_entry}/" != *"/../"* ]] \
        || fail 'Source archive contains an unsafe path.'
done < <(tar -tzf "$source_archive")
(( source_entries > 0 )) || fail 'Source archive is empty.'

log 'Installing the pinned production runtime prerequisites.'
sudo apt-get update
sudo env DEBIAN_FRONTEND=noninteractive apt-get install -y \
    docker.io \
    docker-compose-v2 \
    php8.4-redis
sudo systemctl enable --now docker

"$php_binary" -r 'exit(class_exists("Redis") ? 0 : 1);' \
    || fail 'The PHP Redis extension is not active for CLI PHP.'
sudo docker version >/dev/null
sudo docker compose version >/dev/null

valhalla_dir="$target_dir/infrastructure/valhalla"
data_dir="$valhalla_dir/data"
graphs_dir="$data_dir/graphs"
sources_dir="$data_dir/sources"
final_graph_dir="$graphs_dir/$snapshot"
final_source_dir="$sources_dir/$snapshot"
mkdir -p "$graphs_dir" "$sources_dir"

if [[ -d "$final_source_dir" ]]; then
    [[ -s "$final_source_dir/SHA256SUMS" ]] \
        || fail 'An incomplete source archive already exists for this snapshot.'
    log "OSM sources ${snapshot} already exist; reusing the immutable files."
else
    incoming_sources="$(mktemp -d "$sources_dir/.${snapshot}.incoming.XXXXXXXX")"
    cleanup_sources() {
        if [[ -d "${incoming_sources:-}" ]]; then
            rm -rf -- "$incoming_sources"
        fi
    }
    trap cleanup_sources EXIT

    tar -xzf "$source_archive" \
        --directory "$incoming_sources" \
        --no-same-owner \
        --no-same-permissions
    extracted_sources="$incoming_sources/$snapshot"
    [[ -s "$extracted_sources/central-fed-district-${snapshot}.osm.pbf" ]] \
        || fail 'Central Federal District PBF is missing.'
    [[ -s "$extracted_sources/northwestern-fed-district-${snapshot}.osm.pbf" ]] \
        || fail 'Northwestern Federal District PBF is missing.'
    [[ -s "$extracted_sources/SHA256SUMS" ]] || fail 'Source SHA256SUMS is missing.'
    if find "$extracted_sources" -type l -print -quit | grep -q .; then
        fail 'Extracted sources must not contain symbolic links.'
    fi
    (
        cd "$extracted_sources"
        sha256sum --check SHA256SUMS
    )
    printf '%s  %s\n' "$source_checksum" "$(basename "$source_archive")" \
        > "$extracted_sources/source-archive.sha256"

    mv "$extracted_sources" "$final_source_dir"
    cleanup_sources
    trap - EXIT
fi

if [[ -d "$final_graph_dir" ]]; then
    [[ -s "$final_graph_dir/valhalla_tiles.tar" && -s "$final_graph_dir/build-metadata.json" ]] \
        || fail 'An incomplete graph already exists for this snapshot.'
    log "Graph ${snapshot} already exists; reusing the immutable verified files."
else
    incoming_dir="$(mktemp -d "$graphs_dir/.${snapshot}.incoming.XXXXXXXX")"
    cleanup_incoming() {
        if [[ -d "${incoming_dir:-}" ]]; then
            rm -rf -- "$incoming_dir"
        fi
    }
    trap cleanup_incoming EXIT

    tar -xzf "$graph_archive" \
        --directory "$incoming_dir" \
        --no-same-owner \
        --no-same-permissions
    extracted_graph="$incoming_dir/$snapshot"
    [[ -s "$extracted_graph/valhalla_tiles.tar" && -s "$extracted_graph/build-metadata.json" ]] \
        || fail 'Extracted graph is incomplete.'
    if find "$extracted_graph" -type l -print -quit | grep -q .; then
        fail 'Extracted graph must not contain symbolic links.'
    fi

    printf '%s  %s\n' "$graph_checksum" "$(basename "$graph_archive")" \
        > "$extracted_graph/source-archive.sha256"
    mv "$extracted_graph" "$final_graph_dir"
    cleanup_incoming
    trap - EXIT
fi

if [[ ! -f "$valhalla_dir/.env" ]]; then
    install -m 0640 "$valhalla_dir/.env.example" "$valhalla_dir/.env"
fi

"$php_binary" -r '
$path = $argv[1];
$snapshot = $argv[2];
$updates = [
    "VALHALLA_IMAGE" => "ghcr.io/valhalla/valhalla-scripted:3.6.3",
    "VALHALLA_PORT" => "8002",
    "VALHALLA_SERVER_THREADS" => "1",
    "VALHALLA_BUILD_THREADS" => "2",
    "OSM_SNAPSHOT" => $snapshot,
    "VALHALLA_ACTIVE_GRAPH" => "./data/current",
];
$contents = file_get_contents($path);
foreach ($updates as $key => $value) {
    $line = $key."=".$value;
    $pattern = "/^".preg_quote($key, "/")."=.*$/m";
    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, $line, $contents);
    } else {
        $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }
}
if (file_put_contents($path, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to update Valhalla environment.\n");
    exit(1);
}
' "$valhalla_dir/.env" "$snapshot"

log "Activating Valhalla graph ${snapshot} and running route/matrix smoke tests."
sudo "$valhalla_dir/scripts/activate-graph.sh" "$snapshot"

production_env="$target_dir/.env"
environment_backup_dir="$target_dir/storage/app/.routing-env-backups"
mkdir -p "$environment_backup_dir"
environment_backup="$environment_backup_dir/.env-$(date -u +%Y%m%dT%H%M%SZ)"
install -m 0600 "$production_env" "$environment_backup"

"$php_binary" -r '
$path = $argv[1];
$snapshot = $argv[2];
$updates = [
    "REDIS_CLIENT" => "phpredis",
    "LOGISTICS_ROUTING_DRIVER" => "valhalla",
    "LOGISTICS_ROUTING_QUEUE" => "routing",
    "LOGISTICS_ROUTING_QUEUE_CONNECTION" => "redis-routing",
    "LOGISTICS_ROUTING_REDIS_CONNECTION" => "default",
    "LOGISTICS_ROUTING_RETRY_AFTER" => "180",
    "LOGISTICS_ROUTING_LOCK_STORE" => "redis",
    "LOGISTICS_OSM_DATA_VERSION" => $snapshot,
    "VALHALLA_ENGINE_VERSION" => "3.6.3",
    "VALHALLA_BASE_URL" => "http://127.0.0.1:8002",
    "VALHALLA_CONNECT_TIMEOUT" => "3",
    "VALHALLA_TIMEOUT" => "30",
    "VALHALLA_RETRY_TIMES" => "2",
    "VALHALLA_RETRY_DELAY_MS" => "250",
];
$contents = file_get_contents($path);
foreach ($updates as $key => $value) {
    $line = $key."=".$value;
    $pattern = "/^".preg_quote($key, "/")."=.*$/m";
    if (preg_match($pattern, $contents)) {
        $contents = preg_replace($pattern, $line, $contents);
    } else {
        $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
    }
}
if (file_put_contents($path, $contents, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to update production environment.\n");
    exit(1);
}
' "$production_env" "$snapshot"

cd "$target_dir"
"$php_binary" artisan optimize:clear
"$php_binary" artisan config:cache
"$php_binary" artisan route:cache
"$php_binary" artisan view:cache
"$php_binary" artisan logistics:routing-recover-stuck --older-than=15

unit_staging="$(mktemp)"
sed \
    -e "s|^User=.*$|User=${application_owner}|" \
    -e "s|^Group=.*$|Group=${application_group}|" \
    -e "s|^WorkingDirectory=.*$|WorkingDirectory=${target_dir}|" \
    -e "s|^ExecStart=.*$|ExecStart=${php_binary} artisan queue:work redis-routing --queue=routing --sleep=2 --tries=3 --timeout=120 --max-time=3600|" \
    "$target_dir/deploy/systemd/pischeprom-routing-worker.service.example" \
    > "$unit_staging"
sudo install -m 0644 "$unit_staging" /etc/systemd/system/pischeprom-routing-worker.service
rm -- "$unit_staging"

sudo systemctl daemon-reload
sudo systemctl enable --now pischeprom-routing-worker

if sudo systemctl cat php8.4-fpm.service >/dev/null 2>&1; then
    sudo systemctl restart php8.4-fpm
fi

sudo chown -R "${application_owner}:${application_group}" "$data_dir"

"$php_binary" artisan logistics:routing-health
sudo systemctl is-active --quiet pischeprom-routing-worker \
    || fail 'The routing queue worker did not become active.'

log "Production routing is ready with OSM snapshot ${snapshot}."
log "Previous Laravel environment saved at ${environment_backup}."
