#!/usr/bin/env bash

set -Eeuo pipefail
IFS=$'\n\t'
umask 027

GIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GIS_INFRA_DIR="$(cd "${GIS_SCRIPT_DIR}/.." && pwd)"
GIS_BASE_DIR="${GIS_BASE_DIR:-/srv/pischeprom-gis}"
GIS_SOURCE_DIR="${GIS_SOURCE_DIR:-${GIS_BASE_DIR}/sources}"
GIS_STAGING_DIR="${GIS_STAGING_DIR:-${GIS_BASE_DIR}/staging}"
GIS_RELEASES_DIR="${GIS_RELEASES_DIR:-${GIS_BASE_DIR}/releases}"
GIS_LOG_DIR="${GIS_LOG_DIR:-${GIS_BASE_DIR}/logs}"
GIS_STATE_DIR="${GIS_STATE_DIR:-${GIS_BASE_DIR}/state}"
GIS_LOCK_DIR="${GIS_LOCK_DIR:-${GIS_BASE_DIR}/locks}"
GIS_PBF_URL="${GIS_PBF_URL:-https://download.geofabrik.de/russia-latest.osm.pbf}"
GIS_PBF_MD5_URL="${GIS_PBF_MD5_URL:-https://download.geofabrik.de/russia-latest.osm.pbf.md5}"
GIS_PBF_INDEX_URL="${GIS_PBF_INDEX_URL:-https://download.geofabrik.de/russia.html}"
VALHALLA_STATUS_URL="${VALHALLA_STATUS_URL:-http://127.0.0.1:8002/status}"

gis_log() {
    printf '[logistics-gis] %s\n' "$*"
}

gis_fail() {
    printf '[logistics-gis] ERROR: %s\n' "$*" >&2
    exit 1
}

gis_require_command() {
    command -v "$1" >/dev/null 2>&1 || gis_fail "Required command is unavailable: $1"
}

gis_validate_release() {
    [[ "${1:-}" =~ ^russia-[0-9]{8}$ ]] \
        || gis_fail 'Release must use russia-YYYYMMDD format.'
}

gis_realpath_existing() {
    realpath -- "$1"
}

gis_assert_inside_base() {
    local candidate="$1"
    local base_resolved candidate_resolved
    base_resolved="$(realpath -- "${GIS_BASE_DIR}")"
    candidate_resolved="$(realpath -- "$candidate")"
    [[ "$candidate_resolved" == "$base_resolved/"* ]] \
        || gis_fail "Resolved path is outside GIS_BASE_DIR: ${candidate_resolved}"
    printf '%s\n' "$candidate_resolved"
}

gis_file_size() {
    if [[ -f "$1" ]]; then
        stat -c '%s' -- "$1"
    else
        printf '0\n'
    fi
}

gis_directory_size() {
    if [[ -d "$1" ]]; then
        local resolved
        resolved="$(realpath -- "$1")"
        du -sb -- "$resolved" | awk '{print $1}'
    else
        printf '0\n'
    fi
}

gis_low_priority() {
    if command -v nice >/dev/null 2>&1 && command -v ionice >/dev/null 2>&1; then
        nice -n "${GIS_BUILD_NICE:-10}" ionice -c2 -n "${GIS_BUILD_IONICE:-7}" "$@"
    else
        "$@"
    fi
}

gis_md5() {
    gis_low_priority md5sum -- "$1" | awk '{print $1}'
}

gis_sha256() {
    gis_low_priority sha256sum -- "$1" | awk '{print $1}'
}

gis_atomic_symlink() {
    local target="$1"
    local link="$2"
    local temporary="${link}.next.$$"
    [[ -e "$target" ]] || gis_fail "Symlink target does not exist: ${target}"
    [[ ! -e "$link" || -L "$link" ]] || gis_fail "Refusing to replace non-symlink path: ${link}"
    ln -s -- "$target" "$temporary"
    mv -Tf -- "$temporary" "$link"
}

gis_verified_pbf_manifest() {
    local pbf="$1"
    local manifest="${pbf%.osm.pbf}.manifest.json"
    local release="${pbf##*/}"
    release="${release%.osm.pbf}"
    gis_validate_release "$release"
    [[ -f "$pbf" && -s "$pbf" && -f "$manifest" && -s "$manifest" && ! -L "$manifest" ]] \
        || gis_fail 'Verified regular PBF and manifest files are required.'
    local manifest_valid
    manifest_valid="$(MANIFEST="$manifest" EXPECTED_RELEASE="$release" EXPECTED_SIZE="$(gis_file_size "$pbf")" php -r '
        try{$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);}catch(Throwable){echo "no";exit;}
        echo (($v["verified"]??false)===true&&($v["release"]??null)===getenv("EXPECTED_RELEASE")&&(int)($v["size_bytes"]??0)===(int)getenv("EXPECTED_SIZE"))?"yes":"no";
    ')"
    [[ "$manifest_valid" == "yes" ]] || gis_fail 'PBF manifest is invalid or does not match the immutable source file.'
    printf '%s\n' "$manifest"
}

gis_validate_valhalla_restart() {
    local unit="${VALHALLA_SYSTEMD_UNIT:-}"
    local helper="${VALHALLA_RESTART_HELPER:-}"

    if [[ -n "$unit" ]]; then
        [[ "$unit" != */* ]] || gis_fail 'VALHALLA_SYSTEMD_UNIT must be an exact unit name, not a path.'
        command -v systemctl >/dev/null 2>&1 || gis_fail 'systemctl is required for the configured Valhalla unit.'
        systemctl cat "$unit" >/dev/null || gis_fail "Existing Valhalla systemd unit is unavailable: ${unit}"
        return
    fi

    [[ "$helper" == /* && -x "$helper" && ! -L "$helper" ]] \
        || gis_fail 'Set VALHALLA_SYSTEMD_UNIT or an absolute, executable, non-symlink VALHALLA_RESTART_HELPER for the audited existing runtime.'
}

gis_validated_valhalla_listen() {
    local listen="${VALHALLA_SERVICE_LISTEN:-tcp://127.0.0.1:8002}"
    local port=""

    if [[ "$listen" =~ ^tcp://127\.0\.0\.1:([0-9]{1,5})$ ]]; then
        port="${BASH_REMATCH[1]}"
    elif [[ "$listen" =~ ^tcp://0\.0\.0\.0:([0-9]{1,5})$ \
        && "${VALHALLA_ALLOW_WILDCARD_LISTEN:-false}" == "true" ]]
    then
        port="${BASH_REMATCH[1]}"
    else
        gis_fail 'VALHALLA_SERVICE_LISTEN must use loopback, or an explicitly approved wildcard bind inside the audited private runtime.'
    fi

    (( port >= 1 && port <= 65535 )) || gis_fail 'VALHALLA_SERVICE_LISTEN contains an invalid TCP port.'
    printf '%s\n' "$listen"
}

gis_restart_valhalla() {
    gis_validate_valhalla_restart
    if [[ -n "${VALHALLA_SYSTEMD_UNIT:-}" ]]; then
        systemctl restart "$VALHALLA_SYSTEMD_UNIT"
    else
        "$VALHALLA_RESTART_HELPER"
    fi
}
