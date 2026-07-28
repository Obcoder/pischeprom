#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command curl
snapshot="${1:-${OSM_SNAPSHOT:-}}"
validate_snapshot "${snapshot}"

target_dir="${DATA_DIR}/sources/${snapshot}"
mkdir -p "${target_dir}"
regions=(central-fed-district northwestern-fed-district)

for region in "${regions[@]}"; do
    filename="${region}-${snapshot}.osm.pbf"
    base_url="https://download.geofabrik.de/russia/${filename}"
    checksum_file="${target_dir}/${filename}.md5"
    pbf_file="${target_dir}/${filename}"

    echo "Downloading published checksum for ${filename}"
    curl --fail --location --silent --show-error "${base_url}.md5" --output "${checksum_file}.part"
    mv "${checksum_file}.part" "${checksum_file}"

    if [[ ! -f "${pbf_file}" ]]; then
        echo "Downloading immutable OSM extract ${filename}"
        curl --fail --location --show-error --continue-at - "${base_url}" --output "${pbf_file}.part"
        mv "${pbf_file}.part" "${pbf_file}"
    fi

    expected="$(published_md5 "${checksum_file}")"
    actual="$(local_md5 "${pbf_file}")"
    if [[ -z "${expected}" || "${expected}" != "${actual}" ]]; then
        echo "MD5 mismatch for ${filename}; refusing to build." >&2
        exit 1
    fi
    echo "Verified ${filename}: ${actual}"
done

{
    for region in "${regions[@]}"; do
        local_sha256 "${target_dir}/${region}-${snapshot}.osm.pbf"
    done
} > "${target_dir}/SHA256SUMS"

echo "Sources ready in ${target_dir}"
