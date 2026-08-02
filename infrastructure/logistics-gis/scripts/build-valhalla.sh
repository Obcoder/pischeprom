#!/usr/bin/env bash

set -Eeuo pipefail
source "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/common.sh"

[[ $# -eq 1 ]] || gis_fail 'Usage: build-valhalla.sh /absolute/path/russia-YYYYMMDD.osm.pbf'
pbf="$(gis_realpath_existing "$1")"
gis_assert_inside_base "$pbf" >/dev/null
manifest="$(gis_verified_pbf_manifest "$pbf")"
release="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo (string)($v["release"]??"");')"
expected_md5="$(MANIFEST="$manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true,flags:JSON_THROW_ON_ERROR);echo strtolower((string)($v["md5"]??""));')"
gis_validate_release "$release"

GIS_PREFLIGHT_PBF_PATH="$pbf" "${GIS_SCRIPT_DIR}/preflight.sh" --mode valhalla
for directory in "$GIS_STAGING_DIR" "$GIS_LOCK_DIR" "$GIS_LOG_DIR"; do
    [[ -d "$directory" && -w "$directory" ]] || gis_fail "Required writable directory is missing: ${directory}"
    gis_assert_inside_base "$directory" >/dev/null
done
[[ -x /usr/bin/time ]] || gis_fail 'GNU /usr/bin/time is required for peak-resource metrics.'
[[ -n "${VALHALLA_EXPECTED_VERSION:-}" \
    && "$(valhalla_service --version 2>&1 | head -n1)" == *"${VALHALLA_EXPECTED_VERSION}"* ]] \
    || gis_fail 'Installed Valhalla does not match VALHALLA_EXPECTED_VERSION.'
service_listen="$(gis_validated_valhalla_listen)"

exec 9>"${GIS_LOCK_DIR}/gis-build.lock"
flock -n 9 || gis_fail 'Another GIS build is already running.'
[[ "$expected_md5" =~ ^[0-9a-f]{32}$ && "$(gis_md5 "$pbf")" == "$expected_md5" ]] \
    || gis_fail 'Selected PBF no longer matches its verified manifest.'

[[ ! -e "${GIS_RELEASES_DIR}/${release}" ]] || gis_fail 'Immutable release already exists.'
release_stage="${GIS_STAGING_DIR}/${release}"
if [[ -e "$release_stage" ]]; then
    [[ -d "$release_stage" && ! -L "$release_stage" ]] || gis_fail 'Release staging path must be a real directory.'
else
    mkdir -- "$release_stage"
fi
release_stage="$(gis_assert_inside_base "$release_stage")"
component="${release_stage}/valhalla"
if [[ -e "$component" ]]; then
    [[ -d "$component" && ! -L "$component" ]] || gis_fail 'Valhalla component path must be a real directory.'
    component="$(gis_assert_inside_base "$component")"
fi
component_manifest="${component}/component-manifest.json"
if [[ -s "$component_manifest" ]]; then
    existing_md5="$(MANIFEST="$component_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["pbf_md5"]??""));')"
    [[ "$existing_md5" == "$expected_md5" ]] || gis_fail 'Existing staged graph belongs to another PBF.'
    existing_graph_sha="$(MANIFEST="$component_manifest" php -r '$v=json_decode((string)file_get_contents(getenv("MANIFEST")),true);echo strtolower((string)($v["graph_sha256"]??""));')"
    existing_listen="$(CONFIG="${component}/valhalla.json" php -r '$v=json_decode((string)file_get_contents(getenv("CONFIG")),true);echo (string)($v["httpd"]["service"]["listen"]??"");' 2>/dev/null || true)"
    [[ -s "${component}/valhalla_tiles.tar" && "$existing_graph_sha" =~ ^[0-9a-f]{64}$ \
        && "$(gis_sha256 "${component}/valhalla_tiles.tar")" == "$existing_graph_sha" ]] \
        || gis_fail 'Existing staged Valhalla graph failed checksum verification.'
    [[ "$existing_listen" == "$service_listen" ]] \
        || gis_fail 'Existing staged Valhalla config uses another listen address.'
    gis_log "Verified staged Valhalla component already exists: ${component}"
    exit 0
fi
[[ ! -e "$component" ]] || gis_fail 'Incomplete Valhalla staging directory exists; inspect it manually.'

mkdir -- "$component"
component="$(gis_assert_inside_base "$component")"
mkdir -- "$component/tiles"
config="${component}/valhalla.json"
metrics="${component}/resource-usage.log"
build_log="${GIS_LOG_DIR}/valhalla-${release}-$(date -u +%Y%m%dT%H%M%SZ).log"
started_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
started_epoch="$(date +%s)"

