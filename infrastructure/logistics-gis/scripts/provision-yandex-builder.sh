#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

fail() {
    printf '[logistics-gis] ERROR: %s\n' "$*" >&2
    exit 1
}

log() {
    printf '[logistics-gis] %s\n' "$*"
}

[[ "$(uname -s)" == 'Linux' && "$(uname -m)" == 'x86_64' ]] \
    || fail 'Yandex builder provisioning supports Linux x86_64 only.'
[[ "$EUID" -eq 0 ]] || fail 'Yandex builder provisioning must run as root.'

for command_name in apt-get awk bash chmod chown cmp cp find getent grep install ln mktemp mv readlink rm sed sha256sum sort sudo systemctl uname useradd xargs; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Required builder provisioning command is unavailable: ${command_name}"
done

source_dir="${GIS_PROVISION_SOURCE_DIR:-}"
source_commit="${GIS_PROVISION_SOURCE_COMMIT:-}"
map_bucket="${YC_MAP_BUCKET:-}"
private_bucket="${YC_GIS_PRIVATE_BUCKET:-}"
application_origin="${LOGISTICS_APP_ORIGIN:-}"

[[ "$source_dir" == /* && -d "$source_dir" && ! -L "$source_dir" ]] \
    || fail 'GIS_PROVISION_SOURCE_DIR must be an absolute real directory.'
source_dir="$(readlink -f -- "$source_dir")"
[[ "$source_commit" =~ ^[0-9a-f]{40}$ ]] \
    || fail 'GIS_PROVISION_SOURCE_COMMIT must be an exact lowercase Git commit.'
[[ "$map_bucket" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ \
    && "$private_bucket" =~ ^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$ \
    && "$map_bucket" != "$private_bucket" ]] \
    || fail 'Yandex Object Storage bucket variables are missing or invalid.'
[[ "$application_origin" =~ ^https://[a-z0-9.-]+(:[0-9]{1,5})?$ ]] \
    || fail 'LOGISTICS_APP_ORIGIN must be one exact HTTPS origin.'
[[ -x "$source_dir/scripts/install-gis-toolchain.sh" \
    && -x "$source_dir/scripts/install-map-assets.sh" \
    && -x "$source_dir/scripts/preflight.sh" \
    && -x "$source_dir/scripts/run-builder-pipeline.sh" \
    && -f "$source_dir/systemd/pischeprom-valhalla.service.example" \
    && -f "$source_dir/systemd/pischeprom-gis-build.service.example" ]] \
    || fail 'The audited GIS source bundle is incomplete.'
[[ -z "$(find "$source_dir" -type l -print -quit)" ]] \
    || fail 'The audited GIS source bundle must not contain symlinks.'

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install --yes --no-install-recommends \
    ca-certificates \
    curl \
    git \
    jq \
    openjdk-21-jre-headless \
    php-cli \
    python3-venv \
    spatialite-bin \
    time \
    unzip

for command_name in curl git java jq php python3 spatialite spatialite_tool unzip; do
    command -v "$command_name" >/dev/null 2>&1 \
        || fail "Installed builder prerequisite is unavailable: ${command_name}"
done

service_user='pischeprom-gis'
service_group='pischeprom-gis'
gis_base='/srv/pischeprom-gis'
operations_root='/opt/pischeprom-gis-operations'
operations_releases="${operations_root}/releases"
operations_release="${operations_releases}/${source_commit}"

if ! getent passwd "$service_user" >/dev/null; then
    useradd \
        --system \
        --user-group \
        --home-dir "$gis_base" \
        --shell /usr/sbin/nologin \
        "$service_user"
fi
[[ "$(getent passwd "$service_user" | awk -F: '{print $1":"$6":"$7}')" \
    == "${service_user}:${gis_base}:/usr/sbin/nologin" ]] \
    || fail 'Existing pischeprom-gis account differs from the managed service account.'
[[ "$(getent group "$service_group" | awk -F: '{print $1}')" == "$service_group" ]] \
    || fail 'Managed pischeprom-gis group is unavailable.'

install -d -m 0755 -o root -g root -- "$operations_root" "$operations_releases"
install -d -m 0750 -o "$service_user" -g "$service_group" -- \
    "$gis_base" \
    "$gis_base/sources" \
    "$gis_base/staging" \
    "$gis_base/releases" \
    "$gis_base/logs" \
    "$gis_base/state" \
    "$gis_base/locks"

manifest_for() {
    local directory="$1"
    (
        cd "$directory"
        find . -type f \
            ! -path './.source-sha256' \
            ! -path './SOURCE_COMMIT' \
            -print0 \
            | LC_ALL=C sort -z \
            | xargs -0 -r sha256sum
    )
}

source_manifest="$(mktemp)"
existing_manifest="$(mktemp)"
release_stage=''
cleanup() {
    rm -f -- "$source_manifest" "$existing_manifest"
    if [[ -n "$release_stage" && -d "$release_stage" && ! -L "$release_stage" ]]; then
        rm -r -- "$release_stage"
    fi
}
trap cleanup EXIT

manifest_for "$source_dir" > "$source_manifest"
[[ -s "$source_manifest" ]] || fail 'The audited GIS source manifest is empty.'

if [[ -e "$operations_release" ]]; then
    [[ -d "$operations_release" && ! -L "$operations_release" \
        && -f "$operations_release/SOURCE_COMMIT" \
        && -f "$operations_release/.source-sha256" \
        && "$(<"$operations_release/SOURCE_COMMIT")" == "$source_commit" ]] \
        || fail 'Existing operations release is incomplete or mutable.'
    manifest_for "$operations_release" > "$existing_manifest"
    cmp -s -- "$source_manifest" "$existing_manifest" \
        || fail 'Existing operations release differs from the audited Git source.'
    cmp -s -- "$source_manifest" "$operations_release/.source-sha256" \
        || fail 'Existing operations release manifest differs from its contents.'
else
    release_stage="$(mktemp -d "${operations_releases}/.${source_commit}.XXXXXXXX")"
    cp -a -- "$source_dir/." "$release_stage/"
    chown -R root:root -- "$release_stage"
    install -m 0444 -o root -g root -- "$source_manifest" "$release_stage/.source-sha256"
    printf '%s\n' "$source_commit" > "$release_stage/SOURCE_COMMIT"
    chmod 0444 "$release_stage/SOURCE_COMMIT"
    mv -- "$release_stage" "$operations_release"
    release_stage=''
fi

current_link="${operations_root}/current"
if [[ -e "$current_link" && ! -L "$current_link" ]]; then
    fail 'Operations current path is not a managed symlink.'
fi
if [[ -L "$current_link" ]]; then
    current_target="$(readlink -f -- "$current_link")"
    [[ "$current_target" == "${operations_releases}/"* ]] \
        || fail 'Operations current symlink points outside the managed releases root.'
fi
link_stage="${operations_root}/.current.${source_commit}.$$"
ln -s -- "$operations_release" "$link_stage"
mv -Tf -- "$link_stage" "$current_link"

"$operations_release/scripts/install-gis-toolchain.sh"
"$operations_release/scripts/install-map-assets.sh" /opt/pischeprom-map-assets

environment_stage="$(mktemp /etc/.pischeprom-gis.env.XXXXXXXX)"
trap 'rm -f -- "$source_manifest" "$existing_manifest" "$environment_stage"; if [[ -n "${release_stage:-}" && -d "$release_stage" && ! -L "$release_stage" ]]; then rm -r -- "$release_stage"; fi' EXIT
printf '%s\n' \
    'GIS_BASE_DIR=/srv/pischeprom-gis' \
    'GIS_PBF_URL=https://download.geofabrik.de/russia-latest.osm.pbf' \
    'GIS_PBF_MD5_URL=https://download.geofabrik.de/russia-latest.osm.pbf.md5' \
    'GIS_PBF_INDEX_URL=https://download.geofabrik.de/russia.html' \
    'GIS_APP_DISK_RESERVE_BYTES=21474836480' \
    'GIS_APP_RAM_RESERVE_BYTES=2147483648' \
    'GIS_PLANETILER_DISK_MULTIPLIER=10' \
    'GIS_PLANETILER_RAM_MULTIPLIER=0.5' \
    'GIS_VALHALLA_GRAPH_MULTIPLIER=3' \
    'GIS_VALHALLA_RAM_MULTIPLIER=0.75' \
    'GIS_PMTILES_MULTIPLIER=1.5' \
    'GIS_WARN_LOAD_PER_CORE=0.7' \
    'GIS_FAIL_LOAD_PER_CORE=1.0' \
    'GIS_REQUIRE_CURRENT_VALHALLA_HEALTH=false' \
    'GIS_BUILD_NICE=10' \
    'GIS_BUILD_IONICE=7' \
    'VALHALLA_STATUS_URL=http://127.0.0.1:8002/status' \
    'VALHALLA_EXPECTED_VERSION=3.6.3' \
    'VALHALLA_SERVICE_LISTEN=tcp://127.0.0.1:8002' \
    'VALHALLA_ALLOW_PRIVATE_NETWORK_LISTEN=false' \
    'VALHALLA_ALLOW_WILDCARD_LISTEN=false' \
    'VALHALLA_SYSTEMD_UNIT=pischeprom-valhalla.service' \
    'VALHALLA_SMOKE_PORT=18002' \
    'PLANETILER_JAR=/opt/planetiler/planetiler.jar' \
    'PLANETILER_VERSION=0.10.2' \
    'PLANETILER_JAR_SHA256=f310bd0413e2e4512b27f4046d418664e8e1d3bf31603c2a70e23de06c167e4d' \
    'GIS_PMTILES_CLI_VERSION=1.31.2' \
    'GIS_MAP_ASSETS_DIR=/opt/pischeprom-map-assets' \
    'GIS_OBJECT_STORAGE_CLI=yc' \
    "GIS_OBJECT_STORAGE_BUCKET=${map_bucket}" \
    'GIS_OBJECT_STORAGE_PREFIX=logistics' \
    'GIS_OBJECT_STORAGE_ENDPOINT=https://storage.yandexcloud.net' \
    'GIS_OBJECT_STORAGE_REGION=ru-central1' \
    "GIS_PUBLIC_ASSET_BASE_URL=https://${map_bucket}.storage.yandexcloud.net/logistics" \
    "GIS_MAP_APPLICATION_ORIGIN=${application_origin}" \
    'GIS_VERIFY_PUBLIC_PMTILES_SHA256=true' \
    'GIS_REQUIRE_OBJECT_STORAGE_PUBLICATION=true' \
    'GIS_REQUIRE_LOCAL_MAP_NGINX=false' \
    "GIS_PRIVATE_OBJECT_STORAGE_BUCKET=${private_bucket}" \
    > "$environment_stage"
install -m 0640 -o root -g "$service_group" -- "$environment_stage" /etc/pischeprom-gis.env
rm -f -- "$environment_stage"

install -m 0644 -o root -g root -- \
    "$operations_release/systemd/pischeprom-valhalla.service.example" \
    /etc/systemd/system/pischeprom-valhalla.service
build_unit_stage="$(mktemp /etc/systemd/system/.pischeprom-gis-build.service.XXXXXXXX)"
trap 'rm -f -- "$source_manifest" "$existing_manifest" "$build_unit_stage"; if [[ -n "${release_stage:-}" && -d "$release_stage" && ! -L "$release_stage" ]]; then rm -r -- "$release_stage"; fi' EXIT
sed "s|@GIS_OPERATIONS_RELEASE@|${operations_release}|g" \
    "$operations_release/systemd/pischeprom-gis-build.service.example" \
    > "$build_unit_stage"
[[ "$(grep -cF -- "$operations_release" "$build_unit_stage")" == '1' \
    && "$(grep -cF -- '@GIS_OPERATIONS_RELEASE@' "$build_unit_stage")" == '0' ]] \
    || fail 'Unable to render the commit-pinned GIS build systemd unit.'
install -m 0644 -o root -g root -- \
    "$build_unit_stage" /etc/systemd/system/pischeprom-gis-build.service
rm -f -- "$build_unit_stage"
systemctl daemon-reload

preflight_output="$(mktemp)"
preflight_stderr="$(mktemp)"
preflight_state="${gis_base}/state/last-preflight.json"
trap 'rm -f -- "$source_manifest" "$existing_manifest" "$preflight_output" "$preflight_stderr"; if [[ -n "${release_stage:-}" && -d "$release_stage" && ! -L "$release_stage" ]]; then rm -r -- "$release_stage"; fi' EXIT
[[ ! -e "$preflight_state" || ( -f "$preflight_state" && ! -L "$preflight_state" ) ]] \
    || fail 'Existing target-host preflight state is not a managed regular file.'
rm -f -- "$preflight_state"
set +e
sudo -u "$service_user" -- bash -c '
    set -Eeuo pipefail
    set -a
    source /etc/pischeprom-gis.env
    set +a
    exec /opt/pischeprom-gis-operations/current/scripts/preflight.sh --mode full --json
' > "$preflight_output" 2> "$preflight_stderr"
preflight_exit=$?
set -e
if [[ ! -s "$preflight_state" || ! -f "$preflight_state" || -L "$preflight_state" ]]; then
    if [[ -s "$preflight_stderr" ]]; then
        printf '[logistics-gis] Target-host preflight stderr follows:\n' >&2
        sed -n '1,80p' "$preflight_stderr" >&2
    fi
    fail "Target-host full preflight produced no trusted state with exit code ${preflight_exit}."
fi
php -r '
    $value=json_decode((string)file_get_contents($argv[1]),true,flags:JSON_THROW_ON_ERROR);
    if (($value["mode"]??null)!=="full"
        || !in_array($value["result"]??null,["PASS","WARN","FAIL"],true)
        || !is_int($value["exit_code"]??null)) {
        throw new RuntimeException("Target-host preflight state has an invalid schema.");
    }
    echo json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),PHP_EOL;
' "$preflight_state"
[[ "$preflight_exit" -eq 0 ]] \
    || fail "Target-host full preflight blocked provisioning with exit code ${preflight_exit}."

trap - EXIT
rm -f -- "$source_manifest" "$existing_manifest" "$preflight_output" "$preflight_stderr"
log "Provisioned checksum-pinned GIS builder operations from commit ${source_commit}; full target-host preflight PASS."
log 'Valhalla is loopback-only and remains disabled until a paired release passes smoke and activation checks.'
