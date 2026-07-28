#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALHALLA_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DATA_DIR="${VALHALLA_DATA_DIR:-${VALHALLA_DIR}/data}"
COMPOSE_FILE="${VALHALLA_DIR}/compose.yml"

require_command() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "Required command is missing: $1" >&2
        exit 1
    }
}

atomic_replace_symlink() {
    local source_link="$1"
    local target_link="$2"

    [[ -L "${source_link}" ]] || {
        echo "Atomic replacement source is not a symlink: ${source_link}" >&2
        exit 1
    }

    php -r '
        if (! rename($argv[1], $argv[2])) {
            fwrite(STDERR, "Unable to atomically replace symlink: {$argv[2]}\n");
            exit(1);
        }
    ' "${source_link}" "${target_link}"
}

validate_snapshot() {
    local snapshot="$1"
    if [[ ! "${snapshot}" =~ ^[0-9]{6}$ ]]; then
        echo "OSM snapshot must be an immutable YYMMDD value, for example 260725." >&2
        exit 1
    fi
}

published_md5() {
    awk '{print $1}' "$1"
}

local_md5() {
    if command -v md5sum >/dev/null 2>&1; then
        md5sum "$1" | awk '{print $1}'
    else
        md5 -q "$1"
    fi
}

local_sha256() {
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$1"
    else
        shasum -a 256 "$1"
    fi
}

compose() {
    docker compose --env-file "${VALHALLA_DIR}/.env" -f "${COMPOSE_FILE}" "$@"
}
