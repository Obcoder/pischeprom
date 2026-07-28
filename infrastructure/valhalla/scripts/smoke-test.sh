#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command curl
graph_dir="${1:-}"
base_url="${2:-}"
container_name=""

cleanup() {
    if [[ -n "${container_name}" ]]; then
        docker rm --force "${container_name}" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

if [[ -z "${base_url}" ]]; then
    if [[ -z "${graph_dir}" || ! -d "${graph_dir}" ]]; then
        echo "Usage: smoke-test.sh GRAPH_DIR [BASE_URL]" >&2
        exit 1
    fi
    require_command docker
    smoke_port="${VALHALLA_SMOKE_PORT:-18002}"
    container_name="pischeprom-valhalla-smoke-$$_${RANDOM}"
    docker run --detach --rm \
        --name "${container_name}" \
        --publish "127.0.0.1:${smoke_port}:8002" \
        --volume "${graph_dir}:/custom_files:ro" \
        --env use_tiles_ignore_pbf=True \
        --env force_rebuild=False \
        --env serve_tiles=True \
        --env build_admins=False \
        --env build_time_zones=False \
        --env build_tar=False \
        --env update_existing_config=False \
        --env use_default_speeds_config=False \
        "${VALHALLA_IMAGE:-ghcr.io/valhalla/valhalla-scripted:3.6.3}" >/dev/null
    base_url="http://127.0.0.1:${smoke_port}"
fi

for attempt in $(seq 1 60); do
    if curl --fail --silent "${base_url}/status" >/dev/null; then
        break
    fi
    if [[ "${attempt}" -eq 60 ]]; then
        echo "Valhalla did not become healthy within 120 seconds." >&2
        exit 1
    fi
    sleep 2
done

route_response="$(curl --fail --silent --show-error \
    --header 'Content-Type: application/json' \
    --data '{"locations":[{"lat":59.9343,"lon":30.3351},{"lat":55.7558,"lon":37.6173}],"costing":"truck","units":"kilometers"}' \
    "${base_url}/route")"
matrix_response="$(curl --fail --silent --show-error \
    --header 'Content-Type: application/json' \
    --data '{"sources":[{"lat":59.9343,"lon":30.3351}],"targets":[{"lat":55.7558,"lon":37.6173}],"costing":"truck","units":"kilometers","verbose":false}' \
    "${base_url}/sources_to_targets")"

ROUTE_JSON="${route_response}" MATRIX_JSON="${matrix_response}" php -r '
$route = json_decode((string) getenv("ROUTE_JSON"), true, flags: JSON_THROW_ON_ERROR);
$matrix = json_decode((string) getenv("MATRIX_JSON"), true, flags: JSON_THROW_ON_ERROR);
$length = $route["trip"]["summary"]["length"] ?? null;
$distance = $matrix["sources_to_targets"]["distances"][0][0] ?? null;
if (!is_numeric($length) || $length <= 0 || !is_numeric($distance) || $distance <= 0) {
    fwrite(STDERR, "Smoke route or matrix has no positive automobile distance.\n");
    exit(1);
}
printf("Truck smoke passed: route=%.1f km, matrix=%.1f km\n", $length, $distance);
'
