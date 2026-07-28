#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command docker
require_command php
current_link="${DATA_DIR}/current"
previous_link="${DATA_DIR}/previous"
[[ -L "${current_link}" && -L "${previous_link}" ]] || { echo "Both current and previous graph links are required." >&2; exit 1; }

current_target="$(readlink "${current_link}")"
previous_target="$(readlink "${previous_link}")"
current_next="${DATA_DIR}/.current.rollback"
previous_next="${DATA_DIR}/.previous.rollback"
ln -s "${previous_target}" "${current_next}"
ln -s "${current_target}" "${previous_next}"
atomic_replace_symlink "${current_next}" "${current_link}"
atomic_replace_symlink "${previous_next}" "${previous_link}"
compose up --detach --force-recreate valhalla
"${SCRIPT_DIR}/smoke-test.sh" "" "http://127.0.0.1:${VALHALLA_PORT:-8002}"
echo "Rollback completed. Synchronize LOGISTICS_OSM_DATA_VERSION with $(basename "${previous_target}")."
