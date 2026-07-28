#!/usr/bin/env bash
set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

require_command docker
snapshot="${1:-${OSM_SNAPSHOT:-}}"
validate_snapshot "${snapshot}"

source_dir="${DATA_DIR}/sources/${snapshot}"
staging_dir="${DATA_DIR}/graphs/${snapshot}.staging"
final_dir="${DATA_DIR}/graphs/${snapshot}"

if [[ ! -f "${source_dir}/SHA256SUMS" ]]; then
    echo "Run scripts/download-osm.sh ${snapshot} first." >&2
    exit 1
fi
if [[ -e "${staging_dir}" || -e "${final_dir}" ]]; then
    echo "Graph target already exists. Choose another snapshot or remove the exact failed staging directory manually." >&2
    exit 1
fi

mkdir -p "${staging_dir}"
for region in central-fed-district northwestern-fed-district; do
    source_file="${source_dir}/${region}-${snapshot}.osm.pbf"
    expected="$(published_md5 "${source_file}.md5")"
    actual="$(local_md5 "${source_file}")"
    [[ "${expected}" == "${actual}" ]] || { echo "Checksum mismatch: ${source_file}" >&2; exit 1; }
    ln "${source_file}" "${staging_dir}/$(basename "${source_file}")" 2>/dev/null \
        || cp "${source_file}" "${staging_dir}/$(basename "${source_file}")"
done

started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
started_epoch="$(date +%s)"
echo "Building Valhalla ${VALHALLA_IMAGE:-ghcr.io/valhalla/valhalla-scripted:3.6.3} for OSM ${snapshot}"
VALHALLA_BUILD_GRAPH="${staging_dir}" compose --profile build run --rm valhalla-build

[[ -s "${staging_dir}/valhalla_tiles.tar" ]] || { echo "valhalla_tiles.tar was not created." >&2; exit 1; }
[[ -s "${staging_dir}/valhalla.json" ]] || { echo "valhalla.json was not created." >&2; exit 1; }

# The upstream default (400 km) rejects ordinary interregional matrix requests,
# including Saint Petersburg ↔ Voronezh. Keep route and matrix limits aligned.
CONFIG_PATH="${staging_dir}/valhalla.json" php -r '
$path = (string) getenv("CONFIG_PATH");
$nextPath = $path.".next";
$config = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
foreach (["auto", "truck"] as $profile) {
    if (!isset($config["service_limits"][$profile])
        || !is_array($config["service_limits"][$profile])) {
        fwrite(STDERR, "Missing Valhalla service limits for {$profile}.\n");
        exit(1);
    }
    $config["service_limits"][$profile]["max_matrix_distance"] = 5000000.0;
}
$json = json_encode(
    $config,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
).PHP_EOL;
if (file_put_contents($nextPath, $json, LOCK_EX) === false || !rename($nextPath, $path)) {
    @unlink($nextPath);
    fwrite(STDERR, "Unable to atomically update Valhalla service limits.\n");
    exit(1);
}
'

# The verified PBF source archive stays under data/sources; runtime graphs do not duplicate it.
for region in central-fed-district northwestern-fed-district; do
    rm -- "${staging_dir}/${region}-${snapshot}.osm.pbf"
done

"${SCRIPT_DIR}/smoke-test.sh" "${staging_dir}"
finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
duration="$(( $(date +%s) - started_epoch ))"
size_bytes="$(du -sk "${staging_dir}" | awk '{print $1 * 1024}')"

SNAPSHOT="${snapshot}" \
IMAGE="${VALHALLA_IMAGE:-ghcr.io/valhalla/valhalla-scripted:3.6.3}" \
STARTED="${started_at}" \
FINISHED="${finished_at}" \
DURATION="${duration}" \
SIZE="${size_bytes}" \
TARGET="${staging_dir}" \
php -r '$metadata = [
    "osm_data_version" => getenv("SNAPSHOT"),
    "valhalla_image" => getenv("IMAGE"),
    "started_at" => getenv("STARTED"),
    "finished_at" => getenv("FINISHED"),
    "duration_seconds" => (int) getenv("DURATION"),
    "graph_size_bytes" => (int) getenv("SIZE"),
]; file_put_contents(getenv("TARGET")."/build-metadata.json", json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);'

mv "${staging_dir}" "${final_dir}"
echo "Graph verified and ready: ${final_dir} (${size_bytes} bytes, ${duration} seconds)"
echo "Activate with: ${SCRIPT_DIR}/activate-graph.sh ${snapshot}"
