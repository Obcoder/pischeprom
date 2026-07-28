#!/usr/bin/env bash
set -Eeuo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
snapshot="${1:-}"

if [[ -z "${snapshot}" ]]; then
    echo "Usage: update-graph.sh YYMMDD" >&2
    exit 1
fi

"${script_dir}/download-osm.sh" "${snapshot}"
"${script_dir}/build-graph.sh" "${snapshot}"
"${script_dir}/activate-graph.sh" "${snapshot}"