run_timed() {
    /usr/bin/time -v -a -o "$metrics" nice -n "${GIS_BUILD_NICE:-10}" ionice -c2 -n "${GIS_BUILD_IONICE:-7}" "$@"
}

{
    gis_log "Building native Valhalla graph ${release}; active service remains untouched."
    valhalla_build_config \
        --mjolnir-tile-dir "$component/tiles" \
        --mjolnir-tile-extract "$component/valhalla_tiles.tar" \
        --mjolnir-timezone "$component/tiles/timezones.sqlite" \
        --mjolnir-admin "$component/tiles/admins.sqlite" \
        > "$config"
    CONFIG_PATH="$config" SERVICE_LISTEN="$service_listen" php -r '
        $path=(string)getenv("CONFIG_PATH");
        $value=json_decode((string)file_get_contents($path),true,flags:JSON_THROW_ON_ERROR);
        if (!isset($value["httpd"]["service"]) || !is_array($value["httpd"]["service"])) {
            fwrite(STDERR,"Missing Valhalla httpd.service config.\n");
            exit(1);
        }
        $value["httpd"]["service"]["listen"]=(string)getenv("SERVICE_LISTEN");
        foreach (["auto","truck"] as $profile) {
            if (!isset($value["service_limits"][$profile]) || !is_array($value["service_limits"][$profile])) {
                fwrite(STDERR,"Missing service_limits for {$profile}.\n");
                exit(1);
            }
            $value["service_limits"][$profile]["max_matrix_distance"]=5000000.0;
        }
        $next=$path.".next";
        file_put_contents($next,json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX);
        rename($next,$path);
    '
    run_timed valhalla_build_timezones > "$component/tiles/timezones.sqlite"
    run_timed valhalla_build_admins -c "$config" "$pbf"
    run_timed valhalla_build_tiles -c "$config" "$pbf"
    run_timed valhalla_build_extract -c "$config" -v
} > >(tee -a "$build_log") 2>&1

[[ -s "$config" && -s "$component/valhalla_tiles.tar" && -s "$component/tiles/admins.sqlite" && -s "$component/tiles/timezones.sqlite" ]] \
    || gis_fail 'Valhalla build did not create all required artifacts.'
finished_at="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
duration="$(( $(date +%s) - started_epoch ))"
graph_size="$(gis_directory_size "$component")"
graph_sha256="$(gis_sha256 "$component/valhalla_tiles.tar")"
peak_rss_kb="$(awk -F: '/Maximum resident set size/{gsub(/[^0-9]/, "", $2); if ($2+0 > max) max=$2+0} END {print max+0}' "$metrics")"
valhalla_version="${VALHALLA_EXPECTED_VERSION}"

export COMPONENT_RELEASE="$release" COMPONENT_PBF_MD5="$expected_md5" COMPONENT_PBF_SIZE="$(gis_file_size "$pbf")"
export COMPONENT_VERSION="$valhalla_version" COMPONENT_STARTED="$started_at" COMPONENT_FINISHED="$finished_at"
export COMPONENT_DURATION="$duration" COMPONENT_SIZE="$graph_size" COMPONENT_PEAK_RSS="$peak_rss_kb"
export COMPONENT_GRAPH_SHA256="$graph_sha256"
php -r '
    $value = [
        "component" => "valhalla", "status" => "built", "release" => getenv("COMPONENT_RELEASE"),
        "pbf_md5" => getenv("COMPONENT_PBF_MD5"), "pbf_size_bytes" => (int)getenv("COMPONENT_PBF_SIZE"),
        "version" => getenv("COMPONENT_VERSION"), "started_at" => getenv("COMPONENT_STARTED"),
        "finished_at" => getenv("COMPONENT_FINISHED"), "build_duration_seconds" => (int)getenv("COMPONENT_DURATION"),
        "graph_size_bytes" => (int)getenv("COMPONENT_SIZE"), "graph_sha256" => getenv("COMPONENT_GRAPH_SHA256"),
        "peak_rss_kb" => (int)getenv("COMPONENT_PEAK_RSS")
    ];
    file_put_contents($argv[1], json_encode($value, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);
' "${component_manifest}.part"
mv -- "${component_manifest}.part" "$component_manifest"
gis_log "Valhalla staging build ready: ${component} (${graph_size} bytes, ${duration}s)."
gis_log 'It is not active; build PMTiles from the same PBF, then finalize and smoke-test the release.'
