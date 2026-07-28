#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command docker
require_command php
snapshot="${1:-}"
validate_snapshot "${snapshot}"
graph_dir="${DATA_DIR}/graphs/${snapshot}"
current_link="${DATA_DIR}/current"
previous_link="${DATA_DIR}/previous"
next_link="${DATA_DIR}/.current.next"

[[ -s "${graph_dir}/build-metadata.json" ]] || { echo "Verified graph metadata is missing: ${graph_dir}" >&2; exit 1; }
[[ -s "${graph_dir}/valhalla_tiles.tar" ]] || { echo "Graph archive is missing: ${graph_dir}" >&2; exit 1; }
if [[ -e "${current_link}" && ! -L "${current_link}" ]]; then
    echo "Refusing to replace a non-symlink current path." >&2
    exit 1
fi

old_target=""
if [[ -L "${current_link}" ]]; then
    old_target="$(readlink "${current_link}")"
    previous_next="${DATA_DIR}/.previous.next"
    ln -s "${old_target}" "${previous_next}"
    atomic_replace_symlink "${previous_next}" "${previous_link}"
fi

ln -s "${graph_dir}" "${next_link}"
atomic_replace_symlink "${next_link}" "${current_link}"

if ! compose up --detach --force-recreate valhalla || ! "${SCRIPT_DIR}/smoke-test.sh" "" "http://127.0.0.1:${VALHALLA_PORT:-8002}"; then
    echo "Activation failed; restoring the previous graph." >&2
    if [[ -n "${old_target}" ]]; then
        rollback_next="${DATA_DIR}/.rollback.next"
        ln -s "${old_target}" "${rollback_next}"
        atomic_replace_symlink "${rollback_next}" "${current_link}"
        compose up --detach --force-recreate valhalla
    fi
    exit 1
fi

echo "OSM graph ${snapshot} is active. Set LOGISTICS_OSM_DATA_VERSION=${snapshot} in Laravel and restart workers."
